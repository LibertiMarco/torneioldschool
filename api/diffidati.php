<?php
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/diffidati.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$torneo = trim((string)($_GET['torneo'] ?? ''));
if ($torneo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro torneo mancante']);
    exit;
}
try {
    echo json_encode(diffidati_fetch($conn, $torneo), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore caricamento diffidati']);
}
