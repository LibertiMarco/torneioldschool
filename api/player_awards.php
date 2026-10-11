<?php
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito.']);
    exit;
}
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$section = $_GET['section'] ?? 'calcio';
if ($id === false || !in_array($section, ['calcio', 'esport'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Giocatore o sezione non validi.']);
    exit;
}
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/player_awards.php';
try {
    // Only public player and award information is returned, never account data.
    $stmt = $conn->prepare('SELECT id, nome, cognome, foto FROM giocatori WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$player) {
        http_response_code(404);
        echo json_encode(['error' => 'Giocatore non trovato.']);
        exit;
    }
    $stmt = $conn->prepare("SELECT s.id, s.nome, s.torneo, s.logo, t.nome AS torneo_nome, t.filetorneo AS torneo_file
        FROM squadre_giocatori sg JOIN squadre s ON s.id = sg.squadra_id
        LEFT JOIN tornei t ON (t.filetorneo = s.torneo OR t.filetorneo = CONCAT(s.torneo, '.php') OR t.nome = s.torneo)
        WHERE sg.giocatore_id = ? AND COALESCE(t.sezione, 'calcio') = ? ORDER BY sg.created_at DESC");
    $stmt->bind_param('is', $id, $section);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['awards' => player_awards_fetch_all($conn, $player, $teams, $section)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Player awards: ' . get_class($error) . ' request failed');
    http_response_code(503);
    echo json_encode(['error' => 'Premi momentaneamente non disponibili.']);
}
