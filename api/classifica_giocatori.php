<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione al database non disponibile']);
    exit;
}

$conn->set_charset('utf8mb4');

// Parametri e sanitizzazione
$torneo = null; // classifica all-time, senza filtro torneo
$search = trim($_GET['search'] ?? '');
$ordine = strtolower(trim($_GET['ordine'] ?? 'gol'));
$ordine = in_array($ordine, ['gol', 'presenze'], true) ? $ordine : 'gol';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 10);
$perPage = $perPage > 0 ? min($perPage, 50) : 10;
$offset = ($page - 1) * $perPage;

$excludedTournaments = ['SerieB']; // usa lo slug del file torneo (SerieB.php)
$excludedPlaceholder = implode(',', array_fill(0, count($excludedTournaments), '?'));

$conditionsBase = [];
$searchConditions = [];
$paramsSearch = [];
$typesSearch = '';

// Filtri base (escludi zero)
if ($ordine === 'gol') {
    $conditionsBase[] = 'agg.gol > 0';
} elseif ($ordine === 'presenze') {
    $conditionsBase[] = 'agg.presenze > 0';
}

// Filtro ricerca (applicato PRIMA del ranking)
if ($search !== '') {
    $searchConditions[] = '(CONCAT_WS(" ", nome, cognome) LIKE ? OR CONCAT_WS(" ", cognome, nome) LIKE ?)';
    $like = '%' . $search . '%';
    $paramsSearch[] = $like;
    $paramsSearch[] = $like;
    $typesSearch .= 'ss';
}

$allConditions = array_merge($conditionsBase, $searchConditions);
$whereAll = $allConditions ? 'WHERE ' . implode(' AND ', $allConditions) : '';

$orderFieldsInner = $ordine === 'presenze'
    ? 'agg.presenze DESC, agg.gol DESC, g.cognome ASC, g.nome ASC'
    : 'agg.gol DESC, agg.presenze DESC, g.cognome ASC, g.nome ASC';
$orderFieldsOuter = $ordine === 'presenze'
    ? 'presenze DESC, gol DESC, cognome ASC, nome ASC'
    : 'gol DESC, presenze DESC, cognome ASC, nome ASC';

// Colonne chiave per il ranking (competizione: 1,1,3 in caso di pari)
$rankPrimary = $ordine === 'presenze' ? 'agg.presenze' : 'agg.gol';

$aggregateSubquery = "
    SELECT 
        pg.giocatore_id,
        SUM(pg.goal) AS gol,
        SUM(CASE WHEN pg.presenza = 1 THEN 1 ELSE 0 END) AS presenze,
        CASE 
            WHEN SUM(CASE WHEN pg.voto IS NOT NULL THEN 1 ELSE 0 END) > 0
                THEN ROUND(SUM(CASE WHEN pg.voto IS NOT NULL THEN pg.voto ELSE 0 END) / SUM(CASE WHEN pg.voto IS NOT NULL THEN 1 ELSE 0 END), 2)
            ELSE NULL
        END AS media_voti
    FROM partita_giocatore pg
    JOIN partite p ON p.id = pg.partita_id
    WHERE p.giocata = 1 AND p.torneo NOT IN ($excludedPlaceholder)
    GROUP BY pg.giocatore_id
";

// Query dati con rank calcolato via variabili (comportamento tipo RANK: 1,2,2,4)
$sql = "
    SELECT *
    FROM (
        SELECT 
            g.id,
            g.nome,
            g.cognome,
            g.ruolo,
            '' AS squadra,
            '' AS torneo,
            g.foto,
            agg.gol,
            agg.presenze,
            agg.media_voti,
            @rownum := @rownum + 1 AS rownum_seq,
            @rank := CASE 
                WHEN @prev1 = $rankPrimary THEN @rank 
                ELSE @rownum 
            END AS posizione,
            @prev1 := $rankPrimary
        FROM giocatori g
        INNER JOIN (
            $aggregateSubquery
        ) AS agg ON agg.giocatore_id = g.id
        CROSS JOIN (SELECT @rownum := 0, @rank := 0, @prev1 := NULL) AS r
        $whereAll
        ORDER BY $orderFieldsInner
    ) AS ordered
    ORDER BY posizione ASC, $orderFieldsOuter
    LIMIT ? OFFSET ?
";

$paramsData = array_merge($excludedTournaments, $paramsSearch);
$typesData = str_repeat('s', count($excludedTournaments)) . $typesSearch . 'ii';
$paramsData[] = $perPage;
$paramsData[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno durante la preparazione della query']);
    exit;
}

$stmt->bind_param($typesData, ...$paramsData);

$stmt->execute();
$res = $stmt->get_result();
$giocatori = [];
while ($row = $res->fetch_assoc()) {
    $giocatori[] = $row;
}
$stmt->close();

// Conteggio totale per la paginazione
$countSql = "
    SELECT COUNT(*) AS totale
    FROM (
        SELECT g.id
        FROM giocatori g
        INNER JOIN (
            $aggregateSubquery
        ) AS agg ON agg.giocatore_id = g.id
        $whereAll
    ) AS ordered
";

$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    $paramsCount = array_merge($excludedTournaments, $paramsSearch);
    $typesCount = str_repeat('s', count($excludedTournaments)) . $typesSearch;
    $countStmt->bind_param($typesCount, ...$paramsCount);
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    $totale = (int)($countRes->fetch_assoc()['totale'] ?? 0);
    $countStmt->close();
} else {
    $totale = count($giocatori);
}

$pagination = [
    'page' => $page,
    'per_page' => $perPage,
    'total' => $totale,
    'total_pages' => $perPage > 0 ? (int)ceil($totale / $perPage) : 0,
];

echo json_encode([
    'data' => $giocatori,
    'pagination' => $pagination,
    'filters' => [
        'torneo' => $torneo,
        'search' => $search,
        'ordine' => $ordine,
    ],
], JSON_UNESCAPED_UNICODE);
