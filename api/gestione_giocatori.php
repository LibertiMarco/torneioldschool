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
    $nuovoId = $giocatore->crea(
        trim($_POST['nome']),
        trim($_POST['cognome']),
        trim($_POST['ruolo']),
        trim($_POST['squadra']),
        trim($_POST['torneo']),
        (int)$_POST['presenze'],
        (int)$_POST['reti'],
        (int)$_POST['gialli'],
        (int)$_POST['rossi'],
        trim($_POST['media_voti']),
        trim($_POST['foto'])
    );
    $squadraNome = trim($_POST['squadra']);
    $torneo = trim($_POST['torneo']);

    if ($nuovoId && $squadraNome !== '' && $torneo !== '') {
        $team = $squadraModel->getByNomeETorneo($squadraNome, $torneo);
        if ($team) {
            $pivot->assegna($nuovoId, (int)$team['id'], trim($_POST['foto']));
        }
    }

    header("Location: gestione_giocatori.php");
    exit;
}

// --- AGGIORNA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
    $giocatore->aggiorna(
        (int)$_POST['id'],
        trim($_POST['nome']),
        trim($_POST['cognome']),
        trim($_POST['ruolo']),
        trim($_POST['squadra']),
        trim($_POST['torneo']),
        (int)$_POST['presenze'],
        (int)$_POST['reti'],
        (int)$_POST['gialli'],
        (int)$_POST['rossi'],
        trim($_POST['media_voti']),
        trim($_POST['foto'])
    );
    $squadraNome = trim($_POST['squadra']);
    $torneo = trim($_POST['torneo']);

    if ($squadraNome !== '' && $torneo !== '') {
        $team = $squadraModel->getByNomeETorneo($squadraNome, $torneo);
        if ($team) {
            $pivot->assegna((int)$_POST['id'], (int)$team['id'], trim($_POST['foto']));
        }
    }

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
</head>

<body>
<?php include __DIR__ . '/../includi/header.php'; ?>

<main class="admin-wrapper">
<section class="admin-container">

<h1 class="admin-title">Gestione Giocatori</h1>

<!-- PICKLIST -->
<div class="admin-select-action">
    <label for="azione">Seleziona azione:</label>
    <select id="azione" class="operation-picker">
        <option value="crea" selected>Aggiungi Giocatore</option>
        <option value="modifica">Modifica Giocatore</option>
        <option value="elimina">Elimina Giocatore</option>
    </select>
</div>

<!-- ✅ FORM CREA -->
<form method="POST" class="admin-form form-crea">
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
    <label>Squadra</label>
    <input type="text" name="squadra" required>
</div>

<div class="form-group">
    <label>Torneo</label>
    <input type="text" name="torneo" required>
</div>

<div class="form-group">
    <label>Percorso Foto</label>
    <input type="text" name="foto" value="/torneioldschool/img/giocatori/unknown.jpg">
</div>

<button type="submit" name="crea" class="btn-primary">Crea Giocatore</button>
</form>


<!-- ✅ FORM MODIFICA -->
<form method="POST" class="admin-form form-modifica hidden" id="formModifica">
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
<div class="form-group"><label>Squadra</label><input type="text" name="squadra" id="mod_squadra"></div>
<div class="form-group"><label>Torneo</label><input type="text" name="torneo" id="mod_torneo"></div>

<div class="form-row">
    <div class="form-group half"><label>Presenze</label><input type="number" name="presenze" id="mod_presenze"></div>
    <div class="form-group half"><label>Reti</label><input type="number" name="reti" id="mod_reti"></div>
</div>

<div class="form-row">
    <div class="form-group half"><label>Gialli</label><input type="number" name="gialli" id="mod_gialli"></div>
    <div class="form-group half"><label>Rossi</label><input type="number" name="rossi" id="mod_rossi"></div>
</div>

<div class="form-group"><label>Media Voti</label><input type="text" name="media_voti" id="mod_media"></div>
<div class="form-group"><label>Percorso Foto</label><input type="text" name="foto" id="mod_foto"></div>

<button type="submit" name="aggiorna" class="btn-primary">Aggiorna Giocatore</button>
</form>

<!-- ✅ SEZIONE ELIMINA -->
<!-- GESTIONE ASSOCIAZIONI -->
<section class="admin-associazioni">
<form method="POST" class="admin-form form-associa">
    <h2>Associa Giocatore a una Squadra</h2>
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
        <select name="giocatore_associa" required>
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

<form method="POST" class="admin-form form-dissocia">
    <h2>Rimuovi Giocatore dalla Squadra</h2>
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
    <td><?= htmlspecialchars($row['squadre_assoc'] ?? $row['squadra'] ?? '-') ?></td>
    <td><?= htmlspecialchars($row['tornei_assoc'] ?? $row['torneo'] ?? '-') ?></td>
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

<!-- ✅ SCRIPTS -->
<script>
const selectAzione = document.getElementById('azione');
const formCrea = document.querySelector('.form-crea');
const formModifica = document.querySelector('.form-modifica');
const formElimina = document.querySelector('.form-elimina');

function mostraSezione(val) {
    [formCrea, formModifica, formElimina].forEach(f => f.classList.add('hidden'));
    if (val === 'crea') formCrea.classList.remove('hidden');
    if (val === 'modifica') formModifica.classList.remove('hidden');
    if (val === 'elimina') formElimina.classList.remove('hidden');
}
selectAzione.addEventListener('change', e => mostraSezione(e.target.value));
</script>

<script>
const selectTorneoFiltro = document.getElementById("selectTorneoFiltro");
const selectSquadraFiltro = document.getElementById("selectSquadraFiltro");
const selectGiocatore = document.getElementById("selectGiocatore");
const assocTorneo = document.getElementById("assocTorneo");
const assocSquadra = document.getElementById("assocSquadra");
const remTorneo = document.getElementById("remTorneo");
const remSquadra = document.getElementById("remSquadra");
const remGiocatore = document.getElementById("remGiocatore");

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

selectGiocatore?.addEventListener("change", async e => {
    const id = e.target.value;
    if (!id) return;

    const res = await fetch(`/torneioldschool/api/get_giocatore.php?id=${id}`);
    const data = await res.json();
    if (!data) return;

    document.getElementById("mod_nome").value       = data.nome;
    document.getElementById("mod_cognome").value    = data.cognome;
    document.getElementById("mod_ruolo").value      = data.ruolo;
    document.getElementById("mod_squadra").value    = data.squadra;
    document.getElementById("mod_torneo").value     = data.torneo;
    document.getElementById("mod_presenze").value   = data.presenze;
    document.getElementById("mod_reti").value       = data.reti;
    document.getElementById("mod_gialli").value     = data.gialli;
    document.getElementById("mod_rossi").value      = data.rossi;
    document.getElementById("mod_media").value      = data.media_voti;
    document.getElementById("mod_foto").value       = data.foto;
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

</body>
</html>
