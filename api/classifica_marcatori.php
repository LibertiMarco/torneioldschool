<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';

$torneo = trim($_GET['torneo'] ?? '');
$limit = (int)($_GET['limit'] ?? 20);

if ($torneo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro torneo mancante']);
    exit;
}

$limit = $limit > 0 ? min($limit, 100) : 20;

$sql = "
    SELECT 
        g.id,
        g.nome,
        g.cognome,
        s.nome AS squadra,
        s.logo AS logo,
        s.torneo,
        sg.reti AS gol,
        sg.presenze
    FROM squadre_giocatori sg
    JOIN giocatori g ON g.id = sg.giocatore_id
    JOIN squadre s ON s.id = sg.squadra_id
    WHERE s.torneo = ?
    ORDER BY sg.reti DESC, sg.presenze DESC, g.cognome ASC, g.nome ASC
    LIMIT ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno']);
    exit;
}

$stmt->bind_param('si', $torneo, $limit);
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
        'torneo' => $row['torneo'],
        'gol' => (int)$row['gol'],
        'presenze' => (int)$row['presenze'],
    ];
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
