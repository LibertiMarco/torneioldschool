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
</head>
<body>
<?php include __DIR__ . '/includi/header.php'; ?>

<main class="page-container" style="padding:20px;">
    <a class="admin-back-link" href="/account.php">← Torna al tuo account</a>
    <h1 class="admin-title">Statistiche giocatore</h1>

    <?php if (isset($msg)): ?>
        <p><?= h($msg) ?></p>
    <?php else: ?>
        <section class="card" style="margin-bottom:20px;">
            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                <img src="<?= h($giocatore['foto'] ?? '/img/giocatori/unknown.jpg') ?>" alt="Foto giocatore" style="width:110px;height:110px;object-fit:cover;border-radius:14px;border:1px solid #e2e8f0;">
                <div>
                    <h2 style="margin:0;"><?= h($giocatore['nome'] . ' ' . $giocatore['cognome']) ?></h2>
                    <p style="margin:4px 0; color:#555;">Ruolo: <?= h($giocatore['ruolo']) ?></p>
                    <div style="display:flex; gap:14px; flex-wrap:wrap; color:#15293e; font-weight:600;">
                        <span>Presenze: <?= (int)$giocatore['presenze'] ?></span>
                        <span>Gol: <?= (int)$giocatore['reti'] ?></span>
                        <span>Assist: <?= (int)$giocatore['assist'] ?></span>
                        <span>Gialli: <?= (int)$giocatore['gialli'] ?></span>
                        <span>Rossi: <?= (int)$giocatore['rossi'] ?></span>
                        <span>Media voto: <?= $giocatore['media_voti'] !== null ? h($giocatore['media_voti']) : 'N/D' ?></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom:20px;">
            <h3>Squadre</h3>
            <?php if (empty($squadre)): ?>
                <p>Non risultano squadre associate.</p>
            <?php else: ?>
                <div style="display:grid; gap:12px; grid-template-columns: repeat(auto-fit,minmax(240px,1fr));">
                    <?php foreach ($squadre as $s): ?>
                        <div style="border:1px solid #e2e8f0; border-radius:12px; padding:12px; background:#fff;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <?php if (!empty($s['logo'])): ?>
                                    <img src="<?= h($s['logo']) ?>" alt="Logo <?= h($s['nome']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:50%;">
                                <?php endif; ?>
                                <div>
                                    <strong><?= h($s['nome']) ?></strong><br>
                                    <small><?= h($s['torneo']) ?><?= $s['is_captain'] ? ' · Capitano' : '' ?></small>
                                </div>
                            </div>
                            <div style="margin-top:8px; display:flex; gap:12px; flex-wrap:wrap; font-size:0.95rem;">
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

        <section class="card" style="margin-bottom:20px;">
            <h3>Prossime partite</h3>
            <?php if (empty($prossimePartite)): ?>
                <p>Nessuna partita programmata.</p>
            <?php else: ?>
                <ul class="simple-list">
                    <?php foreach ($prossimePartite as $p): ?>
                        <li>
                            <strong><?= h($p['squadra_casa']) ?> vs <?= h($p['squadra_ospite']) ?></strong>
                            <div><?= h($p['torneo']) ?> · <?= h($p['campo']) ?></div>
                            <div><?= h($p['data_partita']) ?> <?= h($p['ora_partita']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <h3>Partite giocate</h3>
            <?php if (empty($partiteGiocate)): ?>
                <p>Nessuna partita giocata trovata.</p>
            <?php else: ?>
                <ul class="simple-list">
                    <?php foreach ($partiteGiocate as $p): ?>
                        <li>
                            <strong><?= h($p['squadra_casa']) ?> <?= $p['gol_casa'] !== null ? (int)$p['gol_casa'] : '-' ?> - <?= $p['gol_ospite'] !== null ? (int)$p['gol_ospite'] : '-' ?> <?= h($p['squadra_ospite']) ?></strong>
                            <div><?= h($p['torneo']) ?> · <?= h($p['campo']) ?></div>
                            <div><?= h($p['data_partita']) ?> <?= h($p['ora_partita']) ?></div>
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
