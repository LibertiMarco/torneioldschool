<?php
require_once __DIR__ . '/includi/security.php';
if (!isset($_SESSION['user_id'])) {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '/totocalcio.php';
    login_remember_redirect($currentPath);
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';
require_once __DIR__ . '/includi/user_features.php';
require_once __DIR__ . '/includi/totocalcio.php';

$userId = (int)$_SESSION['user_id'];
$userRole = (string)($_SESSION['ruolo'] ?? 'user');
$isSysadmin = $userRole === 'sysadmin';
$hasAdminAccess = user_has_admin_access($userRole);
$userFlags = load_user_feature_flags($conn, $userId);
$hasTotocalcioFlag = user_feature_enabled($userFlags, 'totocalcio');
$canAccess = $hasAdminAccess || $hasTotocalcioFlag;
$canParticipate = $hasTotocalcioFlag;
$messages = [];
$errors = [];
$matches = [];
$leaderboard = [];
$competitions = [];
$selectedCompetition = null;
$csrfKey = 'totocalcio_predictions';

if (!$canAccess) {
    http_response_code(403);
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function totocalcio_page_datetime_label(?string $date, ?string $time = null): string
{
    $date = trim((string)$date);
    $time = trim((string)$time);

    if ($date === '') {
        return 'Data da definire';
    }

    $value = $time !== '' ? $date . ' ' . $time : $date;
    $timestamp = strtotime($value);
    if (!$timestamp) {
        return 'Data da definire';
    }

    return $time !== ''
        ? date('d/m/Y H:i', $timestamp)
        : date('d/m/Y', $timestamp);
}

function totocalcio_page_parse_score($value, string $label, array &$errors): ?int
{
    $value = trim((string)$value);
    if ($value === '') {
        $errors[] = 'Completa il campo "' . $label . '".';
        return null;
    }

    if (!ctype_digit($value)) {
        $errors[] = 'Il campo "' . $label . '" deve contenere un numero intero non negativo.';
        return null;
    }

    $score = (int)$value;
    if ($score > 99) {
        $errors[] = 'Il campo "' . $label . '" non puo superare 99.';
        return null;
    }

    return $score;
}

function totocalcio_page_competition_url(string $slug): string
{
    return '/totocalcio.php?competizione=' . rawurlencode($slug);
}

function totocalcio_page_find_competition(array $competitions, string $slug): ?array
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
        $errors[] = 'Il Totocalcio non e disponibile in questo momento.';
    } else {
        $requestedCompetitionSlug = trim((string)($_GET['competizione'] ?? ''));
        $competitions = totocalcio_fetch_competitions($conn, !$isSysadmin);
        $selectedCompetitionId = 0;

        if ($requestedCompetitionSlug !== '') {
            $selectedCompetition = totocalcio_page_find_competition($competitions, $requestedCompetitionSlug);
            if (!$selectedCompetition || (string)$selectedCompetition['slug'] !== $requestedCompetitionSlug) {
                $selectedCompetition = null;
                $errors[] = 'La competizione richiesta non e disponibile.';
            } else {
                $selectedCompetitionId = (int)($selectedCompetition['id'] ?? 0);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
            if (!$canParticipate) {
                $errors[] = 'Questo account puo visualizzare la pagina ma non partecipare al Totocalcio.';
            } elseif ($selectedCompetitionId <= 0) {
                $errors[] = 'Nessuna competizione disponibile per salvare il pronostico.';
            } else {
                csrf_require($csrfKey);

                $action = (string)($_POST['action'] ?? '');
                if ($action === 'save_prediction') {
                    $matchId = (int)($_POST['match_id'] ?? 0);
                    $sign = (string)($_POST['segno'] ?? '');
                    $predHome = totocalcio_page_parse_score($_POST['gol_casa_previsti'] ?? '', 'Gol casa previsti', $errors);
                    $predAway = totocalcio_page_parse_score($_POST['gol_trasferta_previsti'] ?? '', 'Gol trasferta previsti', $errors);
                    $match = totocalcio_fetch_match_by_id($conn, $matchId, $selectedCompetitionId);

                    if (!in_array($sign, ['1', 'X', '2'], true)) {
                        $errors[] = 'Seleziona uno tra 1, X e 2.';
                    }

                    if (!$match || empty($match['visibile']) || empty($match['competizione_attiva'])) {
                        $errors[] = 'La partita selezionata non e disponibile.';
                    } elseif (!totocalcio_is_match_open($match)) {
                        $errors[] = 'I pronostici per questa partita sono chiusi.';
                    }

                    if ($predHome !== null && $predAway !== null) {
                        $computedSign = totocalcio_compute_sign($predHome, $predAway);
                        if ($sign !== '' && $sign !== $computedSign) {
                            $errors[] = 'Il segno selezionato deve essere coerente con il risultato esatto inserito.';
                        }
                    }

                    if (empty($errors) && !totocalcio_save_prediction($conn, $matchId, $userId, $sign, $predHome, $predAway)) {
                        $errors[] = 'Impossibile salvare il pronostico. Riprova.';
                    } elseif (empty($errors)) {
                        $messages[] = 'Pronostico salvato correttamente.';
                    }
                }
            }
        }

        if ($selectedCompetitionId > 0) {
            $matches = totocalcio_fetch_matches($conn, true, $userId, $selectedCompetitionId);
            $leaderboard = totocalcio_fetch_leaderboard($conn, $selectedCompetitionId);
        }
    }
}

$accessibleCompetitionCount = count($competitions);
$accessibleMatchCount = 0;
foreach ($competitions as $competition) {
    $accessibleMatchCount += (int)($competition['total_matches'] ?? 0);
}

$myRank = null;
$myRow = null;
foreach ($leaderboard as $index => $row) {
    if ((int)($row['id'] ?? 0) === $userId) {
        $myRank = $index + 1;
        $myRow = $row;
        break;
    }
}

$selectedCompetitionSlug = (string)($selectedCompetition['slug'] ?? '');
$selectedCompetitionName = (string)($selectedCompetition['nome'] ?? 'Totocalcio');
$pageTitle = $selectedCompetitionSlug !== '' ? 'Totocalcio - ' . $selectedCompetitionName : 'Totocalcio';
$baseUrl = seo_base_url();
$canonicalPath = '/totocalcio.php' . ($selectedCompetitionSlug !== '' ? '?competizione=' . rawurlencode($selectedCompetitionSlug) : '');
$seo = [
    'title' => $pageTitle,
    'description' => 'Pronostici Totocalcio con esito, risultato esatto e classifica punti per competizione.',
    'url' => $baseUrl . $canonicalPath,
    'canonical' => $baseUrl . $canonicalPath,
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <?php render_seo_tags($seo); ?>
  <link rel="stylesheet" href="/style.min.css">
  <style>
    body { background: #f4f6fb; }
    .totocalcio-page { max-width: 1180px; margin: 0 auto; padding: 110px 20px 60px; }
    .hero-card, .panel-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
      box-shadow: 0 18px 36px rgba(15, 31, 51, 0.08);
    }
    .hero-card { padding: 28px; margin-bottom: 18px; }
    .panel-card { padding: 22px; }
    .eyebrow {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      background: #e8edf5;
      color: #15293e;
      font-size: 0.82rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 12px;
    }
    .hero-card h1 { margin: 0 0 10px; color: #15293e; font-size: 2.1rem; }
    .hero-card p { margin: 0 0 10px; color: #4c5b71; line-height: 1.6; }
    .hero-grid { display: grid; grid-template-columns: 1.25fr 0.95fr; gap: 18px; margin-bottom: 20px; }
    .stats-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
    .stat-box {
      border-radius: 16px;
      padding: 18px;
      background: linear-gradient(135deg, #15293e 0%, #23415f 100%);
      color: #fff;
    }
    .stat-box strong { display: block; font-size: 1.75rem; line-height: 1; margin-bottom: 8px; }
    .stat-box span { color: rgba(255,255,255,0.82); font-size: 0.94rem; }
    .msg { padding: 12px 14px; border-radius: 12px; margin-bottom: 12px; font-weight: 700; }
    .msg.ok { background: #e8f6ef; color: #065f46; border: 1px solid #34d399; }
    .msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
    .warning-card {
      margin-top: 14px;
      padding: 14px 16px;
      border-radius: 12px;
      background: #fff7ed;
      border: 1px solid #fdba74;
      color: #9a3412;
      font-weight: 600;
    }
    .competition-switcher {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 16px;
    }
    .competition-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 999px;
      border: 1px solid #cbd5e1;
      background: #fff;
      color: #1e293b;
      font-weight: 700;
      text-decoration: none;
    }
    .competition-pill.active {
      background: #15293e;
      color: #fff;
      border-color: #15293e;
    }
    .competition-pill small { font-size: 0.78rem; opacity: 0.82; }
    .competition-overview {
      display: grid;
      gap: 14px;
    }
    .competition-entry {
      border: 1px solid #dce4ef;
      border-radius: 16px;
      padding: 18px;
      background: #f8fafc;
    }
    .competition-entry__top {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 12px;
    }
    .competition-entry__title { margin: 0 0 6px; color: #15293e; font-size: 1.1rem; }
    .competition-entry__meta { margin: 0; color: #64748b; font-size: 0.94rem; }
    .competition-entry__actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 14px;
    }
    .section-grid { display: grid; grid-template-columns: 1.35fr 0.95fr; gap: 18px; align-items: start; }
    .panel-card h2, .panel-card h3 { margin: 0 0 12px; color: #15293e; }
    .panel-card p { color: #4c5b71; line-height: 1.55; }
    .rule-list { margin: 0; padding-left: 18px; color: #475569; line-height: 1.7; }
    .match-list { display: grid; gap: 16px; }
    .match-card {
      border: 1px solid #dce4ef;
      border-radius: 16px;
      padding: 18px;
      background: #f8fafc;
    }
    .match-card__top {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 14px;
    }
    .match-card__title { margin: 0 0 6px; color: #15293e; font-size: 1.15rem; }
    .meta-line { margin: 0; color: #64748b; font-size: 0.94rem; }
    .pill-row { display: flex; flex-wrap: wrap; gap: 8px; }
    .status-pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
    .status-pill.ok { background: #dcfce7; color: #166534; }
    .status-pill.warn { background: #fef3c7; color: #92400e; }
    .status-pill.muted { background: #e2e8f0; color: #334155; }
    .prediction-form { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; align-items: end; }
    .field { display: flex; flex-direction: column; gap: 8px; color: #15293e; font-weight: 700; }
    .field input[type="number"] {
      width: 100%;
      padding: 11px 12px;
      border: 1px solid #d7dce5;
      border-radius: 10px;
      background: #fff;
    }
    .sign-options { display: flex; gap: 10px; flex-wrap: wrap; }
    .sign-option {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border: 1px solid #d7dce5;
      border-radius: 10px;
      background: #fff;
      font-weight: 700;
      color: #15293e;
    }
    .match-actions { display: flex; justify-content: flex-end; }
    .saved-prediction, .scored-box {
      margin-top: 14px;
      padding: 12px 14px;
      border-radius: 12px;
      background: #fff;
      border: 1px solid #dce4ef;
      color: #334155;
    }
    .scored-box.ok { border-color: #34d399; background: #ecfdf5; color: #065f46; }
    .scored-box.muted { background: #f8fafc; color: #475569; }
    .leader-table { width: 100%; border-collapse: collapse; }
    .leader-table th, .leader-table td { padding: 12px 10px; border-bottom: 1px solid #e5eaf0; text-align: left; }
    .leader-table th { background: #f8fafc; color: #15293e; }
    .leader-table td:last-child, .leader-table th:last-child { text-align: right; }
    .empty-state { margin: 0; color: #64748b; }
    @media (max-width: 980px) {
      .hero-grid, .section-grid { grid-template-columns: 1fr; }
      .match-actions { justify-content: flex-start; }
    }
    @media (max-width: 720px) {
      .stats-grid { grid-template-columns: 1fr; }
      .prediction-form { grid-template-columns: 1fr; }
      .match-card__top, .competition-entry__top { flex-direction: column; }
      .leader-table { display: block; overflow-x: auto; white-space: nowrap; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/includi/header.php'; ?>

<main class="totocalcio-page">
  <section class="hero-grid">
    <article class="hero-card">
      <span class="eyebrow">Pronostici</span>
      <h1><?= h($selectedCompetition ? $selectedCompetitionName : 'Totocalcio') ?></h1>

      <?php if ($canAccess): ?>
        <?php if ($selectedCompetition): ?>
          <p>Scegli il segno <strong>1</strong>, <strong>X</strong> o <strong>2</strong>, inserisci anche il risultato esatto e salva il tuo pronostico per ogni partita pubblicata per la competizione selezionata.</p>
          <p>Regole punteggio: <strong>+1</strong> per l esito corretto, <strong>+3</strong> per il risultato esatto. Se prendi il risultato esatto fai <strong>4 punti totali</strong> sulla partita.</p>
        <?php else: ?>
          <p>Qui trovi l elenco delle competizioni Totocalcio a cui puoi accedere. Scegline una per aprire schedina, pronostici e classifica dedicata.</p>
          <p>Ogni competizione mantiene partite e classifica separate. Il menu Totocalcio ora ti porta sempre prima a questa lista.</p>
        <?php endif; ?>

        <?php if (!$canParticipate): ?>
          <div class="warning-card">Il tuo account puo entrare qui solo perche hai privilegi di amministrazione, ma per partecipare devi avere il flag Totocalcio attivo.</div>
        <?php endif; ?>

        <?php if ($selectedCompetition && !empty($competitions)): ?>
          <div class="competition-switcher">
            <?php foreach ($competitions as $competition): ?>
              <?php
                $competitionSlug = (string)($competition['slug'] ?? '');
                $isActiveCompetition = $selectedCompetition && (int)$selectedCompetition['id'] === (int)$competition['id'];
              ?>
              <a class="competition-pill <?= $isActiveCompetition ? 'active' : '' ?>" href="<?= h(totocalcio_page_competition_url($competitionSlug)) ?>">
                <span><?= h($competition['nome']) ?></span>
                <small><?= (int)($competition['total_matches'] ?? 0) ?> partite</small>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <p>Non hai i permessi per accedere a questa sezione.</p>
      <?php endif; ?>
    </article>

    <aside class="panel-card">
      <span class="eyebrow">Il tuo stato</span>
      <h3>Riepilogo rapido</h3>
      <div class="stats-grid">
        <div class="stat-box">
          <strong><?= $selectedCompetition ? (int)count($matches) : $accessibleCompetitionCount ?></strong>
          <span><?= $selectedCompetition ? 'Partite pubblicate' : 'Competizioni accessibili' ?></span>
        </div>
        <div class="stat-box">
          <strong><?= $selectedCompetition ? ($myRow ? (int)$myRow['punti_totali'] : 0) : $accessibleMatchCount ?></strong>
          <span><?= $selectedCompetition ? 'Punti nella competizione' : 'Partite totali visibili' ?></span>
        </div>
        <div class="stat-box">
          <strong><?= $selectedCompetition ? ($myRank ?? '-') : ($canParticipate ? 'SI' : 'NO') ?></strong>
          <span><?= $selectedCompetition ? 'Posizione in classifica' : 'Pronostici abilitati' ?></span>
        </div>
      </div>
    </aside>
  </section>

  <?php foreach ($messages as $message): ?>
    <div class="msg ok"><?= h($message) ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $error): ?>
    <div class="msg err"><?= h($error) ?></div>
  <?php endforeach; ?>

  <?php if ($selectedCompetition === null): ?>
    <section class="panel-card">
      <span class="eyebrow">Competizioni</span>
      <h2>Lista Totocalcio accessibili</h2>
      <p style="margin: 0 0 16px;">Seleziona una competizione per entrare nella sua area dedicata.<?php if ($isSysadmin): ?> Come sysadmin vedi anche le competizioni non pubbliche.<?php endif; ?></p>

      <?php if (empty($competitions)): ?>
        <p class="empty-state">Non hai competizioni Totocalcio disponibili in questo momento.</p>
      <?php else: ?>
        <div class="competition-overview">
          <?php foreach ($competitions as $competition): ?>
            <?php
              $competitionSlug = (string)($competition['slug'] ?? '');
              $competitionIsActive = !empty($competition['attiva']);
            ?>
            <article class="competition-entry">
              <div class="competition-entry__top">
                <div>
                  <h3 class="competition-entry__title"><?= h($competition['nome']) ?></h3>
                  <p class="competition-entry__meta">Partite: <?= (int)($competition['total_matches'] ?? 0) ?> | Partite attive: <?= (int)($competition['active_matches'] ?? 0) ?> | Pronostici salvati: <?= (int)($competition['total_predictions'] ?? 0) ?></p>
                </div>

                <div class="pill-row">
                  <span class="status-pill <?= $competitionIsActive ? 'ok' : 'muted' ?>">
                    <?= $competitionIsActive ? 'Pubblica' : 'Nascosta' ?>
                  </span>
                </div>
              </div>

              <div class="competition-entry__actions">
                <a class="btn-primary" href="<?= h(totocalcio_page_competition_url($competitionSlug)) ?>">Apri competizione</a>
                <span class="helper-text">URL: <code>/totocalcio.php?competizione=<?= h($competitionSlug) ?></code></span>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php else: ?>
  <section class="section-grid">
    <div class="panel-card">
      <span class="eyebrow">Schedina</span>
      <h2>Partite del Totocalcio</h2>
      <p style="margin: 0 0 16px;">Ogni pronostico resta modificabile finche la partita e aperta. Quando la partita viene segnata come giocata nel calendario ufficiale, qui vedrai automaticamente risultato e punti ottenuti. Se il risultato ufficiale cambia dopo, i punti vengono ricalcolati sui nuovi gol.</p>

      <?php if (empty($competitions)): ?>
        <p class="empty-state">Non ci sono competizioni Totocalcio attive in questo momento.</p>
      <?php elseif (empty($matches)): ?>
        <p class="empty-state">Non ci sono ancora partite selezionate per questa competizione.</p>
      <?php else: ?>
        <div class="match-list">
          <?php foreach ($matches as $match): ?>
            <?php
              $prediction = null;
              if (!empty($match['user_segno'])) {
                  $prediction = [
                      'segno' => $match['user_segno'],
                      'gol_casa_previsti' => $match['user_gol_casa_previsti'],
                      'gol_trasferta_previsti' => $match['user_gol_trasferta_previsti'],
                  ];
              }
              $evaluation = totocalcio_evaluate_prediction($match, $prediction);
              $isOpen = totocalcio_is_match_open($match);
              $officialResult = totocalcio_is_result_available($match)
                  ? (int)$match['gol_casa_reale'] . ' - ' . (int)$match['gol_trasferta_reale']
                  : 'In attesa';
            ?>
            <article class="match-card">
              <div class="match-card__top">
                <div>
                  <h3 class="match-card__title"><?= h($match['squadra_casa']) ?> vs <?= h($match['squadra_trasferta']) ?></h3>
                  <p class="meta-line">Data partita: <?= h(totocalcio_page_datetime_label($match['data_partita'] ?? null, $match['ora_partita'] ?? null)) ?></p>
                  <p class="meta-line">Risultato ufficiale: <?= h($officialResult) ?> | Pronostici inviati: <?= (int)($match['total_predictions'] ?? 0) ?></p>
                </div>

                <div class="pill-row">
                  <span class="status-pill <?= $isOpen ? 'warn' : 'muted' ?>">
                    <?= $isOpen ? 'Pronostici aperti' : 'Pronostici chiusi' ?>
                  </span>
                  <span class="status-pill <?= $evaluation['risultato_esatto'] ? 'ok' : 'muted' ?>">
                    <?= $evaluation['risultato_esatto'] ? 'Risultato esatto preso' : 'Risultato esatto non assegnato' ?>
                  </span>
                </div>
              </div>

              <?php if ($prediction !== null): ?>
                <div class="saved-prediction">
                  Il tuo pronostico: <strong><?= h($prediction['segno']) ?></strong> con risultato esatto <strong><?= (int)$prediction['gol_casa_previsti'] ?> - <?= (int)$prediction['gol_trasferta_previsti'] ?></strong>.
                </div>
              <?php endif; ?>

              <?php if ($evaluation['is_scored']): ?>
                <div class="scored-box <?= $evaluation['punti_totali'] > 0 ? 'ok' : 'muted' ?>">
                  Punti assegnati: <strong><?= (int)$evaluation['punti_totali'] ?></strong>
                  <?php if ($evaluation['esito_corretto']): ?>
                    | esito corretto +1
                  <?php endif; ?>
                  <?php if ($evaluation['risultato_esatto']): ?>
                    | risultato esatto +3
                  <?php endif; ?>
                </div>
              <?php elseif (totocalcio_is_result_available($match) && $prediction === null): ?>
                <div class="scored-box muted">Nessun pronostico inviato per questa partita.</div>
              <?php endif; ?>

              <?php if ($canParticipate && $isOpen): ?>
                <form method="POST" style="margin-top: 14px;">
                  <?= csrf_field($csrfKey) ?>
                  <input type="hidden" name="action" value="save_prediction">
                  <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">
                  <div class="prediction-form">
                    <label class="field">
                      Segno
                      <span class="sign-options">
                        <?php foreach (['1', 'X', '2'] as $signOption): ?>
                          <label class="sign-option">
                            <input type="radio" name="segno" value="<?= $signOption ?>" <?= ($prediction['segno'] ?? '') === $signOption ? 'checked' : '' ?> required>
                            <span><?= $signOption ?></span>
                          </label>
                        <?php endforeach; ?>
                      </span>
                    </label>

                    <label class="field">
                      Gol casa previsti
                      <input type="number" name="gol_casa_previsti" min="0" max="99" step="1" value="<?= $prediction !== null ? (int)$prediction['gol_casa_previsti'] : '' ?>" required>
                    </label>

                    <label class="field">
                      Gol trasferta previsti
                      <input type="number" name="gol_trasferta_previsti" min="0" max="99" step="1" value="<?= $prediction !== null ? (int)$prediction['gol_trasferta_previsti'] : '' ?>" required>
                    </label>
                  </div>
                  <div class="match-actions" style="margin-top: 14px;">
                    <button class="btn-primary" type="submit"><?= $prediction !== null ? 'Aggiorna pronostico' : 'Salva pronostico' ?></button>
                  </div>
                </form>
              <?php elseif (!$canParticipate): ?>
                <div class="warning-card">Partecipazione disabilitata per questo account.</div>
              <?php else: ?>
                <div class="scored-box muted" style="margin-top: 14px;">I pronostici per questa partita non sono piu modificabili.</div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel-card">
      <span class="eyebrow">Classifica</span>
      <h2>Classifica Totocalcio</h2>
      <p style="margin: 0 0 12px;">La classifica e riferita alla competizione selezionata. Sono inclusi solo gli account con flag Totocalcio attivo. Ordinamento: punti, risultati esatti, esiti corretti.</p>

      <ul class="rule-list" style="margin-bottom: 18px;">
        <li>Esito corretto: +1 punto.</li>
        <li>Risultato esatto: +3 punti bonus.</li>
        <li>Totale massimo per partita: 4 punti.</li>
      </ul>

      <?php if (empty($competitions)): ?>
        <p class="empty-state">Ancora nessuna competizione attiva.</p>
      <?php elseif (empty($leaderboard)): ?>
        <p class="empty-state">Ancora nessun partecipante abilitato.</p>
      <?php else: ?>
        <table class="leader-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Utente</th>
              <th>Esiti</th>
              <th>Esatti</th>
              <th>Punti</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leaderboard as $index => $row): ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td>
                  <strong><?= h($row['display_name']) ?></strong>
                  <?php if ((int)$row['id'] === $userId): ?>
                    <span style="color:#64748b;">(tu)</span>
                  <?php endif; ?>
                </td>
                <td><?= (int)$row['esiti_corretti'] ?></td>
                <td><?= (int)$row['risultati_esatti'] ?></td>
                <td><strong><?= (int)$row['punti_totali'] ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<div id="footer-container"></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const footer = document.getElementById('footer-container');
  if (!footer) return;
  fetch('/includi/footer.html')
    .then(response => response.text())
    .then(html => { footer.innerHTML = html; })
    .catch(err => console.error('Errore caricamento footer:', err));
});
</script>
</body>
</html>
