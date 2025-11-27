<?php
require_once __DIR__ . '/../includi/security.php';
header('Content-Type: application/json');

// Consent proof for anonymous visitors: store hashed session + truncated IP/UA
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/../includi/db.php';

$hasDb = isset($conn) && $conn instanceof mysqli && !$conn->connect_errno;
if (!$hasDb) {
    http_response_code(503);
    echo json_encode(['error' => 'DB non disponibile']);
    exit;
}

function json_response(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function session_hash(): string
{
    $sid = session_id() ?: '';
    return hash('sha256', $sid);
}

function truncate_ip(?string $ip): ?string
{
    if (!$ip) return null;
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

function parse_bool($value): int
{
    if (is_bool($value)) return $value ? 1 : 0;
    if (is_numeric($value)) return (int)$value ? 1 : 0;
    if (is_string($value)) {
        $v = strtolower($value);
        return in_array($v, ['1', 'true', 'yes', 'on', 'si'], true) ? 1 : 0;
    }
    return 0;
}

$sessHash = session_hash();
$ip = truncate_ip($_SERVER['REMOTE_ADDR'] ?? null);
$ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
if (strlen($ua) > 255) {
    $ua = substr($ua, 0, 255);
}

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT marketing, newsletter, tracking, recaptcha, updated_at FROM consensi_anonimi WHERE session_hash = ? LIMIT 1");
    $stmt->bind_param("s", $sessHash);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        json_response(404, ['error' => 'Nessun consenso salvato']);
    }

    json_response(200, ['consents' => [
        'marketing' => (int)$row['marketing'] === 1,
        'newsletter' => (int)$row['newsletter'] === 1,
        'tracking' => (int)$row['tracking'] === 1,
        'recaptcha' => (int)$row['recaptcha'] === 1,
        'updated_at' => $row['updated_at'],
    ]]);
}

if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?? '', true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $payload = [
        'marketing' => parse_bool($data['marketing'] ?? null),
        'newsletter' => parse_bool($data['newsletter'] ?? null),
        'tracking' => parse_bool($data['tracking'] ?? null),
        'recaptcha' => parse_bool($data['recaptcha'] ?? null),
    ];

    $stmt = $conn->prepare("
        INSERT INTO consensi_anonimi (session_hash, marketing, newsletter, tracking, recaptcha, ip, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            marketing = VALUES(marketing),
            newsletter = VALUES(newsletter),
            tracking = VALUES(tracking),
            recaptcha = VALUES(recaptcha),
            ip = VALUES(ip),
            user_agent = VALUES(user_agent),
            updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
        json_response(500, ['error' => 'Errore salvataggio']);
    }
    $stmt->bind_param(
        "siiiiss",
        $sessHash,
        $payload['marketing'],
        $payload['newsletter'],
        $payload['tracking'],
        $payload['recaptcha'],
        $ip,
        $ua
    );
    $ok = $stmt->execute();
    $stmt->close();

    json_response($ok ? 200 : 500, ['consents' => $payload]);
}

if ($method === 'DELETE') {
    $stmt = $conn->prepare("DELETE FROM consensi_anonimi WHERE session_hash = ?");
    $stmt->bind_param("s", $sessHash);
    $stmt->execute();
    $stmt->close();
    json_response(200, ['status' => 'revoked']);
}

json_response(405, ['error' => 'Metodo non consentito']);
