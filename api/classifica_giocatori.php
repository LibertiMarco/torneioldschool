<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione al database non disponibile']);
    exit;
}

$conn->set_charset('utf8mb4');

$defaultFoto = '/img/giocatori/unknown.jpg';
$latestSquadPhotoSubquery = "
    SELECT sg2.foto
    FROM squadre_giocatori sg2
    WHERE sg2.giocatore_id = g.id
      AND sg2.foto IS NOT NULL AND sg2.foto <> ''
    ORDER BY sg2.created_at DESC, sg2.id DESC
    LIMIT 1
";
$fotoSelect = "
    CASE
        WHEN g.foto IS NULL OR g.foto = '' OR g.foto = '{$defaultFoto}'
            THEN COALESCE(($latestSquadPhotoSubquery), '{$defaultFoto}')
        ELSE g.foto
    END AS foto
";

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

$whereBase = $conditionsBase ? 'WHERE ' . implode(' AND ', $conditionsBase) : '';
$whereSearch = $searchConditions ? ($whereBase ? ' AND ' : 'WHERE ') . implode(' AND ', $searchConditions) : '';
$whereAll = $whereBase . $whereSearch;

$orderFields = $ordine === 'presenze'
    ? 'agg.presenze DESC, agg.gol DESC, g.cognome ASC, g.nome ASC'
    : 'agg.gol DESC, agg.presenze DESC, g.cognome ASC, g.nome ASC';

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

// Query base senza filtro di ricerca: serve per calcolare il rank globale
$sqlAll = "
    SELECT 
        g.id,
        g.nome,
        g.cognome,
        g.ruolo,
        '' AS squadra,
        '' AS torneo,
        {$fotoSelect},
        agg.gol,
        agg.presenze,
        agg.media_voti
    FROM giocatori g
    INNER JOIN (
        $aggregateSubquery
    ) AS agg ON agg.giocatore_id = g.id
    $whereBase
    ORDER BY $orderFields
";

$paramsBase = $excludedTournaments;
$typesBase = str_repeat('s', count($excludedTournaments));

$stmt = $conn->prepare($sqlAll);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno durante la preparazione della query']);
    exit;
}
$stmt->bind_param($typesBase, ...$paramsBase);
$stmt->execute();
$res = $stmt->get_result();
$allRows = [];
while ($row = $res->fetch_assoc()) {
    $allRows[] = $row;
}
$stmt->close();

// Calcolo rank (dense) globale, poi applico paginazione
$primaryField = $ordine === 'presenze' ? 'presenze' : 'gol';
$lastVal = null;
$rank = 0;
$posMap = [];
foreach ($allRows as $idx => $row) {
    $val = (int)($row[$primaryField] ?? 0);
    if ($lastVal === null || $val !== $lastVal) {
        $rank = $idx + 1;
        $lastVal = $val;
    }
    $posMap[$row['id']] = $rank;
}

// Query filtrata (con ricerca) e applico il rank calcolato sul set completo
$sqlFiltered = "
    SELECT 
        g.id,
        g.nome,
        g.cognome,
        g.ruolo,
        '' AS squadra,
        '' AS torneo,
        {$fotoSelect},
        agg.gol,
        agg.presenze,
        agg.media_voti
    FROM giocatori g
    INNER JOIN (
        $aggregateSubquery
    ) AS agg ON agg.giocatore_id = g.id
    $whereAll
    ORDER BY $orderFields
";

$paramsFiltered = array_merge($excludedTournaments, $paramsSearch);
$typesFiltered = str_repeat('s', count($excludedTournaments)) . $typesSearch;

$stmt = $conn->prepare($sqlFiltered);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno durante la preparazione della query']);
    exit;
}
$stmt->bind_param($typesFiltered, ...$paramsFiltered);
$stmt->execute();
$res = $stmt->get_result();
$filteredRows = [];
while ($row = $res->fetch_assoc()) {
    $row['posizione'] = $posMap[$row['id']] ?? null;
    $filteredRows[] = $row;
}
$stmt->close();

$totale = count($filteredRows);
$giocatori = array_slice($filteredRows, $offset, $perPage);

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
