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

$conditionsBase = [];
$conditionsSearch = [];
$paramsSearch = [];
$typesSearch = '';

if ($search !== '') {
    $conditionsSearch[] = '(CONCAT_WS(" ", nome, cognome) LIKE ? OR CONCAT_WS(" ", cognome, nome) LIKE ?)';
    $like = '%' . $search . '%';
    $paramsSearch[] = $like;
    $paramsSearch[] = $like;
    $typesSearch .= 'ss';
}

// Filtri per escludere valori a zero (sempre applicati alla base classifica)
if ($ordine === 'gol') {
    $conditionsBase[] = 'g.reti > 0';
} elseif ($ordine === 'presenze') {
    $conditionsBase[] = 'g.presenze > 0';
}

$whereBase = $conditionsBase ? 'WHERE ' . implode(' AND ', $conditionsBase) : '';
$whereSearch = $conditionsSearch ? 'WHERE ' . implode(' AND ', $conditionsSearch) : '';

$orderClause = $ordine === 'presenze'
    ? 'ORDER BY g.presenze DESC, g.reti DESC, g.cognome ASC, g.nome ASC'
    : 'ORDER BY g.reti DESC, g.presenze DESC, g.cognome ASC, g.nome ASC';

// Query dati
$sql = "
    SELECT *
    FROM (
        SELECT 
            base.id,
            base.nome,
            base.cognome,
            base.ruolo,
            '' AS squadra,
            '' AS torneo,
            base.foto,
            base.gol,
            base.presenze,
            base.media_voti,
            @rownum := @rownum + 1 AS posizione
        FROM (
            SELECT 
                g.id,
                g.nome,
                g.cognome,
                g.ruolo,
                g.foto,
                g.reti AS gol,
                g.presenze,
                g.media_voti
            FROM giocatori g
            $whereBase
            $orderClause
        ) AS base
        CROSS JOIN (SELECT @rownum := 0) AS r
    ) AS ordered
    $whereSearch
    ORDER BY posizione ASC
    LIMIT ? OFFSET ?
";

$paramsData = $paramsSearch;
$typesData = $typesSearch . 'ii';
$paramsData[] = $perPage;
$paramsData[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno durante la preparazione della query']);
    exit;
}

if ($typesSearch !== '' && $paramsData) {
    $stmt->bind_param($typesData, ...$paramsData);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}

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
        SELECT 
            g.id,
            g.nome,
            g.cognome
        FROM giocatori g
        $whereBase
    ) AS base
    $whereSearch
";

$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($typesSearch !== '' && $paramsSearch) {
        $countStmt->bind_param($typesSearch, ...$paramsSearch);
    }
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
