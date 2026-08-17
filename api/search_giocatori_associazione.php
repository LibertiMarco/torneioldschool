<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$term = trim((string)($_GET['q'] ?? ''));
$squadraId = (int)($_GET['squadra_id'] ?? 0);
if (mb_strlen($term) < 2) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $term . '%';
$stmt = $conn->prepare("
    SELECT g.id, g.nome, g.cognome, g.ruolo, g.foto
    FROM giocatori g
    WHERE (
        g.nome LIKE ?
        OR g.cognome LIKE ?
        OR CONCAT_WS(' ', g.nome, g.cognome) LIKE ?
        OR CONCAT_WS(' ', g.cognome, g.nome) LIKE ?
        OR CAST(g.id AS CHAR) = ?
    )
      AND NOT EXISTS (
          SELECT 1
          FROM squadre_giocatori sg
          WHERE sg.giocatore_id = g.id
            AND sg.squadra_id = ?
      )
    ORDER BY g.cognome, g.nome, g.id
    LIMIT 30
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Ricerca non disponibile']);
    exit;
}

$stmt->bind_param('sssssi', $like, $like, $like, $like, $term, $squadraId);
$stmt->execute();
$result = $stmt->get_result();
$players = [];
while ($row = $result->fetch_assoc()) {
    $players[] = $row;
}
$stmt->close();

echo json_encode($players, JSON_UNESCAPED_UNICODE);
