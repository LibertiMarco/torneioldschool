<?php
require_once __DIR__ . '/includi/security.php';
if (!isset($_SESSION['user_id'])) {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '/index.php';
    login_remember_redirect($currentPath);
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';

$userId = (int)$_SESSION['user_id'];
$baseUrl = seo_base_url();
$defaultTeamLogo = '/img/logo_old_school.png';
header('Content-Type: text/html; charset=utf-8');

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Carica il giocatore associato all'account
$giocatore = null;
$stmt = $conn->prepare("SELECT id, nome, cognome, ruolo, presenze, reti, assist, gialli, rossi, media_voti, foto FROM giocatori WHERE utente_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        $giocatore = $stmt->get_result()->fetch_assoc();
    }
    $stmt->close();
}

if (!$giocatore) {
    http_response_code(404);
    $msg = "Non risulta alcun profilo giocatore associato al tuo account.";
}

// Squadre a cui appartiene il giocatore
$squadre = [];
if ($giocatore) {
    $stmt = $conn->prepare("
        SELECT s.id, s.nome, s.torneo, s.logo,
               sg.presenze, sg.reti, sg.assist, sg.gialli, sg.rossi, sg.media_voti, sg.is_captain
        FROM squadre_giocatori sg
        JOIN squadre s ON s.id = sg.squadra_id
        WHERE sg.giocatore_id = ?
        ORDER BY sg.created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $giocatore['id']);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $squadre[] = $row;
            }
        }
        $stmt->close();
    }
}

function format_match_datetime(?string $data, ?string $ora): string {
    if (empty($data)) return '';
    $dateTime = trim($data . ' ' . ($ora ?? ''));
    $ts = strtotime($dateTime);
    if (!$ts) {
        return trim($data . ' ' . ($ora ? substr($ora, 0, 5) : ''));
    }
    return date('d/m/Y', $ts) . ($ora ? ' ' . date('H:i', $ts) : '');
}

function render_stage(array $p): string {
    if (!empty($p['giornata'])) {
        return 'Giornata ' . (int)$p['giornata'];
    }
    $parts = [];
    if (!empty($p['fase'])) $parts[] = $p['fase'];
    if (!empty($p['fase_round'])) $parts[] = $p['fase_round'];
    if (!empty($p['fase_leg'])) $parts[] = $p['fase_leg'];
    return $parts ? implode(' - ', $parts) : '';
}
function match_link(array $p): string {
    $id = (int)($p['id'] ?? 0);
    if ($id <= 0) return '#';
    $torneo = trim($p['torneo'] ?? '');
    $query = 'id=' . $id;
    if ($torneo !== '') {
        $query .= '&torneo=' . rawurlencode($torneo);
    }
    return '/tornei/partita_eventi.php?' . $query;
}

function maps_search_url(string $place): string {
    $query = trim($place);
    if ($query === '') {
        return '';
    }
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
}

function format_team_name_multiline(string $name): string {
    $parts = array_filter(preg_split('/\s+/', trim($name)) ?: [], static fn($part) => $part !== '');
    if (empty($parts)) {
        return '';
    }
    return implode('<br>', array_map('h', $parts));
}

function team_name_class(string $name): string {
    $clean = trim($name);
    if ($clean === '') {
        return '';
    }
    $length = strlen($clean);
    $words = array_filter(preg_split('/\s+/', $clean) ?: []);
    if ($length > 14 || count($words) >= 3) {
        return 'is-long';
    }
    return '';
}

function fetchMatches(mysqli $conn, array $teamNames, bool $future = true): array {
    if (empty($teamNames)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($teamNames), '?'));
    $limit = $future ? 5 : 10;
    $condition = $future
        ? "AND giocata = 0 AND data_partita >= CURDATE()"
        : "AND giocata = 1";
    $order = $future
        ? "ORDER BY data_partita ASC, ora_partita ASC"
        : "ORDER BY data_partita DESC, ora_partita DESC";

    $sql = "
        SELECT p.id, p.torneo, p.squadra_casa, p.squadra_ospite, p.data_partita, p.ora_partita, p.campo, p.giocata, p.gol_casa, p.gol_ospite,
               p.giornata, p.fase, p.fase_round, p.fase_leg,
               sc.logo AS logo_casa, so.logo AS logo_ospite,
               t.nome AS torneo_nome
        FROM partite p
        LEFT JOIN squadre sc ON sc.nome = p.squadra_casa AND sc.torneo = p.torneo
        LEFT JOIN squadre so ON so.nome = p.squadra_ospite AND so.torneo = p.torneo
        LEFT JOIN tornei t ON (t.filetorneo = p.torneo OR t.filetorneo = CONCAT(p.torneo, '.php') OR t.nome = p.torneo)
        WHERE (squadra_casa IN ($placeholders) OR squadra_ospite IN ($placeholders))
        $condition
        $order
        LIMIT $limit
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $params = array_merge($teamNames, $teamNames);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $matches = [];
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $matches[] = $row;
        }
    }
    $stmt->close();
    return $matches;
}

$teamNames = array_map(static fn($row) => $row['nome'], $squadre);
$prossimePartite = $giocatore ? fetchMatches($conn, $teamNames, true) : [];
$partiteGiocate  = $giocatore ? fetchMatches($conn, $teamNames, false) : [];

$seo = [
    'title' => 'Statistiche Giocatore',
    'description' => 'Profilo giocatore con statistiche, squadre e partite.',
    'url' => $baseUrl . '/statistiche_giocatore.php',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-VZ982XSRRN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-VZ982XSRRN');
  </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php render_seo_tags($seo); ?>
    <link rel="stylesheet" href="/style.min.css">
    <style>
        body { background: #f4f6fb; }
        .player-page { padding: 110px 20px 50px; max-width: 1200px; margin: 0 auto; }
        .player-hero { display: grid; grid-template-columns: auto 1fr; gap: 18px; align-items: center; background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border-radius: 16px; padding: 18px; box-shadow: 0 10px 30px rgba(16,35,59,0.25); }
        .player-hero img { width: 120px; height: 120px; object-fit: cover; border-radius: 16px; border: 2px solid rgba(255,255,255,0.2); background: #0f1f2c; }
        .hero-stats { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
        .stat-chip { padding: 8px 12px; border-radius: 10px; background: rgba(255,255,255,0.12); font-weight: 600; font-size: 0.95rem; }
        .section-card { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 10px 20px rgba(15,31,51,0.06); border: 1px solid #e5eaf1; margin-bottom: 18px; }
        .section-card h3 { margin: 0 0 10px; color: #15293e; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 8px; background: #e8edf5; color: #1a2d44; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
        .squadre-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .team-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #fff; display: flex; flex-direction: column; gap: 8px; }
        .team-head { display: flex; align-items: center; gap: 10px; }
        .team-head img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; }
        .team-stats { display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.95rem; color: #1a2d44; }
        .simple-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
        .simple-list li { padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
        .simple-list strong { color: #15293e; }
        .muted { color: #5f6b7b; }
        .page-title { margin: 0 0 12px; color: #15293e; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #15293e; text-decoration: none; font-weight: 600; margin-bottom: 14px; }
        .back-link:hover { color: #0f1f2c; }
        /* Stile coerente con il calendario (anche per partite giocate) */
        .calendar-list { display: grid; gap: 12px; }
        .calendar-list .match-card {
            display: block;
            text-decoration: none;
            color: inherit;
            border: 1px solid #dfe6f0;
            border-radius: 14px;
            background: #fff;
            padding: 12px 12px 14px;
            box-shadow: 0 6px 14px rgba(15,31,51,0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .calendar-list .match-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(15,31,51,0.14);
        }
        .match-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 0 4px 6px;
            font-size: 0.95rem;
            color: #1a2d44;
            font-weight: 600;
        }
        .match-top-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .match-top-left .match-tournament {
            font-weight: 700;
            color: #3c4c63;
            font-size: 0.93rem;
            letter-spacing: 0.01em;
        }
        .match-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .match-time { color: #4c5b71; font-weight: 700; }
        .match-badge {
            background: #e8edf5;
            color: #1a2d44;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.83rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .match-location {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 4px 10px;
            color: #4c5b71;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .match-location-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #d80000;
            box-shadow: 0 0 0 4px rgba(216,0,0,0.12);
            flex-shrink: 0;
        }
        .match-location .maps-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #eef2f8;
            color: #d80000;
            text-decoration: none;
            border: 1px solid #dfe4ed;
            flex-shrink: 0;
            transition: background 0.15s ease, transform 0.15s ease;
        }
        .match-location .maps-link:hover {
            background: #e2e7f0;
            transform: translateY(-1px);
        }
        .match-location .maps-icon {
            display: inline-flex;
            font-size: 16px;
            line-height: 1;
        }
        .match-location .maps-icon::before { content: "\1F4CD"; }
        .calendar-list .match-body {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 12px;
            padding: 6px 8px 0 8px;
        }
        .calendar-list .team {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .calendar-list .team.away { justify-content: center; }
        .calendar-list .team img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            background: #f6f8fb;
            border: 1px solid #e5e8ee;
        }
        .calendar-list .team-name {
            font-weight: 800;
            color: #15293e;
            text-align: right;
            font-size: 0.92rem;
            letter-spacing: 0.01em;
            min-width: 0;
            max-width: 140px;
            white-space: normal;
            word-break: keep-all;
            overflow-wrap: break-word;
            line-height: 1.15;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .calendar-list .team-name.is-long {
            font-size: 0.84rem;
            line-height: 1.12;
        }
        .calendar-list .team.away .team-name { text-align: center; }
        .calendar-list .match-center {
            display: grid;
            gap: 4px;
            justify-items: center;
            color: #5f6b7b;
            font-weight: 700;
            min-width: 80px;
        }
        .calendar-list .vs { font-size: 1.05rem; color: #1f3f63; }
        .calendar-list .score {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f1f33;
            letter-spacing: 0.02em;
        }
        .match-subtext { color: #5f6b7b; font-size: 0.9rem; }
        @media (max-width: 600px) {
            .calendar-list .match-card { padding: 10px 10px 12px; }
            .match-top { flex-direction: column; align-items: flex-start; gap: 4px; font-size: 0.9rem; }
            .match-time { width: 100%; text-align: left; }
            .match-location { padding: 2px 4px 10px; font-size: 0.9rem; }
            .calendar-list .match-body { gap: 8px; grid-template-columns: 1fr auto 1fr; }
            .calendar-list .team img { width: 30px; height: 30px; }
            .calendar-list .team-name {
                font-size: 0.88rem;
                white-space: normal;
                word-break: keep-all;
                overflow-wrap: break-word;
                line-height: 1.18;
                max-width: 120px;
                align-items: center;
                text-align: center;
            }
            .calendar-list .team-name.is-long {
                font-size: 0.82rem;
                line-height: 1.12;
            }
            .calendar-list .team.away .team-name { text-align: center; }
            .calendar-list .vs { font-size: 0.98rem; }
            .calendar-list .score { font-size: 1.1rem; }
        }
        .match-card.is-disabled { cursor: default; }
        .match-card.is-disabled:hover {
            transform: none;
            box-shadow: 0 6px 14px rgba(15,31,51,0.08);
        }
        @media (max-width: 640px) {
            .player-hero { grid-template-columns: 1fr; text-align: center; }
            .player-hero img { margin: 0 auto; }
            .hero-stats { justify-content: center; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includi/header.php'; ?>

<main class="page-container player-page">
    <h1 class="page-title">Statistiche giocatore</h1>

    <?php if (isset($msg)): ?>
        <p><?= h($msg) ?></p>
    <?php else: ?>
        <section class="player-hero">
            <img src="<?= h($giocatore['foto'] ?? '/img/giocatori/unknown.jpg') ?>" alt="Foto giocatore">
            <div>
                <div class="badge">Profilo giocatore</div>
                <h2 style="margin:8px 0 4px;"><?= h($giocatore['nome'] . ' ' . $giocatore['cognome']) ?></h2>
                <div class="hero-stats">
                    <span class="stat-chip">Presenze: <?= (int)$giocatore['presenze'] ?></span>
                    <span class="stat-chip">Gol: <?= (int)$giocatore['reti'] ?></span>
                    <span class="stat-chip">Assist: <?= (int)$giocatore['assist'] ?></span>
                    <span class="stat-chip">Gialli: <?= (int)$giocatore['gialli'] ?></span>
                    <span class="stat-chip">Rossi: <?= (int)$giocatore['rossi'] ?></span>
                    <span class="stat-chip">Media voto: <?= $giocatore['media_voti'] !== null ? h($giocatore['media_voti']) : 'N/D' ?></span>
                </div>
            </div>
        </section>

        <section class="section-card">
            <h3>Squadre</h3>
            <?php if (empty($squadre)): ?>
                <p>Non risultano squadre associate.</p>
            <?php else: ?>
                <div class="squadre-grid">
                    <?php foreach ($squadre as $s): ?>
                        <div class="team-card">
                            <div class="team-head">
                                <?php if (!empty($s['logo'])): ?>
                                    <img src="<?= h($s['logo']) ?>" alt="Logo <?= h($s['nome']) ?>">
                                <?php endif; ?>
                                <div>
                                    <strong><?= h($s['nome']) ?></strong><br>
                                    <small><?= h($s['torneo']) ?><?= $s['is_captain'] ? ' - Capitano' : '' ?></small>
                                </div>
                            </div>
                            <div class="team-stats">
                                <span><strong>P:</strong> <?= (int)$s['presenze'] ?></span>
                                <span><strong>G:</strong> <?= (int)$s['reti'] ?></span>
                                <span><strong>A:</strong> <?= (int)$s['assist'] ?></span>
                                <span><strong>Gialli:</strong> <?= (int)$s['gialli'] ?></span>
                                <span><strong>Rossi:</strong> <?= (int)$s['rossi'] ?></span>
                                <span><strong>MV:</strong> <?= $s['media_voti'] !== null ? h($s['media_voti']) : 'N/D' ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="section-card">
            <h3>Prossime partite</h3>
            <?php if (empty($prossimePartite)): ?>
                <p class="muted">Nessuna partita programmata.</p>
            <?php else: ?>
                <div class="calendar-list">
                    <?php foreach ($prossimePartite as $p): ?>
                        <?php
                          $stage = render_stage($p);
                          $link = match_link($p);
                          $logoCasa = !empty($p['logo_casa']) ? $p['logo_casa'] : $defaultTeamLogo;
                          $logoOspite = !empty($p['logo_ospite']) ? $p['logo_ospite'] : $defaultTeamLogo;
                          $campoLabel = trim($p['campo'] ?? '');
                          $mapsUrl = maps_search_url($campoLabel);
                          $homeNameClass = team_name_class($p['squadra_casa']);
                          $awayNameClass = team_name_class($p['squadra_ospite']);
                          $torneoNome = trim($p['torneo_nome'] ?? '') !== '' ? $p['torneo_nome'] : $p['torneo'];
                        ?>
                        <div class="match-card upcoming is-disabled" aria-disabled="true">
                            <div class="match-top">
                                <div class="match-top-left">
                                    <?php if ($stage): ?><span class="match-badge"><?= h($stage) ?></span><?php endif; ?>
                                    <span class="match-tournament"><?= h($torneoNome) ?></span>
                                </div>
                                <span class="match-time"><?= h(format_match_datetime($p['data_partita'], $p['ora_partita'])) ?></span>
                            </div>
                            <?php if ($campoLabel !== '' && $mapsUrl !== ''): ?>
                                <div class="match-location">
                                    <a class="maps-link" href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener" aria-label="Apri <?= h($campoLabel) ?> su Google Maps">
                                        <span class="maps-icon" aria-hidden="true"></span>
                                    </a>
                                    <span><?= h($campoLabel) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="match-body">
                                <div class="team home">
                                    <img src="<?= h($logoCasa) ?>" alt="Logo <?= h($p['squadra_casa']) ?>" onerror="this.src='<?= h($defaultTeamLogo) ?>';">
                                    <div class="team-name<?= $homeNameClass ? ' ' . h($homeNameClass) : '' ?>"><?= format_team_name_multiline($p['squadra_casa']) ?></div>
                                </div>
                                <div class="match-center">
                                    <span class="vs">VS</span>
                                </div>
                                <div class="team away">
                                    <div class="team-name<?= $awayNameClass ? ' ' . h($awayNameClass) : '' ?>"><?= format_team_name_multiline($p['squadra_ospite']) ?></div>
                                    <img src="<?= h($logoOspite) ?>" alt="Logo <?= h($p['squadra_ospite']) ?>" onerror="this.src='<?= h($defaultTeamLogo) ?>';">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="section-card">
            <h3>Partite giocate</h3>
            <?php if (empty($partiteGiocate)): ?>
                <p class="muted">Nessuna partita giocata trovata.</p>
            <?php else: ?>
                <div class="calendar-list">
                    <?php foreach ($partiteGiocate as $p): ?>
                        <?php
                          $stage = render_stage($p);
                          $link = match_link($p);
                          $logoCasa = !empty($p['logo_casa']) ? $p['logo_casa'] : $defaultTeamLogo;
                          $logoOspite = !empty($p['logo_ospite']) ? $p['logo_ospite'] : $defaultTeamLogo;
                          $scoreHome = $p['gol_casa'] !== null ? (int)$p['gol_casa'] : '-';
                          $scoreAway = $p['gol_ospite'] !== null ? (int)$p['gol_ospite'] : '-';
                          $homeNameClass = team_name_class($p['squadra_casa']);
                          $awayNameClass = team_name_class($p['squadra_ospite']);
                          $torneoNome = trim($p['torneo_nome'] ?? '') !== '' ? $p['torneo_nome'] : $p['torneo'];
                        ?>
                        <a class="match-card played" href="<?= h($link) ?>">
                            <div class="match-top">
                                <div class="match-top-left">
                                    <?php if ($stage): ?><span class="match-badge"><?= h($stage) ?></span><?php endif; ?>
                                    <span class="match-tournament"><?= h($torneoNome) ?></span>
                                </div>
                                <span class="match-time"><?= h(format_match_datetime($p['data_partita'], $p['ora_partita'])) ?></span>
                            </div>
                            <?php if (!empty($p['campo'])): ?>
                                <div class="match-location">
                                    <span class="match-location-dot"></span>
                                    <span><?= h($p['campo']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="match-body">
                                <div class="team home">
                                    <img src="<?= h($logoCasa) ?>" alt="Logo <?= h($p['squadra_casa']) ?>" onerror="this.src='<?= h($defaultTeamLogo) ?>';">
                                    <div class="team-name<?= $homeNameClass ? ' ' . h($homeNameClass) : '' ?>"><?= format_team_name_multiline($p['squadra_casa']) ?></div>
                                </div>
                                <div class="match-center">
                                    <span class="score"><?= $scoreHome ?> - <?= $scoreAway ?></span>
                                </div>
                                <div class="team away">
                                    <div class="team-name<?= $awayNameClass ? ' ' . h($awayNameClass) : '' ?>"><?= format_team_name_multiline($p['squadra_ospite']) ?></div>
                                    <img src="<?= h($logoOspite) ?>" alt="Logo <?= h($p['squadra_ospite']) ?>" onerror="this.src='<?= h($defaultTeamLogo) ?>';">
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
    .then(html => footer.innerHTML = html)
    .catch(err => console.error('Errore caricamento footer:', err));
});
</script>
</body>
</html>
