<?php
require_once __DIR__ . '/../includi/security.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/push_notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$csrfKey = 'push_subscription';

function push_api_read_json(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

if ($method === 'GET') {
    $status = tos_push_subscription_status($conn, $userId);
    $status['csrfToken'] = csrf_get_token($csrfKey);
    echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
if (!csrf_is_valid($csrfToken, $csrfKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF non valido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = push_api_read_json();
$action = trim((string)($payload['action'] ?? ($_POST['action'] ?? '')));

if ($action === 'subscribe') {
    $subscription = isset($payload['subscription']) && is_array($payload['subscription']) ? $payload['subscription'] : [];
    if (!$subscription || !tos_push_save_subscription($conn, $userId, $subscription)) {
        http_response_code(422);
        echo json_encode(['error' => 'Sottoscrizione push non valida'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $status = tos_push_subscription_status($conn, $userId);
    $status['success'] = true;
    $status['message'] = 'Notifiche push attivate su questo dispositivo.';
    $status['csrfToken'] = csrf_get_token($csrfKey);
    echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'unsubscribe') {
    $subscription = isset($payload['subscription']) && is_array($payload['subscription']) ? $payload['subscription'] : null;
    if (!tos_push_delete_subscription($conn, $userId, $subscription)) {
        http_response_code(422);
        echo json_encode(['error' => 'Disattivazione notifiche non riuscita'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $status = tos_push_subscription_status($conn, $userId);
    $status['success'] = true;
    $status['message'] = 'Notifiche push disattivate su questo dispositivo.';
    $status['csrfToken'] = csrf_get_token($csrfKey);
    echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'test') {
    $result = tos_push_send_test($conn, $userId);
    if ((int)($result['sent'] ?? 0) <= 0) {
        http_response_code(422);
        echo json_encode([
            'error' => 'Nessun dispositivo attivo per l\'invio del test.',
            'details' => $result,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notifica di test inviata.',
        'details' => $result,
        'csrfToken' => csrf_get_token($csrfKey),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Azione non valida'], JSON_UNESCAPED_UNICODE);
