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
        COALESCE(sg.foto, g.foto) AS foto,
        p.torneo,
        SUM(pg.goal) AS gol,
        COUNT(*) AS presenze
    FROM partita_giocatore pg
    JOIN partite p ON p.id = pg.partita_id
    JOIN giocatori g ON g.id = pg.giocatore_id
    JOIN squadre s ON s.torneo = p.torneo
        AND s.nome IN (p.squadra_casa, p.squadra_ospite)
    JOIN squadre_giocatori sg ON sg.giocatore_id = g.id AND sg.squadra_id = s.id
    WHERE p.torneo = ?
    GROUP BY g.id, s.id, p.torneo
    HAVING SUM(pg.goal) > 0
    ORDER BY gol DESC, presenze DESC, g.cognome ASC, g.nome ASC
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
        'foto' => $row['foto'],
        'torneo' => $row['torneo'],
        'gol' => (int)$row['gol'],
        'presenze' => (int)$row['presenze'],
    ];
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
