<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';

$torneo = trim($_GET['torneo'] ?? '');
if ($torneo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro torneo mancante']);
    exit;
}

// Per Coppa d'Africa contiamo anche la fase finale, per gli altri solo fase REGULAR (anche se fase vuota o \"GIRONE\")
// Uso COALESCE per includere anche valori NULL di fase come regular, evitando di escludere partite registrate senza fase.
$phaseClause = ($torneo === 'Coppadafrica')
    ? ''
    : "AND UPPER(CASE WHEN TRIM(COALESCE(p.fase, '')) IN ('', 'GIRONE') THEN 'REGULAR' ELSE TRIM(COALESCE(p.fase, '')) END) = 'REGULAR'";

$sql = "
    SELECT 
        g.id,
        g.nome,
        g.cognome,
        s.nome AS squadra,
        s.logo AS logo,
        COALESCE(g.foto, sg.foto, s.logo) AS foto,
        p.torneo AS torneo,
        SUM(pg.goal) AS gol,
        SUM(CASE WHEN pg.presenza = 1 THEN 1 ELSE 0 END) AS presenze
    FROM partita_giocatore pg
    JOIN partite p ON p.id = pg.partita_id
    /* squadra della partita nel torneo corrente (casa o ospite) */
    JOIN squadre s 
      ON s.torneo = p.torneo
     AND s.nome IN (p.squadra_casa, p.squadra_ospite)
    /* associazione giocatore-squadra (se esiste) per foto dedicata */
    LEFT JOIN squadre_giocatori sg 
      ON sg.squadra_id = s.id
     AND sg.giocatore_id = pg.giocatore_id
    JOIN giocatori g ON g.id = pg.giocatore_id
    WHERE p.torneo = ?
      $phaseClause
    GROUP BY g.id, s.id
    HAVING SUM(pg.goal) > 0
    ORDER BY gol DESC, presenze DESC, g.cognome ASC, g.nome ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno']);
    exit;
}

$stmt->bind_param('s', $torneo);
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
