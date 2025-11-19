<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /torneioldschool/index.php");
    exit;
}

require_once __DIR__ . '/crud/Giocatore.php';
require_once __DIR__ . '/crud/Squadra.php';
require_once __DIR__ . '/crud/SquadraGiocatore.php';
$giocatore = new Giocatore();
$squadraModel = new Squadra();
$pivot = new SquadraGiocatore();

function salvaFotoGiocatore($nome, $cognome, $fieldName, $fotoEsistente = null) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $fotoEsistente ?: '/torneioldschool/img/giocatori/unknown.jpg';
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return $fotoEsistente ?: '/torneioldschool/img/giocatori/unknown.jpg';
    }

    $maxSize = 2 * 1024 * 1024;
    if ($_FILES[$fieldName]['size'] > $maxSize) {
        return $fotoEsistente ?: '/torneioldschool/img/giocatori/unknown.jpg';
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$fieldName]['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
        return $fotoEsistente ?: '/torneioldschool/img/giocatori/unknown.jpg';
    }

    $baseDir = realpath(__DIR__ . '/../img/giocatori');
    if (!$baseDir) {
        return $fotoEsistente ?: '/torneioldschool/img/giocatori/unknown.jpg';
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

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $baseDir . '/' . $filename)) {
        return $fotoEsistente ?: '/torneioldschool/img/giocatori/unknown.jpg';
    }

    return '/torneioldschool/img/giocatori/' . $filename;
}

$tornei = [];
$torneiResult = $squadraModel->getTornei();
if ($torneiResult) {
    while ($row = $torneiResult->fetch_assoc()) {
        if (!empty($row['torneo'])) {
            $tornei[] = $row['torneo'];
        }
    }
}

// --- CREA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $fotoPath = salvaFotoGiocatore($nome, $cognome, 'foto_upload');
    $nuovoId = $giocatore->crea(
        $nome,
        $cognome,
        trim($_POST['ruolo']),
        (int)$_POST['presenze'],
        (int)$_POST['reti'],
        (int)$_POST['gialli'],
        (int)$_POST['rossi'],
        trim($_POST['media_voti']),
        $fotoPath
    );

    header("Location: gestione_giocatori.php");
    exit;
}

// --- AGGIORNA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
    $id = (int)$_POST['id'];
    $record = $giocatore->getById($id);
    $fotoEsistente = $record['foto'] ?? '/torneioldschool/img/giocatori/unknown.jpg';
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $fotoPath = salvaFotoGiocatore($nome, $cognome, 'foto_upload_mod', $fotoEsistente);

    $giocatore->aggiorna(
        $id,
        $nome,
        $cognome,
        trim($_POST['ruolo']),
        (int)$_POST['presenze'],
        (int)$_POST['reti'],
        (int)$_POST['gialli'],
        (int)$_POST['rossi'],
        trim($_POST['media_voti']),
        $fotoPath
    );

    header("Location: gestione_giocatori.php");
    exit;
}

// --- ASSOCIA GIOCATORE A SQUADRA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['associa_squadra'])) {
    $giocatoreAssoc = (int)($_POST['giocatore_associa'] ?? 0);
    $squadraAssoc = (int)($_POST['squadra_associa'] ?? 0);
    $fotoAssoc = trim($_POST['foto_associazione'] ?? '');

    if ($giocatoreAssoc && $squadraAssoc) {
        $pivot->assegna($giocatoreAssoc, $squadraAssoc, $fotoAssoc === '' ? null : $fotoAssoc);
    }

    header("Location: gestione_giocatori.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifica_associazione'])) {
    $giocatoreAssoc = (int)($_POST['mod_assoc_giocatore'] ?? 0);
    $squadraAssoc = (int)($_POST['mod_assoc_squadra'] ?? 0);
    $fotoAssoc = trim($_POST['mod_assoc_foto'] ?? '');

    if ($giocatoreAssoc && $squadraAssoc) {
        $pivot->assegna($giocatoreAssoc, $squadraAssoc, $fotoAssoc === '' ? null : $fotoAssoc);
    }

    header("Location: gestione_giocatori.php");
    exit;
}

// --- DISSOCIA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dissocia_squadra'])) {
    $giocatoreId = (int)($_POST['giocatore_rimozione'] ?? 0);
    $squadraId = (int)($_POST['squadra_rimozione'] ?? 0);

    if ($giocatoreId && $squadraId) {
        $pivot->dissocia($giocatoreId, $squadraId);
    }

    header("Location: gestione_giocatori.php");
    exit;
}

// --- ELIMINA ---
if (isset($_GET['elimina'])) {
    $giocatore->elimina((int)$_GET['elimina']);
    header("Location: gestione_giocatori.php");
    exit;
}

$listaResult = $giocatore->getAll();
$giocatori = [];
if ($listaResult) {
    while ($row = $listaResult->fetch_assoc()) {
        $giocatori[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Giocatori</title>
    <link rel="stylesheet" href="/torneioldschool/style.css">
    <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
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
    </style>
</head>

<body>
<?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <section class="admin-container">
<a class="admin-back-link" href="/torneioldschool/admin_dashboard.php">Torna alla dashboard</a>
<h1 class="admin-title">Gestione Giocatori</h1>

<!-- PICKLIST -->
<div class="admin-select-action">
    <label for="azione">Seleziona azione:</label>
        <select id="azione" class="operation-picker">
          <option value="crea" selected>Aggiungi Giocatore</option>
          <option value="modifica">Modifica Giocatore</option>
          <option value="elimina">Elimina Giocatore</option>
          <option value="associazioni">Associazione Calciatore-Squadra</option>
        </select>
      </div>

<!-- ✅ FORM CREA -->
<form method="POST" class="admin-form form-crea" enctype="multipart/form-data">
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
    <label>Ruolo</label>
    <select name="ruolo">
        <option value="">-- Nessun ruolo --</option>
        <option value="Portiere">Portiere</option>
        <option value="Difensore">Difensore</option>
        <option value="Centrocampista">Centrocampista</option>
        <option value="Attaccante">Attaccante</option>
    </select>
</div>

<div class="form-group">
    <label>Foto</label>
    <div class="file-upload">
        <input type="file" name="foto_upload" id="foto_upload" accept="image/png,image/jpeg,image/webp,image/gif">
        <button type="button" class="file-btn" data-target="foto_upload">Scegli immagine</button>
        <span class="file-name" id="foto_upload_name">Nessun file selezionato</span>
    </div>
    <small>Se non carichi un'immagine verrà usata <code>unknown.jpg</code>.</small>
</div>

<button type="submit" name="crea" class="btn-primary">Crea Giocatore</button>
</form>


<!-- ✅ FORM MODIFICA -->
<form method="POST" class="admin-form form-modifica hidden" id="formModifica" enctype="multipart/form-data">
<h2>Modifica Giocatore</h2>

<!-- FILTRO TORNEO -->
<div class="form-group">
    <label>Seleziona Torneo</label>
    <select id="selectTorneoFiltro" required>
        <option value="">-- Seleziona un torneo --</option>
            <?php foreach ($tornei as $torneoVal): ?>
            <option value="<?= htmlspecialchars($torneoVal) ?>"><?= htmlspecialchars($torneoVal) ?></option>
            <?php endforeach; ?>
            </select>
        </div>

<!-- FILTRO SQUADRA -->
<div class="form-group">
    <label>Seleziona Squadra</label>
    <select id="selectSquadraFiltro" required disabled></select>
</div>

<!-- FILTRO GIOCATORE -->
<div class="form-group">
    <label>Seleziona Giocatore</label>
    <select name="id" id="selectGiocatore" required disabled>
        <option value="">-- Seleziona un giocatore --</option>
    </select>
</div>

<div class="form-group"><label>Nome</label><input type="text" name="nome" id="mod_nome"></div>
<div class="form-group"><label>Cognome</label><input type="text" name="cognome" id="mod_cognome"></div>
<div class="form-group"><label>Ruolo</label><input type="text" name="ruolo" id="mod_ruolo"></div>
<div class="form-row">
    <div class="form-group half"><label>Presenze</label><input type="number" name="presenze" id="mod_presenze"></div>
    <div class="form-group half"><label>Reti</label><input type="number" name="reti" id="mod_reti"></div>
</div>

<div class="form-row">
    <div class="form-group half"><label>Gialli</label><input type="number" name="gialli" id="mod_gialli"></div>
    <div class="form-group half"><label>Rossi</label><input type="number" name="rossi" id="mod_rossi"></div>
</div>

<div class="form-group"><label>Media Voti</label><input type="text" name="media_voti" id="mod_media"></div>
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

<!-- ✅ SEZIONE ELIMINA -->
<!-- GESTIONE ASSOCIAZIONI -->
<section class="admin-associazioni form-associazioni hidden">
  <h2>Associazione Calciatore-Squadra</h2>
  <div class="admin-select-action">
    <label for="assocOperation">Operazione:</label>
    <select id="assocOperation" class="operation-picker">
      <option value="aggiungi" selected>Aggiungi Giocatore a Squadra</option>
      <option value="modifica">Modifica Associazione</option>
      <option value="rimuovi">Elimina Associazione</option>
    </select>
  </div>

  <form method="POST" class="admin-form assoc-form assoc-form-add" enctype="multipart/form-data">
      <div class="form-group">
          <label>Seleziona Torneo</label>
          <select id="assocTorneo" required>
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($tornei as $torneoVal): ?>
              <option value="<?= htmlspecialchars($torneoVal) ?>"><?= htmlspecialchars($torneoVal) ?></option>
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
              <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['cognome'] . ' ' . $g['nome']) ?></option>
              <?php endforeach; ?>
          </select>
      </div>

      <div class="form-group">
          <label>Foto specifica (opzionale)</label>
          <input type="text" name="foto_associazione" placeholder="Percorso foto per questa squadra">
      </div>

      <button type="submit" name="associa_squadra" class="btn-primary">Associa / aggiorna foto</button>
  </form>

  <form method="POST" class="admin-form assoc-form assoc-form-edit hidden">
      <div class="form-group">
          <label>Seleziona Torneo</label>
          <select id="modAssocTorneo" required>
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($tornei as $torneoVal): ?>
              <option value="<?= htmlspecialchars($torneoVal) ?>"><?= htmlspecialchars($torneoVal) ?></option>
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
          <label>Nuova foto (opzionale)</label>
          <input type="text" name="mod_assoc_foto" placeholder="Percorso foto per questa squadra">
      </div>

      <button type="submit" name="modifica_associazione" class="btn-primary">Modifica associazione</button>
  </form>

  <form method="POST" class="admin-form assoc-form assoc-form-remove hidden">
      <div class="form-group">
          <label>Seleziona Torneo</label>
          <select id="remTorneo" required>
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($tornei as $torneoVal): ?>
              <option value="<?= htmlspecialchars($torneoVal) ?>"><?= htmlspecialchars($torneoVal) ?></option>
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

      <button type="submit" name="dissocia_squadra" class="btn-danger">Rimuovi associazione</button>
  </form>
</section>

<section class="admin-table-section form-elimina hidden">
<h2>Elimina Giocatore</h2>
<input type="text" id="search" placeholder="Cerca giocatore..." class="search-input">

<table class="admin-table-squadre" id="tabellaGiocatori">
<thead>
<tr>
    <th data-col="nome">Nome</th>
    <th data-col="cognome">Cognome</th>
    <th data-col="ruolo">Ruolo</th>
    <th data-col="squadra">Squadra</th>
    <th data-col="torneo">Torneo</th>
    <th>Azioni</th>
</tr>
</thead>
<tbody>
<?php foreach ($giocatori as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['nome']) ?></td>
    <td><?= htmlspecialchars($row['cognome']) ?></td>
    <td><?= htmlspecialchars($row['ruolo']) ?></td>
    <td><?= htmlspecialchars($row['squadre_assoc'] ?? '-') ?></td>
    <td><?= htmlspecialchars($row['tornei_assoc'] ?? '-') ?></td>
    <td>
        <a href="?elimina=<?= $row['id'] ?>" class="btn-danger" onclick="return confirm('Eliminare questo giocatore?')">Elimina</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>

</section>
</main>

<div id="footer-container"></div>


<!-- ✅ SCRIPTS -->
<script>
const selectAzione = document.getElementById('azione');
const formCrea = document.querySelector('.form-crea');
const formModifica = document.querySelector('.form-modifica');
const formElimina = document.querySelector('.form-elimina');
const formAssociazioni = document.querySelector('.form-associazioni');

function mostraSezione(val) {
    [formCrea, formModifica, formElimina, formAssociazioni].forEach(f => f && f.classList.add('hidden'));
    if (val === 'crea' && formCrea) formCrea.classList.remove('hidden');
    if (val === 'modifica' && formModifica) formModifica.classList.remove('hidden');
    if (val === 'elimina' && formElimina) formElimina.classList.remove('hidden');
    if (val === 'associazioni' && formAssociazioni) formAssociazioni.classList.remove('hidden');
}

mostraSezione(selectAzione.value);
selectAzione.addEventListener('change', e => mostraSezione(e.target.value));
</script>

<script>
const selectTorneoFiltro = document.getElementById("selectTorneoFiltro");
const selectSquadraFiltro = document.getElementById("selectSquadraFiltro");
const selectGiocatore = document.getElementById("selectGiocatore");
const assocTorneo = document.getElementById("assocTorneo");
const assocSquadra = document.getElementById("assocSquadra");
const assocGiocatore = document.getElementById("assocGiocatore");
const remTorneo = document.getElementById("remTorneo");
const remSquadra = document.getElementById("remSquadra");
const remGiocatore = document.getElementById("remGiocatore");
const modAssocTorneo = document.getElementById("modAssocTorneo");
const modAssocSquadra = document.getElementById("modAssocSquadra");
const modAssocGiocatore = document.getElementById("modAssocGiocatore");
const assocOperationSelect = document.getElementById("assocOperation");
const assocFormAdd = document.querySelector(".assoc-form-add");
const assocFormEdit = document.querySelector(".assoc-form-edit");
const assocFormRemove = document.querySelector(".assoc-form-remove");

const API_SQUADRE_TORNEO = "/torneioldschool/api/get_squadre_torneo.php";
const API_GIOCATORI_SQUADRA = "/torneioldschool/api/get_giocatori_squadra.php";

function resetSelect(select, placeholder, disable = true) {
    if (!select) return;
    select.innerHTML = placeholder ? `<option value="">${placeholder}</option>` : "";
    select.disabled = disable;
}

async function loadSquadre(select, torneo, placeholder = "-- Seleziona una squadra --") {
    resetSelect(select, placeholder);
    if (!select || !torneo) return [];

    try {
        const res = await fetch(`${API_SQUADRE_TORNEO}?torneo=${encodeURIComponent(torneo)}`);
        const data = await res.json();
        if (!Array.isArray(data) || !data.length) return [];

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
            opt.textContent = `${g.nome} ${g.cognome}`;
            select.appendChild(opt);
        });
        return data;
    } catch (err) {
        console.error("Errore nel caricamento giocatori:", err);
        return [];
    }
}

selectTorneoFiltro?.addEventListener("change", async () => {
    const torneo = selectTorneoFiltro.value;
    await loadSquadre(selectSquadraFiltro, torneo);
    resetSelect(selectGiocatore, "-- Seleziona un giocatore --");
});

selectSquadraFiltro?.addEventListener("change", async () => {
    const squadraId = selectSquadraFiltro.value;
    const torneo = selectTorneoFiltro.value;
    await loadGiocatori(selectGiocatore, squadraId, torneo);
});

assocTorneo?.addEventListener("change", async () => {
    await loadSquadre(assocSquadra, assocTorneo.value);
});

remTorneo?.addEventListener("change", async () => {
    await loadSquadre(remSquadra, remTorneo.value);
    resetSelect(remGiocatore, "-- Seleziona un giocatore --");
});

remSquadra?.addEventListener("change", async () => {
    await loadGiocatori(remGiocatore, remSquadra.value, remTorneo.value);
});

modAssocTorneo?.addEventListener("change", async () => {
    await loadSquadre(modAssocSquadra, modAssocTorneo.value);
    resetSelect(modAssocGiocatore, "-- Seleziona un giocatore --");
});

modAssocSquadra?.addEventListener("change", async () => {
    await loadGiocatori(modAssocGiocatore, modAssocSquadra.value, modAssocTorneo.value);
});

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

    const res = await fetch(`/torneioldschool/api/get_giocatore.php?id=${id}`);
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
// ✅ ORDINAMENTO TABELLA ELIMINA GIOCATORI
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
    fetch('/torneioldschool/includi/footer.html')
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
