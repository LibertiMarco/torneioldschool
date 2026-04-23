<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/totocalcio.php';

$messages = [];
$errors = [];
$matches = [];
$candidateMatches = [];
$leaderboard = [];
$competitions = [];
$selectedCompetition = null;
$accessAccounts = [];
$selectedCompetitionAccessUserIds = [];
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

function admin_totocalcio_competition_url(string $slug): string
{
    return '/api/gestione_totocalcio.php?competizione=' . rawurlencode($slug);
}

function admin_totocalcio_public_url(string $slug): string
{
    return '/totocalcio.php?competizione=' . rawurlencode($slug);
}

function admin_totocalcio_find_competition(array $competitions, string $slug): ?array
{
    foreach ($competitions as $competition) {
        if ((string)($competition['slug'] ?? '') === $slug) {
            return $competition;
        }
    }

    return $competitions[0] ?? null;
}

if ($conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');

    if (!totocalcio_ensure_tables($conn)) {
        $errors[] = 'Impossibile inizializzare il database del Totocalcio.';
    } else {
        $requestedCompetitionSlug = trim((string)($_GET['competizione'] ?? ''));
        $competitions = totocalcio_fetch_competitions($conn, false);
        $selectedCompetition = admin_totocalcio_find_competition($competitions, $requestedCompetitionSlug);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_require($csrfKey);

            $action = (string)($_POST['action'] ?? '');
            $selectedCompetitionId = (int)($selectedCompetition['id'] ?? 0);
            $updatedCompetitionId = 0;

            if ($action === 'create_competition') {
                $name = trim((string)($_POST['competition_name'] ?? ''));
                $slug = trim((string)($_POST['competition_slug'] ?? ''));
                $order = max(0, (int)($_POST['competition_order'] ?? count($competitions)));
                $active = !empty($_POST['competition_active']);
                $publicAccess = !empty($_POST['competition_public_access']);

                if ($name === '') {
                    $errors[] = 'Inserisci il nome della competizione.';
                } else {
                    $createdCompetition = totocalcio_create_competition($conn, $name, $slug, $order, $active, $publicAccess);
                    if ($createdCompetition === null) {
                        $errors[] = 'Impossibile creare la competizione.';
                    } else {
                        $messages[] = 'Competizione creata correttamente.';
                        $requestedCompetitionSlug = (string)$createdCompetition['slug'];
                    }
                }
            } elseif ($action === 'save_competition') {
                $competitionId = (int)($_POST['competition_id'] ?? 0);
                $name = trim((string)($_POST['competition_name'] ?? ''));
                $slug = trim((string)($_POST['competition_slug'] ?? ''));
                $order = max(0, (int)($_POST['competition_order'] ?? 0));
                $active = !empty($_POST['competition_active']);
                $publicAccess = !empty($_POST['competition_public_access']);

                if ($competitionId <= 0 || $name === '') {
                    $errors[] = 'Competizione non valida.';
                } elseif (!totocalcio_update_competition($conn, $competitionId, $name, $slug, $order, $active, $publicAccess)) {
                    $errors[] = 'Aggiornamento competizione non riuscito.';
                } else {
                    $messages[] = 'Competizione aggiornata.';
                    $updatedCompetitionId = $competitionId;
                }
            } elseif ($action === 'save_competition_access') {
                $competitionId = (int)($_POST['competition_id'] ?? 0);
                $allowedUsers = $_POST['competition_access_users'] ?? [];

                if ($competitionId <= 0) {
                    $errors[] = 'Competizione non valida.';
                } elseif (!totocalcio_replace_competition_access($conn, $competitionId, is_array($allowedUsers) ? $allowedUsers : [])) {
                    $errors[] = 'Aggiornamento accessi non riuscito.';
                } else {
                    $messages[] = 'Accessi competizione aggiornati.';
                    $updatedCompetitionId = $competitionId;
                }
            } elseif ($action === 'add_match') {
                $partitaId = (int)($_POST['partita_id'] ?? 0);
                $ordine = max(0, (int)($_POST['ordine'] ?? 0));

                if ($selectedCompetitionId <= 0) {
                    $errors[] = 'Seleziona prima una competizione.';
                } elseif ($partitaId <= 0) {
                    $errors[] = 'Seleziona una partita dal calendario.';
                } elseif (totocalcio_add_match($conn, $selectedCompetitionId, $partitaId, $ordine)) {
                    $messages[] = 'Partita aggiunta al Totocalcio.';
                } else {
                    $errors[] = 'Impossibile aggiungere la partita. Verifica che non sia gia selezionata nella competizione corrente o gia giocata.';
                }
            } elseif ($action === 'save_match') {
                $selectionId = (int)($_POST['match_id'] ?? 0);
                $ordine = max(0, (int)($_POST['ordine'] ?? 0));
                $attiva = !empty($_POST['visibile']);
                $match = totocalcio_fetch_match_by_id($conn, $selectionId, $selectedCompetitionId);

                if (!$match) {
                    $errors[] = 'Partita Totocalcio non valida per la competizione selezionata.';
                } elseif (totocalcio_update_match($conn, $selectionId, $ordine, $attiva)) {
                    $messages[] = 'Configurazione Totocalcio aggiornata.';
                } else {
                    $errors[] = 'Aggiornamento non riuscito.';
                }
            } elseif ($action === 'delete_match') {
                $selectionId = (int)($_POST['match_id'] ?? 0);
                $match = totocalcio_fetch_match_by_id($conn, $selectionId, $selectedCompetitionId);

                if (!$match) {
                    $errors[] = 'Partita Totocalcio non valida per la competizione selezionata.';
                } elseif (totocalcio_delete_match($conn, $selectionId)) {
                    $messages[] = 'Partita rimossa dal Totocalcio.';
                } else {
                    $errors[] = 'Eliminazione non riuscita.';
                }
            }

            $competitions = totocalcio_fetch_competitions($conn, false);

            if ($updatedCompetitionId > 0) {
                foreach ($competitions as $competition) {
                    if ((int)($competition['id'] ?? 0) === $updatedCompetitionId) {
                        $requestedCompetitionSlug = (string)($competition['slug'] ?? $requestedCompetitionSlug);
                        break;
                    }
                }
            }

            $selectedCompetition = admin_totocalcio_find_competition($competitions, $requestedCompetitionSlug);
        }

        $selectedCompetitionId = (int)($selectedCompetition['id'] ?? 0);
        if ($selectedCompetitionId > 0) {
            $matches = totocalcio_fetch_matches($conn, false, 0, $selectedCompetitionId);
            $candidateMatches = totocalcio_fetch_candidate_matches($conn, $selectedCompetitionId);
            $leaderboard = totocalcio_fetch_leaderboard($conn, $selectedCompetitionId);
            $selectedCompetitionAccessUserIds = totocalcio_fetch_competition_granted_user_ids($conn, $selectedCompetitionId);
        }

        $accessAccounts = totocalcio_fetch_access_accounts($conn);
    }
}

$visibleMatches = 0;
$openMatches = 0;
$playedMatches = 0;
$totalPredictions = 0;
$selectedCompetitionDrawPoints = $selectedCompetition ? totocalcio_competition_draw_points($selectedCompetition) : 1;
$selectedCompetitionExactBonus = $selectedCompetition ? totocalcio_competition_exact_bonus($selectedCompetition) : 3;
$selectedCompetitionMaxPoints = max(1, $selectedCompetitionDrawPoints) + $selectedCompetitionExactBonus;
$selectedCompetitionAntepostEnabled = $selectedCompetition ? totocalcio_competition_has_antepost($selectedCompetition) : false;

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
    main.admin-wrapper { max-width: 1280px; margin: 0 auto; padding: 36px 16px 70px; flex: 1 0 auto; }
    .panel-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 16px; padding: 22px; box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08); margin-bottom: 20px; }
    .panel-card h2, .panel-card h3 { margin: 0 0 12px; color: #15293e; }
    .panel-card p { color: #4c5b71; line-height: 1.55; }
    .msg { padding: 12px 14px; border-radius: 12px; margin-bottom: 12px; font-weight: 700; }
    .msg.ok { background: #e8f6ef; color: #065f46; border: 1px solid #34d399; }
    .msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
    .helper-pill { display: inline-flex; align-items: center; padding: 7px 12px; border-radius: 999px; background: #e8edf5; color: #15293e; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat-box { background: linear-gradient(135deg, #15293e 0%, #23415f 100%); color: #fff; border-radius: 16px; padding: 18px; }
    .stat-box strong { display: block; font-size: 1.8rem; line-height: 1; margin-bottom: 8px; }
    .stat-box span { color: rgba(255,255,255,0.82); font-size: 0.95rem; }
    .hero-grid { display: grid; grid-template-columns: 1.15fr 0.95fr; gap: 18px; margin-bottom: 20px; }
    .switcher-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
    .competition-pill { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; border: 1px solid #cbd5e1; background: #fff; color: #1e293b; font-weight: 700; text-decoration: none; }
    .competition-pill.active { background: #15293e; border-color: #15293e; color: #fff; }
    .competition-pill small { font-size: 0.78rem; opacity: 0.8; }
    .dual-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 18px; }
    .field, .field-inline { display: flex; flex-direction: column; gap: 8px; color: #15293e; font-weight: 700; }
    .field input[type="text"],
    .field input[type="number"],
    .field select,
    .field-inline input[type="text"],
    .field-inline input[type="number"] {
      width: 100%;
      padding: 11px 12px;
      border: 1px solid #d7dce5;
      border-radius: 10px;
      background: #fff;
    }
    .field-grid { display: grid; grid-template-columns: 1.1fr 1fr 140px; gap: 12px; }
    .form-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 16px; }
    .helper-text { color: #64748b; font-size: 0.93rem; }
    .toggle-row { display: flex; align-items: center; gap: 10px; color: #15293e; font-weight: 700; }
    .toggle-row input { width: 18px; height: 18px; }
    .access-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
    .access-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: end;
      gap: 12px;
      margin-bottom: 16px;
    }
    .access-toolbar .field {
      flex: 1 1 320px;
    }
    .access-search-count {
      color: #64748b;
      font-size: 0.92rem;
      font-weight: 700;
      padding-bottom: 11px;
    }
    .access-option {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      border: 1px solid #dce4ef;
      border-radius: 12px;
      padding: 14px;
      background: #f8fafc;
    }
    .access-option input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; flex: 0 0 auto; }
    .access-option strong { display: block; color: #15293e; margin-bottom: 4px; }
    .access-option span { display: block; color: #5c6572; font-size: 0.92rem; line-height: 1.45; }
    .access-option.is-hidden { display: none; }
    .competition-list { display: grid; gap: 14px; }
    .competition-card { border: 1px solid #dce4ef; border-radius: 16px; padding: 16px; background: #f8fafc; }
    .competition-card__head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
    .competition-card__head h3 { margin: 0; }
    .meta-line { margin: 6px 0 0; color: #64748b; font-size: 0.93rem; }
    .status-pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
    .status-pill.ok { background: #dcfce7; color: #166534; }
    .status-pill.warn { background: #fef3c7; color: #92400e; }
    .status-pill.info { background: #dbeafe; color: #1d4ed8; }
    .status-pill.muted { background: #e2e8f0; color: #334155; }
    .match-list { display: grid; gap: 16px; }
    .match-card { border: 1px solid #dce4ef; border-radius: 16px; padding: 18px; background: #f8fafc; }
    .match-card__top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 16px; }
    .match-card__title { margin: 0; color: #15293e; font-size: 1.15rem; }
    .match-form-grid { display: grid; grid-template-columns: 140px auto; gap: 12px; align-items: end; }
    .leader-table { width: 100%; border-collapse: collapse; }
    .leader-table th, .leader-table td { padding: 12px 10px; border-bottom: 1px solid #e5eaf0; text-align: left; vertical-align: top; }
    .leader-table th { background: #f8fafc; color: #15293e; }
    .leader-table td:last-child, .leader-table th:last-child { text-align: right; }
    .score-rule { margin: 0; padding-left: 18px; color: #475569; line-height: 1.7; }
    .btn-danger { border: 1px solid #ef4444; background: #fff; color: #b91c1c; border-radius: 10px; padding: 10px 14px; font-weight: 700; cursor: pointer; }
    .btn-danger:hover { background: #fef2f2; }
    @media (max-width: 980px) {
      .hero-grid, .dual-grid, .field-grid, .match-form-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 720px) {
      .competition-card__head, .match-card__top { flex-direction: column; }
      .leader-table { display: block; overflow-x: auto; white-space: nowrap; }
    }
  </style>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
    <h1 class="admin-title">Gestione Totocalcio</h1>
    <p style="margin: 0 0 18px; color: #475569;">Il Totocalcio ora puo ospitare piu competizioni. Quella storica esistente viene mantenuta come <strong><?= h(totocalcio_default_competition_name()) ?></strong>. Per ogni competizione scegli partite dal calendario ufficiale, mentre risultati e classifica continuano ad aggiornarsi automaticamente dalla tabella <code>partite</code>.</p>

    <?php foreach ($messages as $message): ?>
      <div class="msg ok"><?= h($message) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
      <div class="msg err"><?= h($error) ?></div>
    <?php endforeach; ?>

    <section class="panel-card">
      <span class="helper-pill">Competizioni</span>
      <h2>Selettore competizione</h2>
      <p style="margin: 0;">Lavora su una competizione alla volta. Le partite e la classifica sotto vengono filtrate in base alla competizione selezionata.</p>

      <?php if (empty($competitions)): ?>
        <p style="margin: 16px 0 0; color: #64748b;">Nessuna competizione disponibile.</p>
      <?php else: ?>
        <div class="switcher-row">
          <?php foreach ($competitions as $competition): ?>
            <?php
              $competitionSlug = (string)($competition['slug'] ?? '');
              $isActiveCompetition = $selectedCompetition && (int)$selectedCompetition['id'] === (int)$competition['id'];
            ?>
            <a class="competition-pill <?= $isActiveCompetition ? 'active' : '' ?>" href="<?= h(admin_totocalcio_competition_url($competitionSlug)) ?>">
              <span><?= h($competition['nome']) ?></span>
              <small><?= (int)($competition['total_matches'] ?? 0) ?> partite</small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="stats-grid">
      <article class="stat-box">
        <strong><?= (int)count($matches) ?></strong>
        <span>Partite nella competizione</span>
      </article>
      <article class="stat-box">
        <strong><?= $openMatches ?></strong>
        <span>Partite ancora aperte</span>
      </article>
      <article class="stat-box">
        <strong><?= $playedMatches ?></strong>
        <span>Partite gia giocate</span>
      </article>
      <article class="stat-box">
        <strong><?= $totalPredictions ?></strong>
        <span>Pronostici salvati</span>
      </article>
    </section>

    <section class="hero-grid">
      <article class="panel-card">
        <span class="helper-pill">Nuova competizione</span>
        <h2>Crea una nuova competizione</h2>
        <p style="margin: 0 0 16px;">Se non inserisci lo slug, viene generato automaticamente dal nome. La competizione storica resta disponibile come default. Puoi scegliere se renderla pubblica oppure lasciarla accessibile solo agli account che abiliti manualmente.</p>

        <form method="POST" autocomplete="off">
          <?= csrf_field($csrfKey) ?>
          <input type="hidden" name="action" value="create_competition">

          <div class="field-grid">
            <label class="field">
              Nome competizione
              <input type="text" name="competition_name" maxlength="150" placeholder="Es. Totocalcio Champions League" required>
            </label>

            <label class="field">
              Slug URL
              <input type="text" name="competition_slug" maxlength="180" placeholder="opzionale">
            </label>

            <label class="field">
              Ordine
              <input type="number" name="competition_order" min="0" step="1" value="<?= count($competitions) ?>">
            </label>
          </div>

          <div class="form-actions">
            <label class="toggle-row">
              <input type="checkbox" name="competition_active" value="1" checked>
              <span>Competizione visibile</span>
            </label>
            <label class="toggle-row">
              <input type="checkbox" name="competition_public_access" value="1" checked>
              <span>Accesso pubblico</span>
            </label>
            <button class="btn-primary" type="submit">Crea competizione</button>
          </div>
        </form>
      </article>

      <aside class="panel-card">
        <span class="helper-pill">Competizione attiva</span>
        <?php if ($selectedCompetition): ?>
          <h3><?= h($selectedCompetition['nome']) ?></h3>
          <p class="meta-line">Slug: <code><?= h($selectedCompetition['slug']) ?></code></p>
          <p class="meta-line">Visibile sul sito: <?= !empty($selectedCompetition['attiva']) ? 'si' : 'no' ?></p>
          <p class="meta-line">Accesso pubblico: <?= !empty($selectedCompetition['accesso_pubblico']) ? 'si' : 'no, solo utenti autorizzati' ?></p>
          <p class="meta-line">Pareggio corretto: <?= $selectedCompetitionDrawPoints ?> punti | Bonus risultato esatto: <?= $selectedCompetitionExactBonus ?> punti | Totale massimo partita: <?= $selectedCompetitionMaxPoints ?></p>
          <p class="meta-line">Antepost disponibili: <?= $selectedCompetitionAntepostEnabled ? 'si' : 'no' ?></p>
          <p class="meta-line">Utenti autorizzati: <?= count($selectedCompetitionAccessUserIds) ?></p>
          <p class="meta-line">Link pubblico: <a href="<?= h(admin_totocalcio_public_url((string)$selectedCompetition['slug'])) ?>"><?= h(admin_totocalcio_public_url((string)$selectedCompetition['slug'])) ?></a></p>
        <?php else: ?>
          <p style="margin: 0; color: #64748b;">Seleziona o crea una competizione.</p>
        <?php endif; ?>

        <ul class="score-rule" style="margin-top: 16px;">
          <li>Esito corretto: <strong>+1 punto</strong>.</li>
          <?php if ($selectedCompetitionDrawPoints > 1): ?>
            <li>Pareggio corretto: <strong>+<?= $selectedCompetitionDrawPoints ?> punti</strong>.</li>
          <?php endif; ?>
          <li>Risultato esatto: <strong>+<?= $selectedCompetitionExactBonus ?> punti</strong>.</li>
          <li>Totale massimo per partita: <strong><?= $selectedCompetitionMaxPoints ?> punti</strong>.</li>
          <li>Una partita reale puo comparire in piu competizioni diverse.</li>
          <li>I risultati ufficiali restano sempre quelli del calendario.</li>
          <li>Se l accesso non e pubblico, entrano solo admin, sysadmin e utenti autorizzati.</li>
          <?php if ($selectedCompetitionAntepostEnabled): ?>
            <li>La pagina pubblica mostra anche il tab Antepost con scelte per Regular Season, Coppa Gold, Coppa Silver e squadra capocannoniere.</li>
          <?php endif; ?>
        </ul>
      </aside>
    </section>

    <section class="panel-card">
      <span class="helper-pill">Elenco</span>
      <h2>Configurazione competizioni</h2>
      <p style="margin: 0 0 16px;">Puoi rinominare, riordinare o nascondere una competizione. Lo slug viene reso univoco automaticamente se necessario.</p>

      <?php if (empty($competitions)): ?>
        <p style="margin: 0; color: #64748b;">Nessuna competizione presente.</p>
      <?php else: ?>
        <div class="competition-list">
          <?php foreach ($competitions as $competition): ?>
            <article class="competition-card">
              <div class="competition-card__head">
                <div>
                  <h3><?= h($competition['nome']) ?></h3>
                  <p class="meta-line">Slug: <code><?= h($competition['slug']) ?></code></p>
                  <p class="meta-line">Partite: <?= (int)($competition['total_matches'] ?? 0) ?> | Attive: <?= (int)($competition['active_matches'] ?? 0) ?> | Pronostici: <?= (int)($competition['total_predictions'] ?? 0) ?> | Accessi assegnati: <?= (int)($competition['granted_users'] ?? 0) ?></p>
                </div>
                <div class="switcher-row" style="margin-top: 0;">
                  <span class="status-pill <?= !empty($competition['attiva']) ? 'ok' : 'muted' ?>">
                    <?= !empty($competition['attiva']) ? 'Visibile' : 'Nascosta' ?>
                  </span>
                  <span class="status-pill <?= !empty($competition['accesso_pubblico']) ? 'info' : 'warn' ?>">
                    <?= !empty($competition['accesso_pubblico']) ? 'Pubblica' : 'Riservata' ?>
                  </span>
                  <a class="competition-pill" href="<?= h(admin_totocalcio_competition_url((string)$competition['slug'])) ?>">Apri</a>
                </div>
              </div>

              <form method="POST" autocomplete="off">
                <?= csrf_field($csrfKey) ?>
                <input type="hidden" name="action" value="save_competition">
                <input type="hidden" name="competition_id" value="<?= (int)$competition['id'] ?>">

                <div class="field-grid">
                  <label class="field">
                    Nome
                    <input type="text" name="competition_name" maxlength="150" value="<?= h($competition['nome']) ?>" required>
                  </label>

                  <label class="field">
                    Slug
                    <input type="text" name="competition_slug" maxlength="180" value="<?= h($competition['slug']) ?>">
                  </label>

                  <label class="field">
                    Ordine
                    <input type="number" name="competition_order" min="0" step="1" value="<?= (int)($competition['ordine'] ?? 0) ?>">
                  </label>
                </div>

                <div class="form-actions">
                  <label class="toggle-row">
                    <input type="checkbox" name="competition_active" value="1" <?= !empty($competition['attiva']) ? 'checked' : '' ?>>
                    <span>Competizione visibile</span>
                  </label>
                  <label class="toggle-row">
                    <input type="checkbox" name="competition_public_access" value="1" <?= !empty($competition['accesso_pubblico']) ? 'checked' : '' ?>>
                    <span>Accesso pubblico</span>
                  </label>
                  <button class="btn-primary" type="submit">Salva competizione</button>
                </div>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel-card">
      <span class="helper-pill">Accessi</span>
      <h2>Utenti autorizzati alla competizione</h2>
      <?php if (!$selectedCompetition): ?>
        <p style="margin: 0; color: #64748b;">Seleziona una competizione per configurare gli accessi.</p>
      <?php else: ?>
        <p style="margin: 0 0 16px;">Se <strong><?= h($selectedCompetition['nome']) ?></strong> e pubblica, tutti gli utenti abilitati al Totocalcio la vedono. Se la rendi riservata, qui scegli manualmente chi puo accedervi anche senza visibilita pubblica.</p>

        <form method="POST" autocomplete="off">
          <?= csrf_field($csrfKey) ?>
          <input type="hidden" name="action" value="save_competition_access">
          <input type="hidden" name="competition_id" value="<?= (int)($selectedCompetition['id'] ?? 0) ?>">

          <?php if (empty($accessAccounts)): ?>
            <p style="margin: 0; color: #64748b;">Non ci sono account disponibili.</p>
          <?php else: ?>
            <div class="access-toolbar">
              <label class="field">
                Cerca utente
                <input type="search" id="competitionAccessSearch" placeholder="Nome, cognome, email o ruolo">
              </label>
              <span class="access-search-count" id="competitionAccessSearchCount"><?= count($accessAccounts) ?> utenti visibili</span>
            </div>

            <div class="access-grid">
              <?php foreach ($accessAccounts as $account): ?>
                <?php
                  $accountId = (int)($account['id'] ?? 0);
                  $isGranted = in_array($accountId, $selectedCompetitionAccessUserIds, true);
                  $hasTotocalcioMenu = !empty(($account['feature_flags'] ?? [])['totocalcio']);
                  $isAdminRole = user_has_admin_access((string)($account['ruolo'] ?? 'user'));
                ?>
                <label class="access-option">
                  <input type="checkbox" name="competition_access_users[]" value="<?= $accountId ?>" <?= $isGranted ? 'checked' : '' ?>>
                  <span>
                    <strong><?= h($account['display_name'] ?? ($account['email'] ?? 'Account')) ?></strong>
                    <span><?= h($account['email'] ?? '') ?><?php if (!empty($account['ruolo'])): ?> | ruolo: <?= h($account['ruolo']) ?><?php endif; ?></span>
                    <span>
                      <?= $isAdminRole ? 'Accesso gia garantito dal ruolo admin/sysadmin.' : ($hasTotocalcioMenu ? 'Ha gia il Totocalcio nel menu.' : 'Ricevera accesso solo a questa competizione.') ?>
                    </span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="form-actions">
              <button class="btn-primary" type="submit">Salva accessi</button>
              <span class="helper-text">Gli utenti selezionati potranno vedere la competizione anche se non pubblica. Admin e sysadmin non hanno bisogno di essere selezionati.</span>
            </div>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </section>

    <section class="dual-grid">
      <article class="panel-card">
        <span class="helper-pill">Selezione</span>
        <h2>Aggiungi partite alla competizione</h2>
        <?php if ($selectedCompetition): ?>
          <p style="margin: 0 0 16px;">Le partite vengono aggiunte a <strong><?= h($selectedCompetition['nome']) ?></strong>. Qui puoi scegliere solo partite reali ancora non giocate e non gia presenti nella competizione corrente.</p>

          <form method="POST" autocomplete="off">
            <?= csrf_field($csrfKey) ?>
            <input type="hidden" name="action" value="add_match">

            <div class="field-grid">
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
                <input type="number" name="ordine" min="0" step="1" value="<?= count($matches) ?>">
              </label>

              <div class="field">
                <span>&nbsp;</span>
                <button class="btn-primary" type="submit" <?= empty($candidateMatches) ? 'disabled' : '' ?>>Aggiungi</button>
              </div>
            </div>

            <div class="form-actions">
              <span class="helper-text">
                <?php if (empty($candidateMatches)): ?>
                  Nessuna nuova partita disponibile per questa competizione.
                <?php else: ?>
                  Le partite appariranno subito in <a href="<?= h(admin_totocalcio_public_url((string)$selectedCompetition['slug'])) ?>">questa pagina pubblica</a>.
                <?php endif; ?>
              </span>
            </div>
          </form>
        <?php else: ?>
          <p style="margin: 0; color: #64748b;">Non c e una competizione selezionata.</p>
        <?php endif; ?>
      </article>

      <aside class="panel-card">
        <span class="helper-pill">Regole</span>
        <h3>Promemoria</h3>
        <ul class="score-rule">
          <li>Ogni competizione ha la sua schedina e la sua classifica.</li>
          <li>La stessa partita reale puo essere riutilizzata in un altra competizione.</li>
          <li>Se il risultato ufficiale cambia nel calendario, il Totocalcio si riallinea.</li>
          <li>Nascondere una competizione la esclude anche dalla classifica pubblica.</li>
          <li>Una competizione riservata compare solo agli utenti a cui assegni l accesso.</li>
        </ul>
      </aside>
    </section>

    <section class="panel-card">
      <span class="helper-pill">Lista attuale</span>
      <h2>Partite della competizione selezionata</h2>
      <?php if (!$selectedCompetition): ?>
        <p style="margin: 0; color: #64748b;">Nessuna competizione selezionata.</p>
      <?php elseif (empty($matches)): ?>
        <p style="margin: 0; color: #64748b;">Non hai ancora selezionato partite per questa competizione.</p>
      <?php else: ?>
        <div class="match-list">
          <?php foreach ($matches as $match): ?>
            <?php
              $officialResult = totocalcio_is_result_available($match)
                  ? (int)$match['gol_casa_reale'] . ' - ' . (int)$match['gol_trasferta_reale']
                  : 'Non ancora disponibile';
              $predictionCutoff = totocalcio_match_cutoff_datetime($match);
            ?>
            <article class="match-card">
              <div class="match-card__top">
                <div>
                  <h3 class="match-card__title"><?= h($match['squadra_casa']) ?> vs <?= h($match['squadra_trasferta']) ?></h3>
                  <p class="meta-line">Calendario: <?= h(admin_totocalcio_match_label($match)) ?></p>
                  <p class="meta-line">Campo: <?= h($match['campo'] ?? 'Da definire') ?> | Chiusura pronostici: <?= h($predictionCutoff ? $predictionCutoff->format('d/m/Y H:i') : 'Da definire') ?> | Pronostici ricevuti: <?= (int)($match['total_predictions'] ?? 0) ?> | Risultato ufficiale: <?= h($officialResult) ?></p>
                </div>

                <div class="switcher-row" style="margin-top: 0;">
                  <span class="status-pill <?= !empty($match['visibile']) ? 'ok' : 'muted' ?>">
                    <?= !empty($match['visibile']) ? 'Attiva' : 'Disattivata' ?>
                  </span>
                  <span class="status-pill <?= totocalcio_is_match_open($match) ? 'warn' : 'info' ?>">
                    <?= totocalcio_is_match_open($match) ? 'Pronostici aperti' : 'Giocata / chiusa' ?>
                  </span>
                </div>
              </div>

              <form method="POST" autocomplete="off">
                <?= csrf_field($csrfKey) ?>
                <input type="hidden" name="action" value="save_match">
                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">

                <div class="match-form-grid">
                  <label class="field">
                    Ordine
                    <input type="number" name="ordine" min="0" step="1" value="<?= (int)($match['ordine'] ?? 0) ?>">
                  </label>

                  <div class="form-actions" style="margin-top: 0;">
                    <label class="toggle-row">
                      <input type="checkbox" name="visibile" value="1" <?= !empty($match['visibile']) ? 'checked' : '' ?>>
                      <span>Mostra in Totocalcio</span>
                    </label>
                    <button class="btn-primary" type="submit">Salva configurazione</button>
                  </div>
                </div>
              </form>

              <form method="POST" onsubmit="return confirm('Rimuovere questa partita dal Totocalcio e cancellare tutti i pronostici collegati?');" style="margin-top: 10px;">
                <?= csrf_field($csrfKey) ?>
                <input type="hidden" name="action" value="delete_match">
                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">
                <button type="submit" class="btn-danger">Rimuovi dalla competizione</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel-card">
      <span class="helper-pill">Classifica</span>
      <h2>Anteprima classifica</h2>
      <?php if (!$selectedCompetition): ?>
        <p style="margin: 0; color: #64748b;">Nessuna competizione selezionata.</p>
      <?php else: ?>
        <p style="margin: 0 0 16px;">Classifica di <strong><?= h($selectedCompetition['nome']) ?></strong>, ordinata per punti, risultati esatti ed esiti corretti. Sono inclusi gli account con flag Totocalcio attivo oppure con accesso assegnato alla competizione.</p>

        <?php if (empty($leaderboard)): ?>
          <p style="margin: 0; color: #64748b;">Nessun account abilitato o autorizzato per questa competizione.</p>
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
      <?php endif; ?>
    </section>
  </main>

  <div id="footer-container"></div>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const accessSearchInput = document.getElementById('competitionAccessSearch');
      const accessSearchCount = document.getElementById('competitionAccessSearchCount');
      const accessOptions = Array.from(document.querySelectorAll('.access-grid .access-option'));

      if (accessSearchInput && accessOptions.length > 0) {
        const applyAccessFilter = () => {
          const query = accessSearchInput.value.trim().toLowerCase();
          let visibleCount = 0;

          accessOptions.forEach((option) => {
            const haystack = option.textContent.toLowerCase();
            const isVisible = query === '' || haystack.includes(query);
            option.classList.toggle('is-hidden', !isVisible);
            if (isVisible) {
              visibleCount++;
            }
          });

          if (accessSearchCount) {
            accessSearchCount.textContent = visibleCount + (visibleCount === 1 ? ' utente visibile' : ' utenti visibili');
          }
        };

        accessSearchInput.addEventListener('input', applyAccessFilter);
        applyAccessFilter();
      }

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
