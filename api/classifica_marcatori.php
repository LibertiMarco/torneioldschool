<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/partite_schema.php';
require_once __DIR__ . '/../includi/giocatore_goal_extra.php';
if (!function_exists('partita_giocatore_resolved_team_expr')) {
    http_response_code(500);
    echo json_encode(['error' => 'Helper squadra partita non disponibile']);
    exit;
}

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

$teamIdExpr = partita_giocatore_team_id_expr($conn, 'pg.squadra_id');
$resolvedTeamExpr = partita_giocatore_resolved_team_expr('pg.giocatore_id', $teamIdExpr, 'p.torneo', 'p.squadra_casa', 'p.squadra_ospite');
$extraTeamGoalsExpr = giocatore_goal_extra_team_expr($conn, 'g.id', 's.id');
$goalField = "(COALESCE(match_stats.gol, 0) + {$extraTeamGoalsExpr})";
$matchStatsSubquery = "
    SELECT
        pg.giocatore_id,
        {$resolvedTeamExpr} AS squadra_ref,
        SUM(pg.goal) AS gol,
        SUM(CASE WHEN pg.presenza = 1 THEN 1 ELSE 0 END) AS presenze
    FROM partita_giocatore pg
    JOIN partite p
      ON p.id = pg.partita_id
    WHERE p.torneo = ?
      $phaseClause
    GROUP BY pg.giocatore_id, squadra_ref
";

$sql = "
    SELECT 
        g.id,
        g.nome,
        g.cognome,
        s.nome AS squadra,
        s.logo AS logo,
        COALESCE(sg.foto, g.foto, s.logo) AS foto,
        s.torneo AS torneo,
        {$goalField} AS gol,
        COALESCE(match_stats.presenze, 0) AS presenze
    FROM squadre_giocatori sg
    JOIN squadre s ON s.id = sg.squadra_id
    JOIN giocatori g ON g.id = sg.giocatore_id
    LEFT JOIN (
        {$matchStatsSubquery}
    ) match_stats
     ON match_stats.giocatore_id = g.id
     AND match_stats.squadra_ref = s.id
    WHERE s.torneo = ?
      AND {$goalField} > 0
    ORDER BY gol DESC, presenze DESC, g.cognome ASC, g.nome ASC
";

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
