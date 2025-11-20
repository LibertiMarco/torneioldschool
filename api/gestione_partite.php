<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
  header("Location: /torneioldschool/index.php");
  exit;
}

require_once __DIR__ . '/../includi/db.php';

$errore = '';
$successo = '';
$torneiDisponibili = [];
$squadrePerTorneo = [];
$fasiAmmesse = ['REGULAR', 'GOLD', 'SILVER'];
$roundMap = [
  'TRENTADUESIMI' => 6,
  'SEDICESIMI' => 5,
  'OTTAVI' => 4,
  'QUARTI' => 3,
  'SEMIFINALE' => 2,
  'FINALE' => 1,
];

$torneiRes = $conn->query("SELECT nome, filetorneo FROM tornei WHERE stato <> 'terminato' ORDER BY nome ASC");
if ($torneiRes) {
  while ($row = $torneiRes->fetch_assoc()) {
    $slug = preg_replace('/\.html$/i', '', $row['filetorneo'] ?? '');
    $torneiDisponibili[] = [
      'nome' => $row['nome'] ?: $slug,
      'slug' => $slug,
    ];
  }
}

if (!empty($torneiDisponibili)) {
  $slugs = array_column($torneiDisponibili, 'slug');
  $placeholders = implode(',', array_fill(0, count($slugs), '?'));
  $types = str_repeat('s', count($slugs));
  $sq = $conn->prepare("SELECT nome, torneo FROM squadre WHERE torneo IN ($placeholders) ORDER BY nome ASC");
  if ($sq) {
    $sq->bind_param($types, ...$slugs);
    $sq->execute();
    $resSq = $sq->get_result();
    while ($r = $resSq->fetch_assoc()) {
      $slug = $r['torneo'] ?? '';
      if (!isset($squadrePerTorneo[$slug])) {
        $squadrePerTorneo[$slug] = [];
      }
      $squadrePerTorneo[$slug][] = $r['nome'];
    }
    $sq->close();
  }
}

function sanitize_text(?string $v): string {
  return trim((string)$v);
}

function sanitize_int(?string $v): int {
  return (int)($v === '' || $v === null ? 0 : $v);
}

function sanitize_fase(?string $v, array $allowed): string {
  $val = strtoupper(trim((string)$v));
  return in_array($val, $allowed, true) ? $val : 'REGULAR';
}

function round_to_giornata(?string $roundLabel, array $map): ?int {
  if ($roundLabel === null) return null;
  $key = strtoupper(trim($roundLabel));
  return $map[$key] ?? null;
}

function giornata_to_roundLabel(?int $giornata, array $map): ?string {
  if ($giornata === null) return null;
  $flip = array_flip($map);
  return $flip[$giornata] ?? null;
}

// ===== OPERAZIONI CRUD =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $azione = $_POST['azione'] ?? '';

  if ($azione === 'crea') {
    $torneo = sanitize_text($_POST['torneo'] ?? '');
    $fase = sanitize_fase($_POST['fase'] ?? '', $fasiAmmesse);
    $casa = sanitize_text($_POST['squadra_casa'] ?? '');
    $ospite = sanitize_text($_POST['squadra_ospite'] ?? '');
    $data = sanitize_text($_POST['data_partita'] ?? '');
    $ora = sanitize_text($_POST['ora_partita'] ?? '');
    $campo = sanitize_text($_POST['campo'] ?? '');
    $roundSelezionato = sanitize_text($_POST['round_eliminazione'] ?? '');
    $giornata = sanitize_int($_POST['giornata'] ?? '');
    $giocata = isset($_POST['giocata']) ? 1 : 0;
    $gol_casa = sanitize_int($_POST['gol_casa'] ?? '0');
    $gol_ospite = sanitize_int($_POST['gol_ospite'] ?? '0');
    $link_youtube = sanitize_text($_POST['link_youtube'] ?? '');
    $link_instagram = sanitize_text($_POST['link_instagram'] ?? '');

    if ($torneo === '' || $fase === '' || $casa === '' || $ospite === '' || $data === '' || $ora === '' || $campo === '' || ($fase === 'REGULAR' && $giornata <= 0) || ($fase !== 'REGULAR' && $roundSelezionato === '')) {
      $errore = 'Compila tutti i campi obbligatori.';
    } elseif ($casa === $ospite) {
      $errore = 'Le due squadre non possono coincidere.';
    } else {
      if ($fase !== 'REGULAR') {
        $giornata = round_to_giornata($roundSelezionato, $roundMap) ?? 0;
      }
      $stmt = $conn->prepare("INSERT INTO partite (torneo, fase, squadra_casa, squadra_ospite, gol_casa, gol_ospite, data_partita, ora_partita, campo, giornata, giocata, link_youtube, link_instagram, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
      if ($stmt) {
        $stmt->bind_param(
          'ssssiisssiiss',
          $torneo,
          $fase,
          $casa,
          $ospite,
          $gol_casa,
          $gol_ospite,
          $data,
          $ora,
          $campo,
          $giornata,
          $giocata,
          $link_youtube,
          $link_instagram
        );
        if ($stmt->execute()) {
          $successo = 'Partita creata correttamente.';
        } else {
          $errore = 'Inserimento non riuscito.';
        }
        $stmt->close();
      } else {
        $errore = 'Errore interno durante la creazione.';
      }
    }
  }

  if ($azione === 'modifica') {
    $id = (int)($_POST['partita_id'] ?? 0);
    $torneo = sanitize_text($_POST['torneo_mod'] ?? '');
    $fase = sanitize_fase($_POST['fase_mod'] ?? '', $fasiAmmesse);
    $casa = sanitize_text($_POST['squadra_casa_mod'] ?? '');
    $ospite = sanitize_text($_POST['squadra_ospite_mod'] ?? '');
    $data = sanitize_text($_POST['data_partita_mod'] ?? '');
    $ora = sanitize_text($_POST['ora_partita_mod'] ?? '');
    $campo = sanitize_text($_POST['campo_mod'] ?? '');
    $roundSelezionato = sanitize_text($_POST['round_eliminazione_mod'] ?? '');
    $giornata = sanitize_int($_POST['giornata_mod'] ?? '');
    $giocata = isset($_POST['giocata_mod']) ? 1 : 0;
    $gol_casa = sanitize_int($_POST['gol_casa_mod'] ?? '0');
    $gol_ospite = sanitize_int($_POST['gol_ospite_mod'] ?? '0');
    $link_youtube = sanitize_text($_POST['link_youtube_mod'] ?? '');
    $link_instagram = sanitize_text($_POST['link_instagram_mod'] ?? '');

    if ($id <= 0 || $torneo === '' || $fase === '' || $casa === '' || $ospite === '' || $data === '' || $ora === '' || $campo === '' || ($fase === 'REGULAR' && $giornata <= 0) || ($fase !== 'REGULAR' && $roundSelezionato === '')) {
      $errore = 'Seleziona una partita e compila i campi obbligatori.';
    } elseif ($casa === $ospite) {
      $errore = 'Le due squadre non possono coincidere.';
    } else {
      if ($fase !== 'REGULAR') {
        $giornata = round_to_giornata($roundSelezionato, $roundMap) ?? 0;
      }
      $stmt = $conn->prepare("UPDATE partite SET torneo=?, fase=?, squadra_casa=?, squadra_ospite=?, gol_casa=?, gol_ospite=?, data_partita=?, ora_partita=?, campo=?, giornata=?, giocata=?, link_youtube=?, link_instagram=? WHERE id=?");
      if ($stmt) {
        $stmt->bind_param(
          'ssssiisssiissi',
          $torneo,
          $fase,
          $casa,
          $ospite,
          $gol_casa,
          $gol_ospite,
          $data,
          $ora,
          $campo,
          $giornata,
          $giocata,
          $link_youtube,
          $link_instagram,
          $id
        );
        if ($stmt->execute()) {
          $successo = 'Partita aggiornata correttamente.';
        } else {
          $errore = 'Aggiornamento non riuscito.';
        }
        $stmt->close();
      } else {
        $errore = 'Errore interno durante l\'aggiornamento.';
      }
    }
  }

  if ($azione === 'elimina') {
    $id = (int)($_POST['partita_id'] ?? 0);
    if ($id <= 0) {
      $errore = 'Seleziona una partita valida da eliminare.';
    } else {
      $stmt = $conn->prepare("DELETE FROM partite WHERE id=?");
      if ($stmt) {
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
          $successo = 'Partita eliminata.';
        } else {
          $errore = 'Eliminazione non riuscita.';
        }
        $stmt->close();
      } else {
        $errore = 'Errore interno durante l\'eliminazione.';
      }
    }
  }
}

$partite = [];
$res = $conn->query("SELECT id, torneo, fase, squadra_casa, squadra_ospite, gol_casa, gol_ospite, data_partita, ora_partita, campo, giornata, giocata, link_youtube, link_instagram, created_at FROM partite ORDER BY data_partita DESC, ora_partita DESC, id DESC");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $partite[] = $row;
  }
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
    body { display: flex; flex-direction: column; min-height: 100vh; background: #f7f9fc; }
    main.admin-wrapper { flex: 1 0 auto; }
    .tab-buttons { display: flex; gap: 12px; margin: 10px 0 20px; flex-wrap: wrap; }
    .tab-buttons button { padding: 12px 16px; border: 1px solid #cbd5e1; background: #ecf1f7; cursor: pointer; border-radius: 10px; font-weight: 600; color: #1c2a3a; box-shadow: 0 2px 6px rgba(0,0,0,0.04); transition: all .2s; }
    .tab-buttons button:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
    .tab-buttons button.active { background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border-color: #15293e; box-shadow: 0 8px 20px rgba(21,41,62,0.25); }

    .form-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 18px 18px 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .form-card h3 { margin: 0 0 14px; color: #15293e; font-size: 1.1rem; }

    .admin-form.inline { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px 18px; }
    .admin-form.inline .full { grid-column: 1 / -1; }
    .admin-form.inline label { font-weight: 600; color: #1c2a3a; }
    .admin-form.inline input,
    .admin-form.inline select { border-radius: 10px; border: 1px solid #d5dbe4; background: #fafbff; transition: border-color .2s, box-shadow .2s; width: 100%; display: block; }
    .admin-form.inline input:focus,
    .admin-form.inline select:focus { border-color: #15293e; box-shadow: 0 0 0 3px rgba(21,41,62,0.15); outline: none; }

    .table-scroll { overflow-x: auto; background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
    #footer-container { margin-top: auto; padding-top: 40px; }

    .modern-danger { background: linear-gradient(135deg, #d72638, #b1172a); border: none; color: #fff; padding: 12px 18px; border-radius: 12px; box-shadow: 0 10px 25px rgba(183, 23, 42, 0.3); transition: transform .15s, box-shadow .15s; font-weight: 700; letter-spacing: 0.2px; }
    .modern-danger:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(183, 23, 42, 0.4); }

    .confirm-modal {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(0,0,0,0.45);
      backdrop-filter: blur(2px);
      z-index: 9999;
    }
    .confirm-modal.active { display: flex; }
    .confirm-card {
      background: #fff;
      border-radius: 14px;
      padding: 22px;
      width: min(420px, 90vw);
      box-shadow: 0 18px 34px rgba(0,0,0,0.15);
      border: 1px solid #e5eaf0;
    }
    .confirm-card h4 { margin: 0 0 8px; color: #15293e; }
    .confirm-card p { margin: 0 0 16px; color: #345; }
    .confirm-actions { display: flex; gap: 12px; justify-content: center; }
    .confirm-actions button { flex: 1 1 0; min-width: 140px; text-align: center; }
    .btn-ghost { border: 1px solid #d5dbe4; background: #fff; color: #1c2a3a; border-radius: 10px; padding: 12px 14px; cursor: pointer; font-weight: 700; }
    .btn-ghost:hover { border-color: #15293e; color: #15293e; }
    .btn-secondary-modern { border: 1px solid #cbd5e1; background: #f5f7fb; color: #15293e; border-radius: 10px; padding: 10px 14px; font-weight: 700; box-shadow: 0 6px 14px rgba(0,0,0,0.08); transition: transform .15s, box-shadow .15s; }
    .btn-secondary-modern:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(0,0,0,0.12); }
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
    <?php if ($successo): ?>
      <div class="alert-success"><?= htmlspecialchars($successo) ?></div>
    <?php endif; ?>

    <div class="tab-buttons">
      <button type="button" data-tab="crea" class="active">Crea</button>
      <button type="button" data-tab="modifica">Modifica</button>
      <button type="button" data-tab="elimina">Elimina</button>
    </div>

    <!-- CREA -->
    <section class="tab-section active" data-tab="crea">
      <div class="form-card">
        <h3>Crea partita</h3>
        <form class="admin-form inline" method="POST">
        <input type="hidden" name="azione" value="crea">
        <div>
          <label>Torneo</label>
          <select name="torneo" id="torneoCrea" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Fase</label>
          <select name="fase" required>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Squadra casa</label>
          <select name="squadra_casa" id="squadraCasaCrea" required>
            <option value="">-- Seleziona torneo prima --</option>
          </select>
        </div>
        <div>
          <label>Squadra ospite</label>
          <select name="squadra_ospite" id="squadraOspiteCrea" required>
            <option value="">-- Seleziona torneo prima --</option>
          </select>
        </div>
        <input type="hidden" name="gol_casa" value="0">
        <input type="hidden" name="gol_ospite" value="0">
        <div>
          <label>Data</label>
          <input type="date" name="data_partita" required>
        </div>
        <div>
          <label>Ora</label>
          <input type="time" name="ora_partita" required>
        </div>
        <div>
          <label>Campo</label>
          <select name="campo" required>
            <option value="">-- Seleziona campo --</option>
            <option value="Sporting Club San Francesco, Napoli">Sporting Club San Francesco, Napoli</option>
            <option value="Centro Sportivo La Paratina, Napoli">Centro Sportivo La Paratina, Napoli</option>
            <option value="Sporting S.Antonio, Napoli">Sporting S.Antonio, Napoli</option>
            <option value="La Boutique del Calcio, Napoli">La Boutique del Calcio, Napoli</option>
            <option value="Campo Centrale del Parco Corto Maltese, Napoli">Campo Centrale del Parco Corto Maltese, Napoli</option>
          </select>
        </div>
        <div id="giornataWrapper">
          <label>Giornata</label>
          <input type="number" name="giornata" id="giornataCrea" min="1" required>
        </div>
        <div id="roundWrapper" class="hidden">
          <label>Fase eliminazione</label>
          <select name="round_eliminazione" id="roundCrea">
            <option value="">-- Seleziona fase --</option>
            <option value="TRENTADUESIMI">Trentaduesimi di finale</option>
            <option value="SEDICESIMI">Sedicesimi di finale</option>
            <option value="OTTAVI">Ottavi di finale</option>
            <option value="QUARTI">Quarti di finale</option>
            <option value="SEMIFINALE">Semifinale</option>
            <option value="FINALE">Finale</option>
          </select>
        </div>
        <input type="hidden" name="giocata" value="0">
        <input type="hidden" name="link_youtube" value="">
        <input type="hidden" name="link_instagram" value="">
        <div class="full">
          <button type="submit" class="btn-primary">Crea partita</button>
        </div>
        </form>
      </div>
    </section>

    <!-- MODIFICA -->
    <section class="tab-section" data-tab="modifica">
      <div class="form-card">
        <h3>Modifica partita</h3>
        <form class="admin-form inline" method="POST" id="formModifica">
        <input type="hidden" name="azione" value="modifica">
        <div class="full">
          <label>Seleziona torneo</label>
          <select id="selTorneoMod" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Fase</label>
          <select id="selFaseMod" required>
            <option value="">-- Seleziona fase --</option>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Giornata / Turno</label>
          <select id="selGiornataMod" required disabled>
            <option value="">-- Seleziona fase --</option>
          </select>
        </div>
        <div>
          <label>Partita</label>
          <select name="partita_id" id="selPartitaMod" required disabled>
            <option value="">-- Seleziona giornata/turno --</option>
          </select>
        </div>
        <div class="full">
          <button type="button" id="btnStatsMod" class="btn-secondary-modern" style="display:none;">Statistiche partita</button>
        </div>
        <div>
          <label>Torneo</label>
          <select name="torneo_mod" id="torneo_mod" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Fase</label>
          <select name="fase_mod" id="fase_mod" required>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Squadra casa</label>
          <select name="squadra_casa_mod" id="squadra_casa_mod" required>
            <option value="">-- Seleziona torneo prima --</option>
          </select>
        </div>
        <div>
          <label>Squadra ospite</label>
          <select name="squadra_ospite_mod" id="squadra_ospite_mod" required>
            <option value="">-- Seleziona torneo prima --</option>
          </select>
        </div>
        <div>
          <label>Gol casa</label>
          <input type="number" name="gol_casa_mod" id="gol_casa_mod" min="0">
        </div>
        <div>
          <label>Gol ospite</label>
          <input type="number" name="gol_ospite_mod" id="gol_ospite_mod" min="0">
        </div>
        <div>
          <label>Data</label>
          <input type="date" name="data_partita_mod" id="data_partita_mod" required>
        </div>
        <div>
          <label>Ora</label>
          <input type="time" name="ora_partita_mod" id="ora_partita_mod" required>
        </div>
        <div>
          <label>Campo</label>
          <select name="campo_mod" id="campo_mod" required>
            <option value="">-- Seleziona campo --</option>
            <option value="Sporting Club San Francesco, Napoli">Sporting Club San Francesco, Napoli</option>
            <option value="Centro Sportivo La Paratina, Napoli">Centro Sportivo La Paratina, Napoli</option>
            <option value="Sporting S.Antonio, Napoli">Sporting S.Antonio, Napoli</option>
            <option value="La Boutique del Calcio, Napoli">La Boutique del Calcio, Napoli</option>
            <option value="Campo Centrale del Parco Corto Maltese, Napoli">Campo Centrale del Parco Corto Maltese, Napoli</option>
          </select>
        </div>
        <div id="giornataWrapperMod">
          <label>Giornata</label>
          <input type="number" name="giornata_mod" id="giornata_mod" min="1" required>
        </div>
        <div id="roundWrapperMod" class="hidden">
          <label>Fase eliminazione</label>
          <select name="round_eliminazione_mod" id="round_eliminazione_mod">
            <option value="">-- Seleziona fase --</option>
            <option value="TRENTADUESIMI">Trentaduesimi di finale</option>
            <option value="SEDICESIMI">Sedicesimi di finale</option>
            <option value="OTTAVI">Ottavi di finale</option>
            <option value="QUARTI">Quarti di finale</option>
            <option value="SEMIFINALE">Semifinale</option>
            <option value="FINALE">Finale</option>
          </select>
        </div>
        <div>
          <label>Giocata</label>
          <input type="checkbox" name="giocata_mod" id="giocata_mod" value="1">
        </div>
        <div>
          <label>Link YouTube</label>
          <input type="url" name="link_youtube_mod" id="link_youtube_mod" placeholder="https://youtube.com/...">
        </div>
        <div>
          <label>Link Instagram</label>
          <input type="url" name="link_instagram_mod" id="link_instagram_mod" placeholder="https://instagram.com/...">
        </div>
        <div class="full">
          <button type="submit" class="btn-primary">Salva modifiche</button>
        </div>
        </form>
      </div>
    </section>

    <!-- ELIMINA -->
    <section class="tab-section" data-tab="elimina">
      <div class="form-card">
        <h3>Elimina partita</h3>
        <form method="POST" class="admin-form" id="formElimina">
        <input type="hidden" name="azione" value="elimina">
        <input type="hidden" name="partita_id" id="partitaEliminaHidden">
        <div>
          <label>Torneo</label>
          <select id="selTorneoElim" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Fase</label>
          <select id="selFaseElim" required>
            <option value="">-- Seleziona fase --</option>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Giornata / Turno</label>
          <select id="selGiornataElim" required disabled>
            <option value="">-- Seleziona fase --</option>
          </select>
        </div>
        <div>
          <label>Partita</label>
          <select id="selPartitaElim" required disabled>
            <option value="">-- Seleziona giornata/turno --</option>
          </select>
        </div>
        <button type="button" id="btnApriConfermaElimina" class="btn-danger modern-danger">Elimina partita</button>
        </form>
      </div>
    </section>

  </section>
</main>

<div id="footer-container"></div>

<div class="confirm-modal" id="modalElimina">
    <div class="confirm-card">
      <h4>Conferma eliminazione</h4>
      <p id="modalEliminaTesto">Sei sicuro di voler eliminare questa partita?</p>
      <div class="confirm-actions">
        <button type="button" class="btn-ghost" id="btnAnnullaElimina">Annulla</button>
        <button type="button" class="modern-danger" id="btnConfermaElimina">Elimina</button>
      </div>
    </div>
  </div>

<script>
  const partiteData = <?php echo json_encode($partite, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  const squadreMap = <?php echo json_encode($squadrePerTorneo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  const roundLabelMap = {
    'TRENTADUESIMI': 6,
    'SEDICESIMI': 5,
    'OTTAVI': 4,
    'QUARTI': 3,
    'SEMIFINALE': 2,
    'FINALE': 1,
  };
  const roundLabelFromGiornata = Object.fromEntries(Object.entries(roundLabelMap).map(([k,v]) => [String(v), k]));
  const roundLabelByKey = roundLabelFromGiornata;

  // Tabs
  const tabButtons = document.querySelectorAll('.tab-buttons button');
  const tabSections = document.querySelectorAll('.tab-section');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      tabSections.forEach(sec => sec.classList.remove('active'));
      btn.classList.add('active');
      const target = btn.dataset.tab;
      const section = document.querySelector(`.tab-section[data-tab="${target}"]`);
      if (section) section.classList.add('active');
    });
  });

  const populateSquadre = (torneoSlug, selectId, selectedValue = '') => {
    const select = document.getElementById(selectId);
    if (!select) return;
    select.innerHTML = '<option value=\"\">-- Seleziona --</option>';
    const lista = squadreMap[torneoSlug] || [];
    lista.forEach(nome => {
      const opt = new Option(nome, nome);
      select.add(opt);
    });
    if (selectedValue) select.value = selectedValue;
  };

  const enforceDifferentTeams = (idA, idB) => {
    const a = document.getElementById(idA);
    const b = document.getElementById(idB);
    if (!a || !b) return;
    a.addEventListener('change', () => {
      if (a.value && a.value === b.value) {
        b.value = '';
      }
    });
    b.addEventListener('change', () => {
      if (a.value && a.value === b.value) {
        b.value = '';
      }
    });
  };

  const torneoCrea = document.getElementById('torneoCrea');
  if (torneoCrea) {
    torneoCrea.addEventListener('change', () => {
      populateSquadre(torneoCrea.value, 'squadraCasaCrea');
      populateSquadre(torneoCrea.value, 'squadraOspiteCrea');
    });
  }
  enforceDifferentTeams('squadraCasaCrea', 'squadraOspiteCrea');

  const toggleRoundGiornata = (faseSelect, giornataWrapId, roundWrapId) => {
    const isRegular = (faseSelect.value || '').toUpperCase() === 'REGULAR';
    const giornataWrap = document.getElementById(giornataWrapId);
    const roundWrap = document.getElementById(roundWrapId);
    if (giornataWrap) giornataWrap.classList.toggle('hidden', !isRegular);
    if (roundWrap) roundWrap.classList.toggle('hidden', isRegular);
    const giornataInput = giornataWrap ? giornataWrap.querySelector('input') : null;
    const roundSelect = roundWrap ? roundWrap.querySelector('select') : null;
    if (giornataInput) {
      giornataInput.required = isRegular;
      if (!isRegular) giornataInput.value = '';
    }
    if (roundSelect) {
      roundSelect.required = !isRegular;
      if (isRegular) roundSelect.value = '';
    }
  };

  const faseCrea = document.querySelector('select[name="fase"]');
  if (faseCrea) {
    toggleRoundGiornata(faseCrea, 'giornataWrapper', 'roundWrapper');
    faseCrea.addEventListener('change', () => toggleRoundGiornata(faseCrea, 'giornataWrapper', 'roundWrapper'));
  }

  const fillField = (id, val) => { const el = document.getElementById(id); if (el) { if (el.type === 'checkbox') { el.checked = !!val; } else { el.value = val ?? ''; } } };

  const setupSelector = ({ torneoId, faseId, giornataId, partitaId, onPartita }) => {
    const torneoSel = document.getElementById(torneoId);
    const faseSel = document.getElementById(faseId);
    const giorSel = document.getElementById(giornataId);
    const partSel = document.getElementById(partitaId);

    const resetSelect = (sel, placeholder) => {
      if (!sel) return;
      sel.innerHTML = `<option value=\"\">${placeholder}</option>`;
      sel.disabled = true;
    };

    const populateGiornate = () => {
      if (!torneoSel || !faseSel || !giorSel) return;
      resetSelect(giorSel, '-- Seleziona fase --');
      resetSelect(partSel, '-- Seleziona giornata/turno --');
      const torneoVal = torneoSel.value;
      const faseVal = (faseSel.value || '').toUpperCase();
      if (!torneoVal || !faseVal) return;
      const filtrate = partiteData.filter(p =>
        p.torneo === torneoVal && (p.fase || '').toUpperCase() === faseVal
      );
      const uniche = Array.from(new Set(filtrate.map(p => p.giornata === null ? '' : String(p.giornata))));
      uniche.sort((a, b) => Number(a) - Number(b));
      uniche.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g;
        if (faseVal === 'REGULAR') {
          opt.textContent = `Giornata ${g}`;
        } else {
          opt.textContent = roundLabelByKey[String(g)] || 'Turno';
        }
        giorSel.appendChild(opt);
      });
      giorSel.disabled = uniche.length === 0;
      partSel.disabled = true;
    };

    const populatePartite = () => {
      if (!torneoSel || !faseSel || !giorSel || !partSel) return;
      resetSelect(partSel, '-- Seleziona giornata/turno --');
      const torneoVal = torneoSel.value;
      const faseVal = (faseSel.value || '').toUpperCase();
      const gVal = giorSel.value;
      if (!torneoVal || !faseVal || gVal === '') return;
      const filtrate = partiteData.filter(p =>
        p.torneo === torneoVal &&
        (p.fase || '').toUpperCase() === faseVal &&
        String(p.giornata ?? '') === gVal
      );
      filtrate.forEach(p => {
        const label = `${p.squadra_casa} - ${p.squadra_ospite} (${p.data_partita} ${p.ora_partita ?? ''})`;
        const opt = new Option(label, p.id);
        partSel.add(opt);
      });
      partSel.disabled = filtrate.length === 0;
    };

    torneoSel?.addEventListener('change', populateGiornate);
    faseSel?.addEventListener('change', populateGiornate);
    giorSel?.addEventListener('change', populatePartite);
    partSel?.addEventListener('change', () => {
      const id = parseInt(partSel.value, 10);
      const partita = partiteData.find(p => parseInt(p.id, 10) === id);
      onPartita?.(partita || null);
    });
  };

  setupSelector({
    torneoId: 'selTorneoMod',
    faseId: 'selFaseMod',
    giornataId: 'selGiornataMod',
    partitaId: 'selPartitaMod',
    onPartita: (partita) => {
      if (!partita) return;
      const torneoMod = document.getElementById('torneo_mod');
      if (torneoMod) {
        if (![...torneoMod.options].some(o => o.value === partita.torneo)) {
          const opt = new Option(partita.torneo, partita.torneo, true, true);
          torneoMod.add(opt);
        }
        torneoMod.value = partita.torneo;
        populateSquadre(partita.torneo, 'squadra_casa_mod', partita.squadra_casa);
        populateSquadre(partita.torneo, 'squadra_ospite_mod', partita.squadra_ospite);
      }
      fillField('fase_mod', partita.fase);
      const faseModSelect = document.getElementById('fase_mod');
      if (faseModSelect) toggleRoundGiornata(faseModSelect, 'giornataWrapperMod', 'roundWrapperMod');
      if (partita.fase && partita.fase.toUpperCase() !== 'REGULAR') {
        const lbl = roundLabelFromGiornata[String(partita.giornata)] || '';
        const roundSel = document.getElementById('round_eliminazione_mod');
        if (roundSel) roundSel.value = lbl;
        const giornataInput = document.getElementById('giornata_mod');
        if (giornataInput) giornataInput.value = '';
      } else {
        const roundSel = document.getElementById('round_eliminazione_mod');
        if (roundSel) roundSel.value = '';
        fillField('giornata_mod', partita.giornata);
      }
      fillField('gol_casa_mod', partita.gol_casa);
      fillField('gol_ospite_mod', partita.gol_ospite);
      fillField('data_partita_mod', partita.data_partita);
      fillField('ora_partita_mod', partita.ora_partita);
      fillField('campo_mod', partita.campo);
      fillField('giocata_mod', Number(partita.giocata) === 1);
      fillField('link_youtube_mod', partita.link_youtube);
      fillField('link_instagram_mod', partita.link_instagram);
    }
  });
  enforceDifferentTeams('squadra_casa_mod', 'squadra_ospite_mod');

  const faseModSelect = document.getElementById('fase_mod');
  if (faseModSelect) {
    faseModSelect.addEventListener('change', () => toggleRoundGiornata(faseModSelect, 'giornataWrapperMod', 'roundWrapperMod'));
    toggleRoundGiornata(faseModSelect, 'giornataWrapperMod', 'roundWrapperMod');
  }

  setupSelector({
    torneoId: 'selTorneoElim',
    faseId: 'selFaseElim',
    giornataId: 'selGiornataElim',
    partitaId: 'selPartitaElim',
    onPartita: (partita) => {
      const hidden = document.getElementById('partitaEliminaHidden');
      if (hidden) hidden.value = partita ? partita.id : '';
      const testo = document.getElementById('modalEliminaTesto');
      if (testo) {
        if (partita) {
          testo.textContent = `Eliminare ${partita.squadra_casa} - ${partita.squadra_ospite} (${partita.data_partita} ${partita.ora_partita ?? ''})?`;
        } else {
          testo.textContent = 'Sei sicuro di voler eliminare questa partita?';
        }
      }
    }
  });

  const modal = document.getElementById('modalElimina');
  const btnApri = document.getElementById('btnApriConfermaElimina');
  const btnChiudi = document.getElementById('btnAnnullaElimina');
  const btnConferma = document.getElementById('btnConfermaElimina');
  const formElim = document.getElementById('formElimina');
  btnApri?.addEventListener('click', () => {
    if (modal) modal.classList.add('active');
  });
  btnChiudi?.addEventListener('click', () => modal?.classList.remove('active'));
  btnConferma?.addEventListener('click', () => {
    if (formElim) formElim.submit();
  });
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) modal.classList.remove('active');
  });

  // Footer
  const footer = document.getElementById('footer-container');
  if (footer) {
    fetch('/torneioldschool/includi/footer.html')
      .then(r => r.text())
      .then(html => { footer.innerHTML = html; })
      .catch(err => console.error('Errore footer:', err));
  }
</script>

</body>
</html>
