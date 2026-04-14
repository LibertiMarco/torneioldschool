<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';

$torneo = trim($_GET['torneo'] ?? '');
if ($torneo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro torneo mancante']);
    exit;
}

function get_torneo_config(mysqli $conn, string $torneo): array
{
    if ($torneo === '') {
        return [];
    }

    $filenamePhp = $torneo . '.php';
    $filenameHtml = $torneo . '.html';
    $stmt = $conn->prepare("
        SELECT config
        FROM tornei
        WHERE filetorneo IN (?, ?)
        ORDER BY (filetorneo LIKE '%.php') DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ss', $filenamePhp, $filenameHtml);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['config'])) {
        return [];
    }

    $decoded = json_decode((string)$row['config'], true);
    return is_array($decoded) ? $decoded : [];
}

// Per Coppa d'Africa contiamo tutte le fasi.
// Per tutti i tornei a gironi includiamo anche la Coppa Gold.
// Per gli altri tornei consideriamo solo la fase REGULAR (anche se fase vuota o "GIRONE").
// Uso COALESCE per includere anche valori NULL di fase come regular, evitando di escludere partite registrate senza fase.
$phaseExpr = "UPPER(CASE WHEN TRIM(COALESCE(p.fase, '')) IN ('', 'GIRONE') THEN 'REGULAR' ELSE TRIM(COALESCE(p.fase, '')) END)";
$torneoConfig = get_torneo_config($conn, $torneo);
$torneoFormat = strtolower(trim((string)($torneoConfig['formato'] ?? $torneoConfig['formula_torneo'] ?? '')));
$isGironeTournament = ($torneoFormat === 'girone');

if ($torneo === 'Coppadafrica') {
    $phaseClause = '';
} elseif ($isGironeTournament) {
    $phaseClause = "AND $phaseExpr IN ('REGULAR','GOLD')";
} else {
    $phaseClause = "AND $phaseExpr = 'REGULAR'";
}

$sql = "
    SELECT 
        g.id,
        g.nome,
        g.cognome,
        s.nome AS squadra,
        s.logo AS logo,
        COALESCE(sg.foto, g.foto, s.logo) AS foto,
        s.torneo AS torneo,
        SUM(pg.goal) AS gol,
        SUM(CASE WHEN pg.presenza = 1 THEN 1 ELSE 0 END) AS presenze
    FROM squadre_giocatori sg
    JOIN squadre s ON s.id = sg.squadra_id AND s.torneo = ?
    JOIN giocatori g ON g.id = sg.giocatore_id
    JOIN partita_giocatore pg ON pg.giocatore_id = g.id
    JOIN partite p 
      ON p.id = pg.partita_id
     AND p.torneo = s.torneo
     AND (p.squadra_casa = s.nome OR p.squadra_ospite = s.nome)
    WHERE 1=1
      $phaseClause
    GROUP BY sg.id
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
