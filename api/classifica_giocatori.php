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

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = '(CONCAT_WS(" ", g.nome, g.cognome) LIKE ? OR CONCAT_WS(" ", g.cognome, g.nome) LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

// Filtri per escludere valori a zero
if ($ordine === 'gol') {
    $conditions[] = 'g.reti > 0';
} elseif ($ordine === 'presenze') {
    $conditions[] = 'g.presenze > 0';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$orderClause = $ordine === 'presenze'
    ? 'ORDER BY presenze DESC, gol DESC, g.cognome ASC, g.nome ASC'
    : 'ORDER BY gol DESC, presenze DESC, g.cognome ASC, g.nome ASC';

// Query dati
$sql = "
    SELECT 
        g.id,
        g.nome,
        g.cognome,
        g.ruolo,
        '' AS squadra,
        '' AS torneo,
        g.foto AS foto,
        g.reti AS gol,
        g.presenze AS presenze,
        g.media_voti AS media_voti
    FROM giocatori g
    $where
    $orderClause
    LIMIT ? OFFSET ?
";

$paramsData = $params;
$typesData = $types . 'ii';
$paramsData[] = $perPage;
$paramsData[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno durante la preparazione della query']);
    exit;
}

if ($typesData !== '' && $paramsData) {
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
    FROM giocatori g
    $where
";

$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($types !== '' && $params) {
        $countStmt->bind_param($types, ...$params);
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
