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
$hasExplicitCompetitionAccess = false;
$grantedCompetitionIds = [];
$canAccess = false;
$canParticipate = false;
$messages = [];
$errors = [];
$matches = [];
$leaderboard = [];
$predictionMatrix = [];
$drawOutcomePoints = 1;
$exactBonusPoints = 3;
$maxPointsPerMatch = 4;
$antepostCategories = totocalcio_antepost_categories();
$antepostTeams = [];
$antepostPredictions = [];
$antepostPredictionMatrix = [];
$antepostTournament = '';
$supportsAntepost = false;
$antepostIsOpen = true;
$antepostFirstMatchStart = null;
$savedPredictionsCount = 0;
$matchesByGiornata = [];
$antepostRows = [];
$competitions = [];
$selectedCompetition = null;
$csrfKey = 'totocalcio_predictions';

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

function totocalcio_page_match_result_label(array $match): string
{
    if (!totocalcio_is_result_available($match)) {
        return 'Risultato in attesa';
    }

    return (int)($match['gol_casa_reale'] ?? 0) . ' - ' . (int)($match['gol_trasferta_reale'] ?? 0);
}

function totocalcio_page_group_matches_by_giornata(array $matches): array
{
    $groups = [];

    foreach ($matches as $match) {
        $giornata = $match['giornata'] ?? null;
        $hasGiornata = $giornata !== null && $giornata !== '';
        $groupKey = $hasGiornata ? 'giornata-' . (int)$giornata : 'senza-giornata';

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'key' => $groupKey,
                'label' => $hasGiornata ? 'Giornata ' . (int)$giornata : 'Partite senza giornata',
                'matches' => [],
            ];
        }

        $groups[$groupKey]['matches'][] = $match;
    }

    return array_values($groups);
}

if ($conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');

    if (!totocalcio_ensure_tables($conn)) {
        $errors[] = 'Il Totocalcio non e disponibile in questo momento.';
    } else {
        $grantedCompetitionIds = totocalcio_fetch_user_granted_competition_ids($conn, $userId);
        $hasExplicitCompetitionAccess = !empty($grantedCompetitionIds);
        $canAccess = $hasAdminAccess || $hasTotocalcioFlag || $hasExplicitCompetitionAccess;
        $canParticipate = $hasTotocalcioFlag || $hasExplicitCompetitionAccess;

        if (!$canAccess) {
            http_response_code(403);
        }

        $requestedCompetitionSlug = trim((string)($_GET['competizione'] ?? ''));
        $allCompetitions = totocalcio_fetch_competitions($conn, !$hasAdminAccess);
        foreach ($allCompetitions as $competition) {
            if (totocalcio_user_can_access_competition($competition, $hasAdminAccess, $hasTotocalcioFlag, $grantedCompetitionIds)) {
                $competitions[] = $competition;
            }
        }

        $selectedCompetitionId = 0;

        if ($requestedCompetitionSlug !== '') {
            $selectedCompetition = totocalcio_page_find_competition($competitions, $requestedCompetitionSlug);
            if (!$selectedCompetition || (string)$selectedCompetition['slug'] !== $requestedCompetitionSlug) {
                $selectedCompetition = null;
                $errors[] = 'La competizione richiesta non e disponibile.';
            } else {
                $selectedCompetitionId = (int)($selectedCompetition['id'] ?? 0);
                $hasSelectedCompetitionGrant = in_array($selectedCompetitionId, $grantedCompetitionIds, true);
                $canParticipate = $hasTotocalcioFlag || $hasSelectedCompetitionGrant;
                $drawOutcomePoints = totocalcio_competition_draw_points($selectedCompetition);
                $exactBonusPoints = totocalcio_competition_exact_bonus($selectedCompetition);
                $maxPointsPerMatch = max(1, $drawOutcomePoints) + $exactBonusPoints;
                $supportsAntepost = totocalcio_competition_has_antepost($selectedCompetition);

                if ($supportsAntepost) {
                    $antepostTournament = totocalcio_resolve_competition_tournament($conn, $selectedCompetition);
                    $antepostTeams = totocalcio_fetch_antepost_teams($conn, $selectedCompetition);
                    $antepostFirstMatchStart = totocalcio_fetch_first_match_start($conn, $selectedCompetitionId);
                    $antepostIsOpen = totocalcio_antepost_is_open($conn, $selectedCompetitionId);
                    if (!empty($antepostTeams)) {
                        $antepostTournament = trim((string)($antepostTeams[0]['torneo'] ?? $antepostTournament));
                    }
                }
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
                if ($action === 'save_antepost') {
                    if (!$supportsAntepost) {
                        $errors[] = 'Gli antepost non sono disponibili per questa competizione.';
                    } elseif (!$antepostIsOpen) {
                        $errors[] = 'Gli antepost sono bloccati: la prima partita della competizione e gia iniziata.';
                    } elseif (empty($antepostTeams)) {
                        $errors[] = 'Non ci sono squadre disponibili per gli antepost di questa competizione.';
                    } else {
                        $submittedChoices = $_POST['antepost_choices'] ?? [];
                        $submittedChoices = is_array($submittedChoices) ? $submittedChoices : [];
                        $allowedTeamNames = [];
                        foreach ($antepostTeams as $team) {
                            $teamName = trim((string)($team['nome'] ?? ''));
                            if ($teamName !== '') {
                                $allowedTeamNames[] = $teamName;
                            }
                        }

                        $normalizedChoices = [];
                        foreach ($antepostCategories as $categoryKey => $categoryLabel) {
                            $selectedTeam = trim((string)($submittedChoices[$categoryKey] ?? ''));
                            if ($selectedTeam === '') {
                                $errors[] = 'Seleziona una squadra per "' . $categoryLabel . '".';
                                continue;
                            }

                            $matchedTeam = null;
                            foreach ($allowedTeamNames as $allowedTeam) {
                                if (strcasecmp($allowedTeam, $selectedTeam) === 0) {
                                    $matchedTeam = $allowedTeam;
                                    break;
                                }
                            }

                            if ($matchedTeam === null) {
                                $errors[] = 'La squadra scelta per "' . $categoryLabel . '" non e valida.';
                                continue;
                            }

                            $normalizedChoices[$categoryKey] = $matchedTeam;
                        }

                        if (empty($errors) && !totocalcio_save_antepost_predictions($conn, $selectedCompetitionId, $userId, $normalizedChoices)) {
                            $errors[] = 'Impossibile salvare gli antepost. Riprova.';
                        } elseif (empty($errors)) {
                            $messages[] = 'Antepost salvati correttamente.';
                        }
                    }
                } elseif ($action === 'save_prediction') {
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
            $matchesByGiornata = totocalcio_page_group_matches_by_giornata($matches);
            $leaderboard = totocalcio_fetch_leaderboard($conn, $selectedCompetitionId);
            $predictionMatrix = totocalcio_fetch_prediction_matrix($conn, $selectedCompetitionId);
            if ($supportsAntepost) {
                $antepostPredictions = totocalcio_fetch_user_antepost_predictions($conn, $selectedCompetitionId, $userId);
                $antepostPredictionMatrix = totocalcio_fetch_competition_antepost_predictions($conn, $selectedCompetitionId);
            }
            foreach ($matches as $matchRow) {
                if (!empty($matchRow['user_segno'])) {
                    $savedPredictionsCount++;
                }
            }
        }
    }
}

if (!$canAccess) {
    http_response_code(403);
}

$accessibleCompetitionCount = count($competitions);

$myRank = null;
$myRow = null;
foreach ($leaderboard as $index => $row) {
    if ((int)($row['id'] ?? 0) === $userId) {
        $myRank = $index + 1;
        $myRow = $row;
        break;
    }
}

if ($supportsAntepost && !empty($antepostPredictionMatrix)) {
    foreach ($leaderboard as $row) {
        $rowUserId = (int)($row['id'] ?? 0);
        if (!empty($antepostPredictionMatrix[$rowUserId])) {
            $antepostRows[] = $row;
        }
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
    body {
      background: #f4f6fb;
      overflow-x: hidden;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .totocalcio-page {
      flex: 1 0 auto;
      width: 100%;
      max-width: 1180px;
      margin: 0 auto;
      padding: 110px 20px 60px;
      overflow-x: hidden;
    }
    #footer-container {
      width: 100%;
      margin-top: auto;
      flex-shrink: 0;
    }
    #footer-container .site-footer {
      position: static !important;
      width: 100%;
    }
    .hero-card, .panel-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
      box-shadow: 0 18px 36px rgba(15, 31, 51, 0.08);
    }
    .hero-card { padding: 28px; margin-bottom: 18px; }
    .hero-card--compact { padding: 22px 24px 18px; }
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
    .hero-grid > * { min-width: 0; }
    .stats-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
    .stat-box {
      border-radius: 16px;
      padding: 18px;
      background: linear-gradient(135deg, #15293e 0%, #23415f 100%);
      color: #fff;
    }
    .stat-box strong { display: block; font-size: 1.75rem; line-height: 1; margin-bottom: 8px; }
    .stat-box span { color: rgba(255,255,255,0.82); font-size: 0.94rem; }
    .status-panel { padding: 18px; }
    .status-panel .eyebrow { margin-bottom: 8px; }
    .status-panel h3 { margin-bottom: 10px; }
    .status-panel .stats-grid {
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 8px;
      margin-top: 10px;
    }
    .status-panel .stat-box {
      padding: 14px 12px;
      border-radius: 14px;
    }
    .status-panel .stat-box strong {
      font-size: 1.4rem;
      margin-bottom: 5px;
    }
    .status-panel .stat-box span {
      font-size: 0.82rem;
      line-height: 1.3;
    }
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
    .competition-switcher.compact {
      gap: 8px;
      margin-top: 12px;
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
    .competition-switcher.compact .competition-pill {
      padding: 8px 12px;
      font-size: 0.92rem;
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
    .field input[type="number"],
    .field select {
      width: 100%;
      padding: 11px 12px;
      border: 1px solid #d7dce5;
      border-radius: 10px;
      background: #fff;
    }
    .antepost-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .antepost-summary {
      margin-bottom: 18px;
      padding: 14px 16px;
      border: 1px solid #dce4ef;
      border-radius: 14px;
      background: #f8fafc;
    }
    .antepost-summary strong,
    .antepost-summary span { display: block; }
    .antepost-summary span { margin-top: 5px; color: #64748b; font-size: 0.92rem; line-height: 1.45; }
    .antepost-tournament {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 14px;
      padding: 8px 12px;
      border-radius: 999px;
      background: #e8edf5;
      color: #15293e;
      font-weight: 800;
      font-size: 0.84rem;
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
    .totocalcio-tabs {
      margin-bottom: 18px;
      overflow-x: auto;
      flex-wrap: nowrap;
      padding-bottom: 4px;
      -webkit-overflow-scrolling: touch;
    }
    .totocalcio-tabs .tab-button {
      flex: 0 0 auto;
      white-space: nowrap;
    }
    .tab-section { min-width: 0; }
    .tab-section > .panel-card { margin-top: 0; min-width: 0; overflow: hidden; }
    .prediction-picker {
      margin-bottom: 16px;
      max-width: 320px;
    }
    .prediction-group { margin-top: 18px; }
    .prediction-group:first-child { margin-top: 0; }
    .prediction-group__title {
      margin: 0 0 10px;
      color: #15293e;
      font-size: 1rem;
    }
    .prediction-view-panel { display: none; }
    .prediction-view-panel.active { display: block; }
    .prediction-matrix-wrap {
      width: 100%;
      overflow-x: auto;
      max-width: 100%;
      border: 1px solid #dce4ef;
      border-radius: 16px;
      background: #fff;
      overscroll-behavior-x: contain;
    }
    .prediction-matrix {
      width: max-content;
      min-width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    .prediction-matrix th, .prediction-matrix td {
      min-width: 170px;
      padding: 12px 10px;
      border-right: 1px solid #e5eaf0;
      border-bottom: 1px solid #e5eaf0;
      vertical-align: top;
      background: #fff;
    }
    .prediction-matrix th:last-child, .prediction-matrix td:last-child { border-right: 0; }
    .prediction-matrix tbody tr:last-child td { border-bottom: 0; }
    .prediction-matrix thead th {
      position: sticky;
      top: 0;
      z-index: 2;
      background: #f8fafc;
      color: #15293e;
      text-align: left;
    }
    .prediction-matrix__user {
      position: sticky;
      left: 0;
      z-index: 1;
      min-width: 220px;
      background: #f8fafc;
    }
    .prediction-matrix thead .prediction-matrix__user { z-index: 3; }
    .prediction-matrix__match strong,
    .prediction-matrix__user strong,
    .prediction-matrix__cell strong {
      display: block;
      color: #15293e;
    }
    .prediction-matrix__meta,
    .prediction-matrix__user span,
    .prediction-matrix__cell span {
      display: block;
      margin-top: 4px;
      color: #64748b;
      font-size: 0.85rem;
      line-height: 1.4;
    }
    .prediction-matrix__cell.empty {
      text-align: center;
      color: #94a3b8;
      font-weight: 800;
    }
    .antepost-table-wrap {
      width: 100%;
      overflow-x: auto;
      max-width: 100%;
      margin-top: 18px;
      border: 1px solid #dce4ef;
      border-radius: 16px;
      background: #fff;
      overscroll-behavior-x: contain;
    }
    .antepost-table {
      width: 100%;
      min-width: 760px;
      border-collapse: collapse;
    }
    .antepost-table th,
    .antepost-table td {
      padding: 12px 10px;
      border-bottom: 1px solid #e5eaf0;
      text-align: left;
      vertical-align: top;
    }
    .antepost-table th {
      background: #f8fafc;
      color: #15293e;
    }
    .antepost-table tbody tr:last-child td { border-bottom: 0; }
    .empty-state { margin: 0; color: #64748b; }
    @media (max-width: 980px) {
      .hero-grid, .section-grid { grid-template-columns: 1fr; }
      .match-actions { justify-content: flex-start; }
    }
    @media (max-width: 720px) {
      .stats-grid { grid-template-columns: 1fr; }
      .prediction-form { grid-template-columns: 1fr; }
      .antepost-grid { grid-template-columns: 1fr; }
      .hero-card--compact { padding: 18px 18px 14px; }
      .totocalcio-tabs { gap: 8px; }
      .prediction-picker { max-width: 100%; }
      .competition-switcher.compact .competition-pill {
        width: 100%;
        justify-content: space-between;
      }
      .match-card__top, .competition-entry__top { flex-direction: column; }
      .leader-table { display: block; overflow-x: auto; white-space: nowrap; }
      .prediction-matrix__user { min-width: 180px; }
      .prediction-matrix th, .prediction-matrix td { min-width: 150px; }
      .status-panel { padding: 16px; }
      .status-panel .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .status-panel .stat-box { padding: 12px 10px; }
      .status-panel .stat-box strong { font-size: 1.2rem; }
      .status-panel .stat-box span { font-size: 0.78rem; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/includi/header.php'; ?>

<main class="totocalcio-page">
  <section class="hero-grid">
    <article class="hero-card <?= $selectedCompetition ? 'hero-card--compact' : '' ?>">
      <span class="eyebrow">Pronostici</span>
      <h1><?= h($selectedCompetition ? $selectedCompetitionName : 'Totocalcio') ?></h1>

      <?php if ($canAccess): ?>
        <?php if ($selectedCompetition): ?>
          <p>Scegli il segno <strong>1</strong>, <strong>X</strong> o <strong>2</strong>, inserisci anche il risultato esatto e salva il tuo pronostico per ogni partita pubblicata per la competizione selezionata.</p>
          <p>
            Regole punteggio:
            <?php if ($drawOutcomePoints > 1): ?>
              <strong>+1</strong> per l esito corretto, ma se indovini un pareggio fai <strong>+<?= $drawOutcomePoints ?></strong> sull esito <strong>X</strong>;
            <?php else: ?>
              <strong>+1</strong> per l esito corretto;
            <?php endif; ?>
            il risultato esatto vale <strong>+<?= $exactBonusPoints ?></strong>. Il massimo per partita e <strong><?= $maxPointsPerMatch ?> punti</strong>.
          </p>
          <p>Usa i pulsanti qui sotto per passare da classifica generale, scelte della settimana e tabella completa dei pronostici di tutti i partecipanti.<?php if ($supportsAntepost): ?> In piu trovi il tab antepost con le scelte su Regular Season, Coppa Gold, Coppa Silver e squadra capocannoniere.<?php endif; ?></p>
        <?php else: ?>
          <p>Qui trovi l elenco delle competizioni Totocalcio a cui puoi accedere. Scegline una per aprire schedina, pronostici e classifica dedicata.</p>
        <?php endif; ?>

        <?php if (!$canParticipate): ?>
          <div class="warning-card">Il tuo account puo entrare qui solo perche hai privilegi di amministrazione, ma per partecipare devi avere il flag Totocalcio attivo oppure un accesso assegnato alla competizione.</div>
        <?php endif; ?>

        <?php if ($selectedCompetition && !empty($competitions)): ?>
          <div class="competition-switcher <?= $selectedCompetition ? 'compact' : '' ?>">
            <?php foreach ($competitions as $competition): ?>
              <?php
                $competitionSlug = (string)($competition['slug'] ?? '');
                $isActiveCompetition = $selectedCompetition && (int)$selectedCompetition['id'] === (int)$competition['id'];
              ?>
              <?php if ($isActiveCompetition): ?>
                <span class="competition-pill active">
                  <span><?= h($competition['nome']) ?></span>
                  <small><?= !empty($competition['accesso_pubblico']) ? 'Pubblica' : 'Riservata' ?></small>
                </span>
              <?php else: ?>
                <a class="competition-pill" href="<?= h(totocalcio_page_competition_url($competitionSlug)) ?>">
                  <span><?= h($competition['nome']) ?></span>
                  <small><?= !empty($competition['accesso_pubblico']) ? 'Pubblica' : 'Riservata' ?></small>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <p>Non hai i permessi per accedere a questa sezione.</p>
      <?php endif; ?>
    </article>

    <aside class="panel-card status-panel">
      <span class="eyebrow">Il tuo stato</span>
      <h3>Riepilogo rapido</h3>
      <div class="stats-grid">
        <div class="stat-box">
          <strong><?= $selectedCompetition ? (int)count($matches) : $accessibleCompetitionCount ?></strong>
          <span><?= $selectedCompetition ? 'Partite pubblicate' : 'Competizioni accessibili' ?></span>
        </div>
        <?php if ($selectedCompetition): ?>
          <div class="stat-box">
            <strong><?= $savedPredictionsCount ?></strong>
            <span>Partite inserite</span>
          </div>
          <div class="stat-box">
            <strong><?= $myRow ? (int)$myRow['punti_totali'] : 0 ?></strong>
            <span>Punti nella competizione</span>
          </div>
        <?php endif; ?>
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
              $competitionIsGranted = in_array((int)($competition['id'] ?? 0), $grantedCompetitionIds, true);
            ?>
            <article class="competition-entry">
              <div class="competition-entry__top">
                <div>
                  <h3 class="competition-entry__title"><?= h($competition['nome']) ?></h3>
                  <p class="competition-entry__meta">Partite: <?= (int)($competition['total_matches'] ?? 0) ?> | Partite attive: <?= (int)($competition['active_matches'] ?? 0) ?> | Pronostici salvati: <?= (int)($competition['total_predictions'] ?? 0) ?></p>
                </div>

                <div class="pill-row">
                  <span class="status-pill <?= $competitionIsActive ? 'ok' : 'muted' ?>">
                    <?= $competitionIsActive ? 'Attiva' : 'Nascosta' ?>
                  </span>
                  <span class="status-pill <?= !empty($competition['accesso_pubblico']) ? 'info' : 'warn' ?>">
                    <?= !empty($competition['accesso_pubblico']) ? 'Accesso pubblico' : 'Accesso riservato' ?>
                  </span>
                  <?php if ($competitionIsGranted && empty($competition['accesso_pubblico'])): ?>
                    <span class="status-pill ok">Accesso assegnato</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="competition-entry__actions">
                <a class="btn-primary" href="<?= h(totocalcio_page_competition_url($competitionSlug)) ?>">Apri competizione</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <nav class="tabs totocalcio-tabs" data-tab-group="totocalcio">
      <button class="tab-button active" type="button" data-tab="totocalcio-classifica">Classifica</button>
      <button class="tab-button" type="button" data-tab="totocalcio-scelte">Scelte settimana</button>
      <button class="tab-button" type="button" data-tab="totocalcio-tabella">Tabella pronostici</button>
      <?php if ($supportsAntepost): ?>
        <button class="tab-button" type="button" data-tab="totocalcio-antepost">Antepost</button>
      <?php endif; ?>
    </nav>

    <section id="totocalcio-classifica" class="tab-section active" data-tab-group="totocalcio">
      <div class="panel-card">
        <span class="eyebrow">Classifica</span>
        <h2>Classifica generale</h2>
        <p style="margin: 0 0 12px;">La classifica e riferita alla competizione selezionata. Sono inclusi gli account con flag Totocalcio attivo oppure con accesso assegnato alla competizione. Ordinamento: punti, risultati esatti, esiti corretti.</p>

        <ul class="rule-list" style="margin-bottom: 18px;">
          <li>Esito corretto: +1 punto.</li>
          <?php if ($drawOutcomePoints > 1): ?>
            <li>Pareggio corretto: +<?= $drawOutcomePoints ?> punti.</li>
          <?php endif; ?>
          <li>Risultato esatto: +<?= $exactBonusPoints ?> punti bonus.</li>
          <li>Totale massimo per partita: <?= $maxPointsPerMatch ?> punti.</li>
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

    <section id="totocalcio-scelte" class="tab-section" data-tab-group="totocalcio">
      <div class="panel-card">
        <span class="eyebrow">Schedina</span>
        <h2>Scelte della settimana</h2>
        <p style="margin: 0 0 16px;">Ogni pronostico resta modificabile fino a 5 minuti prima dell orario della partita. Quando la partita viene segnata come giocata nel calendario ufficiale, qui vedrai automaticamente risultato e punti ottenuti. Se il risultato ufficiale cambia dopo, i punti vengono ricalcolati sui nuovi gol.</p>

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
                $predictionCutoff = totocalcio_match_cutoff_datetime($match);
                $officialResult = totocalcio_page_match_result_label($match);
              ?>
              <article class="match-card">
                <div class="match-card__top">
                  <div>
                    <h3 class="match-card__title"><?= h($match['squadra_casa']) ?> vs <?= h($match['squadra_trasferta']) ?></h3>
                    <p class="meta-line">Data partita: <?= h(totocalcio_page_datetime_label($match['data_partita'] ?? null, $match['ora_partita'] ?? null)) ?></p>
                    <p class="meta-line">Chiusura pronostici: <?= h($predictionCutoff ? $predictionCutoff->format('d/m/Y H:i') : 'Da definire') ?></p>
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
                      | esito corretto +<?= (int)($evaluation['punti_esito'] ?? 0) ?>
                    <?php endif; ?>
                    <?php if ($evaluation['risultato_esatto']): ?>
                      | risultato esatto +<?= $exactBonusPoints ?>
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
    </section>

    <section id="totocalcio-tabella" class="tab-section" data-tab-group="totocalcio">
      <div class="panel-card">
        <span class="eyebrow">Pronostici</span>
        <h2>Tabella completa delle scelte</h2>
        <p style="margin: 0 0 16px;">Scegli dalla picklist quale vista aprire: antepost di tutti oppure una singola giornata con tutti i pronostici salvati su quelle partite.</p>

        <?php if (empty($leaderboard)): ?>
          <p class="empty-state">Non ci sono ancora partecipanti da mostrare nella tabella.</p>
        <?php elseif (empty($matchesByGiornata) && !$supportsAntepost): ?>
          <p class="empty-state">Non ci sono ancora partite visibili da mostrare nella tabella.</p>
        <?php else: ?>
          <?php
            $predictionPickerDefaultId = !empty($matchesByGiornata)
                ? (string)($matchesByGiornata[0]['key'] ?? 'giornata-1')
                : ($supportsAntepost ? 'antepost' : '');
          ?>

          <label class="field prediction-picker">
            Seleziona vista
            <select id="predictionViewPicker">
              <?php if ($supportsAntepost): ?>
                <option value="antepost">Antepost</option>
              <?php endif; ?>
              <?php foreach ($matchesByGiornata as $group): ?>
                <option value="<?= h((string)($group['key'] ?? 'giornata')) ?>" <?= ((string)($group['key'] ?? '') === $predictionPickerDefaultId) ? 'selected' : '' ?>>
                  <?= h($group['label'] ?? 'Giornata') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <?php if ($supportsAntepost): ?>
            <div class="prediction-view-panel <?= $predictionPickerDefaultId === 'antepost' ? 'active' : '' ?>" data-prediction-view="antepost">
              <h3 class="prediction-group__title">Antepost</h3>
              <div class="antepost-table-wrap" style="margin-top: 0;">
                <?php if (empty($antepostRows)): ?>
                  <div style="padding: 16px; color: #64748b;">Nessun partecipante ha ancora salvato gli antepost.</div>
                <?php else: ?>
                  <table class="antepost-table">
                    <thead>
                      <tr>
                        <th>Partecipante</th>
                        <?php foreach ($antepostCategories as $categoryLabel): ?>
                          <th><?= h($categoryLabel) ?></th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($antepostRows as $row): ?>
                        <?php
                          $rowUserId = (int)($row['id'] ?? 0);
                          $rowAntepost = $antepostPredictionMatrix[$rowUserId] ?? [];
                        ?>
                        <tr>
                          <td><strong><?= h($row['display_name']) ?><?= $rowUserId === $userId ? ' (tu)' : '' ?></strong></td>
                          <?php foreach ($antepostCategories as $categoryKey => $categoryLabel): ?>
                            <td><?= h($rowAntepost[$categoryKey] ?? '-') ?></td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php foreach ($matchesByGiornata as $group): ?>
            <?php
              $groupMatches = $group['matches'] ?? [];
              $groupKey = (string)($group['key'] ?? '');
            ?>
            <?php if ($groupKey === '' || empty($groupMatches)): continue; endif; ?>
            <div class="prediction-view-panel <?= $groupKey === $predictionPickerDefaultId ? 'active' : '' ?>" data-prediction-view="<?= h($groupKey) ?>">
              <div class="prediction-group">
                <h3 class="prediction-group__title"><?= h($group['label'] ?? 'Giornata') ?></h3>
                <div class="prediction-matrix-wrap">
                  <table class="prediction-matrix">
                    <thead>
                      <tr>
                        <th class="prediction-matrix__user">Partecipante</th>
                        <?php foreach ($groupMatches as $match): ?>
                          <th class="prediction-matrix__match">
                            <strong><?= h($match['squadra_casa']) ?> vs <?= h($match['squadra_trasferta']) ?></strong>
                            <span class="prediction-matrix__meta"><?= h(totocalcio_page_datetime_label($match['data_partita'] ?? null, $match['ora_partita'] ?? null)) ?></span>
                            <span class="prediction-matrix__meta">Ufficiale: <?= h(totocalcio_page_match_result_label($match)) ?></span>
                          </th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($leaderboard as $row): ?>
                        <?php
                          $rowUserId = (int)($row['id'] ?? 0);
                          $userPredictions = $predictionMatrix[$rowUserId] ?? [];
                        ?>
                        <tr>
                          <td class="prediction-matrix__user">
                            <strong><?= h($row['display_name']) ?><?= $rowUserId === $userId ? ' (tu)' : '' ?></strong>
                            <span><?= (int)($row['punti_totali'] ?? 0) ?> punti | <?= (int)($row['risultati_esatti'] ?? 0) ?> esatti</span>
                          </td>
                          <?php foreach ($groupMatches as $match): ?>
                            <?php $cellPrediction = $userPredictions[(int)($match['id'] ?? 0)] ?? null; ?>
                            <td>
                              <?php if (is_array($cellPrediction)): ?>
                                <div class="prediction-matrix__cell">
                                  <strong><?= h($cellPrediction['segno'] ?? '') ?></strong>
                                  <span><?= (int)($cellPrediction['gol_casa_previsti'] ?? 0) ?> - <?= (int)($cellPrediction['gol_trasferta_previsti'] ?? 0) ?></span>
                                </div>
                              <?php else: ?>
                                <div class="prediction-matrix__cell empty">-</div>
                              <?php endif; ?>
                            </td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($supportsAntepost): ?>
      <section id="totocalcio-antepost" class="tab-section" data-tab-group="totocalcio">
        <div class="panel-card">
          <span class="eyebrow">Antepost</span>
          <h2>Scelte antepost</h2>
          <p style="margin: 0 0 16px;">Qui puoi indicare le tue scelte fisse per l esito finale della competizione: vincente Regular Season, vincente Coppa Gold, vincente Coppa Silver e squadra capocannoniere.</p>

          <?php if ($antepostTournament !== ''): ?>
            <div class="antepost-tournament">Torneo collegato: <?= h($antepostTournament) ?></div>
          <?php endif; ?>

          <?php if (empty($antepostTeams)): ?>
            <div class="warning-card" style="margin-top: 0;">Non riesco ancora a trovare l elenco squadre collegato a questa competizione. Verifica il torneo di riferimento e riprova.</div>
          <?php else: ?>
            <?php if ($antepostFirstMatchStart instanceof DateTimeImmutable): ?>
              <div class="warning-card" style="margin-top: 0; margin-bottom: 14px;">
                <?php if ($antepostIsOpen): ?>
                  Gli antepost sono modificabili fino all inizio della prima partita della competizione: <strong><?= h($antepostFirstMatchStart->format('d/m/Y H:i')) ?></strong>.
                <?php else: ?>
                  Gli antepost sono bloccati dall inizio della prima partita della competizione: <strong><?= h($antepostFirstMatchStart->format('d/m/Y H:i')) ?></strong>.
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($antepostPredictions)): ?>
              <div class="antepost-summary">
                <?php foreach ($antepostCategories as $categoryKey => $categoryLabel): ?>
                  <?php if (!empty($antepostPredictions[$categoryKey])): ?>
                    <strong><?= h($categoryLabel) ?></strong>
                    <span><?= h($antepostPredictions[$categoryKey]) ?></span>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($canParticipate && $antepostIsOpen): ?>
              <form method="POST">
                <?= csrf_field($csrfKey) ?>
                <input type="hidden" name="action" value="save_antepost">

                <div class="antepost-grid">
                  <?php foreach ($antepostCategories as $categoryKey => $categoryLabel): ?>
                    <label class="field">
                      <?= h($categoryLabel) ?>
                      <select name="antepost_choices[<?= h($categoryKey) ?>]" required>
                        <option value="">-- scegli una squadra --</option>
                        <?php foreach ($antepostTeams as $team): ?>
                          <?php $teamName = trim((string)($team['nome'] ?? '')); ?>
                          <?php if ($teamName === '') { continue; } ?>
                          <option value="<?= h($teamName) ?>" <?= ($antepostPredictions[$categoryKey] ?? '') === $teamName ? 'selected' : '' ?>>
                            <?= h($teamName) ?><?= !empty($team['girone']) ? ' | Girone ' . h($team['girone']) : '' ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  <?php endforeach; ?>
                </div>

                <div class="match-actions" style="margin-top: 18px;">
                  <button class="btn-primary" type="submit">Salva antepost</button>
                </div>
              </form>
            <?php elseif ($canParticipate): ?>
              <div class="warning-card" style="margin-top: 0;">Le scelte antepost non sono piu modificabili perche la prima partita della competizione e gia iniziata.</div>
            <?php else: ?>
              <div class="warning-card" style="margin-top: 0;">Puoi visualizzare gli antepost, ma per salvarli devi avere il Totocalcio attivo oppure un accesso assegnato alla competizione.</div>
            <?php endif; ?>

            <div class="antepost-table-wrap">
              <?php if (empty($antepostRows)): ?>
                <div style="padding: 16px; color: #64748b;">Nessun partecipante ha ancora salvato gli antepost.</div>
              <?php else: ?>
                <table class="antepost-table">
                  <thead>
                    <tr>
                      <th>Partecipante</th>
                      <?php foreach ($antepostCategories as $categoryLabel): ?>
                        <th><?= h($categoryLabel) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($antepostRows as $row): ?>
                      <?php
                        $rowUserId = (int)($row['id'] ?? 0);
                        $rowAntepost = $antepostPredictionMatrix[$rowUserId] ?? [];
                      ?>
                      <tr>
                        <td><strong><?= h($row['display_name']) ?><?= $rowUserId === $userId ? ' (tu)' : '' ?></strong></td>
                        <?php foreach ($antepostCategories as $categoryKey => $categoryLabel): ?>
                          <td><?= h($rowAntepost[$categoryKey] ?? '-') ?></td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</main>

<div id="footer-container"></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tabs[data-tab-group]').forEach((tabNav) => {
    const groupName = tabNav.getAttribute('data-tab-group');
    const buttons = Array.from(tabNav.querySelectorAll('.tab-button[data-tab]'));
    const sections = Array.from(document.querySelectorAll(`.tab-section[data-tab-group="${groupName}"]`));
    if (!groupName || buttons.length === 0 || sections.length === 0) {
      return;
    }

    const activateTab = (tabId) => {
      buttons.forEach((button) => {
        const isActive = button.dataset.tab === tabId;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });

      sections.forEach((section) => {
        section.classList.toggle('active', section.id === tabId);
      });
    };

    buttons.forEach((button) => {
      button.addEventListener('click', () => activateTab(button.dataset.tab || ''));
    });

    const defaultButton = buttons.find((button) => button.classList.contains('active')) || buttons[0];
    if (defaultButton) {
      activateTab(defaultButton.dataset.tab || '');
    }
  });

  const predictionViewPicker = document.getElementById('predictionViewPicker');
  const predictionViewPanels = Array.from(document.querySelectorAll('.prediction-view-panel[data-prediction-view]'));
  if (predictionViewPicker && predictionViewPanels.length > 0) {
    const activatePredictionView = (viewId) => {
      predictionViewPanels.forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.predictionView === viewId);
      });
    };

    predictionViewPicker.addEventListener('change', () => {
      activatePredictionView(predictionViewPicker.value);
    });

    activatePredictionView(predictionViewPicker.value);
  }

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
