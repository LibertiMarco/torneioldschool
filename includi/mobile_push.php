<?php
require_once __DIR__ . '/env_loader.php';

// Server credentials must live outside the document root, never in the app.
function tos_fcm_credentials(): ?array
{
    // Plesk keeps private/ alongside httpdocs/. The environment can override it
    // on other hosts, without putting credentials inside the public directory.
    $path = (string)(getenv('FCM_SERVICE_ACCOUNT_FILE')
        ?: dirname(__DIR__, 2) . '/private/torneioldschool-runtime/firebase-fcm.json');
    if ($path === '' || !is_file($path) || !is_readable($path)) { return null; }
    $resolved = strtolower(str_replace('\\', '/', (string)realpath($path)));
    $publicRoot = strtolower(str_replace('\\', '/', (string)realpath(dirname(__DIR__)))) . '/';
    if (str_starts_with($resolved, $publicRoot)) { return null; }
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data) || ($data['type'] ?? '') !== 'service_account'
        || ($data['project_id'] ?? '') !== 'tornei-old-school-9e11b'
        || empty($data['private_key']) || empty($data['client_email'])) { return null; }
    return $data;
}

function tos_fcm_token_valid(string $token): bool
{
    return strlen($token) >= 20 && strlen($token) <= 4096
        && preg_match('/^[A-Za-z0-9_:.-]+$/D', $token) === 1;
}

function tos_fcm_http(string $url, array $headers, string $body): array
{
    if (!function_exists('curl_init')) { return [0, []]; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, is_string($raw) ? (json_decode($raw, true) ?: []) : []];
}

function tos_fcm_access_token(array $credentials): ?string
{
    static $cached = null;
    if ($cached !== null && $cached['expires'] > time() + 60) { return $cached['token']; }
    $encode = static fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $now = time();
    $jwt = $encode('{"alg":"RS256","typ":"JWT"}') . '.' . $encode(json_encode([
        'iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
    ], JSON_THROW_ON_ERROR));
    if (!openssl_sign($jwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) { return null; }
    [$status, $result] = tos_fcm_http('https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt . '.' . $encode($signature),
        ]));
    if ($status !== 200 || empty($result['access_token'])) { return null; }
    $cached = ['token' => $result['access_token'], 'expires' => $now + (int)($result['expires_in'] ?? 3600)];
    return $cached['token'];
}

function tos_fcm_message(string $token, array $payload): array
{
    $url = (string)($payload['url'] ?? '/account.php');
    // Notification links are restricted to this site's HTTPS origin.
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        $url = 'https://torneioldschool.it' . $url;
    }
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'torneioldschool.it'
        || parse_url($url, PHP_URL_USER) !== null
        || !in_array(parse_url($url, PHP_URL_PORT), [null, 443], true)) { $url = 'https://torneioldschool.it/account.php'; }
    return ['message' => [
        'token' => $token,
        'notification' => ['title' => (string)($payload['title'] ?? 'Tornei Old School'),
            'body' => (string)($payload['body'] ?? '')],
        'data' => ['url' => $url],
        'android' => ['priority' => 'HIGH', 'ttl' => '86400s',
            'restricted_package_name' => 'it.torneioldschool.tornei_old_school',
            'notification' => ['icon' => 'ic_notification', 'sound' => 'default', 'channel_id' => 'tos_updates',
                'tag' => (string)($payload['tag'] ?? 'tos-notification')]],
    ]];
}

function tos_fcm_unregistered(array $response): bool
{
    foreach (($response['error']['details'] ?? []) as $detail) {
        if (($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
            && ($detail['errorCode'] ?? '') === 'UNREGISTERED') { return true; }
    }
    return false;
}

function tos_fcm_deliver(string $token, array $payload, array $credentials, string $access): array
{
    [$status, $result] = tos_fcm_http(
        'https://fcm.googleapis.com/v1/projects/' . rawurlencode($credentials['project_id']) . '/messages:send',
        ['Content-Type: application/json', 'Authorization: Bearer ' . $access],
        json_encode(tos_fcm_message($token, $payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    return ['success' => $status === 200, 'unregistered' => tos_fcm_unregistered($result)];
}

// Shared by the mysqli event producers and the PDO mobile API.
function tos_fcm_recipients_sql(string $placeholders): string
{
    return "SELECT d.token_hash, d.token FROM mobile_push_devices d
        JOIN mobile_sessions s ON s.id = d.session_id AND s.user_id = d.user_id
        JOIN utenti u ON u.id = d.user_id
        WHERE d.active = 1 AND d.user_id IN ($placeholders)
        AND s.revoked_at IS NULL AND s.refresh_expires_at > ?
        AND s.password_fingerprint = SHA2(u.password, 256) AND u.email_verificata = 1";
}

function tos_fcm_send_to_users(mysqli $conn, array $userIds, array $payload): array
{
    $result = ['sent' => 0, 'failed' => 0, 'subscriptions' => 0];
    $credentials = tos_fcm_credentials();
    $userIds = array_values(array_filter(array_unique(array_map('intval', $userIds)), static fn($id) => $id > 0));
    if (!$credentials || !$userIds) { return $result; }
    try {
        $stmt = $conn->prepare(tos_fcm_recipients_sql(implode(',', array_fill(0, count($userIds), '?'))));
        if (!$stmt) { return $result; }
        $values = [...$userIds, time()];
        $stmt->bind_param(str_repeat('i', count($values)), ...$values);
        $stmt->execute();
        $devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!$devices) { return $result; }
        $access = tos_fcm_access_token($credentials);
        if (!$access) { return ['sent' => 0, 'failed' => count($devices), 'subscriptions' => count($devices)]; }
        foreach ($devices as $device) {
            $sent = tos_fcm_deliver($device['token'], $payload, $credentials, $access);
            $result['subscriptions']++;
            $result[$sent['success'] ? 'sent' : 'failed']++;
            if ($sent['unregistered']) {
                $delete = $conn->prepare('DELETE FROM mobile_push_devices WHERE token_hash = ?');
                $delete->bind_param('s', $device['token_hash']);
                $delete->execute();
                $delete->close();
            }
        }
    } catch (Throwable $error) {
        // Push failures must never fail a match finalization or a comment insert.
        error_log('FCM: native notification delivery unavailable');
    }
    return $result;
}
