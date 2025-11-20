<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
  header("Location: /torneioldschool/index.php");
  exit;
}

require_once __DIR__ . '/crud/Partita.php';
require_once __DIR__ . '/crud/Torneo.php';

$partita = new Partita();
$torneoRepo = new Torneo();
$fasiDisponibili = ['REGULAR', 'GOLD', 'SILVER'];
$fasiEliminazione = ['OTTAVI', 'QUARTI', 'SEMIFINALE', 'FINALE'];
$tipiAndataRitorno = ['ANDATA', 'RITORNO', 'UNICA'];
$errore = '';

function sanitizeFasePartita(?string $value, array $allowed): string {
  $fase = strtoupper(trim((string)$value));
  return in_array($fase, $allowed, true) ? $fase : 'REGULAR';
}

function sanitizeEnumValue(?string $value, array $allowed): ?string {
  $val = strtoupper(trim((string)$value));
  return in_array($val, $allowed, true) ? $val : null;
}

/* ============================================================
   CREA PARTITA
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
  $dati = [
    'torneo'         => trim($_POST['torneo']),
    'fase'           => sanitizeFasePartita($_POST['fase'] ?? 'REGULAR', $fasiDisponibili),
    'squadra_casa'   => trim($_POST['squadra_casa']),
    'squadra_ospite' => trim($_POST['squadra_ospite']),
    'gol_casa'       => (int)($_POST['gol_casa'] ?? 0),
    'gol_ospite'     => (int)($_POST['gol_ospite'] ?? 0),
    'data_partita'   => $_POST['data_partita'] ?? '',
    'ora_partita'    => $_POST['ora_partita'] ?? '',
    'campo'          => trim($_POST['campo'] ?? ''),
    'giornata'       => (($_POST['giornata'] ?? '') !== '' ? (int)$_POST['giornata'] : null),
    'fase_round'     => sanitizeEnumValue($_POST['fase_round'] ?? null, $fasiEliminazione),
    'fase_leg'       => sanitizeEnumValue($_POST['fase_leg'] ?? null, $tipiAndataRitorno),
  ];

  if (
    empty($dati['torneo']) || empty($dati['squadra_casa']) || empty($dati['squadra_ospite']) ||
    empty($dati['data_partita']) || empty($dati['ora_partita']) ||
    empty($dati['campo'])
  ) {
    $errore = 'Tutti i campi sono obbligatori.';
  } elseif ($dati['squadra_casa'] === $dati['squadra_ospite']) {
    $errore = 'Le due squadre non possono coincidere.';
  }

  if (empty($errore)) {
    if ($dati['fase'] === 'REGULAR') {
      if ($dati['giornata'] === null || $dati['giornata'] <= 0) {
        $errore = 'La giornata è obbligatoria per la Regular Season.';
      }
      $dati['fase_round'] = null;
      $dati['fase_leg'] = null;
    } else {
      $dati['giornata'] = null;
      if (!$dati['fase_round'] || !$dati['fase_leg']) {
        $errore = 'Per le fasi GOLD/SILVER seleziona fase eliminazione e tipologia gara.';
      }
    }
  }

  if (empty($errore)) {
    $partita->crea(
      $dati['squadra_casa'],
      $dati['squadra_ospite'],
      $dati['gol_casa'],
      $dati['gol_ospite'],
      $dati['data_partita'],
      $dati['ora_partita'],
      $dati['campo'],
      $dati['giornata'],
      $dati['torneo'],
      $dati['fase'],
      $dati['fase_round'],
      $dati['fase_leg']
    );

    $partita->aggiornaClassifica(
      $dati['torneo'],
      $dati['squadra_casa'],
      $dati['squadra_ospite'],
      $dati['gol_casa'],
      $dati['gol_ospite'],
      null,
      $dati['fase']
    );

    header("Location: gestione_partite.php");
    exit;
  }
}

/* ============================================================
   AGGIORNA PARTITA
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
  $id = (int)$_POST['id'];
  $dati = [
    'torneo'         => trim($_POST['torneo']),
    'fase'           => sanitizeFasePartita($_POST['fase'] ?? 'REGULAR', $fasiDisponibili),
    'squadra_casa'   => trim($_POST['squadra_casa']),
    'squadra_ospite' => trim($_POST['squadra_ospite']),
    'gol_casa'       => (int)$_POST['gol_casa'],
    'gol_ospite'     => (int)$_POST['gol_ospite'],
    'data_partita'   => $_POST['data_partita'],
    'ora_partita'    => $_POST['ora_partita'],
    'campo'          => trim($_POST['campo']),
    'giornata'       => (($_POST['giornata'] ?? '') !== '' ? (int)$_POST['giornata'] : null),
    'fase_round'     => sanitizeEnumValue($_POST['fase_round'] ?? null, $fasiEliminazione),
    'fase_leg'       => sanitizeEnumValue($_POST['fase_leg'] ?? null, $tipiAndataRitorno)
  ];

  if ($dati['squadra_casa'] === $dati['squadra_ospite']) {
    $errore = 'Le due squadre non possono coincidere.';
  }

  if (empty($errore)) {
    if ($dati['fase'] === 'REGULAR') {
      if ($dati['giornata'] === null || $dati['giornata'] <= 0) {
        $errore = 'La giornata è obbligatoria per la Regular Season.';
      }
      $dati['fase_round'] = null;
      $dati['fase_leg'] = null;
    } else {
      $dati['giornata'] = null;
      if (!$dati['fase_round'] || !$dati['fase_leg']) {
        $errore = 'Per le fasi GOLD/SILVER seleziona fase eliminazione e tipologia gara.';
      }
    }
  }

  if (empty($errore)) {
    $vecchia = $partita->getById($id);

    $partita->aggiorna(
      $id,
      $dati['squadra_casa'],
      $dati['squadra_ospite'],
      $dati['gol_casa'],
      $dati['gol_ospite'],
      $dati['data_partita'],
      $dati['ora_partita'],
      $dati['campo'],
      $dati['giornata'],
      $dati['torneo'],
      $dati['fase'],
      $dati['fase_round'],
      $dati['fase_leg']
    );

    $partita->aggiornaClassifica(
      $dati['torneo'],
      $dati['squadra_casa'],
      $dati['squadra_ospite'],
      $dati['gol_casa'],
      $dati['gol_ospite'],
      $vecchia,
      $dati['fase']
    );

    header("Location: gestione_partite.php");
    exit;
  }
}

/* ============================================================
   ELIMINA PARTITA
============================================================ */
if (isset($_GET['elimina'])) {
  $id = (int)$_GET['elimina'];
  $vecchia = $partita->getById($id);
  if ($vecchia) {
    $partita->aggiornaClassifica(
      $vecchia['torneo'],
      $vecchia['squadra_casa'],
      $vecchia['squadra_ospite'],
      0, 0,
      $vecchia,
      $vecchia['fase'] ?? 'REGULAR'
    );
  }
  $partita->elimina($id);
  header("Location: gestione_partite.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestione Partite</title>
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
  margin-top: auto;
  padding-top: 40px;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includi/header.php'; ?>

<main class="admin-wrapper">
<section class="admin-container">
<a class="admin-back-link" href="/torneioldschool/admin_dashboard.php">Torna alla dashboard</a>
<h1 class="admin-title">Gestione Partite</h1>

<?php if ($errore): ?>
<div class="alert-error"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<!-- Selettore azione -->
<div class="admin-select-action">
  <label for="azione">Seleziona azione:</label>
  <select id="azione">
    <option value="crea" selected>Aggiungi Partita</option>
    <option value="modifica">Modifica Partita</option>
    <option value="elimina">Elimina Partita</option>
    <option value="statistiche">Statistiche Partita</option>
  </select>
</div>

<!-- CREA -->
<form method="POST" class="admin-form form-crea" data-action-section="crea">
  <h2>Aggiungi Partita</h2>

  <label>Torneo</label>
  <select name="torneo" id="selectTorneo" required>
    <option value="">-- Seleziona torneo --</option>
    <?php
    $tornei = $torneoRepo->getAll();
    while ($t = $tornei->fetch_assoc()):
      $slug = preg_replace('/\.html$/i', '', $t['filetorneo']);
    ?>
    <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($t['nome'] ?? $slug) ?></option>
    <?php endwhile; ?>
  </select>

  <label>Fase</label>
  <select name="fase" id="selectFaseCrea" required>
    <?php foreach ($fasiDisponibili as $fase): ?>
      <option value="<?= htmlspecialchars($fase) ?>"><?= htmlspecialchars($fase) ?></option>
    <?php endforeach; ?>
  </select>

  <label>Squadra Casa</label>
  <select name="squadra_casa" id="squadraCasa" required></select>

  <label>Squadra Ospite</label>
  <select name="squadra_ospite" id="squadraOspite" required></select>

  <div class="form-row">
    <div class="form-group half">
      <label>Data</label>
      <input type="date" name="data_partita" required>
    </div>
    <div class="form-group half">
      <label>Ora</label>
      <input type="time" name="ora_partita" required>
    </div>
  </div>

  <div class="form-group">
    <label>Campo</label>
    <select name="campo" required>
      <option value="">-- Seleziona Campo --</option>
      <option value="Sporting Club San Francesco, Napoli">Sporting Club San Francesco, Napoli</option>
      <option value="Centro Sportivo La Paratina, Napoli">Centro Sportivo La Paratina, Napoli</option>
      <option value="Sporting S.Antonio, Napoli">Sporting S.Antonio, Napoli</option>
      <option value="La Boutique del Calcio, Napoli">La Boutique del Calcio, Napoli</option>
    </select>
  </div>

  <div class="form-group" id="creaGiornataGroup">
    <label>Giornata</label>
    <input type="number" name="giornata" id="crea_giornata" min="1" required>
  </div>

  <div class="form-row hidden" id="creaKnockoutGroup">
    <div class="form-group half">
      <label>Fase eliminazione</label>
      <select name="fase_round" id="crea_fase_round" disabled>
        <option value="">-- Seleziona fase --</option>
        <?php foreach ($fasiEliminazione as $round): ?>
          <option value="<?= htmlspecialchars($round) ?>"><?= ucfirst(strtolower($round)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group half">
      <label>Tipologia partita</label>
      <select name="fase_leg" id="crea_fase_leg" disabled>
        <option value="">-- Seleziona tipologia --</option>
        <?php foreach ($tipiAndataRitorno as $tipo): ?>
          <option value="<?= htmlspecialchars($tipo) ?>"><?= ucfirst(strtolower($tipo)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <button type="submit" name="crea" class="btn-primary">Aggiungi Partita</button>
</form>


<!-- ===== FORM MODIFICA ===== -->
<form method="POST" class="admin-form form-modifica hidden" id="formModifica" data-action-section="modifica">
  <h2>Modifica Partita</h2>

    <!-- 1) Seleziona torneo -->
    <div class="form-group">
      <label>Torneo</label>
      <select id="modSelectTorneo" required>
        <option value="">-- Seleziona torneo --</option>
      <?php
        $tornei_all = $torneoRepo->getAll();
        while ($t = $tornei_all->fetch_assoc()):
          $slug = preg_replace('/\.html$/i', '', $t['filetorneo']);
      ?>
        <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($t['nome'] ?? $slug) ?></option>
      <?php endwhile; ?>
    </select>
    </div>
  
    <!-- Torneo nascosto per POST -->
    <input type="hidden" name="torneo" id="torneoHiddenMod">

    <div class="form-group">
      <label>Fase</label>
      <select id="modSelectFase" required disabled>
        <option value="">-- Seleziona torneo prima --</option>
        <?php foreach ($fasiDisponibili as $fase): ?>
          <option value="<?= htmlspecialchars($fase) ?>"><?= htmlspecialchars($fase) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

  <!-- 2) Seleziona giornata -->
    <div class="form-group">
      <label>Giornata / Fase</label>
      <select id="modSelectGiornata" required disabled>
        <option value="">-- Seleziona torneo prima --</option>
      </select>
    </div>

  <!-- 3) Seleziona partita -->
  <div class="form-group">
    <label>Partita</label>
    <select name="id" id="modSelectPartita" required disabled>
      <option value="">-- Seleziona giornata prima --</option>
    </select>
  </div>

  <button type="button" id="btn_statistiche" class="btn-primary" style="margin-top:10px; display:none;">
      STATISTICHE PARTITA
  </button>

  <hr>

  <!-- FORM DATI PARTITA -->
  <div id="modFormDati" class="hidden">
    <div class="form-group">
      <label>Squadra Casa</label>
      <select name="squadra_casa" id="squadraCasaMod" required>
        <option value="">-- Seleziona torneo prima --</option>
      </select>
    </div>

      <div class="form-group">
        <label>Squadra Ospite</label>
        <select name="squadra_ospite" id="squadraOspiteMod" required>
          <option value="">-- Seleziona torneo prima --</option>
        </select>
      </div>

      <div class="form-group">
        <label>Fase</label>
        <select name="fase" id="mod_fase" required>
          <?php foreach ($fasiDisponibili as $fase): ?>
            <option value="<?= htmlspecialchars($fase) ?>"><?= htmlspecialchars($fase) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

    <div class="form-row">
      <div class="form-group half">
        <label>Gol Casa</label>
        <input type="number" name="gol_casa" id="mod_gol_casa" min="0" required>
      </div>
      <div class="form-group half">
        <label>Gol Ospite</label>
        <input type="number" name="gol_ospite" id="mod_gol_ospite" min="0" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group half">
        <label>Data</label>
        <input type="date" name="data_partita" id="mod_data_partita" required>
      </div>
      <div class="form-group half">
        <label>Ora</label>
        <input type="time" name="ora_partita" id="mod_ora_partita" required>
      </div>
    </div>

    <div class="form-group">
      <label>Campo</label>
      <select name="campo" id="mod_campo" required>
        <option value="">-- Seleziona Campo --</option>
        <option value="Sporting Club San Francesco, Napoli">Sporting Club San Francesco, Napoli</option>
        <option value="Centro Sportivo La Paratina, Napoli">Centro Sportivo La Paratina, Napoli</option>
        <option value="Sporting S.Antonio, Napoli">Sporting S.Antonio, Napoli</option>
        <option value="La Boutique del Calcio, Napoli">La Boutique del Calcio, Napoli</option>
        <option value="Campo Centrale del Parco Corto Maltese, Napoli">Campo Centrale del Parco Corto Maltese, Napoli</option>
      </select>
    </div>

      <div class="form-group" id="modGiornataGroup">
        <label>Giornata</label>
        <input type="number" name="giornata" id="mod_giornata" min="1" required>
      </div>

      <div class="form-row hidden" id="modKnockoutGroup">
        <div class="form-group half">
          <label>Fase eliminazione</label>
          <select name="fase_round" id="mod_fase_round" disabled>
            <option value="">-- Seleziona fase --</option>
            <?php foreach ($fasiEliminazione as $round): ?>
              <option value="<?= htmlspecialchars($round) ?>"><?= ucfirst(strtolower($round)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group half">
          <label>Tipologia partita</label>
          <select name="fase_leg" id="mod_fase_leg" disabled>
            <option value="">-- Seleziona tipologia --</option>
            <?php foreach ($tipiAndataRitorno as $tipo): ?>
              <option value="<?= htmlspecialchars($tipo) ?>"><?= ucfirst(strtolower($tipo)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

    <div class="form-row">
      <div class="form-group half">
        <label>Link YouTube (opzionale)</label>
        <input type="url" name="link_youtube" id="mod_link_youtube" placeholder="https://youtube.com/...">
      </div>
      <div class="form-group half">
        <label>Link Instagram (opzionale)</label>
        <input type="url" name="link_instagram" id="mod_link_instagram" placeholder="https://instagram.com/...">
      </div>
    </div>

    <button type="submit" name="aggiorna" class="btn-primary">💾 Salva Modifiche</button>
  </div>
</form>



<!-- ===== SEZIONE ELIMINA ===== -->
<section class="admin-table-section form-elimina hidden" data-action-section="elimina">
  <h2>Elimina Partita</h2>

  <div class="search-wrapper">
    <input type="text" id="searchPartita" placeholder="🔍 Cerca partita..." class="search-input">
  </div>

  <div class="admin-table-utenti-container">
    <table class="admin-table" id="tabellaPartite" style="min-width:900px;">
      <thead>
        <tr>
          <th data-col="torneo">Torneo</th>
          <th data-col="fase">Fase</th>
          <th data-col="giornata">Giornata</th>
          <th data-col="fase_round">Fase KO</th>
          <th data-col="fase_leg">Tipologia</th>
          <th data-col="squadra_casa">Casa</th>
          <th data-col="squadra_ospite">Ospite</th>
          <th data-col="data_partita">Data</th>
          <th data-col="ora_partita">Ora</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rows = $partita->getAll();
        while ($r = $rows->fetch_assoc()):
        ?>
        <tr>
          <td><?= htmlspecialchars($r['torneo']) ?></td>
          <td><?= htmlspecialchars($r['fase']) ?></td>
          <td><?= htmlspecialchars($r['giornata']) ?></td>
          <td><?= htmlspecialchars($r['fase_round'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['fase_leg'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['squadra_casa']) ?></td>
          <td><?= htmlspecialchars($r['squadra_ospite']) ?></td>
          <td><?= htmlspecialchars($r['data_partita']) ?></td>
          <td><?= htmlspecialchars($r['ora_partita']) ?></td>
            <td>
              <a href="#" class="btn-danger delete-btn" data-id="<?= $r['id'] ?>" data-label="<?= htmlspecialchars($r['squadra_casa'] . ' - ' . $r['squadra_ospite']) ?>" data-type="partita">Elimina</a>
            </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</section>


<!-- ===== SEZIONE STATISTICHE ===== -->
<section class="admin-table-section form-statistiche hidden" data-action-section="statistiche">
  <h2>Statistiche Partita</h2>

  <!-- Selezione Partita -->
  <div class="form-group">
    <label>Torneo</label>
    <select id="statSelectTorneo" required>
      <option value="">-- Seleziona torneo --</option>
      <?php
      $tornei_all = $torneoRepo->getAll();
      while ($t = $tornei_all->fetch_assoc()):
        $slug = preg_replace('/\.html$/i', '', $t['filetorneo']);
      ?>
        <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($t['nome'] ?? $slug) ?></option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="form-group">
    <label>Giornata / Fase</label>
    <select id="statSelectGiornata" required disabled>
      <option value="">-- Seleziona torneo prima --</option>
    </select>
  </div>

  <div class="form-group">
    <label>Partita</label>
    <select id="statSelectPartita" required disabled>
      <option value="">-- Seleziona giornata prima --</option>
    </select>
  </div>

  <hr>

  <!-- TABELLONE GIOCATORI -->
  <div id="statisticheContainer" class="hidden">
    <h3>Statistiche Giocatori</h3>
    <table class="admin-table" id="tabellaStatistiche">
      <thead>
        <tr>
          <th>Giocatore</th>
          <th>Squadra</th>
          <th>Gol</th>
          <th>Assist</th>
          <th>Cartellini</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <h3>Aggiungi Statistica</h3>
    <form id="formAggiungiStat">
      <input type="hidden" name="partita_id" id="stat_partita_id">
      <div class="form-row">
        <div class="form-group half">
          <label>Giocatore</label>
          <select name="giocatore_id" id="statGiocatore" required>
            <option value="">-- Seleziona squadra prima --</option>
          </select>
        </div>
        <div class="form-group half">
          <label>Gol</label>
          <input type="number" name="gol" min="0" value="0" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group half">
          <label>Assist</label>
          <input type="number" name="assist" min="0" value="0" required>
        </div>
        <div class="form-group half">
          <label>Cartellini</label>
          <select name="cartellini">
            <option value="nessuno">Nessuno</option>
            <option value="giallo">Giallo</option>
            <option value="rosso">Rosso</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn-primary">➕ Aggiungi Statistica</button>
    </form>
  </div>
</section>


</section>
</main>

<?php include __DIR__ . '/../includi/delete_modal.php'; ?>

<div id="footer-container"></div>

<script>
console.log("JS caricato correttamente: INIZIO");

document.addEventListener("DOMContentLoaded", () => {
  console.log("DOMContentLoaded OK");

  const selectAzione = document.getElementById("azione");
  if (!selectAzione) console.error("ERRORE: selectAzione non trovato");

  const forms = {
    crea: document.querySelector(".form-crea"),
    modifica: document.querySelector(".form-modifica"),
    elimina: document.querySelector(".form-elimina"),
    statistiche: document.querySelector(".form-statistiche")
  };

  function mostraForm(nome) {
    console.log("Cambio form:", nome);
    const target = nome || "crea";
    Object.entries(forms).forEach(([key, form]) => {
      if (!form) return;
      form.classList.toggle("hidden", key !== target);
    });
  }

  mostraForm(selectAzione ? selectAzione.value : "crea");

  if (selectAzione) {
    selectAzione.addEventListener("change", e => mostraForm(e.target.value));
  }

  function gestisciFase(selectElemento, groupGiornataId, groupKoId, selectRoundId, selectLegId) {
    const valore = selectElemento?.value || "REGULAR";
    const gruppoGiornata = document.getElementById(groupGiornataId);
    const gruppoKo = document.getElementById(groupKoId);
    const selectRound = document.getElementById(selectRoundId);
    const selectLeg = document.getElementById(selectLegId);

    const isRegular = valore === "REGULAR";

    if (gruppoGiornata) {
      gruppoGiornata.classList.toggle("hidden", !isRegular);
      const inputGiornata = gruppoGiornata.querySelector("input");
      if (inputGiornata) {
        inputGiornata.required = isRegular;
        inputGiornata.disabled = !isRegular;
      }
    }

    if (gruppoKo) {
      gruppoKo.classList.toggle("hidden", isRegular);
    }

    [selectRound, selectLeg].forEach(sel => {
      if (!sel) return;
      sel.disabled = isRegular;
      sel.required = !isRegular;
      if (isRegular) sel.value = "";
    });
  }

  async function caricaSquadre(slug, idCasa, idOspite, selCasa = "", selOspite = "") {
    console.log("Carico squadre per torneo:", slug);
    if (!slug) return;
    try {
      const url = "/torneioldschool/api/get_squadre.php?torneo=" + encodeURIComponent(slug);
      const r = await fetch(url);
      if (!r.ok) console.error("ERRORE fetch get_squadre:", r.status);
      const d = await r.json();
      const casa = document.getElementById(idCasa);
      const osp = document.getElementById(idOspite);
      if (!casa || !osp) return console.error("Select squadre non trovate");

      casa.innerHTML = osp.innerHTML = '<option>-- Seleziona --</option>';
      d.forEach(n => {
        casa.add(new Option(n, n));
        osp.add(new Option(n, n));
      });

      casa.value = selCasa;
      osp.value = selOspite;

      console.log("Squadre caricate:", d);
    } catch (err) {
      console.error("Errore caricamento squadre:", err);
    }
  }

  const torneoSelect = document.getElementById("selectTorneo");
  if (torneoSelect) {
    torneoSelect.addEventListener("change", () =>
      caricaSquadre(torneoSelect.value, "squadraCasa", "squadraOspite")
    );
  }

  const faseCrea = document.getElementById("selectFaseCrea");
  faseCrea?.addEventListener("change", () =>
    gestisciFase(faseCrea, "creaGiornataGroup", "creaKnockoutGroup", "crea_fase_round", "crea_fase_leg")
  );
  gestisciFase(faseCrea, "creaGiornataGroup", "creaKnockoutGroup", "crea_fase_round", "crea_fase_leg");

  // ============================
  // MODIFICA PARTITE
  // ============================
  const selectTorneoMod = document.getElementById("modSelectTorneo");
  const selectFaseMod = document.getElementById("modSelectFase");
  const selectGiornataMod = document.getElementById("modSelectGiornata");
  const selectPartitaMod = document.getElementById("modSelectPartita");
  const torneoHiddenMod = document.getElementById("torneoHiddenMod");
  const modFormDati = document.getElementById("modFormDati");
  const btnStats = document.getElementById("btn_statistiche");
  let partiteCache = {};

  function resetModForm() {
    if (modFormDati) modFormDati.classList.add("hidden");
    if (btnStats) btnStats.style.display = "none";
    [selectPartitaMod, selectGiornataMod].forEach(sel => {
      if (!sel) return;
      sel.innerHTML = '<option value="">-- Seleziona torneo prima --</option>';
      sel.disabled = true;
    });
  }

  async function caricaGiornateMod() {
    const torneo = selectTorneoMod?.value || "";
    const fase = selectFaseMod?.value || "";
    if (!torneo || !selectGiornataMod || !selectPartitaMod) return;

    selectGiornataMod.innerHTML = '<option value="">-- Seleziona --</option>';
    selectPartitaMod.innerHTML = '<option value="">-- Seleziona giornata prima --</option>';
    selectPartitaMod.disabled = true;

    try {
      const url = `/torneioldschool/api/get_partite.php?torneo=${encodeURIComponent(torneo)}${fase ? `&fase=${encodeURIComponent(fase)}` : ""}`;
      const res = await fetch(url);
      if (!res.ok) throw new Error("Errore rete " + res.status);
      partiteCache = await res.json();

      const keys = Object.keys(partiteCache).sort((a, b) => Number(a) - Number(b));
      keys.forEach(k => {
        const label = isNaN(Number(k)) ? k : `Giornata ${k}`;
        selectGiornataMod.add(new Option(label, k));
      });
      selectGiornataMod.disabled = false;
    } catch (err) {
      console.error("Errore caricamento partite:", err);
    }
  }

  selectTorneoMod?.addEventListener("change", () => {
    if (torneoHiddenMod) torneoHiddenMod.value = selectTorneoMod.value;
    if (selectFaseMod) selectFaseMod.disabled = !selectTorneoMod.value;
    caricaSquadre(selectTorneoMod.value, "squadraCasaMod", "squadraOspiteMod");
    resetModForm();
    caricaGiornateMod();
  });

  selectFaseMod?.addEventListener("change", () => {
    gestisciFase(selectFaseMod, "modGiornataGroup", "modKnockoutGroup", "mod_fase_round", "mod_fase_leg");
    resetModForm();
    caricaGiornateMod();
  });

  selectGiornataMod?.addEventListener("change", () => {
    if (!selectPartitaMod) return;
    const lista = partiteCache[selectGiornataMod.value] || [];
    selectPartitaMod.innerHTML = '<option value="">-- Seleziona partita --</option>';
    lista.forEach(p => {
      const label = `${p.squadra_casa} - ${p.squadra_ospite}`;
      selectPartitaMod.add(new Option(label, p.id));
    });
    selectPartitaMod.disabled = lista.length === 0;
    if (lista.length === 0) {
      console.warn("Nessuna partita trovata per la selezione attuale");
    }
  });

  selectPartitaMod?.addEventListener("change", async () => {
    const id = selectPartitaMod.value;
    if (!id) return;
    try {
      const res = await fetch(`/torneioldschool/api/get_partita.php?id=${id}`);
      if (!res.ok) throw new Error("Errore rete " + res.status);
      const data = await res.json();

      ["squadraCasaMod", "squadraOspiteMod"].forEach(selId => {
        const el = document.getElementById(selId);
        if (el) el.innerHTML = "";
      });
      caricaSquadre(data.torneo, "squadraCasaMod", "squadraOspiteMod", data.squadra_casa, data.squadra_ospite);

      document.getElementById("mod_fase").value = data.fase || "REGULAR";
      document.getElementById("mod_gol_casa").value = data.gol_casa ?? 0;
      document.getElementById("mod_gol_ospite").value = data.gol_ospite ?? 0;
      document.getElementById("mod_data_partita").value = data.data_partita || "";
      document.getElementById("mod_ora_partita").value = data.ora_partita || "";
      document.getElementById("mod_campo").value = data.campo || "";
      document.getElementById("mod_giornata").value = data.giornata ?? "";
      document.getElementById("mod_fase_round").value = data.fase_round || "";
      document.getElementById("mod_fase_leg").value = data.fase_leg || "";
      document.getElementById("mod_link_youtube").value = data.link_youtube || "";
      document.getElementById("mod_link_instagram").value = data.link_instagram || "";

      gestisciFase(document.getElementById("mod_fase"), "modGiornataGroup", "modKnockoutGroup", "mod_fase_round", "mod_fase_leg");

      if (modFormDati) modFormDati.classList.remove("hidden");
      if (btnStats) {
        btnStats.style.display = "block";
        btnStats.onclick = () => {
          window.location.href = `/torneioldschool/api/statistiche_partita.php?partitaid=${id}`;
        };
      }
    } catch (err) {
      console.error("Errore recupero partita:", err);
    }
  });

  document.getElementById("mod_fase")?.addEventListener("change", () =>
    gestisciFase(document.getElementById("mod_fase"), "modGiornataGroup", "modKnockoutGroup", "mod_fase_round", "mod_fase_leg")
  );

  // ============================
  // FOOTER
  // ============================
  const footer = document.getElementById("footer-container");
  if (footer) {
    fetch("/torneioldschool/includi/footer.html")
      .then(r => r.text())
      .then(html => footer.innerHTML = html)
      .catch(err => console.error("Errore footer:", err));
  }

});
</script>

<script src="/torneioldschool/includi/delete-modal.js"></script>


</body>
</html>

