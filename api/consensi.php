<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/consent_helpers.php';

function json_response(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

$email = consent_get_user_email($conn, $userId) ?? '';

$hasDb = isset($conn) && $conn instanceof mysqli && !$conn->connect_errno;
if (!$hasDb) {
    json_response(503, ['error' => 'DB non disponibile']);
}

function parse_bool($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_string($value)) {
        $value = strtolower($value);
        return in_array($value, ['1', 'true', 'on', 'yes', 'si'], true) ? 1 : 0;
    }
    if (is_numeric($value)) {
        return (int)$value ? 1 : 0;
    }
    return 0;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $snapshot = consent_current_snapshot($conn, $userId, $email);
    echo json_encode(['consents' => $snapshot]);
    exit;
}

if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?? '', true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $payload = [
        'marketing' => parse_bool($data['marketing'] ?? null),
        'newsletter' => parse_bool($data['newsletter'] ?? null),
        'tracking' => parse_bool($data['tracking'] ?? null),
        'terms' => parse_bool($data['terms'] ?? 0),
    ];

    $consents = consent_save($conn, $userId, $email, $payload, 'api');
    echo json_encode(['consents' => $consents]);
    exit;
}

if ($method === 'DELETE') {
    $consents = consent_save($conn, $userId, $email, [
        'marketing' => 0,
        'newsletter' => 0,
        'tracking' => 0,
    ], 'api', 'revoke_all');
    echo json_encode(['consents' => $consents, 'status' => 'revoked']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Metodo non consentito']);
