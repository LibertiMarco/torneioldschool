<?php
require_once __DIR__ . '/../includi/security.php';
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/crud/giocatore.php';
require_once __DIR__ . '/crud/Squadra.php';
require_once __DIR__ . '/crud/SquadraGiocatore.php';
require_once __DIR__ . '/../includi/image_optimizer.php';
$giocatore = new Giocatore();
$squadraModel = new Squadra();
$pivot = new SquadraGiocatore();
$adminCsrf = csrf_get_token('admin_giocatori');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('admin_giocatori');
}
$currentAction = $_GET['action'] ?? 'crea';

function redirectGestione($action = null, $extraParams = []) {
    $params = [];
    if ($action) {
        $params['action'] = $action;
    }
    foreach ($extraParams as $key => $value) {
        $params[$key] = $value;
    }
    $query = $params ? '?' . http_build_query($params) : '';
    header("Location: gestione_giocatori.php{$query}");
    exit;
}

function salvaFotoGiocatore($nome, $cognome, $fieldName, $fotoEsistente = null) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $fotoEsistente ?: '/img/giocatori/unknown.jpg';
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return $fotoEsistente ?: '/img/giocatori/unknown.jpg';
    }

    $maxSize = 20 * 1024 * 1024; // accetta file pesanti, verranno compressi
    if ($_FILES[$fieldName]['size'] > $maxSize) {
        return $fotoEsistente ?: '/img/giocatori/unknown.jpg';
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/pjpeg' => 'jpg',
        'image/jpg' => 'jpg'
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $_FILES[$fieldName]['tmp_name']) : false;
    if ($finfo instanceof finfo) {
        unset($finfo);
    }
    if (!$mime) {
        return $fotoEsistente ?: '/img/giocatori/unknown.jpg';
    }
    if (!isset($allowed[$mime])) {
        return $fotoEsistente ?: '/img/giocatori/unknown.jpg';
    }

    $baseDirPath = __DIR__ . '/../img/giocatori';
    if (!is_dir($baseDirPath)) {
        @mkdir($baseDirPath, 0775, true);
    }
    $baseDir = realpath($baseDirPath);
    if (!$baseDir) {
        return $fotoEsistente ?: '/img/giocatori/unknown.jpg';
    }

    $slugNome = strtolower(preg_replace('/[^a-z0-9]/i', '', $nome . $cognome));
    if ($slugNome === '') {
        $slugNome = 'giocatore';
    }

    $extension = $allowed[$mime];
    $filename = "{$slugNome}.{$extension}";
    $counter = 2;
    while (file_exists($baseDir . '/' . $filename)) {
        $filename = "{$slugNome}_{$counter}.{$extension}";
        $counter++;
    }

    $dest = $baseDir . '/' . $filename;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $dest)) {
        return $fotoEsistente ?: '/img/giocatori/unknown.jpg';
    }

    optimize_image_file($dest, [
        'maxWidth' => 1920,
        'maxHeight' => 1920,
        'quality' => 82,
        'maxBytes' => 8 * 1024 * 1024,
    ]);

    return '/img/giocatori/' . $filename;
}

function cancellaFotoGiocatore($fotoPath) {
    if (!$fotoPath) {
        return;
    }
    $fotoPath = str_replace('\\', '/', $fotoPath);
    $defaultFoto = '/img/giocatori/unknown.jpg';
    if ($fotoPath === $defaultFoto) {
        return;
    }
    if (strpos($fotoPath, '/img/giocatori/') !== 0) {
        return;
    }

    $uploadsDir = realpath(__DIR__ . '/../img/giocatori');
    if (!$uploadsDir) {
        return;
    }

    $filename = basename($fotoPath);
    $absolutePath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function salvaFotoAssociazione($nome, $cognome, $fieldName, $fotoEsistente = null) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $maxSize = 20 * 1024 * 1024;
    if ($_FILES[$fieldName]['size'] > $maxSize) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $_FILES[$fieldName]['tmp_name']) : false;
    if ($finfo instanceof finfo) {
        unset($finfo);
    }
    if (!$mime) {
        return null;
    }
    if (!isset($allowed[$mime])) {
        return null;
    }

    $baseDir = realpath(__DIR__ . '/../img/giocatori');
    if (!$baseDir) {
        return null;
    }

    $slugBase = strtolower(preg_replace('/[^a-z0-9]/i', '', $nome . $cognome));
    if ($slugBase === '') {
        $slugBase = 'associazione';
    }
    $slugNome = "{$slugBase}_assoc";

    $extension = $allowed[$mime];
    $filename = "{$slugNome}.{$extension}";
    $counter = 2;
    while (file_exists($baseDir . '/' . $filename)) {
        $filename = "{$slugNome}_{$counter}.{$extension}";
        $counter++;
    }

    $dest = $baseDir . '/' . $filename;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $dest)) {
        return null;
    }

    optimize_image_file($dest, [
        'maxWidth' => 1920,
        'maxHeight' => 1920,
        'quality' => 82,
        'maxBytes' => 8 * 1024 * 1024,
    ]);

    if ($fotoEsistente && strpos($fotoEsistente, '/img/giocatori/') === 0 && $fotoEsistente !== '/img/giocatori/unknown.jpg') {
        cancellaFotoGiocatore($fotoEsistente);
    }

    return '/img/giocatori/' . $filename;
}

$tornei = [];
$torneiResult = $squadraModel->getTornei();
if ($torneiResult) {
    if ($torneiResult instanceof mysqli_result) {
        while ($row = $torneiResult->fetch_assoc()) {
            $tornei[] = $row;
        }
    } elseif (is_array($torneiResult)) {
        $tornei = $torneiResult;
    }
}

// --- CREA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    if ($giocatore->esistePerNomeCognome($nome, $cognome)) {
        redirectGestione('crea', ['duplicate' => 1]);
    }
    $fotoPath = salvaFotoGiocatore($nome, $cognome, 'foto_upload');
    $nuovoId = $giocatore->crea(
        $nome,
        $cognome,
        '', // ruolo spostato su associazione squadra
        0,
        0,
        0,
        0,
        null,
        $fotoPath
    );

    redirectGestione('crea');
}

// --- AGGIORNA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
    $id = (int)$_POST['id'];
    $record = $giocatore->getById($id);
    if (!$record) {
        redirectGestione('modifica');
    }
    $fotoEsistente = $record['foto'] ?? '/img/giocatori/unknown.jpg';
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $fotoPath = salvaFotoGiocatore($nome, $cognome, 'foto_upload_mod', $fotoEsistente);

    // preserva statistiche e ruolo esistenti
    $ruolo = $record['ruolo'] ?? '';
    $presenze = isset($record['presenze']) ? (int)$record['presenze'] : 0;
    $reti = isset($record['reti']) ? (int)$record['reti'] : 0;
    $gialli = isset($record['gialli']) ? (int)$record['gialli'] : 0;
    $rossi = isset($record['rossi']) ? (int)$record['rossi'] : 0;
    $media = isset($record['media_voti']) && $record['media_voti'] !== '' ? (float)$record['media_voti'] : null;

    $giocatore->aggiorna(
        $id,
        $nome,
        $cognome,
        $ruolo, // mantieni ruolo registrato
        $presenze,
        $reti,
        $gialli,
        $rossi,
        $media,
        $fotoPath
    );

    redirectGestione('modifica');
}

// --- ASSOCIA GIOCATORE A SQUADRA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['associa_squadra'])) {
    $giocatoreAssoc = (int)($_POST['giocatore_associa'] ?? 0);
    $squadraAssoc = (int)($_POST['squadra_associa'] ?? 0);
        if ($giocatoreAssoc && $squadraAssoc) {
            if ($pivot->esisteAssociazione($giocatoreAssoc, $squadraAssoc)) {
                redirectGestione('associazioni', ['assoc_exists' => 1]);
            }
            $giocatoreRecord = $giocatore->getById($giocatoreAssoc);
            $associazioneAttuale = $pivot->getAssociazione($giocatoreAssoc, $squadraAssoc);
            $fotoAttuale = $associazioneAttuale['foto'] ?? null;
            $fotoUpload = $giocatoreRecord
                ? salvaFotoAssociazione($giocatoreRecord['nome'] ?? '', $giocatoreRecord['cognome'] ?? '', 'foto_associazione_upload', $fotoAttuale)
                : null;
            $fotoAssoc = $fotoUpload ?? $fotoAttuale;
            $isCaptain = isset($_POST['capitano_associa']) && $_POST['capitano_associa'] === '1';
            $pivot->assegna($giocatoreAssoc, $squadraAssoc, $fotoAssoc, [
                'ruolo' => trim($_POST['ruolo_associa'] ?? '')
            ], false, $isCaptain);
        }

        redirectGestione('associazioni');
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifica_associazione'])) {
    $giocatoreAssoc = (int)($_POST['mod_assoc_giocatore'] ?? 0);
    $squadraAssoc = (int)($_POST['mod_assoc_squadra'] ?? 0);
    if ($giocatoreAssoc && $squadraAssoc) {
        $giocatoreRecord = $giocatore->getById($giocatoreAssoc);
        $associazioneAttuale = $pivot->getAssociazione($giocatoreAssoc, $squadraAssoc);
        $fotoEsistente = $associazioneAttuale['foto'] ?? null;
        $removeFoto = isset($_POST['mod_assoc_remove_foto']) && $_POST['mod_assoc_remove_foto'] === '1';
        $mediaPost = isset($_POST['mod_assoc_media']) ? trim($_POST['mod_assoc_media']) : '';
        $stats = [
            'ruolo' => trim($_POST['mod_assoc_ruolo'] ?? ''),
            'presenze' => (int)($_POST['mod_assoc_presenze'] ?? 0),
            'reti' => (int)($_POST['mod_assoc_reti'] ?? 0),
            'assist' => (int)($_POST['mod_assoc_assist'] ?? 0),
            'gialli' => (int)($_POST['mod_assoc_gialli'] ?? 0),
            'rossi' => (int)($_POST['mod_assoc_rossi'] ?? 0),
            'media_voti' => $mediaPost === '' ? null : (float)$mediaPost
        ];
            $fotoUpload = (!$removeFoto && $giocatoreRecord)
                ? salvaFotoAssociazione($giocatoreRecord['nome'] ?? '', $giocatoreRecord['cognome'] ?? '', 'mod_assoc_foto_upload', $fotoEsistente)
                : null;
            $fotoAssoc = $removeFoto ? null : ($fotoUpload ?? $fotoEsistente);
            $isCaptain = isset($_POST['mod_assoc_capitano']) && $_POST['mod_assoc_capitano'] === '1';
            $pivot->assegna($giocatoreAssoc, $squadraAssoc, $fotoAssoc, $stats, $removeFoto, $isCaptain);
        }

        redirectGestione('associazioni');
    }

// --- DISSOCIA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dissocia_squadra'])) {
    $giocatoreId = (int)($_POST['giocatore_rimozione'] ?? $_POST['giocatore_rimozione_hidden'] ?? 0);
    $squadraId = (int)($_POST['squadra_rimozione'] ?? $_POST['squadra_rimozione_hidden'] ?? 0);

    if ($giocatoreId && $squadraId) {
        $pivot->dissocia($giocatoreId, $squadraId);
    }

    redirectGestione('associazioni');
}

// --- ELIMINA ---
if (isset($_GET['elimina'])) {
    $idElimina = (int)$_GET['elimina'];
    $recordElimina = $giocatore->getById($idElimina);
    if ($giocatore->elimina($idElimina) && $recordElimina && isset($recordElimina['foto'])) {
        cancellaFotoGiocatore($recordElimina['foto']);
    }
    redirectGestione('elimina');
}

$listaResult = $giocatore->getAll();
$giocatori = [];
if ($listaResult) {
    while ($row = $listaResult->fetch_assoc()) {
        $giocatori[] = $row;
    }
}

$giocatoriElimina = array_slice(array_reverse($giocatori), 0, 10); // ultimi 10 per default
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Gestione Giocatori</title>
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

        .admin-alert {
            margin: 1rem 0;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
        }

        .admin-alert.error {
            background: #ffe3e3;
            color: #a30000;
            border: 1px solid #ff6b6b;
        }

        .btn-secondary {
            background: #ffffff;
            color: #15293e;
            border: 1px solid #d4d9e2;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .btn-secondary:hover {
            background: #f1f3f7;
            color: #0e1d2e;
            transform: translateY(-1px);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.65);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal-overlay.hidden {
            display: none;
        }

        .modal-window {
            display: block;
            background: #fff;
            padding: 32px;
            border-radius: 16px;
            width: min(420px, 90%);
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15);
            text-align: center;
        }

        .modal-window h3 {
            margin-top: 0;
            color: #15293e;
            font-size: 1.3rem;
        }

        .modal-window p {
            color: #5f6b7b;
            margin: 12px 0 24px;
        }

        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: nowrap;
        }

        .modal-actions button {
            flex: 1 1 0;
            min-width: 120px;
        }

        .modal-actions .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .modal-actions .btn-secondary {
            background: #fff;
            border: 1px solid #d4d9e2;
            color: #1f2937;
        }

        .btn-remove-assoc {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            color: #fff;
            padding: 0.9rem 1.8rem;
            border-radius: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 12px 24px rgba(239, 68, 68, 0.25);
        }

        .btn-remove-assoc:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 32px rgba(239, 68, 68, 0.35);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toggle-control {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }

        .toggle-control input {
            display: none;
        }

        .toggle-track {
            width: 46px;
            height: 24px;
            background: #d4d9e2;
            border-radius: 999px;
            position: relative;
            transition: background 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.2);
        }

        .toggle-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ffffff;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.2s ease;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.25);
        }

        .toggle-control input:checked + .toggle-track {
            background: linear-gradient(135deg, #f87171, #dc2626);
        }

        .toggle-control input:checked + .toggle-track .toggle-thumb {
            transform: translateX(22px);
        }

        .toggle-label {
            font-weight: 600;
            color: #1f2937;
        }

        @media (max-width: 600px) {
            .modal-window {
                padding: 24px;
            }
            .modal-actions {
                flex-wrap: nowrap;
                flex-direction: row;
            }
        }
    </style>
</head>

<body>
<?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <section class="admin-container">
<a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
<h1 class="admin-title">Gestione Giocatori</h1>

<?php if (isset($_GET['duplicate']) && $_GET['duplicate'] === '1'): ?>
<div class="admin-alert error" id="duplicateAlert">Giocatore giÃ  esistente</div>
<?php elseif (isset($_GET['assoc_exists']) && $_GET['assoc_exists'] === '1'): ?>
<div class="admin-alert error" id="assocAlert">Il giocatore fa giÃ  parte di questa squadra</div>
<?php endif; ?>

<!-- PICKLIST -->
<div class="admin-select-action">
    <label for="azione">Seleziona azione:</label>
        <select id="azione" class="operation-picker">
          <option value="crea" <?php if(($currentAction ?? 'crea') === 'crea') echo 'selected'; ?>>Aggiungi Giocatore</option>
          <option value="associazioni" <?php if(($currentAction ?? '') === 'associazioni') echo 'selected'; ?>>Associazione Calciatore-Squadra</option>
          <option value="modifica" <?php if(($currentAction ?? '') === 'modifica') echo 'selected'; ?>>Modifica Giocatore</option>
          <option value="elimina" <?php if(($currentAction ?? '') === 'elimina') echo 'selected'; ?>>Elimina Giocatore</option>
        </select>
      </div>
<input type="hidden" id="currentAction" value="<?= htmlspecialchars($currentAction) ?>">

<!-- âœ… FORM CREA -->
<form method="POST" class="admin-form form-crea" enctype="multipart/form-data">
<?= csrf_field('admin_giocatori') ?>
<h2>Aggiungi Giocatore</h2>

<div class="form-group">
    <label>Nome</label>
    <input type="text" name="nome" required>
</div>

<div class="form-group">
    <label>Cognome</label>
    <input type="text" name="cognome" required>
</div>

<div class="form-group">
    <label>Foto</label>
    <div class="file-upload">
        <input type="file" name="foto_upload" id="foto_upload" accept="image/png,image/jpeg,image/webp,image/gif">
        <button type="button" class="file-btn" data-target="foto_upload">Scegli immagine</button>
        <span class="file-name" id="foto_upload_name">Nessun file selezionato</span>
    </div>
    <small>Se non carichi un'immagine verrÃ  usata <code>unknown.jpg</code>.</small>
</div>

<button type="submit" name="crea" class="btn-primary">Crea Giocatore</button>
</form>


<!-- âœ… FORM MODIFICA -->
<form method="POST" class="admin-form form-modifica hidden" id="formModifica" enctype="multipart/form-data">
<?= csrf_field('admin_giocatori') ?>
<h2>Modifica Giocatore</h2>

<div class="form-group">
    <label>Cerca giocatore</label>
    <input type="search" id="searchGiocatore" placeholder="Digita nome o cognome" autocomplete="off">
</div>

<!-- SELEZIONE GIOCATORE -->
<div class="form-group">
    <label>Seleziona Giocatore</label>
    <select name="id" id="selectGiocatore" required>
        <option value="">-- Seleziona un giocatore --</option>
        <?php foreach ($giocatori as $g): ?>
        <?php
            $isPortiere = isset($g['ruolo']) && preg_match('/portiere|\\bgk\\b|^p$/i', $g['ruolo']);
            $label = trim(($g['cognome'] ?? '') . ' ' . ($g['nome'] ?? ''));
            if ($isPortiere) $label .= ' (GK)';
        ?>
        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group"><label>Nome</label><input type="text" name="nome" id="mod_nome"></div>
<div class="form-group"><label>Cognome</label><input type="text" name="cognome" id="mod_cognome"></div>
<div class="form-group"><label>Ruolo</label><input type="text" name="ruolo" id="mod_ruolo" disabled placeholder="Scegli ruolo in associazione"></div>
<div class="form-row">
<div class="form-group half"><label>Presenze</label><input type="number" name="presenze" id="mod_presenze" value="0" readonly></div>
<div class="form-group half"><label>Reti</label><input type="number" name="reti" id="mod_reti" value="0" readonly></div>
</div>

<div class="form-row">
<div class="form-group half"><label>Gialli</label><input type="number" name="gialli" id="mod_gialli" value="0" readonly></div>
<div class="form-group half"><label>Rossi</label><input type="number" name="rossi" id="mod_rossi" value="0" readonly></div>
</div>

<div class="form-group"><label>Media Voti</label><input type="text" name="media_voti" id="mod_media" readonly></div>
<div class="form-group">
    <label>Foto</label>
    <div class="file-upload">
        <input type="file" name="foto_upload_mod" id="foto_upload_mod" accept="image/png,image/jpeg,image/webp,image/gif">
        <button type="button" class="file-btn" data-target="foto_upload_mod">Scegli immagine</button>
        <span class="file-name" id="foto_upload_mod_name">Nessun file selezionato</span>
    </div>
    <small>Lascia vuoto per mantenere la foto corrente.</small>
</div>

<button type="submit" name="aggiorna" class="btn-primary">Aggiorna Giocatore</button>
</form>

<!-- âœ… SEZIONE ELIMINA -->
<!-- GESTIONE ASSOCIAZIONI -->
<section class="admin-associazioni form-associazioni hidden">
  <h2>Associazione Calciatore-Squadra</h2>
  <div class="admin-select-action">
    <label for="assocOperation">Operazione:</label>
    <select id="assocOperation" class="operation-picker">
      <option value="aggiungi" selected>Aggiungi Associazione</option>
      <option value="modifica">Modifica Associazione</option>
      <option value="rimuovi">Elimina Associazione</option>
    </select>
  </div>

  <form method="POST" class="admin-form assoc-form assoc-form-add" enctype="multipart/form-data">
      <?= csrf_field('admin_giocatori') ?>
      <input type="hidden" name="action" value="associazioni">
      <div class="form-group">
          <label>Seleziona Torneo</label>
          <select id="assocTorneo" required>
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($tornei as $torneoVal): ?>
              <?php
                  $val = htmlspecialchars($torneoVal['id'] ?? $torneoVal['torneo'] ?? $torneoVal['nome'] ?? '');
                  $label = htmlspecialchars($torneoVal['nome'] ?? $torneoVal['torneo'] ?? 'Torneo');
              ?>
              <option value="<?= $val ?>">
                  <?= $label ?>
              </option>
              <?php endforeach; ?>
          </select>
      </div>

      <div class="form-group">
          <label>Seleziona Squadra</label>
          <select name="squadra_associa" id="assocSquadra" required disabled>
              <option value="">-- Seleziona una squadra --</option>
          </select>
      </div>

      <div class="form-group">
          <label>Giocatore</label>
          <select name="giocatore_associa" id="assocGiocatore" required>
              <option value="">-- Seleziona un giocatore --</option>
              <?php foreach ($giocatori as $g): ?>
              <?php
                  $isPortiere = isset($g['ruolo']) && preg_match('/portiere|\\bgk\\b|^p$/i', $g['ruolo']);
                  $label = trim(($g['cognome'] ?? '') . ' ' . ($g['nome'] ?? ''));
                  if ($isPortiere) $label .= ' (GK)';
              ?>
              <option value="<?= $g['id'] ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
          </select>
      </div>
      <div class="form-group">
          <label>Ruolo in squadra</label>
          <select name="ruolo_associa" id="ruolo_associa">
              <option value="">-- Seleziona un ruolo --</option>
              <option value="Portiere">Portiere</option>
              <option value="Difensore">Difensore</option>
              <option value="Centrocampista">Centrocampista</option>
              <option value="Attaccante">Attaccante</option>
          </select>
      </div>

      <div class="form-group">
          <label><input type="checkbox" name="capitano_associa" value="1"> Capitano della squadra</label>
          <small>Un solo capitano per squadra; un giocatore puÃ² essere capitano di squadre diverse.</small>
      </div>

      <div class="form-group">
          <label>Foto specifica (opzionale)</label>
          <div class="file-upload">
              <input type="file" name="foto_associazione_upload" id="foto_associazione_upload" accept="image/png,image/jpeg,image/webp,image/gif">
              <button type="button" class="file-btn" data-target="foto_associazione_upload">Scegli immagine</button>
              <span class="file-name" id="foto_associazione_upload_name">Nessun file selezionato</span>
          </div>
          <small>Se non carichi nulla verrÃ  usata la foto del giocatore.</small>
      </div>

      <button type="submit" name="associa_squadra" class="btn-primary">Aggiungi associazione</button>
  </form>

  <form method="POST" class="admin-form assoc-form assoc-form-edit hidden" enctype="multipart/form-data">
      <?= csrf_field('admin_giocatori') ?>
      <input type="hidden" name="action" value="associazioni">
      <div class="form-group">
          <label>Seleziona Torneo</label>
          <select id="modAssocTorneo" required>
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($tornei as $torneoVal): ?>
              <?php
                  $val = htmlspecialchars($torneoVal['id'] ?? $torneoVal['torneo'] ?? $torneoVal['nome'] ?? '');
                  $label = htmlspecialchars($torneoVal['nome'] ?? $torneoVal['torneo'] ?? 'Torneo');
              ?>
              <option value="<?= $val ?>">
                  <?= $label ?>
              </option>
              <?php endforeach; ?>
          </select>
      </div>

      <div class="form-group">
          <label>Seleziona Squadra</label>
          <select id="modAssocSquadra" name="mod_assoc_squadra" required disabled>
              <option value="">-- Seleziona una squadra --</option>
          </select>
      </div>

      <div class="form-group">
          <label>Giocatore</label>
          <select id="modAssocGiocatore" name="mod_assoc_giocatore" required disabled>
              <option value="">-- Seleziona un giocatore --</option>
          </select>
      </div>
      <div class="form-group">
          <label>Ruolo in squadra</label>
          <select id="mod_assoc_ruolo" name="mod_assoc_ruolo">
              <option value="">-- Seleziona un ruolo --</option>
              <option value="Portiere">Portiere</option>
              <option value="Difensore">Difensore</option>
              <option value="Centrocampista">Centrocampista</option>
              <option value="Attaccante">Attaccante</option>
          </select>
      </div>

      <div class="form-group">
          <label><input type="checkbox" name="mod_assoc_capitano" id="mod_assoc_capitano" value="1"> Capitano della squadra</label>
          <small>Un solo capitano per squadra; un giocatore puÃ² essere capitano di squadre diverse.</small>
      </div>

      <div class="form-row">
          <div class="form-group half">
              <label>Presenze</label>
              <input type="number" name="mod_assoc_presenze" id="mod_assoc_presenze" min="0" value="0">
          </div>
          <div class="form-group half">
              <label>Reti</label>
              <input type="number" name="mod_assoc_reti" id="mod_assoc_reti" min="0" value="0">
          </div>
      </div>

      <div class="form-row">
          <div class="form-group half">
              <label>Assist</label>
              <input type="number" name="mod_assoc_assist" id="mod_assoc_assist" min="0" value="0">
          </div>
          <div class="form-group half">
              <label>Gialli</label>
              <input type="number" name="mod_assoc_gialli" id="mod_assoc_gialli" min="0" value="0">
          </div>
      </div>

      <div class="form-row">
          <div class="form-group half">
              <label>Rossi</label>
              <input type="number" name="mod_assoc_rossi" id="mod_assoc_rossi" min="0" value="0">
          </div>
          <div class="form-group half">
              <label>Media voti</label>
              <input type="number" name="mod_assoc_media" id="mod_assoc_media" step="0.01" min="0">
          </div>
      </div>

      <div class="form-group">
          <label>Nuova foto (opzionale)</label>
          <div class="file-upload">
              <input type="file" name="mod_assoc_foto_upload" id="mod_assoc_foto_upload" accept="image/png,image/jpeg,image/webp,image/gif">
              <button type="button" class="file-btn" data-target="mod_assoc_foto_upload">Scegli immagine</button>
              <span class="file-name" id="mod_assoc_foto_upload_name">Nessun file selezionato</span>
          </div>
          <small>Lascia vuoto per mantenere la foto attuale.</small>
      </div>

      <div class="form-group checkbox-group">
          <label class="toggle-control">
              <input type="checkbox" name="mod_assoc_remove_foto" id="mod_assoc_remove_foto" value="1">
              <span class="toggle-track">
                  <span class="toggle-thumb"></span>
              </span>
              <span class="toggle-label">Rimuovi foto dalla squadra</span>
          </label>
      </div>

      <button type="submit" name="modifica_associazione" class="btn-primary">Modifica associazione</button>
  </form>

  <form method="POST" class="admin-form assoc-form assoc-form-remove hidden">
      <?= csrf_field('admin_giocatori') ?>
      <input type="hidden" name="action" value="associazioni">
      <input type="hidden" name="dissocia_squadra" value="1">
      <div class="form-group">
          <label>Seleziona Torneo</label>
          <select id="remTorneo" required>
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($tornei as $torneoVal): ?>
              <?php
                  $val = htmlspecialchars($torneoVal['id'] ?? $torneoVal['torneo'] ?? $torneoVal['nome'] ?? '');
                  $label = htmlspecialchars($torneoVal['nome'] ?? $torneoVal['torneo'] ?? 'Torneo');
              ?>
              <option value="<?= $val ?>">
                  <?= $label ?>
              </option>
              <?php endforeach; ?>
          </select>
      </div>

      <div class="form-group">
          <label>Seleziona Squadra</label>
          <select name="squadra_rimozione" id="remSquadra" required disabled>
              <option value="">-- Seleziona una squadra --</option>
          </select>
      </div>

      <div class="form-group">
          <label>Giocatore</label>
          <select name="giocatore_rimozione" id="remGiocatore" required disabled>
              <option value="">-- Seleziona un giocatore --</option>
          </select>
      </div>

      <input type="hidden" name="squadra_rimozione_hidden" id="remSquadraHidden">
      <input type="hidden" name="giocatore_rimozione_hidden" id="remGiocatoreHidden">
      <button type="button" class="btn-danger btn-remove-assoc">Rimuovi associazione</button>
  </form>
</section>

<section class="admin-table-section form-elimina hidden">
<h2>Elimina Giocatore</h2>
<input type="text" id="search" placeholder="Cerca (mostrati ultimi 10 creati)" class="search-input" data-all-players='<?= json_encode($giocatori, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>'>

<table class="admin-table-squadre" id="tabellaGiocatori">
<thead>
<tr>
    <th data-col="nome">Nome</th>
    <th data-col="cognome">Cognome</th>
    <th data-col="ruolo">Ruolo</th>
    <th>Azioni</th>
</tr>
</thead>
<tbody>
<?php if (!empty($giocatoriElimina)): ?>
    <?php foreach ($giocatoriElimina as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['nome']) ?></td>
        <td><?= htmlspecialchars($row['cognome']) ?></td>
        <td><?= htmlspecialchars($row['ruolo']) ?></td>
        <td>
            <button type="button"
                    class="btn-danger btn-delete"
                    data-id="<?= $row['id'] ?>"
                    data-name="<?= htmlspecialchars($row['nome'] . ' ' . $row['cognome']) ?>">
                Elimina
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="4">Nessun giocatore disponibile.</td></tr>
<?php endif; ?>
</tbody>
</table>
<small>Per altri giocatori usa la ricerca: digita il nome/cognome e scorri i risultati filtrati (max 10 mostrati).</small>
</section>

</section>
</main>

<div class="modal-overlay hidden" id="confirmModal">
    <div class="modal-window">
        <h3>Confermi eliminazione?</h3>
        <p id="confirmMessage">Sei sicuro di voler eliminare questo giocatore?</p>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="cancelDeleteBtn">Annulla</button>
            <button type="button" class="btn-danger" id="confirmDeleteBtn">Elimina</button>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="removeAssocModal">
    <div class="modal-window">
        <h3>Rimuovere l'associazione?</h3>
        <p id="removeAssocMessage">Confermi di voler rimuovere il giocatore da questa squadra?</p>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="cancelRemoveAssoc">Annulla</button>
            <button type="button" class="btn-danger" id="confirmRemoveAssoc">Rimuovi</button>
        </div>
    </div>
</div>

<div id="footer-container"></div>


<!-- âœ… SCRIPTS -->
<script>
const selectAzione = document.getElementById('azione');
const currentActionInput = document.getElementById('currentAction');
const formCrea = document.querySelector('.form-crea');
const formModifica = document.querySelector('.form-modifica');
const formElimina = document.querySelector('.form-elimina');
const formAssociazioni = document.querySelector('.form-associazioni');
const duplicateAlert = document.getElementById('duplicateAlert');
const assocAlert = document.getElementById('assocAlert');

function mostraSezione(val) {
    [formCrea, formModifica, formElimina, formAssociazioni].forEach(f => f && f.classList.add('hidden'));
    if (val === 'crea' && formCrea) formCrea.classList.remove('hidden');
    if (val === 'modifica' && formModifica) formModifica.classList.remove('hidden');
    if (val === 'elimina' && formElimina) formElimina.classList.remove('hidden');
    if (val === 'associazioni' && formAssociazioni) formAssociazioni.classList.remove('hidden');
    if (currentActionInput) currentActionInput.value = val;
    if (selectAzione) selectAzione.value = val;
}

const initialAction = currentActionInput?.value || selectAzione?.value || 'crea';
mostraSezione(initialAction);
function dismissAlerts() {
    [duplicateAlert, assocAlert].forEach(alertEl => {
        if (alertEl && alertEl.parentElement) {
            alertEl.parentElement.removeChild(alertEl);
        }
    });
}

function resetModificaGiocatoreForm() {
    if (searchGiocatoreInput) {
        searchGiocatoreInput.value = "";
    }
    if (selectGiocatore) {
        Array.from(selectGiocatore.options).forEach(opt => {
            opt.hidden = false;
        });
        selectGiocatore.value = "";
    }
    const fields = [
        "mod_nome",
        "mod_cognome",
        "mod_ruolo",
        "mod_presenze",
        "mod_reti",
        "mod_gialli",
        "mod_rossi",
        "mod_media"
    ];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });
    const fotoInput = document.getElementById("foto_upload_mod");
    if (fotoInput) fotoInput.value = "";
    const fotoLabel = document.getElementById("foto_upload_mod_name");
    if (fotoLabel) fotoLabel.textContent = "Nessun file selezionato";
}

function clearAlertParam(param) {
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.has(param)) {
            url.searchParams.delete(param);
            const newUrl = url.pathname + (url.search ? url.search : '') + url.hash;
            window.history.replaceState({}, document.title, newUrl);
        }
    } catch (err) {
        console.warn('Impossibile aggiornare URL:', err);
    }
}

if (duplicateAlert) {
    clearAlertParam('duplicate');
}
if (assocAlert) {
    clearAlertParam('assoc_exists');
}

selectAzione.addEventListener('change', e => {
    mostraSezione(e.target.value);
    dismissAlerts();
    resetModificaGiocatoreForm();
});
</script>

<script>
const searchGiocatoreInput = document.getElementById("searchGiocatore");
const selectGiocatore = document.getElementById("selectGiocatore");
const searchEliminaInput = document.getElementById("search");
const tabellaGiocatori = document.getElementById("tabellaGiocatori");
const confirmModal = document.getElementById("confirmModal");
const confirmMessage = document.getElementById("confirmMessage");
const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
const cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
const removeAssocModal = document.getElementById("removeAssocModal");
const removeAssocMessage = document.getElementById("removeAssocMessage");
const confirmRemoveAssocBtn = document.getElementById("confirmRemoveAssoc");
const cancelRemoveAssocBtn = document.getElementById("cancelRemoveAssoc");
let pendingDeleteId = null;
let pendingDeleteName = "";

const assocTorneo = document.getElementById("assocTorneo");
const assocSquadra = document.getElementById("assocSquadra");
const assocGiocatore = document.getElementById("assocGiocatore");
const remTorneo = document.getElementById("remTorneo");
const remSquadra = document.getElementById("remSquadra");
const remGiocatore = document.getElementById("remGiocatore");
const remSquadraHidden = document.getElementById("remSquadraHidden");
const remGiocatoreHidden = document.getElementById("remGiocatoreHidden");
const modAssocTorneo = document.getElementById("modAssocTorneo");
const modAssocSquadra = document.getElementById("modAssocSquadra");
const modAssocGiocatore = document.getElementById("modAssocGiocatore");
const modAssocPresenze = document.getElementById("mod_assoc_presenze");
const modAssocReti = document.getElementById("mod_assoc_reti");
const modAssocAssist = document.getElementById("mod_assoc_assist");
const modAssocGialli = document.getElementById("mod_assoc_gialli");
const modAssocRossi = document.getElementById("mod_assoc_rossi");
const modAssocMedia = document.getElementById("mod_assoc_media");
const modAssocRemoveFoto = document.getElementById("mod_assoc_remove_foto");
const modAssocCapitano = document.getElementById("mod_assoc_capitano");
const modAssocRuolo = document.getElementById("mod_assoc_ruolo");
const ruoloAssocia = document.getElementById("ruolo_associa");
const assocOperationSelect = document.getElementById("assocOperation");
const assocFormAdd = document.querySelector(".assoc-form-add");
const assocFormEdit = document.querySelector(".assoc-form-edit");
const assocFormRemove = document.querySelector(".assoc-form-remove");
const removeAssocButtons = document.querySelectorAll(".btn-remove-assoc");
const allPlayersDataEl = document.getElementById("search");
let allPlayers = [];
try {
    allPlayers = JSON.parse(allPlayersDataEl?.getAttribute("data-all-players") || "[]") || [];
} catch (e) {
    allPlayers = [];
}

const API_SQUADRE_TORNEO = "/api/get_squadre_torneo.php";
const API_GIOCATORI_SQUADRA = "/api/get_giocatori_squadra.php";

function isPortiereRuolo(ruolo) {
    const r = (ruolo || "").toLowerCase().trim();
    return r.includes("portiere") || r === "gk" || r === "p";
}

function buildPlayerLabel(player = {}, order = "nome") {
    const nome = player.nome || "";
    const cognome = player.cognome || "";
    const base = order === "cognome"
        ? `${cognome} ${nome}`.trim()
        : `${nome} ${cognome}`.trim();
    const suffix = isPortiereRuolo(player.ruolo) ? " (GK)" : "";
    return `${base}${suffix}`;
}

function resetSelect(select, placeholder, disable = true) {
    if (!select) return;
    select.innerHTML = placeholder ? `<option value="">${placeholder}</option>` : "";
    select.disabled = disable;
}

function clearModAssocStatsFields() {
    if (!modAssocPresenze) return;
    const fields = [modAssocPresenze, modAssocReti, modAssocAssist, modAssocGialli, modAssocRossi];
    fields.forEach(field => {
        if (field) field.value = "0";
    });
    if (modAssocMedia) modAssocMedia.value = "";
    if (modAssocRemoveFoto) modAssocRemoveFoto.checked = false;
    if (modAssocCapitano) modAssocCapitano.checked = false;
    if (modAssocRuolo) modAssocRuolo.value = "";
}

function populateModAssocStatsFromOption(option) {
    if (!option || !modAssocPresenze) {
        clearModAssocStatsFields();
        return;
    }
    const ds = option.dataset;
    if (modAssocPresenze) modAssocPresenze.value = ds.presenze ?? "0";
    if (modAssocReti) modAssocReti.value = ds.reti ?? "0";
    if (modAssocAssist) modAssocAssist.value = ds.assist ?? "0";
    if (modAssocGialli) modAssocGialli.value = ds.gialli ?? "0";
    if (modAssocRossi) modAssocRossi.value = ds.rossi ?? "0";
    if (modAssocMedia) modAssocMedia.value = ds.media ?? "";
    if (modAssocRemoveFoto) modAssocRemoveFoto.checked = false;
    if (modAssocCapitano) modAssocCapitano.checked = ds.captain === "1";
    if (modAssocRuolo) modAssocRuolo.value = ds.ruolo || "";
}

async function loadSquadre(select, torneo, placeholder = "-- Seleziona una squadra --") {
    resetSelect(select, placeholder);
    if (!select || !torneo) return [];

    try {
        const res = await fetch(`${API_SQUADRE_TORNEO}?torneo=${encodeURIComponent(torneo)}`);
        const data = await res.json();
        if (!Array.isArray(data) || !data.length) return [];

        data.sort((a, b) => {
            const nameA = (a.nome || '').toLowerCase();
            const nameB = (b.nome || '').toLowerCase();
            return nameA.localeCompare(nameB);
        });

        select.disabled = false;
        select.innerHTML = placeholder ? `<option value="">${placeholder}</option>` : "";
        data.forEach(s => {
            const opt = document.createElement("option");
            opt.value = s.id;
            opt.textContent = s.nome;
            select.appendChild(opt);
        });
        return data;
    } catch (err) {
        console.error("Errore nel caricamento squadre:", err);
        return [];
    }
}

async function loadGiocatori(select, squadraId, torneo, placeholder = "-- Seleziona un giocatore --") {
    resetSelect(select, placeholder);
    if (!select || !squadraId) return [];

    try {
        const res = await fetch(`${API_GIOCATORI_SQUADRA}?squadra_id=${squadraId}&torneo=${encodeURIComponent(torneo || "")}`);
        const data = await res.json();
        if (!Array.isArray(data) || !data.length) return [];

        select.disabled = false;
        select.innerHTML = placeholder ? `<option value="">${placeholder}</option>` : "";
        data.forEach(g => {
            const opt = document.createElement("option");
            opt.value = g.id;
            opt.textContent = buildPlayerLabel(g, "nome");
            if (typeof g.presenze !== "undefined") opt.dataset.presenze = g.presenze;
            if (typeof g.reti !== "undefined") opt.dataset.reti = g.reti;
            if (typeof g.assist !== "undefined") opt.dataset.assist = g.assist;
            if (typeof g.gialli !== "undefined") opt.dataset.gialli = g.gialli;
            if (typeof g.rossi !== "undefined") opt.dataset.rossi = g.rossi;
            if (typeof g.media_voti !== "undefined" && g.media_voti !== null) {
                opt.dataset.media = g.media_voti;
            } else {
                delete opt.dataset.media;
            }
            if (typeof g.is_captain !== "undefined") {
                opt.dataset.captain = String(g.is_captain);
            }
            if (typeof g.ruolo !== "undefined") {
                opt.dataset.ruolo = g.ruolo;
            }
            select.appendChild(opt);
        });
        return data;
    } catch (err) {
        console.error("Errore nel caricamento giocatori:", err);
        return [];
    }
}

async function fetchGiocatoriAssociatiIds(squadraId) {
    if (!squadraId) return [];
    try {
        const res = await fetch(`${API_GIOCATORI_SQUADRA}?squadra_id=${squadraId}`);
        const data = await res.json();
        if (!Array.isArray(data)) return [];
        return data.map(p => String(p.id));
    } catch (err) {
        console.error("Errore nel recupero giocatori associati:", err);
        return [];
    }
}

async function aggiornaGiocatoriDisponibiliPerAssociazione() {
    if (!assocGiocatore) return;
    const squadraId = assocSquadra?.value;
    // Nessuna squadra: mostra tutti
    if (!squadraId) {
        resetSelect(assocGiocatore, "-- Seleziona un giocatore --", false);
        allPlayers.forEach(p => {
            const opt = document.createElement("option");
            opt.value = p.id;
            opt.textContent = buildPlayerLabel(p, "cognome");
            assocGiocatore.appendChild(opt);
        });
        return;
    }

    const associati = await fetchGiocatoriAssociatiIds(squadraId);
    const setAssociati = new Set(associati);
    resetSelect(assocGiocatore, "-- Seleziona un giocatore --", false);
    allPlayers
        .filter(p => !setAssociati.has(String(p.id)))
        .forEach(p => {
            const opt = document.createElement("option");
            opt.value = p.id;
            opt.textContent = buildPlayerLabel(p, "cognome");
            assocGiocatore.appendChild(opt);
        });
}

function filterGiocatori(term) {
    if (!selectGiocatore) return;
    const normalized = term.trim().toLowerCase();
    const options = Array.from(selectGiocatore.options);
    let firstVisible = "";
    options.forEach(opt => {
        if (!opt.value) {
            opt.hidden = false;
            return;
        }
        const text = opt.textContent.toLowerCase();
        const match = normalized === "" || text.includes(normalized);
        opt.hidden = !match;
        if (match && !firstVisible) {
            firstVisible = opt.value;
        }
    });
    if (normalized) {
        selectGiocatore.value = firstVisible || "";
    } else {
        selectGiocatore.value = "";
    }
}

searchGiocatoreInput?.addEventListener("input", e => filterGiocatori(e.target.value));

function filterTabella(term) {
    if (!tabellaGiocatori) return;
    const normalized = term.trim().toLowerCase();
    const tbody = tabellaGiocatori.querySelector("tbody");
    if (!tbody) return;

    // Se non c'Ã¨ testo, mostra gli ultimi 10 (giÃ  popolati dal PHP)
    if (normalized === "") {
        // ripristina ultimi 10
        tbody.querySelectorAll("tr").forEach(row => row.style.display = "");
        return;
    }

    if (!Array.isArray(allPlayers) || allPlayers.length === 0) {
        // fallback: filtro sui presenti
        tbody.querySelectorAll("tr").forEach(row => {
            const testo = row.textContent.toLowerCase();
            row.style.display = testo.includes(normalized) ? "" : "none";
        });
        return;
    }

    const filtered = allPlayers.filter(p => {
        const t = `${p.nome || ""} ${p.cognome || ""} ${p.ruolo || ""}`.toLowerCase();
        return t.includes(normalized);
    });

    tbody.innerHTML = "";
    if (filtered.length === 0) {
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 4;
        td.textContent = "Nessun giocatore trovato.";
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    filtered.forEach(p => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td>${(p.nome || "").replace(/</g,"&lt;")}</td>
            <td>${(p.cognome || "").replace(/</g,"&lt;")}</td>
            <td>${(p.ruolo || "").replace(/</g,"&lt;")}</td>
            <td>
              <button type="button"
                class="btn-danger btn-delete"
                data-id="${p.id}"
                data-name="${(p.nome || "")} ${(p.cognome || "")}">
                Elimina
              </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openConfirmModal(id, name) {
    pendingDeleteId = id;
    pendingDeleteName = name || "";
    if (confirmMessage) {
        confirmMessage.textContent = pendingDeleteName
            ? `Sei sicuro di voler eliminare ${pendingDeleteName}?`
            : "Sei sicuro di voler eliminare questo giocatore?";
    }
    if (confirmModal) {
        confirmModal.classList.remove("hidden");
    }
}

function closeConfirmModal() {
    pendingDeleteId = null;
    pendingDeleteName = "";
    if (confirmModal) {
        confirmModal.classList.add("hidden");
    }
}

if (searchEliminaInput) {
    searchEliminaInput.addEventListener("input", e => filterTabella(e.target.value));
}

document.addEventListener("click", event => {
    let btn = null;
    if (typeof event.target.closest === "function") {
        btn = event.target.closest(".btn-delete");
    } else {
        let node = event.target;
        while (node && node !== document) {
            if (node.classList && node.classList.contains("btn-delete")) {
                btn = node;
                break;
            }
            node = node.parentElement;
        }
    }
    if (!btn) return;

    event.preventDefault();
    const giocatoreId = btn.getAttribute("data-id");
    const giocatoreName = btn.getAttribute("data-name");
    openConfirmModal(giocatoreId, giocatoreName);
});

if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener("click", () => {
        if (!pendingDeleteId) return;
        window.location.href = `?elimina=${encodeURIComponent(pendingDeleteId)}`;
    });
}

if (cancelDeleteBtn) {
    cancelDeleteBtn.addEventListener("click", () => closeConfirmModal());
}

if (confirmModal) {
    confirmModal.addEventListener("click", e => {
        if (e.target === confirmModal) {
            closeConfirmModal();
        }
    });
}

function openRemoveAssocModal() {
    removeAssocModal?.classList.remove("hidden");
}

function closeRemoveAssocModal() {
    removeAssocModal?.classList.add("hidden");
}

removeAssocButtons.forEach(btn => {
    btn.addEventListener("click", () => {
        if (!remSquadra || !remGiocatore) return;
        if (!remSquadra.value || !remGiocatore.value) {
            alert("Seleziona sia la squadra sia il giocatore da rimuovere.");
            return;
        }
        if (remSquadraHidden) remSquadraHidden.value = remSquadra.value;
        if (remGiocatoreHidden) remGiocatoreHidden.value = remGiocatore.value;
        const squadraName = remSquadra.selectedOptions[0]?.textContent?.trim() || "questa squadra";
        const giocatoreName = remGiocatore.selectedOptions[0]?.textContent?.trim() || "questo giocatore";
        if (removeAssocMessage) {
            removeAssocMessage.textContent = `Vuoi rimuovere ${giocatoreName} da ${squadraName}?`;
        }
        openRemoveAssocModal();
    });
});

if (confirmRemoveAssocBtn) {
    confirmRemoveAssocBtn.addEventListener("click", () => {
        if (!assocFormRemove) return;
        closeRemoveAssocModal();
        if (typeof assocFormRemove.requestSubmit === "function") {
            assocFormRemove.requestSubmit();
        } else {
            assocFormRemove.submit();
        }
    });
}

if (cancelRemoveAssocBtn) {
    cancelRemoveAssocBtn.addEventListener("click", () => closeRemoveAssocModal());
}

if (removeAssocModal) {
    removeAssocModal.addEventListener("click", e => {
        if (e.target === removeAssocModal) {
            closeRemoveAssocModal();
        }
    });
}

assocTorneo?.addEventListener("change", async () => {
    await loadSquadre(assocSquadra, assocTorneo.value);
    await aggiornaGiocatoriDisponibiliPerAssociazione();
});
if (assocTorneo?.value) {
    loadSquadre(assocSquadra, assocTorneo.value).then(() => aggiornaGiocatoriDisponibiliPerAssociazione());
}

remTorneo?.addEventListener("change", async () => {
    await loadSquadre(remSquadra, remTorneo.value);
    resetSelect(remGiocatore, "-- Seleziona un giocatore --");
});
if (remTorneo?.value) {
    loadSquadre(remSquadra, remTorneo.value);
}

remSquadra?.addEventListener("change", async () => {
    await loadGiocatori(remGiocatore, remSquadra.value, remTorneo.value);
});

modAssocTorneo?.addEventListener("change", async () => {
    await loadSquadre(modAssocSquadra, modAssocTorneo.value);
    resetSelect(modAssocGiocatore, "-- Seleziona un giocatore --");
    clearModAssocStatsFields();
});
if (modAssocTorneo?.value) {
    loadSquadre(modAssocSquadra, modAssocTorneo.value);
}

modAssocSquadra?.addEventListener("change", async () => {
    await loadGiocatori(modAssocGiocatore, modAssocSquadra.value, modAssocTorneo.value);
    clearModAssocStatsFields();
});

modAssocGiocatore?.addEventListener("change", () => {
    const opt = modAssocGiocatore.selectedOptions && modAssocGiocatore.selectedOptions[0];
    populateModAssocStatsFromOption(opt);
});

assocSquadra?.addEventListener("change", () => aggiornaGiocatoriDisponibiliPerAssociazione());

function mostraFormAssoc(val) {
    [assocFormAdd, assocFormEdit, assocFormRemove].forEach(f => f && f.classList.add('hidden'));
    if (val === 'aggiungi' && assocFormAdd) assocFormAdd.classList.remove('hidden');
    if (val === 'modifica' && assocFormEdit) assocFormEdit.classList.remove('hidden');
    if (val === 'rimuovi' && assocFormRemove) assocFormRemove.classList.remove('hidden');
}

mostraFormAssoc(assocOperationSelect?.value || 'aggiungi');
assocOperationSelect?.addEventListener('change', e => mostraFormAssoc(e.target.value));

selectGiocatore?.addEventListener("change", async e => {
    const id = e.target.value;
    if (!id) return;

    const res = await fetch(`/api/get_giocatore.php?id=${id}`);
    const data = await res.json();
    if (!data) return;

    document.getElementById("mod_nome").value       = data.nome;
    document.getElementById("mod_cognome").value    = data.cognome;
    document.getElementById("mod_ruolo").value      = data.ruolo;
    document.getElementById("mod_presenze").value   = data.presenze;
    document.getElementById("mod_reti").value       = data.reti;
    document.getElementById("mod_gialli").value     = data.gialli;
    document.getElementById("mod_rossi").value      = data.rossi;
    document.getElementById("mod_media").value      = data.media_voti;
});
</script>
<script>
// âœ… ORDINAMENTO TABELLA ELIMINA GIOCATORI
document.addEventListener("DOMContentLoaded", () => {
    const table = document.getElementById("tabellaGiocatori");
    const headers = table.querySelectorAll("th[data-col]");
    const tbody = table.querySelector("tbody");

    let sortDirection = {};

    headers.forEach(header => {
        header.style.cursor = "pointer";

        header.addEventListener("click", () => {
            const col = header.getAttribute("data-col");
            const colIndex = Array.from(header.parentNode.children).indexOf(header);

            // inverti direzione
            sortDirection[col] = sortDirection[col] === "asc" ? "desc" : "asc";

            // prendi tutte le righe
            const rows = Array.from(tbody.querySelectorAll("tr"));

            // ordina le righe
            rows.sort((a, b) => {
                const aText = a.children[colIndex].textContent.trim().toLowerCase();
                const bText = b.children[colIndex].textContent.trim().toLowerCase();

                if (sortDirection[col] === "asc") {
                    return aText.localeCompare(bText);
                } else {
                    return bText.localeCompare(aText);
                }
            });

            // riscrivi la tabella
            tbody.innerHTML = "";
            rows.forEach(r => tbody.appendChild(r));
        });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const footer = document.getElementById('footer-container');
    if (!footer) return;
    fetch('/includi/footer.html')
        .then(r => r.text())
        .then(html => footer.innerHTML = html)
        .catch(err => console.error('Errore footer:', err));
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.file-upload').forEach(wrapper => {
        const input = wrapper.querySelector('input[type="file"]');
        const button = wrapper.querySelector('.file-btn');
        const nameLabel = wrapper.querySelector('.file-name');
        if (!input || !button || !nameLabel) return;

        button.addEventListener('click', (e) => {
            e.preventDefault();
            input.click();
        });

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            nameLabel.textContent = file ? file.name : 'Nessun file selezionato';
        });
    });
});
</script>
</body>
</html>
