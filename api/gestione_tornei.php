<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/crud/torneo.php';
$torneo = new Torneo();
require_once __DIR__ . '/crud/Squadra.php';
$squadraModel = new Squadra();
require_once __DIR__ . '/../includi/image_optimizer.php';
require_once __DIR__ . '/../includi/db.php';

function sanitizeTorneoSlug($value) {
    $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    if ($slug === '') {
        $slug = 'torneo' . time();
    }
    return $slug;
}

function scegliTemplatePerFormula(string $formula, string $faseFinale, string $baseDir): array {
    $default = [
        'html' => $baseDir . '/TorneoTemplate.php',
        'js'   => $baseDir . '/script-TorneoTemplate.js',
    ];

    if (!is_dir($baseDir)) {
        return $default;
    }

    $serieB = [
        'html' => $baseDir . '/SerieB.php',
        'js'   => $baseDir . '/script-SerieB.js',
    ];
    $coppaAfrica = [
        'html' => $baseDir . '/Coppadafrica.php',
        'js'   => $baseDir . '/script-Coppadafrica.js',
    ];

    switch ($formula) {
        case 'campionato':
            // Campionato con Coppa Gold/Silver -> usa impianto Serie B se disponibile
            if ($faseFinale === 'coppe' && file_exists($serieB['html']) && file_exists($serieB['js'])) {
                return $serieB;
            }
            break;
        case 'girone':
            if ($faseFinale === 'eliminazione_diretta' && file_exists($coppaAfrica['html']) && file_exists($coppaAfrica['js'])) {
                return $coppaAfrica; // Gironi + eliminazione diretta stile Coppa d'Africa
            }
            if ($faseFinale === 'coppe' && file_exists($default['html']) && file_exists($default['js'])) {
                return $default; // Gironi + Gold/Silver: usa template generico con entrambe le coppe
            }
            break;
        case 'eliminazione':
            if (file_exists($coppaAfrica['html']) && file_exists($coppaAfrica['js'])) {
                return $coppaAfrica; // Eliminazione diretta: bracket stile Coppa d'Africa (Gold)
            }
            break;
        default:
            break;
    }

    return $default;
}

function creaFileTorneoDaTemplate($nomeTorneo, $slug, $formulaTorneo = '', $faseFinale = '') {
    $baseDir = realpath(__DIR__ . '/../tornei');
    if (!$baseDir) {
        return;
    }

    // Sceglie il template più adatto in base alle scelte
    $templates = scegliTemplatePerFormula($formulaTorneo, $faseFinale, $baseDir);
    $htmlTemplate = $templates['html'] ?? null;
    $jsTemplate   = $templates['js'] ?? null;
    if (!$htmlTemplate || !$jsTemplate || !file_exists($htmlTemplate) || !file_exists($jsTemplate)) {
        return;
    }

    $htmlContent = file_get_contents($htmlTemplate);
    $jsContent = file_get_contents($jsTemplate);
    if ($htmlContent === false || $jsContent === false) {
        return;
    }

    $newScriptName = 'script-' . $slug . '.js';
    $templateSlug = basename($htmlTemplate, '.php');
    // Rimpiazzi segnaposto del template scelto
    $htmlContent = str_replace(
        [
            'TEMPLATE_SLUG',
            'Torneo Template',
            'script-TorneoTemplate.js',
            'Serie A',
            'script-SerieA.js',
            'SerieB',
            'Serie B',
            'script-SerieB.js',
            'Coppadafrica',
            "Coppa d'Africa",
            'script-Coppadafrica.js',
            $templateSlug,
            'script-' . $templateSlug . '.js'
        ],
        [
            $slug,
            $nomeTorneo,
            $newScriptName,
            $nomeTorneo,
            $newScriptName,
            $slug,
            $nomeTorneo,
            $newScriptName,
            $slug,
            $nomeTorneo,
            $newScriptName,
            $slug,
            $newScriptName
        ],
        $htmlContent
    );

    $jsContent = str_replace(
        ['TorneoTemplate', 'SerieA', 'SerieB', 'Coppadafrica', $templateSlug],
        [$slug, $slug, $slug, $slug, $slug],
        $jsContent
    );

    @file_put_contents($baseDir . '/' . $slug . '.php', $htmlContent);
    @file_put_contents($baseDir . '/' . $newScriptName, $jsContent);
}

function eliminaFileTorneo($fileName) {
    if (!$fileName) {
        return;
    }
    $baseDir = realpath(__DIR__ . '/../tornei');
    if (!$baseDir) {
        return;
    }
    $slug = preg_replace('/\.(html?|php)$/i', '', $fileName);
    if ($slug === '') {
        return;
    }
    $phpPath = $baseDir . '/' . $slug . '.php';
    $htmlPath = $baseDir . '/' . $slug . '.html';
    $jsPath = $baseDir . '/script-' . $slug . '.js';
    if (file_exists($phpPath)) {
        @unlink($phpPath);
    }
    if (file_exists($htmlPath)) {
        @unlink($htmlPath); // rimuove eventuali vecchie versioni statiche
    }
    if (file_exists($jsPath)) {
        @unlink($jsPath);
    }
}

function purgeTorneoData(string $torneoSlug): void {
    global $conn;
    if ($torneoSlug === '') {
        return;
    }
    if (!($conn instanceof mysqli)) {
        require __DIR__ . '/../includi/db.php';
    }
    if (!($conn instanceof mysqli)) {
        return;
    }

    // Elimina statistiche collegate alle partite del torneo (fk cascade su partite, ma facciamo una pulizia esplicita)
    if ($stmt = $conn->prepare("DELETE pg FROM partita_giocatore pg JOIN partite p ON pg.partita_id = p.id WHERE p.torneo = ?")) {
        $stmt->bind_param('s', $torneoSlug);
        $stmt->execute();
        $stmt->close();
    }

    // Elimina partite del torneo (cascata già gestisce partita_giocatore se non eseguita sopra)
    if ($stmt = $conn->prepare("DELETE FROM partite WHERE torneo = ?")) {
        $stmt->bind_param('s', $torneoSlug);
        $stmt->execute();
        $stmt->close();
    }

    // Elimina legami giocatori-squadre del torneo
    if ($stmt = $conn->prepare("DELETE sg FROM squadre_giocatori sg JOIN squadre s ON sg.squadra_id = s.id WHERE s.torneo = ?")) {
        $stmt->bind_param('s', $torneoSlug);
        $stmt->execute();
        $stmt->close();
    }

    // Elimina follow/seguiti relativi al torneo o alle sue squadre (se la tabella esiste)
    $safeSlug = $conn->real_escape_string($torneoSlug);
    $conn->query("DELETE FROM seguiti WHERE torneo_slug = '" . $safeSlug . "' OR (tipo = 'squadra' AND torneo_slug = '" . $safeSlug . "')");

    rebuildAggregates($conn);
}

function rebuildAggregates(mysqli $conn): void {
    // Giocatori globali
    $conn->query("
        UPDATE giocatori g
        LEFT JOIN (
            SELECT 
                pg.giocatore_id,
                SUM(pg.goal) AS goal,
                SUM(pg.assist) AS assist,
                SUM(pg.cartellino_giallo) AS gialli,
                SUM(pg.cartellino_rosso) AS rossi,
                SUM(CASE WHEN pg.presenza = 1 THEN 1 ELSE 0 END) AS presenze,
                SUM(CASE WHEN pg.voto IS NOT NULL THEN pg.voto ELSE 0 END) AS somma_voti,
                SUM(CASE WHEN pg.voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
            FROM partita_giocatore pg
            JOIN partite p ON p.id = pg.partita_id
            WHERE p.giocata = 1
            GROUP BY pg.giocatore_id
        ) agg ON agg.giocatore_id = g.id
        SET 
            g.presenze = COALESCE(agg.presenze, 0),
            g.reti = COALESCE(agg.goal, 0),
            g.assist = COALESCE(agg.assist, 0),
            g.gialli = COALESCE(agg.gialli, 0),
            g.rossi = COALESCE(agg.rossi, 0),
            g.media_voti = CASE WHEN COALESCE(agg.num_voti,0) > 0 THEN ROUND(agg.somma_voti / agg.num_voti, 2) ELSE NULL END
    ");

    // Giocatori per squadra
    $conn->query("
        UPDATE squadre_giocatori sg
        LEFT JOIN (
            SELECT 
                sg2.id AS sg_id,
                SUM(pg.goal) AS goal,
                SUM(pg.assist) AS assist,
                SUM(pg.cartellino_giallo) AS gialli,
                SUM(pg.cartellino_rosso) AS rossi,
                SUM(CASE WHEN pg.presenza = 1 THEN 1 ELSE 0 END) AS presenze,
                SUM(CASE WHEN pg.voto IS NOT NULL THEN pg.voto ELSE 0 END) AS somma_voti,
                SUM(CASE WHEN pg.voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
            FROM squadre_giocatori sg2
            JOIN partite p ON p.torneo = (SELECT torneo FROM squadre s WHERE s.id = sg2.squadra_id LIMIT 1)
            JOIN partita_giocatore pg ON pg.giocatore_id = sg2.giocatore_id AND pg.partita_id = p.id
            WHERE p.giocata = 1
            GROUP BY sg2.id
        ) agg ON agg.sg_id = sg.id
        SET 
            sg.presenze = COALESCE(agg.presenze, 0),
            sg.reti = COALESCE(agg.goal, 0),
            sg.assist = COALESCE(agg.assist, 0),
            sg.gialli = COALESCE(agg.gialli, 0),
            sg.rossi = COALESCE(agg.rossi, 0),
            sg.media_voti = CASE WHEN COALESCE(agg.num_voti,0) > 0 THEN ROUND(agg.somma_voti / agg.num_voti, 2) ELSE NULL END
    ");
}

function salvaImmagineTorneo($nomeTorneo, $fileField) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $maxSize = 20 * 1024 * 1024;
    if ($_FILES[$fileField]['size'] > $maxSize) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $_FILES[$fileField]['tmp_name']) : false;
    if ($finfo instanceof finfo) {
        unset($finfo);
    }
    if (!$mime) {
        return null;
    }
    if (!isset($allowed[$mime])) {
        return null;
    }

    $baseDirPath = __DIR__ . '/../img/tornei';
    if (!is_dir($baseDirPath)) {
        @mkdir($baseDirPath, 0775, true);
    }
    $baseDir = realpath($baseDirPath);
    if (!$baseDir) {
        return null;
    }

    // Mantiene tutte le lettere (maiuscole incluse) prima di convertirle in minuscolo
    $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $nomeTorneo));
    if ($slug === '') {
        $slug = 'torneo';
    }

    $extension = $allowed[$mime];
    $filename = $slug . '.' . $extension;
    $counter = 2;
    while (file_exists($baseDir . '/' . $filename)) {
        $filename = $slug . '_' . $counter . '.' . $extension;
        $counter++;
    }

    $dest = $baseDir . '/' . $filename;
    if (!move_uploaded_file($_FILES[$fileField]['tmp_name'], $dest)) {
        return null;
    }

    if ($extension !== 'gif') {
        optimize_image_file($dest, [
            'maxWidth' => 1920,
            'maxHeight' => 1920,
            'quality' => 82,
            'maxBytes' => 8 * 1024 * 1024,
        ]);
    }

    return '/img/tornei/' . $filename;
}

function eliminaImmagineTorneo($imgPath) {
    if (!$imgPath) {
        return;
    }
    $default = '/img/tornei/pallone.png';
    $basename = basename($imgPath);
    if ($basename === basename($default)) {
        return;
    }
    $imgDir = realpath(__DIR__ . '/../img/tornei');
    if (!$imgDir) {
        return;
    }
    $fullPath = $imgDir . '/' . $basename;
    if (!file_exists($fullPath)) {
        return;
    }
    @unlink($fullPath);
}

// --- CREA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
    $nome = trim($_POST['nome']);
    $stato = $_POST['stato'];
    $data_inizio = $_POST['data_inizio'];
    $data_fine = $_POST['data_fine'];
    $rawFile = preg_replace('/\.(html?|php)$/i', '', trim($_POST['filetorneo']));
    $slug = sanitizeTorneoSlug($rawFile);
    $filetorneo = $slug . '.php';
    $categoria = trim($_POST['categoria']);
    $img = salvaImmagineTorneo($nome, 'img_upload');
    $formulaTorneo = $_POST['formula_torneo'] ?? '';
    $faseFinale = $_POST['fase_finale'] ?? '';

    $squadre_complete = isset($_POST['squadre_complete']) ? 1 : 0;
    if ($torneo->crea($nome, $stato, $data_inizio, $data_fine, $filetorneo, $categoria, $img, $squadre_complete)) {
        creaFileTorneoDaTemplate($nome, $slug, $formulaTorneo, $faseFinale);
    }
    header("Location: gestione_tornei.php");
    exit;
}

// --- AGGIORNA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
    $id = (int)$_POST['id'];
    $nome = trim($_POST['nome']);
    $stato = $_POST['stato'];
    $data_inizio = $_POST['data_inizio'];
    $data_fine = $_POST['data_fine'];
    $rawFile = preg_replace('/\.(html?|php)$/i', '', trim($_POST['filetorneo']));
    $slug = sanitizeTorneoSlug($rawFile);
    $filetorneo = $slug . '.php';
    $categoria = trim($_POST['categoria']);
    $record = $torneo->getById($id);
    $img = salvaImmagineTorneo($nome, 'img_upload_mod');
    if (!$img && $record && !empty($record['img'])) {
        $img = $record['img'];
    }
    $squadre_complete = isset($_POST['squadre_complete']) ? 1 : 0;
    $torneo->aggiorna($id, $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $squadre_complete);
    header("Location: gestione_tornei.php");
    exit;
}

// --- ELIMINA ---
if (isset($_GET['elimina'])) {
    $id = (int)$_GET['elimina'];
    $record = $torneo->getById($id);

    if ($record) {
        eliminaFileTorneo($record['filetorneo'] ?? null);
        eliminaImmagineTorneo($record['img'] ?? null);

        $torneoSlug = '';
        if (!empty($record['filetorneo'])) {
            $torneoSlug = sanitizeTorneoSlug(preg_replace('/\.(html?|php)$/i', '', $record['filetorneo']));
        }
        purgeTorneoData($torneoSlug);
        $squadraModel->eliminaByTorneo($torneoSlug);
    }

    $torneo->elimina($id);
    header("Location: gestione_tornei.php");
    exit;
}

// --- LISTA TORNEI ---
$torneiRows = [];
$lista = $torneo->getAll();
if ($lista instanceof mysqli_result) {
    while ($row = $lista->fetch_assoc()) {
        $torneiRows[] = $row;
    }
} else {
    error_log('Errore nel recupero dei tornei: ' . $torneo->getLastError());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Gestione Tornei</title>
        <link rel="stylesheet" href="/style.min.css?v=20251126">
    <link rel="icon" type="image/png" href="/img/logo_old_school.png">
    <link rel="apple-touch-icon" href="/img/logo_old_school.png">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main.admin-wrapper {
            flex: 1 0 auto;
        }
        .admin-container {
            padding-bottom: 80px;
        }
        #footer-container {
            margin-top: 40px;
        }

        .file-upload {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            padding: 10px 14px;
            border: 1px dashed #d4d9e2;
            border-radius: 10px;
            background: #f7f9fc;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload .file-btn {
            background: #15293e;
            color: #fff;
            padding: 8px 18px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: none;
        }

        .file-upload .file-btn:hover {
            background: #0e1d2e;
            transform: translateY(-1px);
        }

        .file-upload .file-name {
            font-size: 0.9rem;
            color: #5f6b7b;
        }

        /* Pulsanti switch azione (stile gestione squadre) */
        .admin-btn-group {
            display: inline-flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .action-toggle {
            border: 1px solid #cbd5e1;
            background: #ecf1f7;
            color: #1c2a3a;
            padding: 10px 18px;
            border-radius: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .action-toggle:hover {
            background: #e0e7ef;
            border-color: #c0cadd;
            transform: translateY(-1px);
        }
        .action-toggle.active {
            background: linear-gradient(135deg, #15293e, #1f3f63);
            border-color: #15293e;
            color: #fff;
            box-shadow: 0 6px 14px rgba(21,41,62,0.25);
        }
        .pill-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pill-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px solid #d5dce8;
            border-radius: 12px;
            background: #f3f6fb;
            font-weight: 600;
            color: #1f2d3d;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
        }
        .pill-toggle:hover {
            background: #e9eef5;
            border-color: #c6cfde;
            transform: translateY(-1px);
        }
        .form-subgroup {
            border: 1px solid #e3e8f0;
            background: #f8faff;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includi/header.php'; ?>

    <main class="admin-wrapper">
        <section class="admin-container">
            <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
            <h1 class="admin-title">Gestione Tornei</h1>

            <!-- SWITCH AZIONI -->
            <div class="admin-select-action" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <span>Seleziona azione:</span>
                <div class="admin-btn-group">
                    <button type="button" class="action-toggle active" data-action="crea">Crea</button>
                    <button type="button" class="action-toggle" data-action="modifica">Modifica</button>
                    <button type="button" class="action-toggle" data-action="elimina">Elimina</button>
                </div>
            </div>


            <!-- FORM CREAZIONE -->
            <form method="POST" class="admin-form form-crea" enctype="multipart/form-data">
                <h2>Aggiungi Torneo</h2>
                <div class="form-group">
                    <label>Formula torneo</label>
                    <div class="pill-group" id="tipoTorneoGroup">
                        <label class="pill-toggle">
                            <input type="radio" name="formula_torneo" value="campionato" required>
                            Campionato
                        </label>
                        <label class="pill-toggle">
                            <input type="radio" name="formula_torneo" value="girone">
                            Girone
                        </label>
                        <label class="pill-toggle">
                            <input type="radio" name="formula_torneo" value="eliminazione">
                            Eliminazione diretta
                        </label>
                    </div>
                    <small>Scegli la formula prima di inserire gli altri dettagli.</small>
                </div>

                <div class="form-subgroup hidden" id="campionatoSettings">
                    <div class="form-group">
                        <label>Numero squadre</label>
                        <input type="number" name="campionato_squadre" min="2" step="1" inputmode="numeric" placeholder="Es. 10">
                    </div>
                </div>

                <div class="form-subgroup hidden" id="gironeSettings">
                    <div class="form-row">
                        <div class="form-group half">
                            <label>Numero gironi</label>
                            <input type="number" name="numero_gironi" min="1" step="1" inputmode="numeric" placeholder="Es. 2">
                        </div>
                        <div class="form-group half">
                            <label>Squadre per girone</label>
                            <input type="number" name="squadre_per_girone" min="2" step="1" inputmode="numeric" placeholder="Es. 4">
                        </div>
                    </div>
                </div>

                <div class="form-subgroup hidden" id="faseFinaleSettings">
                    <div class="form-group">
                        <label>Fase finale prevista</label>
                        <div class="pill-group">
                            <label class="pill-toggle">
                                <input type="radio" name="fase_finale" value="coppe">
                                Coppa Gold e Silver
                            </label>
                            <label class="pill-toggle">
                                <input type="radio" name="fase_finale" value="eliminazione_diretta">
                                Eliminazione diretta
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group"><label>Nome</label><input type="text" name="nome" required></div>
                <div class="form-group"><label>Stato</label>
                    <select name="stato">
                        <option value="programmato">Programmato</option>
                        <option value="in corso">In Corso</option>
                        <option value="terminato">Terminato</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group half"><label>Data Inizio</label><input type="date" name="data_inizio" required></div>
                    <div class="form-group half"><label>Data Fine</label><input type="date" name="data_fine" required></div>
                </div>
                <div class="form-group">
                    <label>Immagine Torneo</label>
                    <div class="file-upload">
                        <input type="file" name="img_upload" id="img_upload" accept="image/*">
                        <button type="button" class="file-btn" data-target="img_upload">Scegli immagine</button>
                        <span class="file-name" id="img_upload_name">Nessun file selezionato</span>
                    </div>
                    <small>Se non carichi un file verrÃ  usata l'immagine predefinita.</small>
                </div>
                <div class="form-group"><label>File Torneo</label><input type="text" name="filetorneo" required></div>
                <div class="form-group"><label>Categoria</label><input type="text" name="categoria" required></div>
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="squadre_complete" value="1">
                        Tutte le squadre già create (nascondi torneo nella creazione squadre)
                    </label>
                </div>
                <button type="submit" name="crea" class="btn-primary">Crea Torneo</button>
            </form>

            <!-- FORM MODIFICA -->
            <form method="POST" class="admin-form form-modifica hidden" id="formModifica" enctype="multipart/form-data">
                <h2>Modifica Torneo</h2>

                <div class="form-group">
                    <label>Seleziona Torneo</label>
                    <select name="id" id="selectTorneo" required>
                        <option value="">-- Seleziona un torneo --</option>
                        <?php foreach ($torneiRows as $row): ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nome']) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($torneiRows)): ?>
                            <option value="" disabled>Nessun torneo disponibile</option>
                        <?php endif; ?>
                    </select>
                </div>
                        
                <div class="form-group"><label>Nome</label><input type="text" name="nome" id="mod_nome"></div>
                <div class="form-group"><label>Stato</label>
                    <select name="stato" id="mod_stato">
                        <option value="programmato">Programmato</option>
                        <option value="in corso">In Corso</option>
                        <option value="terminato">Terminato</option>
                    </select>
                </div>
                <div class="form-group"><label>Data Inizio</label><input type="date" name="data_inizio" id="mod_inizio"></div>
                <div class="form-group"><label>Data Fine</label><input type="date" name="data_fine" id="mod_fine"></div>
                <div class="form-group">
                    <label>Nuova immagine</label>
                    <div class="file-upload">
                        <input type="file" name="img_upload_mod" id="img_upload_mod" accept="image/*">
                        <button type="button" class="file-btn" data-target="img_upload_mod">Scegli immagine</button>
                        <span class="file-name" id="img_upload_mod_name">Nessun file selezionato</span>
                    </div>
                    <small>Se non carichi nulla resterÃ  l'immagine attuale.</small>
                </div>
                <div class="form-group"><label>File Torneo</label><input type="text" name="filetorneo" id="mod_file"></div>
                <div class="form-group"><label>Categoria</label><input type="text" name="categoria" id="mod_categoria"></div>
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="squadre_complete" id="mod_squadre_complete" value="1">
                        Tutte le squadre già create (nascondi torneo nella creazione squadre)
                    </label>
                </div>
                        
                <button type="submit" name="aggiorna" class="btn-primary">Aggiorna Torneo</button>
            </form>

            <!-- SEZIONE ELIMINA -->
            <section class="admin-table-section form-elimina hidden">
                <h2>Elimina Torneo</h2>
                <table class="admin-table" id="tabellaTornei">
                    <thead>
                        <tr>
                            <th data-col="nome">Nome</th>
                            <th data-col="stato">Stato</th>
                            <th data-col="categoria">Categoria</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($torneiRows)): ?>
                            <tr>
                                <td colspan="4">Nessun torneo disponibile.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($torneiRows as $row): ?>
                                <tr data-id="<?= $row['id'] ?>"> <!-- memorizziamo l'ID come attributo -->
                                    <td><?= htmlspecialchars($row['nome']) ?></td>
                                    <td><?= htmlspecialchars($row['stato']) ?></td>
                                    <td><?= htmlspecialchars($row['categoria']) ?></td>
                                    <td>
                                          <a href="#" class="btn-danger delete-btn" data-id="<?= $row['id'] ?>" data-label="<?= htmlspecialchars($row['nome']) ?>" data-type="torneo">Elimina</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>


      </section>
  </main>

  <div id="footer-container"></div>

  <?php include __DIR__ . '/../includi/delete_modal.php'; ?>

    <script>
        const formCrea = document.querySelector('.form-crea');
        const formModifica = document.querySelector('.form-modifica');
        const formElimina = document.querySelector('.form-elimina');
        const actionButtons = document.querySelectorAll('.action-toggle');

        function mostraSezione(valore) {
            [formCrea, formModifica, formElimina].forEach(f => f.classList.add('hidden'));
            actionButtons.forEach(btn => btn.classList.remove('active'));

            const activeBtn = document.querySelector(`.action-toggle[data-action="${valore}"]`);
            if (activeBtn) activeBtn.classList.add('active');

            if (valore === 'crea') formCrea.classList.remove('hidden');
            if (valore === 'modifica') formModifica.classList.remove('hidden');
            if (valore === 'elimina') formElimina.classList.remove('hidden');
        }

        actionButtons.forEach(btn => {
            btn.addEventListener('click', () => mostraSezione(btn.dataset.action));
        });

        mostraSezione('crea');
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.file-upload').forEach(wrapper => {
                const input = wrapper.querySelector('input[type="file"]');
                const btn = wrapper.querySelector('.file-btn');
                const nameLabel = wrapper.querySelector('.file-name');
                if (btn && input) {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        input.click();
                    });
                }
                if (input && nameLabel) {
                    input.addEventListener('change', () => {
                        const file = input.files && input.files[0];
                        nameLabel.textContent = file ? file.name : 'Nessun file selezionato';
                    });
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.file-upload input[type="file"]').forEach(input => {
                const label = input.parentElement.querySelector('.file-name');
                input.addEventListener('change', () => {
                    const file = input.files && input.files[0];
                    label.textContent = file ? file.name : 'Nessun file selezionato';
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tipoRadios = Array.from(document.querySelectorAll('input[name="formula_torneo"]'));
            const campionatoBox = document.getElementById('campionatoSettings');
            const gironeBox = document.getElementById('gironeSettings');
            const finaleBox = document.getElementById('faseFinaleSettings');
            const campionatoInput = document.querySelector('input[name="campionato_squadre"]');
            const numeroGironiInput = document.querySelector('input[name="numero_gironi"]');
            const squadrePerGironeInput = document.querySelector('input[name="squadre_per_girone"]');
            const finaleRadios = Array.from(document.querySelectorAll('input[name="fase_finale"]'));

            function setRequired(inputs, value) {
                inputs.forEach(el => {
                    if (el) el.required = value;
                });
            }

            function toggleFinale(show) {
                if (!finaleBox) return;
                if (show) {
                    finaleBox.classList.remove('hidden');
                    finaleRadios.forEach(r => r.required = true);
                } else {
                    finaleBox.classList.add('hidden');
                    finaleRadios.forEach(r => {
                        r.required = false;
                        r.checked = false;
                    });
                }
            }

            function handleTipoChange(value) {
                if (campionatoBox) campionatoBox.classList.toggle('hidden', value !== 'campionato');
                if (gironeBox) gironeBox.classList.toggle('hidden', value !== 'girone');

                setRequired([campionatoInput], value === 'campionato');
                setRequired([numeroGironiInput, squadrePerGironeInput], value === 'girone');

                toggleFinale(value !== 'eliminazione');
            }

            tipoRadios.forEach(radio => {
                radio.addEventListener('change', () => handleTipoChange(radio.value));
            });

            const preselected = tipoRadios.find(r => r.checked);
            if (preselected) {
                handleTipoChange(preselected.value);
            }
        });
    </script>
    <script>
const selectTorneo = document.getElementById('selectTorneo');
const campi = {
    nome: document.getElementById('mod_nome'),
    stato: document.getElementById('mod_stato'),
    inizio: document.getElementById('mod_inizio'),
    fine: document.getElementById('mod_fine'),
    file: document.getElementById('mod_file'),
    categoria: document.getElementById('mod_categoria')
};

selectTorneo.addEventListener('change', async (e) => {
    const id = e.target.value;
    if (!id) {
        Object.values(campi).forEach(c => c.value = '');
        return;
    }

    try {
        const res = await fetch(`/api/get_torneo.php?id=${id}`);
        const data = await res.json();

        if (data && !data.error) {
            campi.nome.value = data.nome || '';
            campi.stato.value = data.stato || 'programmato';
            campi.inizio.value = data.data_inizio || '';
            campi.fine.value = data.data_fine || '';
            campi.file.value = data.filetorneo || '';
            campi.categoria.value = data.categoria || '';
        } else {
            alert('Errore: ' + (data.error || 'Dati non trovati'));
        }
    } catch (err) {
        console.error('Errore nel recupero del torneo:', err);
    }
});
</script>
<script>
// ============ ORDINAMENTO TABELLA ============ //
document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("tabellaTornei");
  const headers = table.querySelectorAll("th[data-col]");
  let sortDirection = {}; // tiene traccia della direzione per ogni colonna

  headers.forEach(header => {
    header.style.cursor = "pointer";
    header.addEventListener("click", () => {
      const columnIndex = Array.from(header.parentNode.children).indexOf(header);
      const tbody = table.querySelector("tbody");
      const rows = Array.from(tbody.querySelectorAll("tr"));
      const col = header.getAttribute("data-col");

      // Alterna direzione (asc <-> desc)
      sortDirection[col] = sortDirection[col] === "asc" ? "desc" : "asc";

      // Ordina righe
      rows.sort((a, b) => {
        const valA = a.children[columnIndex].textContent.trim().toLowerCase();
        const valB = b.children[columnIndex].textContent.trim().toLowerCase();

        if (!isNaN(valA) && !isNaN(valB)) {
          return sortDirection[col] === "asc" ? valA - valB : valB - valA;
        } else {
          return sortDirection[col] === "asc"
            ? valA.localeCompare(valB)
            : valB.localeCompare(valA);
        }
      });

      // Rimuovi vecchie righe e aggiungi nuove ordinate
      tbody.innerHTML = "";
      rows.forEach(r => tbody.appendChild(r));

      // Aggiorna indicatori â†‘â†“
      headers.forEach(h => h.classList.remove("sort-asc", "sort-desc"));
      header.classList.add(sortDirection[col] === "asc" ? "sort-asc" : "sort-desc");
    });
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const footer = document.getElementById('footer-container');
  if (!footer) return;
  fetch('/includi/footer.html')
    .then(response => response.text())
    .then(html => footer.innerHTML = html)
    .catch(err => console.error('Errore caricamento footer:', err));
});
</script>
<script src="/includi/delete-modal.js"></script>

</body>
</html>
