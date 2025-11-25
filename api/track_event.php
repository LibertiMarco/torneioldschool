<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?? '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload non valido']);
    exit;
}

require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/consent_helpers.php';

function sanitizeStr($value, $max = 255) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    return mb_substr($value, 0, $max);
}

function sanitizeEventType($value) {
    $value = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)$value);
    return $value !== '' ? mb_substr($value, 0, 64) : 'custom';
}

function truncateIp($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = '0';
            return implode('.', $parts);
        }
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return preg_replace('/:[0-9a-f]{1,4}$/i', ':0', $ip);
    }
    return null;
}

function sanitizeDetails($details) {
    if (!is_array($details)) {
        return [];
    }
    $clean = [];
    foreach ($details as $key => $val) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)$key);
        if ($safeKey === '') {
            continue;
        }
        if (is_string($val)) {
            $clean[$safeKey] = mb_substr($val, 0, 200);
        } elseif (is_bool($val) || is_int($val) || is_float($val)) {
            $clean[$safeKey] = $val;
        }
    }
    return $clean;
}

$eventType = sanitizeEventType($data['event_type'] ?? 'custom');
$path = sanitizeStr($data['path'] ?? '', 255);
$referrer = sanitizeStr($data['referrer'] ?? '', 255);
$title = sanitizeStr($data['title'] ?? '', 255);
$detailsArr = sanitizeDetails($data['details'] ?? []);
$detailsJson = json_encode($detailsArr, JSON_UNESCAPED_UNICODE);

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$sessionId = session_id() ?: null;
$ip = truncateIp($_SERVER['REMOTE_ADDR'] ?? '') ?? null;
$userAgent = sanitizeStr($_SERVER['HTTP_USER_AGENT'] ?? '', 255);

// Blocca registrazione se il consenso al tracking non è attivo
if ($userId) {
    $email = consent_get_user_email($conn, $userId) ?? '';
    $consents = consent_current_snapshot($conn, $userId, $email);
    if (empty($consents['tracking'])) {
        echo json_encode(['status' => 'tracking_disabled']);
        exit;
    }
}

$stmt = $conn->prepare("INSERT INTO eventi_utente (user_id, session_id, event_type, path, referrer, title, details, ip_troncato, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
    exit;
}

$stmt->bind_param(
    "issssssss",
    $userId,
    $sessionId,
    $eventType,
    $path,
    $referrer,
    $title,
    $detailsJson,
    $ip,
    $userAgent
);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Impossibile registrare l\'evento']);
}

$stmt->close();
$conn->close();
