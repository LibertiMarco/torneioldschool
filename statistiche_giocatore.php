<?php
require_once __DIR__ . '/includi/security.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';

$userId = (int)$_SESSION['user_id'];
$baseUrl = seo_base_url();

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
        SELECT id, torneo, squadra_casa, squadra_ospite, data_partita, ora_partita, campo, giocata, gol_casa, gol_ospite
        FROM partite
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
    <a class="back-link" href="/account.php">← Torna al tuo account</a>
    <h1 class="page-title">Statistiche giocatore</h1>

    <?php if (isset($msg)): ?>
        <p><?= h($msg) ?></p>
    <?php else: ?>
        <section class="player-hero">
            <img src="<?= h($giocatore['foto'] ?? '/img/giocatori/unknown.jpg') ?>" alt="Foto giocatore">
            <div>
                <div class="badge">Profilo giocatore</div>
                <h2 style="margin:8px 0 4px;"><?= h($giocatore['nome'] . ' ' . $giocatore['cognome']) ?></h2>
                <p style="margin:0; color: rgba(255,255,255,0.8);">Ruolo: <?= h($giocatore['ruolo']) ?></p>
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
                                    <small><?= h($s['torneo']) ?><?= $s['is_captain'] ? ' · Capitano' : '' ?></small>
                                </div>
                            </div>
                            <div class="team-stats">
                                <span>P: <?= (int)$s['presenze'] ?></span>
                                <span>G: <?= (int)$s['reti'] ?></span>
                                <span>A: <?= (int)$s['assist'] ?></span>
                                <span>Gialli: <?= (int)$s['gialli'] ?></span>
                                <span>Rossi: <?= (int)$s['rossi'] ?></span>
                                <span>MV: <?= $s['media_voti'] !== null ? h($s['media_voti']) : 'N/D' ?></span>
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
                <ul class="simple-list">
                    <?php foreach ($prossimePartite as $p): ?>
                        <li>
                            <strong><?= h($p['squadra_casa']) ?> vs <?= h($p['squadra_ospite']) ?></strong>
                            <div class="muted"><?= h($p['torneo']) ?> · <?= h($p['campo']) ?></div>
                            <div class="muted"><?= h($p['data_partita']) ?> <?= h($p['ora_partita']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="section-card">
            <h3>Partite giocate</h3>
            <?php if (empty($partiteGiocate)): ?>
                <p class="muted">Nessuna partita giocata trovata.</p>
            <?php else: ?>
                <ul class="simple-list">
                    <?php foreach ($partiteGiocate as $p): ?>
                        <li>
                            <strong><?= h($p['squadra_casa']) ?> <?= $p['gol_casa'] !== null ? (int)$p['gol_casa'] : '-' ?> - <?= $p['gol_ospite'] !== null ? (int)$p['gol_ospite'] : '-' ?> <?= h($p['squadra_ospite']) ?></strong>
                            <div class="muted"><?= h($p['torneo']) ?> · <?= h($p['campo']) ?></div>
                            <div class="muted"><?= h($p['data_partita']) ?> <?= h($p['ora_partita']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
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
