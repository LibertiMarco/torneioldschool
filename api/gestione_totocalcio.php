<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/totocalcio.php';

$messages = [];
$errors = [];
$matches = [];
$candidateMatches = [];
$leaderboard = [];
$csrfKey = 'admin_totocalcio';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_totocalcio_datetime_label(?string $date, ?string $time = null): string
{
    $date = trim((string)$date);
    $time = trim((string)$time);

    if ($date === '') {
        return 'Data non impostata';
    }

    $format = $time !== '' ? 'Y-m-d H:i:s' : 'Y-m-d';
    $value = $time !== '' ? $date . ' ' . $time : $date;
    $timestamp = strtotime($value);
    if (!$timestamp) {
        return 'Data non impostata';
    }

    return $time !== ''
        ? date('d/m/Y H:i', $timestamp)
        : date('d/m/Y', $timestamp);
}

function admin_totocalcio_match_label(array $match): string
{
    $parts = [];

    if (!empty($match['torneo'])) {
        $parts[] = $match['torneo'];
    }

    if (!empty($match['fase'])) {
        $parts[] = $match['fase'];
    }

    if (!empty($match['giornata'])) {
        $parts[] = 'Giornata ' . (int)$match['giornata'];
    }

    $label = implode(' | ', $parts);
    $teams = trim((string)($match['squadra_casa'] ?? '') . ' vs ' . (string)($match['squadra_trasferta'] ?? ''));
    $when = admin_totocalcio_datetime_label($match['data_partita'] ?? null, $match['ora_partita'] ?? null);

    return trim($label . ' | ' . $teams . ' | ' . $when, ' |');
}

if ($conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');

    if (!totocalcio_ensure_tables($conn)) {
        $errors[] = 'Impossibile inizializzare il database del Totocalcio.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
        csrf_require($csrfKey);

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add_match') {
            $partitaId = (int)($_POST['partita_id'] ?? 0);
            $ordine = max(0, (int)($_POST['ordine'] ?? 0));

            if ($partitaId <= 0) {
                $errors[] = 'Seleziona una partita dal calendario.';
            } elseif (totocalcio_add_match($conn, $partitaId, $ordine)) {
                $messages[] = 'Partita aggiunta al Totocalcio.';
            } else {
                $errors[] = 'Impossibile aggiungere la partita. Verifica che non sia gia selezionata o gia giocata.';
            }
        } elseif ($action === 'save_match') {
            $selectionId = (int)($_POST['match_id'] ?? 0);
            $ordine = max(0, (int)($_POST['ordine'] ?? 0));
            $attiva = !empty($_POST['visibile']);

            if ($selectionId <= 0) {
                $errors[] = 'Partita Totocalcio non valida.';
            } elseif (totocalcio_update_match($conn, $selectionId, $ordine, $attiva)) {
                $messages[] = 'Configurazione Totocalcio aggiornata.';
            } else {
                $errors[] = 'Aggiornamento non riuscito.';
            }
        } elseif ($action === 'delete_match') {
            $selectionId = (int)($_POST['match_id'] ?? 0);

            if ($selectionId <= 0) {
                $errors[] = 'Partita Totocalcio non valida.';
            } elseif (totocalcio_delete_match($conn, $selectionId)) {
                $messages[] = 'Partita rimossa dal Totocalcio.';
            } else {
                $errors[] = 'Eliminazione non riuscita.';
            }
        }
    }

    $matches = totocalcio_fetch_matches($conn, false);
    $candidateMatches = totocalcio_fetch_candidate_matches($conn);
    $leaderboard = totocalcio_fetch_leaderboard($conn);
}

$visibleMatches = 0;
$openMatches = 0;
$playedMatches = 0;
$totalPredictions = 0;

foreach ($matches as $match) {
    if (!empty($match['visibile'])) {
        $visibleMatches++;
    }
    if (totocalcio_is_match_open($match)) {
        $openMatches++;
    }
    if (totocalcio_is_result_available($match)) {
        $playedMatches++;
    }
    $totalPredictions += (int)($match['total_predictions'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Gestione Totocalcio</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link rel="apple-touch-icon" href="/img/logo_old_school.png">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; background: #f6f8fb; }
    main.admin-wrapper { max-width: 1200px; margin: 0 auto; padding: 36px 16px 70px; flex: 1 0 auto; }
    .panel-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 16px; padding: 22px; box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08); margin-bottom: 20px; }
    .panel-card h2, .panel-card h3 { margin: 0 0 12px; color: #15293e; }
    .panel-card p { color: #4c5b71; line-height: 1.55; }
    .msg { padding: 12px 14px; border-radius: 12px; margin-bottom: 12px; font-weight: 700; }
    .msg.ok { background: #e8f6ef; color: #065f46; border: 1px solid #34d399; }
    .msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat-box { background: linear-gradient(135deg, #15293e 0%, #23415f 100%); color: #fff; border-radius: 16px; padding: 18px; }
    .stat-box strong { display: block; font-size: 1.8rem; line-height: 1; margin-bottom: 8px; }
    .stat-box span { color: rgba(255,255,255,0.82); font-size: 0.95rem; }
    .hint-grid { display: grid; grid-template-columns: 1.2fr 0.95fr; gap: 18px; }
    .helper-pill { display: inline-flex; align-items: center; padding: 7px 12px; border-radius: 999px; background: #e8edf5; color: #15293e; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
    .form-grid { display: grid; grid-template-columns: 1.6fr 0.6fr; gap: 14px; }
    .form-grid--compact { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .field { display: flex; flex-direction: column; gap: 8px; color: #15293e; font-weight: 700; }
    .field select,
    .field input[type="number"] {
      width: 100%;
      padding: 11px 12px;
      border: 1px solid #d7dce5;
      border-radius: 10px;
      background: #fff;
    }
    .field-toggle {
      display: flex;
      align-items: center;
      gap: 10px;
      padding-top: 32px;
      color: #15293e;
      font-weight: 700;
    }
    .field-toggle input { width: 18px; height: 18px; }
    .form-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 18px; }
    .helper-text { color: #64748b; font-size: 0.93rem; }
    .match-list { display: grid; gap: 16px; }
    .match-card { border: 1px solid #dce4ef; border-radius: 16px; padding: 18px; background: #f8fafc; }
    .match-card__top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 16px; }
    .match-card__title { margin: 0; color: #15293e; font-size: 1.15rem; }
    .meta-line { margin: 6px 0 0; color: #64748b; font-size: 0.93rem; }
    .pill-row { display: flex; flex-wrap: wrap; gap: 8px; }
    .status-pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
    .status-pill.ok { background: #dcfce7; color: #166534; }
    .status-pill.warn { background: #fef3c7; color: #92400e; }
    .status-pill.muted { background: #e2e8f0; color: #334155; }
    .status-pill.info { background: #dbeafe; color: #1d4ed8; }
    .leader-table { width: 100%; border-collapse: collapse; }
    .leader-table th, .leader-table td { padding: 12px 10px; border-bottom: 1px solid #e5eaf0; text-align: left; vertical-align: top; }
    .leader-table th { background: #f8fafc; color: #15293e; }
    .leader-table td:last-child, .leader-table th:last-child { text-align: right; }
    .score-rule { margin: 0; padding-left: 18px; color: #475569; line-height: 1.7; }
    .btn-danger {
      border: 1px solid #ef4444;
      background: #fff;
      color: #b91c1c;
      border-radius: 10px;
      padding: 10px 14px;
      font-weight: 700;
      cursor: pointer;
    }
    .btn-danger:hover { background: #fef2f2; }
    @media (max-width: 900px) {
      .hint-grid { grid-template-columns: 1fr; }
      .form-grid, .form-grid--compact { grid-template-columns: 1fr; }
      .field-toggle { padding-top: 0; }
    }
    @media (max-width: 640px) {
      .match-card__top { flex-direction: column; }
      .leader-table { display: block; overflow-x: auto; white-space: nowrap; }
    }
  </style>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
    <h1 class="admin-title">Gestione Totocalcio</h1>
    <p style="margin: 0 0 18px; color: #475569;">Le partite del Totocalcio vengono scelte dal calendario gia presente nel sito. Dopo che una partita e stata segnata come giocata, ogni modifica successiva del risultato ufficiale in <a href="/api/gestione_partite.php">Calendario &amp; Risultati</a> aggiorna automaticamente punteggi e classifica del Totocalcio.</p>

    <?php foreach ($messages as $message): ?>
      <div class="msg ok"><?= h($message) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
      <div class="msg err"><?= h($error) ?></div>
    <?php endforeach; ?>

    <section class="stats-grid">
      <article class="stat-box">
        <strong><?= (int)count($matches) ?></strong>
        <span>Partite selezionate</span>
      </article>
      <article class="stat-box">
        <strong><?= $openMatches ?></strong>
        <span>Partite ancora aperte</span>
      </article>
      <article class="stat-box">
        <strong><?= $playedMatches ?></strong>
        <span>Partite gia valorizzate</span>
      </article>
      <article class="stat-box">
        <strong><?= $totalPredictions ?></strong>
        <span>Pronostici salvati</span>
      </article>
    </section>

    <section class="hint-grid">
      <article class="panel-card">
        <span class="helper-pill">Selezione</span>
        <h2>Aggiungi dal calendario esistente</h2>
        <p style="margin: 0 0 16px;">Qui puoi scegliere solo partite reali ancora non giocate. Il Totocalcio non gestisce un risultato separato: prende sempre quello ufficiale dalla tabella <code>partite</code>.</p>

        <form method="POST" autocomplete="off">
          <?= csrf_field($csrfKey) ?>
          <input type="hidden" name="action" value="add_match">

          <div class="form-grid">
            <label class="field">
              Partita disponibile
              <select name="partita_id" required <?= empty($candidateMatches) ? 'disabled' : '' ?>>
                <option value="">-- scegli una partita non ancora giocata --</option>
                <?php foreach ($candidateMatches as $candidate): ?>
                  <option value="<?= (int)$candidate['id'] ?>"><?= h(admin_totocalcio_match_label($candidate)) ?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="field">
              Ordine
              <input type="number" name="ordine" value="<?= count($matches) ?>" min="0" step="1">
            </label>
          </div>

          <div class="form-actions">
            <button class="btn-primary" type="submit" <?= empty($candidateMatches) ? 'disabled' : '' ?>>Aggiungi al Totocalcio</button>
            <span class="helper-text">
              <?php if (empty($candidateMatches)): ?>
                Non ci sono nuove partite non giocate da aggiungere.
              <?php else: ?>
                Le partite selezionate compariranno subito in <a href="/totocalcio.php">/totocalcio.php</a>.
              <?php endif; ?>
            </span>
          </div>
        </form>
      </article>

      <aside class="panel-card">
        <span class="helper-pill">Regole</span>
        <h3>Come funziona ora</h3>
        <ul class="score-rule">
          <li>Selezione da partite esistenti non ancora giocate.</li>
          <li>Nessun risultato manuale nel Totocalcio.</li>
          <li>Quando una partita reale diventa giocata, il Totocalcio legge automaticamente esito e risultato.</li>
          <li>Se il risultato ufficiale viene modificato dopo, il Totocalcio ricalcola tutto sui nuovi gol.</li>
          <li>Esito corretto: <strong>+1 punto</strong>. Risultato esatto: <strong>+3 punti</strong>.</li>
        </ul>
      </aside>
    </section>

    <section class="panel-card">
      <span class="helper-pill">Lista attuale</span>
      <h2>Partite collegate al Totocalcio</h2>
      <p style="margin: 0 0 16px;">Puoi cambiare ordine, disattivare temporaneamente una partita o rimuoverla. Il risultato mostrato sotto arriva sempre dal calendario ufficiale.</p>

      <?php if (empty($matches)): ?>
        <p style="margin: 0; color: #64748b;">Non hai ancora selezionato partite per il Totocalcio.</p>
      <?php else: ?>
        <div class="match-list">
          <?php foreach ($matches as $match): ?>
            <?php
              $officialResult = totocalcio_is_result_available($match)
                  ? (int)$match['gol_casa_reale'] . ' - ' . (int)$match['gol_trasferta_reale']
                  : 'Non ancora disponibile';
            ?>
            <article class="match-card">
              <div class="match-card__top">
                <div>
                  <h3 class="match-card__title"><?= h($match['squadra_casa']) ?> vs <?= h($match['squadra_trasferta']) ?></h3>
                  <p class="meta-line">Calendario: <?= h(admin_totocalcio_match_label($match)) ?></p>
                  <p class="meta-line">Campo: <?= h($match['campo'] ?? 'Da definire') ?> | Pronostici ricevuti: <?= (int)($match['total_predictions'] ?? 0) ?> | Risultato ufficiale: <?= h($officialResult) ?></p>
                </div>

                <div class="pill-row">
                  <span class="status-pill <?= !empty($match['visibile']) ? 'ok' : 'muted' ?>">
                    <?= !empty($match['visibile']) ? 'Attiva' : 'Disattivata' ?>
                  </span>
                  <span class="status-pill <?= totocalcio_is_match_open($match) ? 'warn' : 'info' ?>">
                    <?= totocalcio_is_match_open($match) ? 'Non giocata' : 'Giocata / chiusa' ?>
                  </span>
                </div>
              </div>

              <form method="POST" autocomplete="off">
                <?= csrf_field($csrfKey) ?>
                <input type="hidden" name="action" value="save_match">
                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">

                <div class="form-grid form-grid--compact">
                  <label class="field">
                    Ordine
                    <input type="number" name="ordine" min="0" step="1" value="<?= (int)($match['ordine'] ?? 0) ?>">
                  </label>

                  <label class="field-toggle">
                    <input type="checkbox" name="visibile" value="1" <?= !empty($match['visibile']) ? 'checked' : '' ?>>
                    <span>Mostra in Totocalcio</span>
                  </label>
                </div>

                <div class="form-actions">
                  <button class="btn-primary" type="submit">Salva configurazione</button>
                </div>
              </form>

              <form method="POST" onsubmit="return confirm('Rimuovere questa partita dal Totocalcio e cancellare tutti i pronostici collegati?');" style="margin-top: 10px;">
                <?= csrf_field($csrfKey) ?>
                <input type="hidden" name="action" value="delete_match">
                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">
                <button type="submit" class="btn-danger">Rimuovi dal Totocalcio</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel-card">
      <span class="helper-pill">Classifica</span>
      <h2>Anteprima classifica Totocalcio</h2>
      <p style="margin: 0 0 16px;">Classifica ordinata per punti, poi risultati esatti, poi esiti corretti. Sono inclusi solo gli account con flag Totocalcio attivo.</p>

      <?php if (empty($leaderboard)): ?>
        <p style="margin: 0; color: #64748b;">Nessun account abilitato al Totocalcio.</p>
      <?php else: ?>
        <table class="leader-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Utente</th>
              <th>Esiti corretti</th>
              <th>Risultati esatti</th>
              <th>Pronostici valutati</th>
              <th>Punti</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leaderboard as $index => $row): ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td><?= h($row['display_name']) ?></td>
                <td><?= (int)$row['esiti_corretti'] ?></td>
                <td><?= (int)$row['risultati_esatti'] ?></td>
                <td><?= (int)$row['pronostici_valutati'] ?></td>
                <td><strong><?= (int)$row['punti_totali'] ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>

  <div id="footer-container"></div>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const footer = document.getElementById('footer-container');
      if (!footer) return;

      fetch('/includi/footer.html')
        .then(response => response.text())
        .then(html => { footer.innerHTML = html; })
        .catch(err => console.error('Errore nel caricamento del footer:', err));
    });
  </script>
</body>
</html>
