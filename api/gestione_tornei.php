<?php
require_once __DIR__ . '/../includi/admin_guard.php';

require_once __DIR__ . '/crud/torneo.php';
$torneo = new Torneo();
require_once __DIR__ . '/crud/Squadra.php';
$squadraModel = new Squadra();
require_once __DIR__ . '/../includi/image_optimizer.php';
require_once __DIR__ . '/../includi/db.php';

function intOrZero($value): int {
    return max(0, (int)$value);
}

function sanitizeTorneoSlug($value) {
    $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    if ($slug === '') {
        $slug = 'torneo' . time();
    }
    return $slug;
}

function buildTorneoConfigFromRequest(array $src): array {
    $config = [
        'formato'            => $src['formula_torneo'] ?? '',
        'fase_finale'        => $src['fase_finale'] ?? '',
        'campionato_squadre' => intOrZero($src['campionato_squadre'] ?? 0),
        'numero_gironi'      => intOrZero($src['numero_gironi'] ?? 0),
        'squadre_per_girone' => intOrZero($src['squadre_per_girone'] ?? 0),
        'totale_squadre'     => intOrZero($src['totale_squadre'] ?? 0),
        'qualificati_gold'   => intOrZero($src['qualificati_gold'] ?? 0),
        'qualificati_silver' => intOrZero($src['qualificati_silver'] ?? 0),
        'eliminate'          => intOrZero($src['eliminate'] ?? 0),
        'regole_html'        => trim($src['regole_html'] ?? ''),
    ];

    if ($config['totale_squadre'] === 0) {
        if ($config['campionato_squadre'] > 0) {
            $config['totale_squadre'] = $config['campionato_squadre'];
        } elseif ($config['numero_gironi'] > 0 && $config['squadre_per_girone'] > 0) {
            $config['totale_squadre'] = $config['numero_gironi'] * $config['squadre_per_girone'];
        }
    }

    return $config;
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

    // Sceglie il template piÃ¹ adatto in base alle scelte
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

    // Elimina partite del torneo (cascata giÃ  gestisce partita_giocatore se non eseguita sopra)
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
    $config = buildTorneoConfigFromRequest($_POST);

    $squadre_complete = isset($_POST['squadre_complete']) ? 1 : 0;
    if ($torneo->crea($nome, $stato, $data_inizio, $data_fine, $filetorneo, $categoria, $img, $squadre_complete, $config)) {
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
    $config = buildTorneoConfigFromRequest($_POST);
    $torneo->aggiorna($id, $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $squadre_complete, $config);
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
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-VZ982XSRRN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-VZ982XSRRN');
  </script>
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

                <div class="form-subgroup" id="qualificazioniSettings">
                    <div class="form-row">
                        <div class="form-group half">
                            <label>Totale squadre</label>
                            <input type="number" name="totale_squadre" id="totale_squadre" min="0" step="1" inputmode="numeric" placeholder="Es. 16">
                            <small>Se lasci vuoto userà i valori di campionato/gironi.</small>
                        </div>
                        <div class="form-group half">
                            <label>Qualificate in Coppa Gold</label>
                            <input type="number" name="qualificati_gold" id="qualificati_gold" min="0" step="1" inputmode="numeric" placeholder="Es. 8">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group half">
                            <label>Qualificate in Coppa Silver</label>
                            <input type="number" name="qualificati_silver" id="qualificati_silver" min="0" step="1" inputmode="numeric" placeholder="Es. 8">
                        </div>
                        <div class="form-group half">
                            <label>Eliminate dopo gironi/regular</label>
                            <input type="number" name="eliminate" id="eliminate" min="0" step="1" inputmode="numeric" placeholder="Es. 0">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Regole / Note torneo</label>
                    <textarea name="regole_html" id="regole_html" rows="4" placeholder="Criteri di qualificazione, punti bonus, regole speciali"></textarea>
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
                    <small>Se non carichi un file verrà usata l'immagine predefinita.</small>
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

                <div class="form-group">
                    <label>Formula torneo</label>
                    <div class="pill-group" id="tipoTorneoGroupMod">
                        <label class="pill-toggle">
                            <input type="radio" name="formula_torneo" value="campionato" id="mod_formula_campionato">
                            Campionato
                        </label>
                        <label class="pill-toggle">
                            <input type="radio" name="formula_torneo" value="girone" id="mod_formula_girone">
                            Girone
                        </label>
                        <label class="pill-toggle">
                            <input type="radio" name="formula_torneo" value="eliminazione" id="mod_formula_eliminazione">
                            Eliminazione diretta
                        </label>
                    </div>
                </div>

                <div class="form-subgroup hidden" id="campionatoSettingsMod">
                    <div class="form-group">
                        <label>Numero squadre</label>
                        <input type="number" name="campionato_squadre" id="mod_campionato_squadre" min="2" step="1" inputmode="numeric" placeholder="Es. 10">
                    </div>
                </div>

                <div class="form-subgroup hidden" id="gironeSettingsMod">
                    <div class="form-row">
                        <div class="form-group half">
                            <label>Numero gironi</label>
                            <input type="number" name="numero_gironi" id="mod_numero_gironi" min="1" step="1" inputmode="numeric" placeholder="Es. 2">
                        </div>
                        <div class="form-group half">
                            <label>Squadre per girone</label>
                            <input type="number" name="squadre_per_girone" id="mod_squadre_per_girone" min="2" step="1" inputmode="numeric" placeholder="Es. 4">
                        </div>
                    </div>
                </div>

                <div class="form-subgroup hidden" id="faseFinaleSettingsMod">
                    <div class="form-group">
                        <label>Fase finale prevista</label>
                        <div class="pill-group">
                            <label class="pill-toggle">
                                <input type="radio" name="fase_finale" value="coppe" id="mod_fase_coppe">
                                Coppa Gold e Silver
                            </label>
                            <label class="pill-toggle">
                                <input type="radio" name="fase_finale" value="eliminazione_diretta" id="mod_fase_eliminazione">
                                Eliminazione diretta
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-subgroup" id="qualificazioniSettingsMod">
                    <div class="form-row">
                        <div class="form-group half">
                            <label>Totale squadre</label>
                            <input type="number" name="totale_squadre" id="mod_totale_squadre" min="0" step="1" inputmode="numeric" placeholder="Es. 16">
                            <small>Se vuoto userà i valori di campionato/gironi.</small>
                        </div>
                        <div class="form-group half">
                            <label>Qualificate in Coppa Gold</label>
                            <input type="number" name="qualificati_gold" id="mod_qualificati_gold" min="0" step="1" inputmode="numeric" placeholder="Es. 8">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group half">
                            <label>Qualificate in Coppa Silver</label>
                            <input type="number" name="qualificati_silver" id="mod_qualificati_silver" min="0" step="1" inputmode="numeric" placeholder="Es. 8">
                        </div>
                        <div class="form-group half">
                            <label>Eliminate dopo gironi/regular</label>
                            <input type="number" name="eliminate" id="mod_eliminate" min="0" step="1" inputmode="numeric" placeholder="Es. 0">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Regole / Note torneo</label>
                    <textarea name="regole_html" id="mod_regole_html" rows="4" placeholder="Criteri di qualificazione, punti bonus, regole speciali"></textarea>
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
                    <small>Se non carichi nulla resterà l'immagine attuale.</small>
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
        (() => {
            function setupFormulaForm(formEl) {
                if (!formEl) return null;
                const tipoRadios = Array.from(formEl.querySelectorAll('input[name="formula_torneo"]'));
                const campionatoBox = formEl.querySelector('[id^="campionatoSettings"]');
                const gironeBox = formEl.querySelector('[id^="gironeSettings"]');
                const finaleBox = formEl.querySelector('[id^="faseFinaleSettings"]');
                const qualificheBox = formEl.querySelector('[id^="qualificazioniSettings"]');
                const campionatoInput = formEl.querySelector('input[name="campionato_squadre"]');
                const numeroGironiInput = formEl.querySelector('input[name="numero_gironi"]');
                const squadrePerGironeInput = formEl.querySelector('input[name="squadre_per_girone"]');
                const finaleRadios = Array.from(formEl.querySelectorAll('input[name="fase_finale"]'));
                const totaleInput = formEl.querySelector('input[name="totale_squadre"]');
                const goldInput = formEl.querySelector('input[name="qualificati_gold"]');
                const silverInput = formEl.querySelector('input[name="qualificati_silver"]');
                const eliminateInput = formEl.querySelector('input[name="eliminate"]');

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

                function toggleQualifiche(show) {
                    if (!qualificheBox) return;
                    qualificheBox.classList.toggle('hidden', !show);
                    if (!show) {
                        [totaleInput, goldInput, silverInput, eliminateInput].forEach(el => {
                            if (el) {
                                el.value = '';
                                el.dataset.auto = '';
                            }
                        });
                    }
                }

                function updateTotale() {
                    if (!totaleInput) return;
                    const campVal = parseInt(campionatoInput?.value || "0", 10);
                    const gironi = parseInt(numeroGironiInput?.value || "0", 10);
                    const perGirone = parseInt(squadrePerGironeInput?.value || "0", 10);
                    if (!totaleInput.value) {
                        if (campVal > 0) {
                            totaleInput.value = campVal;
                        } else if (gironi > 0 && perGirone > 0) {
                            totaleInput.value = gironi * perGirone;
                        }
                    }
                }

                function updateEliminate(force = false) {
                    if (!eliminateInput) return;
                    const tot = parseInt(totaleInput?.value || "0", 10);
                    const gold = parseInt(goldInput?.value || "0", 10);
                    const silver = parseInt(silverInput?.value || "0", 10);
                    if (!tot) return;
                    const computed = Math.max(0, tot - gold - silver);
                    if (force || !eliminateInput.value || eliminateInput.dataset.auto === '1') {
                        eliminateInput.value = computed;
                        eliminateInput.dataset.auto = '1';
                    }
                }

                function handleTipoChange(value) {
                    const hasSelection = value === 'campionato' || value === 'girone' || value === 'eliminazione';
                    const showFinale = hasSelection && value !== 'eliminazione';

                    if (campionatoBox) campionatoBox.classList.toggle('hidden', value !== 'campionato');
                    if (gironeBox) gironeBox.classList.toggle('hidden', value !== 'girone');

                    setRequired([campionatoInput], value === 'campionato');
                    setRequired([numeroGironiInput, squadrePerGironeInput], value === 'girone');

                    toggleFinale(showFinale);
                    toggleQualifiche(hasSelection && value !== 'eliminazione');
                }

                tipoRadios.forEach(radio => {
                    radio.addEventListener('change', () => handleTipoChange(radio.value));
                });

                [campionatoInput, numeroGironiInput, squadrePerGironeInput].forEach(el => {
                    if (!el) return;
                    el.addEventListener('change', () => {
                        updateTotale();
                        updateEliminate(false);
                    });
                });

                [totaleInput, goldInput, silverInput].forEach(el => {
                    if (!el) return;
                    el.addEventListener('input', () => updateEliminate(false));
                });
                if (eliminateInput) {
                    eliminateInput.addEventListener('input', () => eliminateInput.dataset.auto = '');
                }

                const preselected = tipoRadios.find(r => r.checked);
                if (preselected) {
                    handleTipoChange(preselected.value);
                } else {
                    handleTipoChange('');
                }
                updateTotale();

                function applyConfig(cfg = {}) {
                    const formato = cfg.formato || cfg.formula_torneo || '';
                    if (formato) {
                        const radio = tipoRadios.find(r => r.value === formato);
                        if (radio) {
                            radio.checked = true;
                            handleTipoChange(formato);
                        }
                    }

                    const finaleVal = cfg.fase_finale || '';
                    if (finaleVal) {
                        const fRadio = finaleRadios.find(r => r.value === finaleVal);
                        if (fRadio) fRadio.checked = true;
                    }

                    if (campionatoInput && cfg.campionato_squadre) campionatoInput.value = cfg.campionato_squadre;
                    if (numeroGironiInput && cfg.numero_gironi) numeroGironiInput.value = cfg.numero_gironi;
                    if (squadrePerGironeInput && cfg.squadre_per_girone) squadrePerGironeInput.value = cfg.squadre_per_girone;
                    if (totaleInput && cfg.totale_squadre) totaleInput.value = cfg.totale_squadre;
                    if (goldInput && cfg.qualificati_gold !== undefined) goldInput.value = cfg.qualificati_gold;
                    if (silverInput && cfg.qualificati_silver !== undefined) silverInput.value = cfg.qualificati_silver;
                    if (eliminateInput && cfg.eliminate !== undefined) {
                        eliminateInput.value = cfg.eliminate;
                        eliminateInput.dataset.auto = '';
                    }

                    handleTipoChange(formato);
                    updateTotale();
                    updateEliminate(true);
                }

                return { applyConfig, handleTipoChange, updateTotale };
            }

            window.__torneoFormControllers__ = {
                crea: setupFormulaForm(document.querySelector('.form-crea')),
                modifica: setupFormulaForm(document.getElementById('formModifica'))
            };
        })();
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
const regoleField = document.getElementById('mod_regole_html');
const modController = window.__torneoFormControllers__ ? window.__torneoFormControllers__.modifica : null;
const configInputs = {
    campionato: document.getElementById('mod_campionato_squadre'),
    gironi: document.getElementById('mod_numero_gironi'),
    perGirone: document.getElementById('mod_squadre_per_girone'),
    totale: document.getElementById('mod_totale_squadre'),
    gold: document.getElementById('mod_qualificati_gold'),
    silver: document.getElementById('mod_qualificati_silver'),
    eliminate: document.getElementById('mod_eliminate')
};
const radioFormatiMod = Array.from(document.querySelectorAll('#formModifica input[name="formula_torneo"]'));
const radioFaseMod = Array.from(document.querySelectorAll('#formModifica input[name="fase_finale"]'));

function resetModFormConfig() {
    radioFormatiMod.forEach(r => r.checked = false);
    radioFaseMod.forEach(r => r.checked = false);
    Object.values(configInputs).forEach(el => { if (el) el.value = ''; });
    if (regoleField) regoleField.value = '';
    if (modController && modController.handleTipoChange) {
        modController.handleTipoChange('');
    }
}

function parseConfig(raw) {
    if (!raw) return {};
    if (typeof raw === 'object') return raw;
    try {
        return JSON.parse(raw);
    } catch (e) {
        return {};
    }
}

selectTorneo.addEventListener('change', async (e) => {
    const id = e.target.value;
    if (!id) {
        Object.values(campi).forEach(c => c.value = '');
        resetModFormConfig();
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

            const cfg = parseConfig(data.config || {});
            if (regoleField && typeof cfg.regole_html === 'string') {
                regoleField.value = cfg.regole_html;
            } else if (regoleField) {
                regoleField.value = '';
            }

            if (modController && modController.applyConfig) {
                modController.applyConfig(cfg);
            } else {
                const radio = radioFormatiMod.find(r => r.value === (cfg.formato || ''));
                if (radio) radio.checked = true;
                const fRadio = radioFaseMod.find(r => r.value === (cfg.fase_finale || ''));
                if (fRadio) fRadio.checked = true;
                if (configInputs.campionato && cfg.campionato_squadre) configInputs.campionato.value = cfg.campionato_squadre;
                if (configInputs.gironi && cfg.numero_gironi) configInputs.gironi.value = cfg.numero_gironi;
                if (configInputs.perGirone && cfg.squadre_per_girone) configInputs.perGirone.value = cfg.squadre_per_girone;
                if (configInputs.totale && cfg.totale_squadre) configInputs.totale.value = cfg.totale_squadre;
                if (configInputs.gold && cfg.qualificati_gold !== undefined) configInputs.gold.value = cfg.qualificati_gold;
                if (configInputs.silver && cfg.qualificati_silver !== undefined) configInputs.silver.value = cfg.qualificati_silver;
                if (configInputs.eliminate && cfg.eliminate !== undefined) configInputs.eliminate.value = cfg.eliminate;
            }
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

      // Aggiorna indicatori Ã¢â€ â€˜Ã¢â€ â€œ
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
