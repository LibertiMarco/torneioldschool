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
$errore = '';

/* ============================================================
   CREA PARTITA
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
  $dati = [
    'torneo'         => trim($_POST['torneo']),
    'squadra_casa'   => trim($_POST['squadra_casa']),
    'squadra_ospite' => trim($_POST['squadra_ospite']),
    'gol_casa'       => (int)($_POST['gol_casa'] ?? 0),
    'gol_ospite'     => (int)($_POST['gol_ospite'] ?? 0),
    'data_partita'   => $_POST['data_partita'] ?? '',
    'ora_partita'    => $_POST['ora_partita'] ?? '',
    'campo'          => trim($_POST['campo'] ?? ''),
    'giornata'       => (int)($_POST['giornata'] ?? 0),
  ];

  if (
    empty($dati['torneo']) || empty($dati['squadra_casa']) || empty($dati['squadra_ospite']) ||
    empty($dati['data_partita']) || empty($dati['ora_partita']) ||
    empty($dati['campo']) || empty($dati['giornata'])
  ) {
    $errore = 'Tutti i campi sono obbligatori.';
  } elseif ($dati['squadra_casa'] === $dati['squadra_ospite']) {
    $errore = 'Le due squadre non possono coincidere.';
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
      $dati['torneo']
    );

    $partita->aggiornaClassifica(
      $dati['torneo'],
      $dati['squadra_casa'],
      $dati['squadra_ospite'],
      $dati['gol_casa'],
      $dati['gol_ospite']
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
    'squadra_casa'   => trim($_POST['squadra_casa']),
    'squadra_ospite' => trim($_POST['squadra_ospite']),
    'gol_casa'       => (int)$_POST['gol_casa'],
    'gol_ospite'     => (int)$_POST['gol_ospite'],
    'data_partita'   => $_POST['data_partita'],
    'ora_partita'    => $_POST['ora_partita'],
    'campo'          => trim($_POST['campo']),
    'giornata'       => (int)$_POST['giornata']
  ];

  if ($dati['squadra_casa'] === $dati['squadra_ospite']) {
    $errore = 'Le due squadre non possono coincidere.';
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
      $dati['torneo']
    );

    $partita->aggiornaClassifica(
      $dati['torneo'],
      $dati['squadra_casa'],
      $dati['squadra_ospite'],
      $dati['gol_casa'],
      $dati['gol_ospite'],
      $vecchia
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
      $vecchia
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
  <label>Seleziona azione:</label>
  <select id="azione">
    <option value="crea" selected>Aggiungi Partita</option>
    <option value="modifica">Modifica Partita</option>
    <option value="elimina">Elimina Partita</option>
  </select>
</div>

<!-- CREA -->
<form method="POST" class="admin-form form-crea">
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

  <div class="form-group">
    <label>Giornata</label>
    <input type="number" name="giornata" min="1" required>
  </div>

  <button type="submit" name="crea" class="btn-primary">Aggiungi Partita</button>
</form>


<!-- ===== FORM MODIFICA ===== -->
<form method="POST" class="admin-form form-modifica hidden" id="formModifica">
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

  <!-- 2) Seleziona giornata -->
  <div class="form-group">
    <label>Giornata</label>
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

    <div class="form-group">
      <label>Giornata</label>
      <input type="number" name="giornata" id="mod_giornata" min="1" required>
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
<section class="admin-table-section form-elimina hidden">
  <h2>Elimina Partita</h2>

  <div class="search-wrapper">
    <input type="text" id="searchPartita" placeholder="🔍 Cerca partita..." class="search-input">
  </div>

  <div class="admin-table-utenti-container">
    <table class="admin-table" id="tabellaPartite" style="min-width:900px;">
      <thead>
        <tr>
          <th data-col="torneo">Torneo</th>
          <th data-col="giornata">Giornata</th>
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
          <td><?= htmlspecialchars($r['giornata']) ?></td>
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
<section class="admin-table-section form-statistiche hidden">
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
    <label>Giornata</label>
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

<script>
document.addEventListener("DOMContentLoaded", () => {

const selectAzione = document.getElementById('azione');
const formCrea = document.querySelector('.form-crea');
const formMod = document.querySelector('.form-modifica');
const formElimina = document.querySelector('.form-elimina');
const formStatistiche = document.querySelector('.form-statistiche');

function mostra(val){
    [formCrea, formMod, formElimina, formStatistiche].forEach(f => f.classList.add('hidden'));
    if(val === 'crea') formCrea.classList.remove('hidden');
    if(val === 'modifica') formMod.classList.remove('hidden');
    if(val === 'elimina') formElimina.classList.remove('hidden');
    if(val === 'statistiche') formStatistiche.classList.remove('hidden');
}

selectAzione.addEventListener('change', e => mostra(e.target.value));


// ======== CARICA SQUADRE ========
async function caricaSquadre(slug, idCasa, idOspite, selCasa='', selOspite=''){
    if (!slug) return;
    const r = await fetch(`/torneioldschool/api/get_squadre.php?torneo=${slug}`);
    const d = await r.json();
    const casa = document.getElementById(idCasa),
          osp = document.getElementById(idOspite);

    casa.innerHTML = osp.innerHTML = '<option>-- Seleziona --</option>';

    d.forEach(n => {
        casa.add(new Option(n, n));
        osp.add(new Option(n, n));
    });

    casa.value = selCasa;
    osp.value = selOspite;
}

document.getElementById('selectTorneo').addEventListener('change', () =>
    caricaSquadre(selectTorneo.value, 'squadraCasa', 'squadraOspite')
);


// ======== MODIFICA PARTITA ========
const modT = document.getElementById('modSelectTorneo');
const modG = document.getElementById('modSelectGiornata');
const modP = document.getElementById('modSelectPartita');
const modForm = document.getElementById('modFormDati');
const torneoHidden = document.getElementById('torneoHiddenMod');

modT.addEventListener('change', async() => {
    torneoHidden.value = modT.value;

    const res = await fetch(`/torneioldschool/api/get_giornate.php?torneo=${modT.value}`);
    const giornate = await res.json();

    modG.innerHTML = '<option>-- Seleziona giornata --</option>';
    giornate.forEach(g => modG.innerHTML += `<option value="${g}">${g}</option>`);
    modG.disabled = false;
});

modG.addEventListener('change', async() => {
    const res = await fetch(`/torneioldschool/api/get_partite_by_giornata.php?torneo=${modT.value}&giornata=${modG.value}`);
    const partite = await res.json();

    modP.innerHTML = '<option>-- Seleziona partita --</option>';
    partite.forEach(p => modP.innerHTML += `<option value="${p.id}">${p.squadra_casa} - ${p.squadra_ospite}</option>`);

    modP.disabled = false;
});

modP.addEventListener('change', async() => {

    const id = modP.value;
    if (!id) return;

    // CARICA DETTAGLI PARTITA
    const res = await fetch(`/torneioldschool/api/get_partita.php?id=${id}`);
    const p = await res.json();

    modForm.classList.remove('hidden');
    document.getElementById('mod_gol_casa').value = p.gol_casa;
    document.getElementById('mod_gol_ospite').value = p.gol_ospite;
    document.getElementById('mod_data_partita').value = p.data_partita;
    document.getElementById('mod_ora_partita').value = p.ora_partita;
    document.getElementById('mod_campo').value = p.campo;
    document.getElementById('mod_giornata').value = p.giornata;

    await caricaSquadre(p.torneo, 'squadraCasaMod', 'squadraOspiteMod', p.squadra_casa, p.squadra_ospite);

    // ===== MOSTRA BOTTONE STATISTICHE QUI 🚀 =====
    const btn = document.getElementById("btn_statistiche");
    btn.style.display = "block";
    btn.onclick = () => {
        window.location.href = `/torneioldschool/api/statistiche_partita.php?partitaid=${id}`;
    };
});

});
</script>
<script src="/torneioldschool/includi/delete-modal.js"></script>

</body>
</html>
