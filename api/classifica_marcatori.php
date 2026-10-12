<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/tournament_scorers.php';
$torneo = trim($_GET['torneo'] ?? '');
if ($torneo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro torneo mancante']);
    exit;
}
$sql = tournament_scorers_sql($conn, $torneo);
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno']);
    exit;
}

$stmt->bind_param('ss', $torneo, $torneo);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$row['id'],
        'nome' => $row['nome'],
        'cognome' => $row['cognome'],
        'squadra' => $row['squadra'],
        'logo' => $row['logo'],
        'foto' => $row['foto'],
        'torneo' => $row['torneo'],
        'gol' => (int)$row['gol'],
        'presenze' => (int)$row['presenze'],
    ];
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
