<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
  header("Location: /torneioldschool/index.php");
  exit;
}

require_once __DIR__ . '/crud/partita.php';
require_once __DIR__ . '/crud/torneo.php';

$partita = new Partita();
$torneoRepo = new Torneo();

$fasiDisponibili = ['REGULAR', 'GOLD', 'SILVER'];
$fasiEliminazione = ['OTTAVI', 'QUARTI', 'SEMIFINALE', 'FINALE'];
$tipiAndataRitorno = ['ANDATA', 'RITORNO', 'UNICA'];

$errore = '';
$successo = '';

if (isset($_SESSION['flash_successo'])) {
  $successo = $_SESSION['flash_successo'];
  unset($_SESSION['flash_successo']);
}

function sanitizeFase(?string $value, array $allowed): string {
  $fase = strtoupper(trim((string)$value));
  return in_array($fase, $allowed, true) ? $fase : 'REGULAR';
}

function sanitizeEnum(?string $value, array $allowed): ?string {
  $val = strtoupper(trim((string)$value));
  return in_array($val, $allowed, true) ? $val : null;
}

function setSuccessAndRedirect(string $message): void {
  $_SESSION['flash_successo'] = $message;
  header('Location: gestione_partite.php');
  exit;
}

/* ============================================================
   CREA PARTITA
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
  $dati = [
    'torneo'         => trim($_POST['torneo'] ?? ''),
    'fase'           => sanitizeFase($_POST['fase'] ?? 'REGULAR', $fasiDisponibili),
    'squadra_casa'   => trim($_POST['squadra_casa'] ?? ''),
    'squadra_ospite' => trim($_POST['squadra_ospite'] ?? ''),
    'gol_casa'       => (int)($_POST['gol_casa'] ?? 0),
    'gol_ospite'     => (int)($_POST['gol_ospite'] ?? 0),
    'data_partita'   => trim($_POST['data_partita'] ?? ''),
    'ora_partita'    => trim($_POST['ora_partita'] ?? ''),
    'campo'          => trim($_POST['campo'] ?? ''),
    'giornata'       => (($_POST['giornata'] ?? '') !== '' ? (int)$_POST['giornata'] : null),
    'fase_round'     => sanitizeEnum($_POST['fase_round'] ?? null, $fasiEliminazione),
    'fase_leg'       => sanitizeEnum($_POST['fase_leg'] ?? null, $tipiAndataRitorno),
    'link_youtube'   => trim($_POST['link_youtube'] ?? ''),
    'link_instagram' => trim($_POST['link_instagram'] ?? ''),
  ];

  if (
    $dati['torneo'] === '' || $dati['squadra_casa'] === '' || $dati['squadra_ospite'] === '' ||
    $dati['data_partita'] === '' || $dati['ora_partita'] === '' || $dati['campo'] === ''
  ) {
    $errore = 'Compila tutti i campi obbligatori.';
  } elseif ($dati['squadra_casa'] === $dati['squadra_ospite']) {
    $errore = 'Le due squadre non possono coincidere.';
  }

  if ($errore === '') {
    if ($dati['fase'] === 'REGULAR') {
      if ($dati['giornata'] === null || $dati['giornata'] <= 0) {
        $errore = 'La giornata è obbligatoria per la Regular Season.';
      }
      $dati['fase_round'] = null;
      $dati['fase_leg'] = null;
    } else {
      $dati['giornata'] = null;
      if (!$dati['fase_round'] || !$dati['fase_leg']) {
        $errore = 'Seleziona fase eliminazione e tipologia per GOLD/SILVER.';
      }
    }
  }

  if ($errore === '') {
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
      $dati['fase_leg'],
      $dati['link_youtube'] !== '' ? $dati['link_youtube'] : null,
      $dati['link_instagram'] !== '' ? $dati['link_instagram'] : null
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

    setSuccessAndRedirect('Partita creata con successo.');
  }
}

/* ============================================================
   AGGIORNA PARTITA
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
  $id = (int)($_POST['id'] ?? 0);
  $vecchia = $partita->getById($id);

  if (!$vecchia) {
    $errore = 'Partita non trovata.';
  } else {
    $dati = [
      'torneo'         => trim($_POST['torneo'] ?? ''),
      'fase'           => sanitizeFase($_POST['fase'] ?? 'REGULAR', $fasiDisponibili),
      'squadra_casa'   => trim($_POST['squadra_casa'] ?? ''),
      'squadra_ospite' => trim($_POST['squadra_ospite'] ?? ''),
      'gol_casa'       => (int)($_POST['gol_casa'] ?? 0),
      'gol_ospite'     => (int)($_POST['gol_ospite'] ?? 0),
      'data_partita'   => trim($_POST['data_partita'] ?? ''),
      'ora_partita'    => trim($_POST['ora_partita'] ?? ''),
      'campo'          => trim($_POST['campo'] ?? ''),
      'giornata'       => (($_POST['giornata'] ?? '') !== '' ? (int)$_POST['giornata'] : null),
      'fase_round'     => sanitizeEnum($_POST['fase_round'] ?? null, $fasiEliminazione),
      'fase_leg'       => sanitizeEnum($_POST['fase_leg'] ?? null, $tipiAndataRitorno),
      'link_youtube'   => trim($_POST['link_youtube'] ?? ''),
      'link_instagram' => trim($_POST['link_instagram'] ?? ''),
    ];

    if (
      $dati['torneo'] === '' || $dati['squadra_casa'] === '' || $dati['squadra_ospite'] === '' ||
      $dati['data_partita'] === '' || $dati['ora_partita'] === '' || $dati['campo'] === ''
    ) {
      $errore = 'Compila tutti i campi obbligatori.';
    } elseif ($dati['squadra_casa'] === $dati['squadra_ospite']) {
      $errore = 'Le due squadre non possono coincidere.';
    }

    if ($errore === '') {
      if ($dati['fase'] === 'REGULAR') {
        if ($dati['giornata'] === null || $dati['giornata'] <= 0) {
          $errore = 'La giornata è obbligatoria per la Regular Season.';
        }
        $dati['fase_round'] = null;
        $dati['fase_leg'] = null;
      } else {
        $dati['giornata'] = null;
        if (!$dati['fase_round'] || !$dati['fase_leg']) {
          $errore = 'Seleziona fase eliminazione e tipologia per GOLD/SILVER.';
        }
      }
    }

    if ($errore === '') {
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
        $dati['fase_leg'],
        $dati['link_youtube'] !== '' ? $dati['link_youtube'] : null,
        $dati['link_instagram'] !== '' ? $dati['link_instagram'] : null
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

      setSuccessAndRedirect('Partita aggiornata correttamente.');
    }
  }
}

/* ============================================================
   ELIMINA PARTITA
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elimina'])) {
  $id = (int)($_POST['id_da_eliminare'] ?? 0);
  $vecchia = $partita->getById($id);

  if (!$vecchia) {
    $errore = 'Partita non trovata.';
  } else {
    $partita->aggiornaClassifica(
      $vecchia['torneo'],
      $vecchia['squadra_casa'],
      $vecchia['squadra_ospite'],
      0,
      0,
      $vecchia,
      $vecchia['fase'] ?? 'REGULAR'
    );

    $partita->elimina($id);
    setSuccessAndRedirect('Partita eliminata.');
  }
}

$tornei = $torneoRepo->getAll();
$partite = $partita->getAll();
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

    .admin-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .admin-card-block {
      background: #0f1624;
      border: 1px solid #233554;
      border-radius: 12px;
      padding: 18px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .admin-card-block h2 {
      margin-top: 0;
      margin-bottom: 12px;
      color: #fff;
    }

    .admin-card-block form label {
      font-weight: 600;
      color: #c3d1e4;
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px;
    }

    .hidden { display: none; }

    .table-responsive {
      overflow-x: auto;
      border-radius: 12px;
      border: 1px solid #233554;
    }

    .btn-secondary {
      display: inline-block;
      background: #233554;
      color: #fff;
      padding: 8px 12px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      border: none;
      cursor: pointer;
    }

    .btn-secondary:hover { background: #1b2a42; }

    .actions { display: flex; gap: 8px; flex-wrap: wrap; }

    #footer-container { margin-top: auto; padding-top: 40px; }
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

      <div class="admin-grid">
        <div class="admin-card-block">
          <h2>Crea partita</h2>
          <form method="POST" id="formCrea" class="admin-form">
            <input type="hidden" name="crea" value="1">

            <label>Torneo</label>
            <select name="torneo" id="create_torneo" required>
              <option value="">-- Seleziona torneo --</option>
              <?php while ($t = $tornei->fetch_assoc()): $slug = preg_replace('/\.html$/i', '', $t['filetorneo']); ?>
                <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($t['nome'] ?? $slug) ?></option>
              <?php endwhile; ?>
            </select>

            <label>Fase</label>
            <select name="fase" id="create_fase" required>
              <?php foreach ($fasiDisponibili as $f): ?>
                <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
              <?php endforeach; ?>
            </select>

            <div class="form-row">
              <div>
                <label>Squadra Casa</label>
                <select name="squadra_casa" id="create_casa" required></select>
              </div>
              <div>
                <label>Squadra Ospite</label>
                <select name="squadra_ospite" id="create_ospite" required></select>
              </div>
            </div>

            <div class="form-row">
              <div>
                <label>Gol Casa</label>
                <input type="number" name="gol_casa" min="0" value="0" required>
              </div>
              <div>
                <label>Gol Ospite</label>
                <input type="number" name="gol_ospite" min="0" value="0" required>
              </div>
            </div>

            <div class="form-row">
              <div>
                <label>Data</label>
                <input type="date" name="data_partita" required>
              </div>
              <div>
                <label>Ora</label>
                <input type="time" name="ora_partita" required>
              </div>
            </div>

            <label>Campo</label>
            <select name="campo" required>
              <option value="">-- Seleziona Campo --</option>
              <option value="Sporting Club San Francesco, Napoli">Sporting Club San Francesco, Napoli</option>
              <option value="Centro Sportivo La Paratina, Napoli">Centro Sportivo La Paratina, Napoli</option>
              <option value="Sporting S.Antonio, Napoli">Sporting S.Antonio, Napoli</option>
              <option value="La Boutique del Calcio, Napoli">La Boutique del Calcio, Napoli</option>
              <option value="Campo Centrale del Parco Corto Maltese, Napoli">Campo Centrale del Parco Corto Maltese, Napoli</option>
            </select>

            <div id="create_regular_group">
              <label>Giornata</label>
              <input type="number" name="giornata" id="create_giornata" min="1">
            </div>

            <div id="create_knockout_group" class="hidden">
              <div class="form-row">
                <div>
                  <label>Fase eliminazione</label>
                  <select name="fase_round" id="create_fase_round">
                    <option value="">-- Seleziona fase --</option>
                    <?php foreach ($fasiEliminazione as $round): ?>
                      <option value="<?= htmlspecialchars($round) ?>"><?= ucfirst(strtolower($round)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Tipologia partita</label>
                  <select name="fase_leg" id="create_fase_leg">
                    <option value="">-- Seleziona tipologia --</option>
                    <?php foreach ($tipiAndataRitorno as $tipo): ?>
                      <option value="<?= htmlspecialchars($tipo) ?>"><?= ucfirst(strtolower($tipo)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-row">
              <div>
                <label>Link YouTube (opzionale)</label>
                <input type="url" name="link_youtube" placeholder="https://youtube.com/...">
              </div>
              <div>
                <label>Link Instagram (opzionale)</label>
                <input type="url" name="link_instagram" placeholder="https://instagram.com/...">
              </div>
            </div>

            <button type="submit" class="btn-primary" style="margin-top:12px;">Aggiungi partita</button>
          </form>
        </div>

        <div class="admin-card-block">
          <h2>Modifica partita</h2>
          <form method="POST" id="formModifica" class="admin-form">
            <input type="hidden" name="aggiorna" value="1">
            <input type="hidden" name="id" id="edit_id">

            <label>Torneo</label>
            <select name="torneo" id="edit_torneo" required>
              <option value="">-- Seleziona torneo --</option>
              <?php $torneiMod = $torneoRepo->getAll(); while ($t = $torneiMod->fetch_assoc()): $slug = preg_replace('/\.html$/i', '', $t['filetorneo']); ?>
                <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($t['nome'] ?? $slug) ?></option>
              <?php endwhile; ?>
            </select>

            <label>Fase</label>
            <select name="fase" id="edit_fase" required>
              <?php foreach ($fasiDisponibili as $f): ?>
                <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
              <?php endforeach; ?>
            </select>

            <div class="form-row">
              <div>
                <label>Squadra Casa</label>
                <select name="squadra_casa" id="edit_casa" required></select>
              </div>
              <div>
                <label>Squadra Ospite</label>
                <select name="squadra_ospite" id="edit_ospite" required></select>
              </div>
            </div>

            <div class="form-row">
              <div>
                <label>Gol Casa</label>
                <input type="number" name="gol_casa" id="edit_gol_casa" min="0" required>
              </div>
              <div>
                <label>Gol Ospite</label>
                <input type="number" name="gol_ospite" id="edit_gol_ospite" min="0" required>
              </div>
            </div>

            <div class="form-row">
              <div>
                <label>Data</label>
                <input type="date" name="data_partita" id="edit_data" required>
              </div>
              <div>
                <label>Ora</label>
                <input type="time" name="ora_partita" id="edit_ora" required>
              </div>
            </div>

            <label>Campo</label>
            <select name="campo" id="edit_campo" required>
              <option value="">-- Seleziona Campo --</option>
              <option value="Sporting Club San Francesco, Napoli">Sporting Club San Francesco, Napoli</option>
              <option value="Centro Sportivo La Paratina, Napoli">Centro Sportivo La Paratina, Napoli</option>
              <option value="Sporting S.Antonio, Napoli">Sporting S.Antonio, Napoli</option>
              <option value="La Boutique del Calcio, Napoli">La Boutique del Calcio, Napoli</option>
              <option value="Campo Centrale del Parco Corto Maltese, Napoli">Campo Centrale del Parco Corto Maltese, Napoli</option>
            </select>

            <div id="edit_regular_group">
              <label>Giornata</label>
              <input type="number" name="giornata" id="edit_giornata" min="1">
            </div>

            <div id="edit_knockout_group" class="hidden">
              <div class="form-row">
                <div>
                  <label>Fase eliminazione</label>
                  <select name="fase_round" id="edit_fase_round">
                    <option value="">-- Seleziona fase --</option>
                    <?php foreach ($fasiEliminazione as $round): ?>
                      <option value="<?= htmlspecialchars($round) ?>"><?= ucfirst(strtolower($round)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Tipologia partita</label>
                  <select name="fase_leg" id="edit_fase_leg">
                    <option value="">-- Seleziona tipologia --</option>
                    <?php foreach ($tipiAndataRitorno as $tipo): ?>
                      <option value="<?= htmlspecialchars($tipo) ?>"><?= ucfirst(strtolower($tipo)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-row">
              <div>
                <label>Link YouTube (opzionale)</label>
                <input type="url" name="link_youtube" id="edit_link_youtube" placeholder="https://youtube.com/...">
              </div>
              <div>
                <label>Link Instagram (opzionale)</label>
                <input type="url" name="link_instagram" id="edit_link_instagram" placeholder="https://instagram.com/...">
              </div>
            </div>

            <div class="actions" style="margin-top:12px;">
              <button type="submit" class="btn-primary">Salva modifiche</button>
              <button type="button" class="btn-secondary" id="reset_edit">Pulisci form</button>
              <a id="stats_link" class="btn-secondary hidden" target="_blank">Statistiche</a>
            </div>
          </form>
        </div>
      </div>

      <section class="admin-table-section">
        <h2>Elenco partite</h2>
        <div class="table-responsive">
          <table class="admin-table" style="min-width:1000px;">
            <thead>
              <tr>
                <th>Torneo</th>
                <th>Fase</th>
                <th>Giornata/Fase</th>
                <th>Casa</th>
                <th>Ospite</th>
                <th>Data</th>
                <th>Ora</th>
                <th>Campo</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($p = $partite->fetch_assoc()): ?>
                <tr
                  data-id="<?= (int)$p['id'] ?>"
                  data-torneo="<?= htmlspecialchars($p['torneo']) ?>"
                  data-fase="<?= htmlspecialchars($p['fase'] ?? 'REGULAR') ?>"
                  data-giornata="<?= htmlspecialchars($p['giornata'] ?? '') ?>"
                  data-fase-round="<?= htmlspecialchars($p['fase_round'] ?? '') ?>"
                  data-fase-leg="<?= htmlspecialchars($p['fase_leg'] ?? '') ?>"
                  data-casa="<?= htmlspecialchars($p['squadra_casa']) ?>"
                  data-ospite="<?= htmlspecialchars($p['squadra_ospite']) ?>"
                  data-gol-casa="<?= (int)$p['gol_casa'] ?>"
                  data-gol-ospite="<?= (int)$p['gol_ospite'] ?>"
                  data-data="<?= htmlspecialchars($p['data_partita']) ?>"
                  data-ora="<?= htmlspecialchars($p['ora_partita']) ?>"
                  data-campo="<?= htmlspecialchars($p['campo']) ?>"
                  data-youtube="<?= htmlspecialchars($p['link_youtube'] ?? '') ?>"
                  data-instagram="<?= htmlspecialchars($p['link_instagram'] ?? '') ?>"
                >
                  <td><?= htmlspecialchars($p['torneo']) ?></td>
                  <td><?= htmlspecialchars($p['fase'] ?? 'REGULAR') ?></td>
                  <td>
                    <?php if (!empty($p['giornata'])): ?>
                      Giornata <?= htmlspecialchars($p['giornata']) ?>
                    <?php else: ?>
                      <?= htmlspecialchars($p['fase_round'] ?? '') ?> (<?= htmlspecialchars($p['fase_leg'] ?? '') ?>)
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($p['squadra_casa']) ?></td>
                  <td><?= htmlspecialchars($p['squadra_ospite']) ?></td>
                  <td><?= htmlspecialchars($p['data_partita']) ?></td>
                  <td><?= htmlspecialchars($p['ora_partita']) ?></td>
                  <td><?= htmlspecialchars($p['campo']) ?></td>
                  <td class="actions">
                    <button type="button" class="btn-secondary btn-edit">Modifica</button>
                    <button type="button" class="btn-danger btn-small btn-delete">Elimina</button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </main>

  <form method="POST" id="formElimina" class="hidden">
    <input type="hidden" name="elimina" value="1">
    <input type="hidden" name="id_da_eliminare" id="id_da_eliminare">
  </form>

  <div id="footer-container"></div>

  <script src="/torneioldschool/includi/header-interactions.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof initHeaderInteractions === 'function') {
        initHeaderInteractions();
      }

      const footer = document.getElementById('footer-container');
      if (footer) {
        fetch('/torneioldschool/includi/footer.html')
          .then(r => r.text())
          .then(html => footer.innerHTML = html)
          .catch(err => console.error('Errore footer:', err));
      }

      const faseGroups = [
        { fase: document.getElementById('create_fase'), reg: document.getElementById('create_regular_group'), ko: document.getElementById('create_knockout_group'), round: document.getElementById('create_fase_round'), leg: document.getElementById('create_fase_leg'), giornata: document.getElementById('create_giornata') },
        { fase: document.getElementById('edit_fase'), reg: document.getElementById('edit_regular_group'), ko: document.getElementById('edit_knockout_group'), round: document.getElementById('edit_fase_round'), leg: document.getElementById('edit_fase_leg'), giornata: document.getElementById('edit_giornata') }
      ];

      function gestisciFase(cfg) {
        if (!cfg.fase) return;
        const isRegular = (cfg.fase.value || '').toUpperCase() === 'REGULAR';
        cfg.reg?.classList.toggle('hidden', !isRegular);
        cfg.ko?.classList.toggle('hidden', isRegular);

        if (cfg.giornata) {
          cfg.giornata.required = isRegular;
          if (!isRegular) cfg.giornata.value = '';
        }
        if (cfg.round) {
          cfg.round.required = !isRegular;
          cfg.round.disabled = isRegular;
          if (isRegular) cfg.round.value = '';
        }
        if (cfg.leg) {
          cfg.leg.required = !isRegular;
          cfg.leg.disabled = isRegular;
          if (isRegular) cfg.leg.value = '';
        }
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

      async function caricaSquadre(torneo, casaId, ospiteId, selCasa = '', selOspite = '') {
        const casa = document.getElementById(casaId);
        const osp = document.getElementById(ospiteId);
        if (!casa || !osp) return;

        casa.innerHTML = osp.innerHTML = '<option value="">-- Seleziona --</option>';
        if (!torneo) return;

        try {
          const res = await fetch(`/torneioldschool/api/get_squadre.php?torneo=${encodeURIComponent(torneo)}`);
          if (!res.ok) throw new Error('Errore rete');
          const dati = await res.json();
          dati.forEach(nome => {
            casa.add(new Option(nome, nome));
            osp.add(new Option(nome, nome));
          });
          if (selCasa) casa.value = selCasa;
          if (selOspite) osp.value = selOspite;
        } catch (err) {
          console.error('Errore caricamento squadre:', err);
        }
      }

      const createTorneo = document.getElementById('create_torneo');
      createTorneo?.addEventListener('change', () => caricaSquadre(createTorneo.value, 'create_casa', 'create_ospite'));

      const editTorneo = document.getElementById('edit_torneo');
      editTorneo?.addEventListener('change', () => caricaSquadre(editTorneo.value, 'edit_casa', 'edit_ospite'));

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

      function pulisciFormEdit() {
        editForm?.reset();
        if (document.getElementById('edit_id')) document.getElementById('edit_id').value = '';
        caricaSquadre('', 'edit_casa', 'edit_ospite');
        if (statsLink) statsLink.classList.add('hidden');
        faseGroups.forEach(cfg => gestisciFase(cfg));
      }

      document.getElementById('reset_edit')?.addEventListener('click', pulisciFormEdit);

      document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
          const row = btn.closest('tr');
          if (!row || !editForm) return;

          document.getElementById('edit_id').value = row.dataset.id || '';
          editTorneo.value = row.dataset.torneo || '';
          document.getElementById('edit_fase').value = row.dataset.fase || 'REGULAR';
          document.getElementById('edit_gol_casa').value = row.dataset.golCasa || 0;
          document.getElementById('edit_gol_ospite').value = row.dataset.golOspite || 0;
          document.getElementById('edit_data').value = row.dataset.data || '';
          document.getElementById('edit_ora').value = row.dataset.ora || '';
          document.getElementById('edit_campo').value = row.dataset.campo || '';
          document.getElementById('edit_giornata').value = row.dataset.giornata || '';
          document.getElementById('edit_fase_round').value = row.dataset.faseRound || '';
          document.getElementById('edit_fase_leg').value = row.dataset.faseLeg || '';
          document.getElementById('edit_link_youtube').value = row.dataset.youtube || '';
          document.getElementById('edit_link_instagram').value = row.dataset.instagram || '';

          caricaSquadre(row.dataset.torneo, 'edit_casa', 'edit_ospite', row.dataset.casa, row.dataset.ospite);

          if (statsLink) {
            statsLink.href = `/torneioldschool/api/statistiche_partita.php?partitaid=${row.dataset.id}`;
            statsLink.classList.remove('hidden');
          }

          gestisciFase(faseGroups[1]);
          editForm.scrollIntoView({ behavior: 'smooth' });
        });
      });

      document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', () => {
          const row = btn.closest('tr');
          if (!row) return;
          const label = `${row.dataset.casa || ''} - ${row.dataset.ospite || ''}`;
          if (confirm(`Eliminare la partita ${label}?`)) {
            document.getElementById('id_da_eliminare').value = row.dataset.id;
            document.getElementById('formElimina').submit();
          }
        });
      });
    });
  </script>
</body>
</html>
