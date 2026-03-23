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

/**
 * Pulisce il testo mantenendolo in UTF-8, eliminando eventuali byte non validi
 * e caratteri di controllo che generano simboli strani a video.
 */
function cleanUtf8Text($value): string {
    if (!is_string($value)) {
        $value = (string)$value;
    }
    $value = str_replace("\r\n", "\n", $value);
    $value = @iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: $value;
    // rimuove caratteri di controllo non stampabili (tranne tab e newline)
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    return trim($value);
}

function normalizeFaseFinaleValue(string $formula, string $fase): string {
    if ($formula === 'campionato') {
        if ($fase === 'gold') {
            return 'gold';
        }
        return 'coppe';
    }

    if ($formula === 'girone') {
        return $fase === 'coppe' ? 'coppe' : 'eliminazione_diretta';
    }

    return '';
}

function buildTorneoConfigFromRequest(array $src): array {
    $config = [
        'formato'            => $src['formula_torneo'] ?? '',
        'fase_finale'        => '',
        'campionato_squadre' => intOrZero($src['campionato_squadre'] ?? 0),
        'numero_gironi'      => intOrZero($src['numero_gironi'] ?? 0),
        'squadre_per_girone' => intOrZero($src['squadre_per_girone'] ?? 0),
        'totale_squadre'     => intOrZero($src['totale_squadre'] ?? 0),
        'qualificati_gold'   => intOrZero($src['qualificati_gold'] ?? 0),
        'qualificati_silver' => intOrZero($src['qualificati_silver'] ?? 0),
        'eliminate'          => intOrZero($src['eliminate'] ?? 0),
        'regole_html'        => cleanUtf8Text($src['regole_html'] ?? ''),
    ];

    if ($config['totale_squadre'] === 0) {
        if ($config['campionato_squadre'] > 0) {
            $config['totale_squadre'] = $config['campionato_squadre'];
        } elseif ($config['numero_gironi'] > 0 && $config['squadre_per_girone'] > 0) {
            $config['totale_squadre'] = $config['numero_gironi'] * $config['squadre_per_girone'];
        }
    }

    $config['fase_finale'] = normalizeFaseFinaleValue(
        (string)$config['formato'],
        (string)($src['fase_finale'] ?? '')
    );

    if ($config['formato'] === 'campionato' && $config['fase_finale'] === 'gold') {
        $config['qualificati_silver'] = 0;
    }

    return $config;
}

function aggiornaGironiSquadreTorneo(?mysqli $dbConn, string $torneoSlug, array $gironiMap): void {
    if ($torneoSlug === '' || empty($gironiMap)) {
        return;
    }

    if (!($dbConn instanceof mysqli)) {
        global $conn;
        if (!($conn instanceof mysqli)) {
            require __DIR__ . '/../includi/db.php';
        }
        if ($conn instanceof mysqli) {
            $dbConn = $conn;
        }
    }
    if (!($dbConn instanceof mysqli)) {
        return;
    }

    $check = @$dbConn->query("SHOW COLUMNS FROM squadre LIKE 'girone'");
    if (!$check || $check->num_rows === 0) {
        @$dbConn->query("ALTER TABLE squadre ADD COLUMN girone VARCHAR(32) DEFAULT NULL AFTER torneo");
        $check = @$dbConn->query("SHOW COLUMNS FROM squadre LIKE 'girone'");
    }
    if (!$check || $check->num_rows === 0) {
        throw new RuntimeException('Colonna girone non disponibile: ' . $dbConn->error);
    }

    $stmt = $dbConn->prepare("UPDATE squadre SET girone = ? WHERE id = ?");
    if (!$stmt) {
        throw new RuntimeException('Prepare update gironi fallita: ' . $dbConn->error);
    }

    foreach ($gironiMap as $id => $girone) {
        $teamId = (int)$id;
        if ($teamId <= 0) {
            continue;
        }

        $gironeValue = strtoupper(trim((string)(is_scalar($girone) ? $girone : '')));
        $gironeValue = preg_replace('/^GIRONE\s+/u', '', $gironeValue);
        $gironeValue = preg_replace('/^GRUPPO\s+/u', '', $gironeValue);
        $gironeValue = substr($gironeValue, 0, 32);

        $stmt->bind_param("si", $gironeValue, $teamId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Execute update gironi fallita: ' . $stmt->error);
        }
    }

    $stmt->close();
}

function scegliTemplatePerFormula(string $formula, string $faseFinale, string $baseDir): array {
    $default = [
        'html' => $baseDir . '/TorneoTemplate.php',
        'js'   => $baseDir . '/script-TorneoTemplate.js',
    ];

    if (!is_dir($baseDir)) {
        return $default;
    }

    $coppaAfrica = [
        'html' => $baseDir . '/Coppadafrica.php',
        'js'   => $baseDir . '/script-Coppadafrica.js',
    ];

    switch ($formula) {
        case 'campionato':
            return $default;
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

    $nomePulito = cleanUtf8Text($nomeTorneo);

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
            $nomePulito,
            $newScriptName,
            $nomePulito,
            $newScriptName,
            $slug,
            $nomePulito,
            $newScriptName,
            $slug,
            $nomePulito,
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

    $htmlContent = cleanUtf8Text($htmlContent);
    $jsContent = cleanUtf8Text($jsContent);

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
    $nome = cleanUtf8Text($_POST['nome'] ?? '');
    $stato = $_POST['stato'];
    $data_inizio = $_POST['data_inizio'];
    $data_fine = $_POST['data_fine'];
    $rawFileInput = cleanUtf8Text($_POST['filetorneo'] ?? '');
    $rawFile = preg_replace('/\.(html?|php)$/i', '', trim($rawFileInput));
    $slug = sanitizeTorneoSlug($rawFile);
    $filetorneo = $slug . '.php';
    $categoria = cleanUtf8Text($_POST['categoria'] ?? '');
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
    $nome = cleanUtf8Text($_POST['nome'] ?? '');
    $stato = $_POST['stato'];
    $data_inizio = $_POST['data_inizio'];
    $data_fine = $_POST['data_fine'];
    $rawFileInput = cleanUtf8Text($_POST['filetorneo'] ?? '');
    $rawFile = preg_replace('/\.(html?|php)$/i', '', trim($rawFileInput));
    $slug = sanitizeTorneoSlug($rawFile);
    $filetorneo = $slug . '.php';
    $categoria = cleanUtf8Text($_POST['categoria'] ?? '');
    $record = $torneo->getById($id);
    $img = salvaImmagineTorneo($nome, 'img_upload_mod');
    if (!$img && $record && !empty($record['img'])) {
        $img = $record['img'];
    }
    $squadre_complete = isset($_POST['squadre_complete']) ? 1 : 0;
    $config = buildTorneoConfigFromRequest($_POST);
    $torneo->aggiorna($id, $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $squadre_complete, $config);
    creaFileTorneoDaTemplate($nome, $slug, $formulaTorneo, $faseFinale);

    $filePrecedente = $record['filetorneo'] ?? '';
    if ($filePrecedente && strcasecmp($filePrecedente, $filetorneo) !== 0) {
        eliminaFileTorneo($filePrecedente);
    }

    $torneoSlugCorrente = sanitizeTorneoSlug(cleanUtf8Text($_POST['torneo_slug_corrente'] ?? ''));
    $gironiSquadre = isset($_POST['gironi_squadre']) && is_array($_POST['gironi_squadre'])
        ? $_POST['gironi_squadre']
        : [];
    if (($config['formato'] ?? '') === 'girone' && $torneoSlugCorrente !== '' && !empty($gironiSquadre)) {
        aggiornaGironiSquadreTorneo($conn ?? null, $torneoSlugCorrente, $gironiSquadre);
    }

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
        .notes-editor {
            min-height: 280px;
            line-height: 1.55;
            white-space: pre-wrap;
        }
        .notes-editor-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .notes-reset-btn {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #15293e;
            padding: 8px 14px;
            border-radius: 999px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease;
        }
        .notes-reset-btn:hover {
            background: #eef3f9;
            border-color: #b7c4d8;
        }
        .team-groups-editor {
            display: grid;
            gap: 10px;
        }
        .team-group-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 180px;
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            border: 1px solid #dbe4ee;
            border-radius: 12px;
            background: #fff;
        }
        .team-group-name {
            font-weight: 700;
            color: #15293e;
        }
        .team-group-empty {
            margin: 0;
            color: #617085;
            font-size: 0.95rem;
        }
        @media (max-width: 640px) {
            .team-group-row {
                grid-template-columns: 1fr;
            }
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
                    <label for="formula_torneo">Formula torneo</label>
                    <select name="formula_torneo" id="formula_torneo" required>
                        <option value="campionato" selected>Campionato</option>
                        <option value="girone">Girone</option>
                        <option value="eliminazione">Eliminazione diretta</option>
                    </select>
                    <small>Il form si adatta automaticamente alla formula selezionata.</small>
                </div>

                <div class="form-subgroup" id="campionatoSettings">
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

                <div class="form-subgroup" id="faseFinaleSettings">
                    <div class="form-group">
                        <label for="fase_finale">Fase finale prevista</label>
                        <select name="fase_finale" id="fase_finale"></select>
                        <small>I valori disponibili cambiano in base alla formula torneo.</small>
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
                        <div class="form-group half" data-role="qualificati-silver-group">
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
                    <div class="notes-editor-toolbar">
                        <label for="regole_html">Regole / Note torneo</label>
                        <button type="button" class="notes-reset-btn" data-action="reset-regole">Ripristina testo predefinito</button>
                    </div>
                    <textarea name="regole_html" id="regole_html" class="notes-editor" rows="12" placeholder="Criteri di qualificazione, punti bonus, regole speciali"></textarea>
                    <small>Per i campionati il testo viene precompilato con righe modificabili in base ai campi inseriti.</small>
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
                <input type="hidden" name="torneo_slug_corrente" id="mod_torneo_slug_corrente" value="">

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
                    <label for="mod_formula_torneo">Formula torneo</label>
                    <select name="formula_torneo" id="mod_formula_torneo">
                        <option value="">-- Seleziona formula --</option>
                        <option value="campionato">Campionato</option>
                        <option value="girone">Girone</option>
                        <option value="eliminazione">Eliminazione diretta</option>
                    </select>
                    <small>Il form si adatta automaticamente alla formula del torneo selezionato.</small>
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

                <div class="form-subgroup hidden" id="gironeSquadreSettingsMod">
                    <div class="form-group">
                        <label>Gironi squadre</label>
                        <div id="mod_gironi_squadre_container" class="team-groups-editor"></div>
                        <p id="mod_gironi_squadre_empty" class="team-group-empty hidden">Nessuna squadra associata a questo torneo.</p>
                        <small>Questa sezione compare solo per i tornei a gironi e permette di spostare manualmente ogni squadra nel girone corretto.</small>
                    </div>
                </div>

                <div class="form-subgroup hidden" id="faseFinaleSettingsMod">
                    <div class="form-group">
                        <label for="mod_fase_finale">Fase finale prevista</label>
                        <select name="fase_finale" id="mod_fase_finale"></select>
                        <small>I valori disponibili cambiano in base alla formula torneo.</small>
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
                        <div class="form-group half" data-role="qualificati-silver-group">
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
                    <div class="notes-editor-toolbar">
                        <label for="mod_regole_html">Regole / Note torneo</label>
                        <button type="button" class="notes-reset-btn" data-action="reset-regole">Ripristina testo predefinito</button>
                    </div>
                    <textarea name="regole_html" id="mod_regole_html" class="notes-editor" rows="12" placeholder="Criteri di qualificazione, punti bonus, regole speciali"></textarea>
                    <small>Per i campionati il testo viene precompilato con righe modificabili in base ai campi inseriti.</small>
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
                const tipoSelect = formEl.querySelector('select[name="formula_torneo"]');
                const tipoRadios = Array.from(formEl.querySelectorAll('input[name="formula_torneo"]'));
                const campionatoBox = formEl.querySelector('[id^="campionatoSettings"]');
                const gironeBox = formEl.querySelector('[id^="gironeSettings"]');
                const finaleBox = formEl.querySelector('[id^="faseFinaleSettings"]');
                const qualificheBox = formEl.querySelector('[id^="qualificazioniSettings"]');
                const campionatoInput = formEl.querySelector('input[name="campionato_squadre"]');
                const numeroGironiInput = formEl.querySelector('input[name="numero_gironi"]');
                const squadrePerGironeInput = formEl.querySelector('input[name="squadre_per_girone"]');
                const finaleSelect = formEl.querySelector('select[name="fase_finale"]');
                const totaleInput = formEl.querySelector('input[name="totale_squadre"]');
                const goldInput = formEl.querySelector('input[name="qualificati_gold"]');
                const silverInput = formEl.querySelector('input[name="qualificati_silver"]');
                const silverGroup = formEl.querySelector('[data-role="qualificati-silver-group"]');
                const eliminateInput = formEl.querySelector('input[name="eliminate"]');
                const regoleInput = formEl.querySelector('textarea[name="regole_html"]');
                const resetRegoleBtn = formEl.querySelector('[data-action="reset-regole"]');

                function getTipoValue() {
                    if (tipoSelect) return tipoSelect.value || '';
                    const checked = tipoRadios.find(radio => radio.checked);
                    return checked ? checked.value : '';
                }

                function setTipoValue(value) {
                    if (tipoSelect) {
                        tipoSelect.value = value;
                        return;
                    }
                    tipoRadios.forEach(radio => {
                        radio.checked = radio.value === value;
                    });
                }

                function setRequired(inputs, value) {
                    inputs.forEach(el => {
                        if (el) el.required = value;
                    });
                }

                function setDisabled(inputs, value) {
                    inputs.forEach(el => {
                        if (el) el.disabled = value;
                    });
                }

                function getFinaleOptions(formula) {
                    if (formula === 'campionato') {
                        return [
                            { value: 'coppe', label: 'Coppa Gold e Silver' },
                            { value: 'gold', label: 'Coppa Gold' }
                        ];
                    }

                    if (formula === 'girone') {
                        return [
                            { value: 'eliminazione_diretta', label: 'Eliminazione diretta' },
                            { value: 'coppe', label: 'Coppa Gold e Silver' }
                        ];
                    }

                    return [];
                }

                function getDefaultFinaleValue(formula) {
                    if (formula === 'campionato') {
                        return 'coppe';
                    }

                    if (formula === 'girone') {
                        return 'eliminazione_diretta';
                    }

                    return '';
                }

                function normalizeFinaleValue(formula, value) {
                    if (formula === 'campionato') {
                        return value === 'gold' ? 'gold' : 'coppe';
                    }

                    if (formula === 'girone') {
                        return value === 'coppe' ? 'coppe' : 'eliminazione_diretta';
                    }

                    return '';
                }

                function syncFinaleOptions(formula, preferredValue = '') {
                    if (!finaleSelect) return '';

                    const options = getFinaleOptions(formula);
                    const currentValue = preferredValue || getDefaultFinaleValue(formula);
                    finaleSelect.innerHTML = '';

                    options.forEach(({ value, label }) => {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = label;
                        finaleSelect.appendChild(option);
                    });

                    if (!options.length) {
                        finaleSelect.required = false;
                        return '';
                    }

                    const normalizedValue = normalizeFinaleValue(formula, currentValue);
                    const nextValue = options.some(option => option.value === normalizedValue)
                        ? normalizedValue
                        : options[0].value;
                    finaleSelect.value = nextValue;
                    finaleSelect.required = true;
                    return nextValue;
                }

                function toggleFinale(show, formula, preferredValue = '') {
                    if (!finaleBox || !finaleSelect) return '';

                    if (show) {
                        finaleBox.classList.remove('hidden');
                        finaleSelect.disabled = false;
                        return syncFinaleOptions(formula, preferredValue);
                    }

                    finaleBox.classList.add('hidden');
                    finaleSelect.innerHTML = '';
                    finaleSelect.required = false;
                    finaleSelect.disabled = true;
                    return '';
                }

                function toggleQualifiche(show) {
                    if (!qualificheBox) return;
                    qualificheBox.classList.toggle('hidden', !show);
                    setDisabled([totaleInput, goldInput, silverInput, eliminateInput], !show);
                    if (!show) {
                        [totaleInput, goldInput, silverInput, eliminateInput].forEach(el => {
                            if (el) {
                                el.value = '';
                                el.dataset.auto = '';
                            }
                        });
                    }
                }

                function toggleSilverField(show) {
                    if (silverGroup) {
                        silverGroup.classList.toggle('hidden', !show);
                    }
                    if (!silverInput) return;

                    if (show) {
                        silverInput.disabled = false;
                        const prevValue = silverInput.dataset.prevValue || '';
                        if ((silverInput.value === '' || silverInput.value === '0') && prevValue !== '') {
                            silverInput.value = prevValue;
                        }
                        return;
                    }

                    if (!silverInput.disabled) {
                        silverInput.dataset.prevValue = silverInput.value;
                    }
                    silverInput.value = '0';
                    silverInput.disabled = true;
                }

                function getEffectiveSilverValue() {
                    if (!silverInput || silverInput.disabled) return 0;
                    const silver = parseInt(silverInput.value || '0', 10);
                    return Number.isFinite(silver) ? silver : 0;
                }

                function updateTotale() {
                    if (!totaleInput) return;
                    const campVal = parseInt(campionatoInput?.value || '0', 10);
                    const gironi = parseInt(numeroGironiInput?.value || '0', 10);
                    const perGirone = parseInt(squadrePerGironeInput?.value || '0', 10);
                    let computed = 0;

                    if (campVal > 0) {
                        computed = campVal;
                    } else if (gironi > 0 && perGirone > 0) {
                        computed = gironi * perGirone;
                    }

                    if (!computed) {
                        return;
                    }

                    if (!totaleInput.value || totaleInput.dataset.auto === '1') {
                        totaleInput.value = computed;
                        totaleInput.dataset.auto = '1';
                    }
                }

                function updateEliminate(force = false) {
                    if (!eliminateInput) return;
                    const tot = parseInt(totaleInput?.value || '0', 10);
                    const gold = parseInt(goldInput?.value || '0', 10);
                    const silver = getEffectiveSilverValue();
                    if (!tot) return;
                    const computed = Math.max(0, tot - gold - silver);
                    if (force || !eliminateInput.value || eliminateInput.dataset.auto === '1') {
                        eliminateInput.value = computed;
                        eliminateInput.dataset.auto = '1';
                    }
                }

                function resolveTeamCount() {
                    const totale = parseInt(totaleInput?.value || '0', 10);
                    const campionato = parseInt(campionatoInput?.value || '0', 10);
                    const gironi = parseInt(numeroGironiInput?.value || '0', 10);
                    const perGirone = parseInt(squadrePerGironeInput?.value || '0', 10);
                    if (totale > 0) return totale;
                    if (campionato > 0) return campionato;
                    if (gironi > 0 && perGirone > 0) return gironi * perGirone;
                    return 0;
                }

                function buildDefaultRegole() {
                    if (getTipoValue() !== 'campionato') {
                        return '';
                    }

                    const teamCount = resolveTeamCount();
                    const gold = parseInt(goldInput?.value || '0', 10);
                    const silver = getEffectiveSilverValue();
                    const finaleValue = finaleSelect?.value || 'coppe';
                    const teamLabel = teamCount > 0 ? String(teamCount) : 'X';
                    const goldLabel = gold > 0 ? String(gold) : 'X';
                    const silverStartLabel = gold > 0 ? String(gold + 1) : 'X';
                    const silverEndLabel = gold > 0 && silver > 0 ? String(gold + silver) : 'X';

                    const sections = [
                        `Struttura del campionato: Il torneo è composto da ${teamLabel} squadre e si sviluppa in due fasi principali.`,
                        `Fase 1 - Regular Season: Le ${teamLabel} squadre partecipano a una Regular Season di X giornate.\nLa squadra prima in classifica al termine del girone riceve il Trofeo Regular Season.`,
                        finaleValue === 'gold'
                            ? `Fase 2 - Coppe: Le squadre classificate dal 1° all'${goldLabel}° posto accedono alla Coppa Gold.\nLa Coppa Gold prevede una premiazione con trofeo per la vincitrice.`
                            : `Fase 2 - Coppe: Le squadre classificate dal 1° all'${goldLabel}° posto accedono alla Coppa Gold.\nLe squadre classificate dal ${silverStartLabel}° al ${silverEndLabel}° posto accedono alla Coppa Silver.\nEntrambe le coppe prevedono una premiazione con trofeo per la vincitrice.`,
                        `Premi finali: Dopo la finale di Coppa Gold verranno assegnati i seguenti riconoscimenti:\nMiglior Giocatore\nMiglior Portiere\nMiglior Difensore\nMiglior Attaccante`,
                        `Regole di gioco: Ogni partita dura 2 tempi da 25 minuti.\nOgni squadra ha 1 chiamata VAR disponibile per partita.`,
                        `Calendario: Le partite si disputano principalmente il mercoledi e il giovedi.\nIl calendario della settimana successiva viene pubblicato ogni giovedi o venerdi.`
                    ];

                    return sections.join('\n\n');
                }

                function buildFormulaAwareRegole() {
                    const formula = getTipoValue();
                    const teamCount = resolveTeamCount();
                    const gironi = parseInt(numeroGironiInput?.value || '0', 10);
                    const perGirone = parseInt(squadrePerGironeInput?.value || '0', 10);
                    const gold = parseInt(goldInput?.value || '0', 10);
                    const silver = getEffectiveSilverValue();
                    const finaleValue = finaleSelect?.value || 'coppe';
                    const teamLabel = teamCount > 0 ? String(teamCount) : 'X';
                    const goldLabel = gold > 0 ? String(gold) : 'X';

                    if (formula === 'campionato') {
                        const silverStartLabel = gold > 0 ? String(gold + 1) : 'X';
                        const silverEndLabel = gold > 0 && silver > 0 ? String(gold + silver) : 'X';
                        return [
                            `Struttura del campionato: Il torneo e composto da ${teamLabel} squadre e si sviluppa in due fasi principali.`,
                            `Fase 1 - Regular Season: Le ${teamLabel} squadre partecipano a una Regular Season di X giornate.\nLa squadra prima in classifica al termine del girone riceve il Trofeo Regular Season.`,
                            finaleValue === 'gold'
                                ? `Fase 2 - Coppe: Le squadre classificate dal 1 al ${goldLabel} posto accedono alla Coppa Gold.\nLa Coppa Gold prevede una premiazione con trofeo per la vincitrice.`
                                : `Fase 2 - Coppe: Le squadre classificate dal 1 al ${goldLabel} posto accedono alla Coppa Gold.\nLe squadre classificate dal ${silverStartLabel} al ${silverEndLabel} posto accedono alla Coppa Silver.\nEntrambe le coppe prevedono una premiazione con trofeo per la vincitrice.`,
                            `Premi finali: Dopo la finale di Coppa Gold verranno assegnati i seguenti riconoscimenti:\nMiglior Giocatore\nMiglior Portiere\nMiglior Difensore\nMiglior Attaccante`,
                            `Regole di gioco: Ogni partita dura 2 tempi da 25 minuti.\nOgni squadra ha 1 chiamata VAR disponibile per partita.`,
                            `Calendario: Le partite si disputano principalmente il mercoledi e il giovedi.\nIl calendario della settimana successiva viene pubblicato ogni giovedi o venerdi.`
                        ].join('\n\n');
                    }

                    if (formula === 'girone') {
                        const gironiLabel = gironi > 0 ? String(gironi) : 'X';
                        const perGironeLabel = perGirone > 0 ? String(perGirone) : 'X';
                        const goldPerGirone = gironi > 0 && gold > 0 ? Math.floor(gold / gironi) : 0;
                        const silverPerGirone = gironi > 0 && silver > 0 ? Math.floor(silver / gironi) : 0;
                        const goldPerGironeLabel = goldPerGirone > 0 ? String(goldPerGirone) : 'X';
                        const silverPerGironeLabel = silverPerGirone > 0 ? String(silverPerGirone) : 'X';
                        return [
                            `Struttura del torneo: Il torneo e composto da ${teamLabel} squadre suddivise in ${gironiLabel} gironi da ${perGironeLabel} squadre.`,
                            `Fase 1 - Gironi: Le squadre disputano la Regular Season all'interno del proprio girone.`,
                            finaleValue === 'coppe'
                                ? `Fase 2 - Coppe: Le prime ${goldPerGironeLabel} squadre di ogni girone accedono alla Coppa Gold.\nLe successive ${silverPerGironeLabel} squadre di ogni girone accedono alla Coppa Silver.\nEntrambe le coppe prevedono una premiazione con trofeo per la vincitrice.`
                                : `Fase 2 - Eliminazione diretta: Le prime ${goldPerGironeLabel} squadre di ogni girone accedono alla fase finale a eliminazione diretta.\nLa fase finale prevede una premiazione con trofeo per la vincitrice.`,
                            `Premi finali: Dopo la finale di Coppa Gold verranno assegnati i seguenti riconoscimenti:\nMiglior Giocatore\nMiglior Portiere\nMiglior Difensore\nMiglior Attaccante`,
                            `Regole di gioco: Ogni partita dura 2 tempi da 25 minuti.\nOgni squadra ha 1 chiamata VAR disponibile per partita.`,
                            `Calendario: Le partite si disputano principalmente il mercoledi e il giovedi.\nIl calendario della settimana successiva viene pubblicato ogni giovedi o venerdi.`
                        ].join('\n\n');
                    }

                    return buildDefaultRegole();
                }

                function syncRegole(force = false) {
                    if (!regoleInput) return;
                    const generated = buildFormulaAwareRegole();
                    const isAuto = regoleInput.dataset.auto === '1';
                    const isEmpty = regoleInput.value.trim() === '';

                    if (generated === '') {
                        if (force || isAuto || isEmpty) {
                            regoleInput.value = '';
                            regoleInput.dataset.auto = '';
                        }
                        return;
                    }

                    if (force || isAuto || isEmpty) {
                        regoleInput.value = generated;
                        regoleInput.dataset.auto = '1';
                    }
                }

                function syncFaseDependents() {
                    const formula = getTipoValue();
                    const finaleValue = finaleSelect?.value || '';
                    const showSilver = formula !== 'eliminazione' && finaleValue === 'coppe';
                    toggleSilverField(showSilver);
                    updateEliminate(false);
                    syncRegole(false);
                }

                function handleTipoChange(value, preferredFinale = '') {
                    const hasSelection = value === 'campionato' || value === 'girone' || value === 'eliminazione';
                    const showFinale = hasSelection && value !== 'eliminazione';

                    if (campionatoBox) campionatoBox.classList.toggle('hidden', value !== 'campionato');
                    if (gironeBox) gironeBox.classList.toggle('hidden', value !== 'girone');

                    setRequired([campionatoInput], value === 'campionato');
                    setRequired([numeroGironiInput, squadrePerGironeInput], value === 'girone');
                    setDisabled([campionatoInput], value !== 'campionato');
                    setDisabled([numeroGironiInput, squadrePerGironeInput], value !== 'girone');

                    toggleFinale(showFinale, value, preferredFinale);
                    toggleQualifiche(hasSelection && value !== 'eliminazione');
                    updateTotale();
                    syncFaseDependents();
                }

                if (tipoSelect) {
                    tipoSelect.addEventListener('change', () => handleTipoChange(tipoSelect.value));
                }
                tipoRadios.forEach(radio => {
                    radio.addEventListener('change', () => handleTipoChange(radio.value));
                });

                if (finaleSelect) {
                    finaleSelect.addEventListener('change', () => syncFaseDependents());
                }

                [campionatoInput, numeroGironiInput, squadrePerGironeInput].forEach(el => {
                    if (!el) return;
                    el.addEventListener('input', () => {
                        updateTotale();
                        updateEliminate(false);
                        syncRegole(false);
                    });
                });

                [totaleInput, goldInput, silverInput].forEach(el => {
                    if (!el) return;
                    el.addEventListener('input', () => {
                        if (el === totaleInput) {
                            totaleInput.dataset.auto = '';
                        }
                        updateEliminate(false);
                        syncRegole(false);
                    });
                });
                if (eliminateInput) {
                    eliminateInput.addEventListener('input', () => eliminateInput.dataset.auto = '');
                }
                if (regoleInput) {
                    regoleInput.addEventListener('input', () => {
                        regoleInput.dataset.auto = '';
                    });
                }
                if (resetRegoleBtn) {
                    resetRegoleBtn.addEventListener('click', () => syncRegole(true));
                }

                handleTipoChange(getTipoValue());
                updateTotale();
                syncRegole(false);

                function applyConfig(cfg = {}) {
                    const formato = cfg.formato || cfg.formula_torneo || '';
                    setTipoValue(formato);

                    if (campionatoInput) campionatoInput.value = cfg.campionato_squadre ?? '';
                    if (numeroGironiInput) numeroGironiInput.value = cfg.numero_gironi ?? '';
                    if (squadrePerGironeInput) squadrePerGironeInput.value = cfg.squadre_per_girone ?? '';
                    if (totaleInput) totaleInput.value = cfg.totale_squadre ?? '';
                    if (goldInput) goldInput.value = cfg.qualificati_gold ?? '';
                    if (silverInput) {
                        silverInput.value = cfg.qualificati_silver ?? '';
                        silverInput.dataset.prevValue = cfg.qualificati_silver ?? '';
                    }
                    if (eliminateInput) {
                        eliminateInput.value = cfg.eliminate ?? '';
                        eliminateInput.dataset.auto = '';
                    }
                    if (totaleInput) {
                        totaleInput.dataset.auto = '';
                    }

                    handleTipoChange(formato, cfg.fase_finale || '');
                    updateTotale();
                    updateEliminate(true);

                    if (regoleInput) {
                        const regoleValue = typeof cfg.regole_html === 'string' ? cfg.regole_html.trim() : '';
                        if (regoleValue !== '') {
                            regoleInput.value = cfg.regole_html;
                            regoleInput.dataset.auto = '';
                        } else {
                            regoleInput.value = '';
                            syncRegole(true);
                        }
                    }
                }

                function reset() {
                    setTipoValue('');
                    if (campionatoInput) campionatoInput.value = '';
                    if (numeroGironiInput) numeroGironiInput.value = '';
                    if (squadrePerGironeInput) squadrePerGironeInput.value = '';
                    if (totaleInput) totaleInput.value = '';
                    if (goldInput) goldInput.value = '';
                    if (silverInput) {
                        silverInput.value = '';
                        silverInput.dataset.prevValue = '';
                        silverInput.disabled = false;
                    }
                    if (eliminateInput) {
                        eliminateInput.value = '';
                        eliminateInput.dataset.auto = '';
                    }
                    if (totaleInput) {
                        totaleInput.dataset.auto = '';
                    }
                    if (regoleInput) {
                        regoleInput.value = '';
                        regoleInput.dataset.auto = '';
                    }
                    handleTipoChange('');
                }

                return { applyConfig, handleTipoChange, reset, updateTotale };
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
const formulaTorneoMod = document.getElementById('mod_formula_torneo');
const faseFinaleMod = document.getElementById('mod_fase_finale');
const modTorneoSlugCorrente = document.getElementById('mod_torneo_slug_corrente');
const gironiSquadreSection = document.getElementById('gironeSquadreSettingsMod');
const gironiSquadreContainer = document.getElementById('mod_gironi_squadre_container');
const gironiSquadreEmpty = document.getElementById('mod_gironi_squadre_empty');
let squadreGironiState = [];

function resetModFormConfig() {
    if (modController && modController.reset) {
        modController.reset();
    } else {
        if (formulaTorneoMod) {
            formulaTorneoMod.value = '';
        }
        Object.values(configInputs).forEach(el => { if (el) el.value = ''; });
        if (faseFinaleMod) {
            faseFinaleMod.innerHTML = '';
            faseFinaleMod.value = '';
        }
    }

    clearSquadreGironiEditor();
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

function torneoSlugFromFile(fileName = '') {
    return String(fileName || '').replace(/\.(html?|php)$/i, '').trim();
}

function buildGironeLabels(count) {
    const total = Math.max(0, Number.parseInt(count || '0', 10));
    return Array.from({ length: total }, (_, idx) => {
        let n = idx;
        let label = '';
        do {
            label = String.fromCharCode(65 + (n % 26)) + label;
            n = Math.floor(n / 26) - 1;
        } while (n >= 0);
        return label;
    });
}

function normalizeGironeEditorValue(value = '') {
    return String(value || '')
        .trim()
        .toUpperCase()
        .replace(/^GIRONE\s+/u, '')
        .replace(/^GRUPPO\s+/u, '');
}

function shouldShowGironiEditor() {
    return (formulaTorneoMod?.value || '') === 'girone';
}

function clearSquadreGironiEditor() {
    squadreGironiState = [];
    if (modTorneoSlugCorrente) {
        modTorneoSlugCorrente.value = '';
    }
    if (gironiSquadreContainer) {
        gironiSquadreContainer.innerHTML = '';
    }
    if (gironiSquadreEmpty) {
        gironiSquadreEmpty.textContent = 'Nessuna squadra associata a questo torneo.';
        gironiSquadreEmpty.classList.add('hidden');
    }
    if (gironiSquadreSection) {
        gironiSquadreSection.classList.add('hidden');
    }
}

function renderSquadreGironiEditor() {
    if (!gironiSquadreSection || !gironiSquadreContainer || !gironiSquadreEmpty) return;

    const show = shouldShowGironiEditor();
    gironiSquadreSection.classList.toggle('hidden', !show);
    gironiSquadreContainer.innerHTML = '';

    if (!show) {
        gironiSquadreEmpty.classList.add('hidden');
        return;
    }

    const labels = buildGironeLabels(configInputs.gironi?.value || 0);
    if (!labels.length) {
        gironiSquadreEmpty.textContent = 'Imposta prima il numero di gironi per assegnare le squadre.';
        gironiSquadreEmpty.classList.remove('hidden');
        return;
    }

    if (!squadreGironiState.length) {
        gironiSquadreEmpty.textContent = 'Nessuna squadra associata a questo torneo.';
        gironiSquadreEmpty.classList.remove('hidden');
        return;
    }

    gironiSquadreEmpty.classList.add('hidden');
    squadreGironiState.forEach((team, index) => {
        const row = document.createElement('div');
        row.className = 'team-group-row';

        const name = document.createElement('div');
        name.className = 'team-group-name';
        name.textContent = team.nome || `Squadra ${index + 1}`;

        const select = document.createElement('select');
        select.name = `gironi_squadre[${team.id}]`;
        select.innerHTML = '<option value="">-- Seleziona girone --</option>';
        labels.forEach(label => {
            const option = document.createElement('option');
            option.value = label;
            option.textContent = `Girone ${label}`;
            select.appendChild(option);
        });

        const currentValue = normalizeGironeEditorValue(team.girone);
        select.value = labels.includes(currentValue) ? currentValue : '';
        select.addEventListener('change', () => {
            squadreGironiState[index].girone = select.value;
        });

        row.appendChild(name);
        row.appendChild(select);
        gironiSquadreContainer.appendChild(row);
    });
}

async function loadSquadreGironiEditor(torneoSlug) {
    if (modTorneoSlugCorrente) {
        modTorneoSlugCorrente.value = torneoSlug || '';
    }

    if (!torneoSlug) {
        squadreGironiState = [];
        renderSquadreGironiEditor();
        return;
    }

    try {
        const res = await fetch(`/api/get_squadre_torneo.php?torneo=${encodeURIComponent(torneoSlug)}`);
        const data = await res.json();
        squadreGironiState = Array.isArray(data) ? data.map(team => ({
            id: team.id,
            nome: team.nome || '',
            girone: normalizeGironeEditorValue(team.girone || '')
        })) : [];
    } catch (err) {
        console.error('Errore nel recupero delle squadre del torneo:', err);
        squadreGironiState = [];
    }

    renderSquadreGironiEditor();
}

if (formulaTorneoMod) {
    formulaTorneoMod.addEventListener('change', () => renderSquadreGironiEditor());
}

if (configInputs.gironi) {
    ['input', 'change'].forEach(eventName => {
        configInputs.gironi.addEventListener(eventName, () => renderSquadreGironiEditor());
    });
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
            const torneoSlug = torneoSlugFromFile(data.filetorneo || '');

            const cfg = parseConfig(data.config || {});
            if (modController && modController.applyConfig) {
                modController.applyConfig(cfg);
            } else {
                if (formulaTorneoMod) {
                    formulaTorneoMod.value = cfg.formato || '';
                }
                if (faseFinaleMod) {
                    faseFinaleMod.innerHTML = '';
                    const formulaValue = cfg.formato || cfg.formula_torneo || '';
                    const options = formulaValue === 'girone'
                        ? [
                            { value: 'eliminazione_diretta', label: 'Eliminazione diretta' },
                            { value: 'coppe', label: 'Coppa Gold e Silver' }
                        ]
                        : [
                            { value: 'coppe', label: 'Coppa Gold e Silver' },
                            { value: 'gold', label: 'Coppa Gold' }
                        ];
                    const finaleValue = formulaValue === 'girone'
                        ? (cfg.fase_finale === 'coppe' ? 'coppe' : 'eliminazione_diretta')
                        : (cfg.fase_finale === 'gold' ? 'gold' : 'coppe');
                    options.forEach(({ value, label }) => {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = label;
                        faseFinaleMod.appendChild(option);
                    });
                    faseFinaleMod.value = finaleValue;
                }
                if (configInputs.campionato && cfg.campionato_squadre) configInputs.campionato.value = cfg.campionato_squadre;
                if (configInputs.gironi && cfg.numero_gironi) configInputs.gironi.value = cfg.numero_gironi;
                if (configInputs.perGirone && cfg.squadre_per_girone) configInputs.perGirone.value = cfg.squadre_per_girone;
                if (configInputs.totale && cfg.totale_squadre) configInputs.totale.value = cfg.totale_squadre;
                if (configInputs.gold && cfg.qualificati_gold !== undefined) configInputs.gold.value = cfg.qualificati_gold;
                if (configInputs.silver && cfg.qualificati_silver !== undefined) configInputs.silver.value = cfg.qualificati_silver;
                if (configInputs.eliminate && cfg.eliminate !== undefined) configInputs.eliminate.value = cfg.eliminate;
            }

            await loadSquadreGironiEditor(torneoSlug);
        } else {
            clearSquadreGironiEditor();
            alert('Errore: ' + (data.error || 'Dati non trovati'));
        }
    } catch (err) {
        clearSquadreGironiEditor();
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
