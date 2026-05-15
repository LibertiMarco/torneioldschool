<?php
require_once __DIR__ . '/env_loader.php';

if (!function_exists('tos_push_base64url_encode')) {
    function tos_push_base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('tos_push_base64url_decode')) {
    function tos_push_base64url_decode(string $value): string
    {
        $normalized = strtr(trim($value), '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        return $decoded === false ? '' : $decoded;
    }
}

if (!function_exists('tos_push_trim_utf8')) {
    function tos_push_trim_utf8(string $value, int $maxLen): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value) > $maxLen ? mb_substr($value, 0, $maxLen) : $value;
        }

        return strlen($value) > $maxLen ? substr($value, 0, $maxLen) : $value;
    }
}

if (!function_exists('tos_push_vapid_subject')) {
    function tos_push_vapid_subject(): string
    {
        $subject = trim((string)(getenv('VAPID_SUBJECT') ?: ''));
        if ($subject !== '') {
            return $subject;
        }

        $replyTo = trim((string)(getenv('MAIL_REPLY_TO') ?: 'info@torneioldschool.it'));
        return stripos($replyTo, 'mailto:') === 0 ? $replyTo : 'mailto:' . $replyTo;
    }
}

if (!function_exists('tos_push_vapid_private_pem')) {
    function tos_push_vapid_private_pem(): string
    {
        $pemB64 = trim((string)(getenv('VAPID_PRIVATE_KEY_PEM_B64') ?: ''));
        if ($pemB64 !== '') {
            $decoded = base64_decode($pemB64, true);
            if (is_string($decoded) && strpos($decoded, 'BEGIN PRIVATE KEY') !== false) {
                return $decoded;
            }
        }

        $pem = trim((string)(getenv('VAPID_PRIVATE_KEY_PEM') ?: ''));
        if ($pem === '') {
            return '';
        }

        $pem = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $pem);
        return strpos($pem, 'BEGIN PRIVATE KEY') !== false ? $pem : '';
    }
}

if (!function_exists('tos_push_vapid_config')) {
    function tos_push_vapid_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = [
            'configured' => false,
            'public_key' => '',
            'public_raw' => '',
            'private_pem' => '',
            'private_key' => null,
            'subject' => tos_push_vapid_subject(),
        ];

        $pem = tos_push_vapid_private_pem();
        if ($pem === '') {
            return $config;
        }

        $privateKey = openssl_pkey_get_private($pem);
        if (!$privateKey) {
            error_log('push_notifications: chiave privata VAPID non valida.');
            return $config;
        }

        $details = openssl_pkey_get_details($privateKey);
        if (
            !is_array($details)
            || !isset($details['ec']['x'], $details['ec']['y'])
            || !is_string($details['ec']['x'])
            || !is_string($details['ec']['y'])
        ) {
            error_log('push_notifications: impossibile leggere la chiave pubblica VAPID.');
            return $config;
        }

        $derivedPublicRaw = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        $envPublicKey = trim((string)(getenv('VAPID_PUBLIC_KEY') ?: ''));
        $publicRaw = $envPublicKey !== '' ? tos_push_base64url_decode($envPublicKey) : $derivedPublicRaw;

        if (strlen($publicRaw) !== 65 || $publicRaw[0] !== "\x04") {
            error_log('push_notifications: chiave pubblica VAPID non valida.');
            return $config;
        }

        $config = [
            'configured' => true,
            'public_key' => tos_push_base64url_encode($publicRaw),
            'public_raw' => $publicRaw,
            'private_pem' => $pem,
            'private_key' => $privateKey,
            'subject' => tos_push_vapid_subject(),
        ];

        return $config;
    }
}

if (!function_exists('tos_push_is_configured')) {
    function tos_push_is_configured(): bool
    {
        $config = tos_push_vapid_config();
        return !empty($config['configured']);
    }
}

if (!function_exists('tos_push_ensure_notifications_table')) {
    function tos_push_ensure_notifications_table(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS notifiche (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                utente_id INT UNSIGNED NOT NULL,
                tipo VARCHAR(50) NOT NULL DEFAULT 'generic',
                titolo VARCHAR(255) NOT NULL,
                testo TEXT NULL,
                link VARCHAR(255) DEFAULT NULL,
                letto TINYINT(1) NOT NULL DEFAULT 0,
                creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notifiche_user (utente_id, letto, creato_il),
                CONSTRAINT fk_notifiche_user FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
}

if (!function_exists('tos_push_ensure_subscriptions_table')) {
    function tos_push_ensure_subscriptions_table(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                endpoint TEXT NOT NULL,
                endpoint_hash CHAR(64) NOT NULL,
                p256dh VARCHAR(255) NOT NULL,
                auth VARCHAR(255) NOT NULL,
                content_encoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
                active TINYINT(1) NOT NULL DEFAULT 1,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_success_at DATETIME DEFAULT NULL,
                last_failure_at DATETIME DEFAULT NULL,
                last_error TEXT DEFAULT NULL,
                UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
                KEY idx_push_user_active (user_id, active),
                CONSTRAINT fk_push_subscription_user FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
}

if (!function_exists('tos_push_normalize_subscription')) {
    function tos_push_normalize_subscription(array $payload): ?array
    {
        $endpoint = trim((string)($payload['endpoint'] ?? ''));
        $keys = isset($payload['keys']) && is_array($payload['keys']) ? $payload['keys'] : [];
        $p256dh = trim((string)($keys['p256dh'] ?? ($payload['p256dh'] ?? '')));
        $auth = trim((string)($keys['auth'] ?? ($payload['auth'] ?? '')));
        $encoding = trim((string)($payload['contentEncoding'] ?? ($payload['content_encoding'] ?? 'aes128gcm')));

        if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL) || $p256dh === '' || $auth === '') {
            return null;
        }

        $publicRaw = tos_push_base64url_decode($p256dh);
        $authRaw = tos_push_base64url_decode($auth);
        if (strlen($publicRaw) !== 65 || $publicRaw[0] !== "\x04" || strlen($authRaw) < 16) {
            return null;
        }

        if ($encoding !== 'aes128gcm') {
            $encoding = 'aes128gcm';
        }

        return [
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'content_encoding' => $encoding,
        ];
    }
}

if (!function_exists('tos_push_save_subscription')) {
    function tos_push_save_subscription(mysqli $conn, int $userId, array $payload): bool
    {
        $normalized = tos_push_normalize_subscription($payload);
        if ($userId <= 0 || !$normalized) {
            return false;
        }

        tos_push_ensure_subscriptions_table($conn);

        $endpointHash = hash('sha256', $normalized['endpoint']);
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $stmt = $conn->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, content_encoding, active, user_agent, last_error, last_failure_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, NULL, NULL)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                endpoint = VALUES(endpoint),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                content_encoding = VALUES(content_encoding),
                active = 1,
                user_agent = VALUES(user_agent),
                updated_at = CURRENT_TIMESTAMP,
                last_error = NULL,
                last_failure_at = NULL
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'issssss',
            $userId,
            $normalized['endpoint'],
            $endpointHash,
            $normalized['p256dh'],
            $normalized['auth'],
            $normalized['content_encoding'],
            $userAgent
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('tos_push_delete_subscription')) {
    function tos_push_delete_subscription(mysqli $conn, int $userId, ?array $payload = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        tos_push_ensure_subscriptions_table($conn);

        if ($payload !== null) {
            $normalized = tos_push_normalize_subscription($payload);
            if (!$normalized) {
                return false;
            }
            $endpointHash = hash('sha256', $normalized['endpoint']);
            $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('is', $userId, $endpointHash);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }

        $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('tos_push_subscription_status')) {
    function tos_push_subscription_status(mysqli $conn, int $userId): array
    {
        tos_push_ensure_subscriptions_table($conn);
        $config = tos_push_vapid_config();
        $count = 0;

        if ($userId > 0) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS totale FROM push_subscriptions WHERE user_id = ? AND active = 1");
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                if ($stmt->execute()) {
                    $row = $stmt->get_result()->fetch_assoc();
                    $count = (int)($row['totale'] ?? 0);
                }
                $stmt->close();
            }
        }

        return [
            'configured' => !empty($config['configured']),
            'publicKey' => $config['public_key'] ?? '',
            'subject' => $config['subject'] ?? '',
            'subscriptionCount' => $count,
            'hasSubscription' => $count > 0,
        ];
    }
}

if (!function_exists('tos_push_build_payload')) {
    function tos_push_build_payload(string $title, string $body, string $url = '', array $extra = []): array
    {
        $safeUrl = trim($url);
        if ($safeUrl === '') {
            $safeUrl = '/account.php';
        } elseif (strpos($safeUrl, 'http://') !== 0 && strpos($safeUrl, 'https://') !== 0 && strpos($safeUrl, '/') !== 0) {
            $safeUrl = '/' . ltrim($safeUrl, '/');
        }

        $tag = trim((string)($extra['tag'] ?? 'tos-notification'));
        if ($tag === '') {
            $tag = 'tos-notification';
        }

        $payload = [
            'title' => tos_push_trim_utf8($title, 80) ?: 'Tornei Old School',
            'body' => tos_push_trim_utf8($body, 220),
            'icon' => '/img/logo_old_school.png',
            'badge' => '/img/logo_old_school.png',
            'url' => $safeUrl,
            'tag' => $tag,
            'renotify' => !empty($extra['renotify']),
            'timestamp' => (int)round(microtime(true) * 1000),
        ];

        if (!empty($extra['data']) && is_array($extra['data'])) {
            $payload['data'] = $extra['data'];
        }

        return $payload;
    }
}

if (!function_exists('tos_push_store_notifications_for_users')) {
    function tos_push_store_notifications_for_users(
        mysqli $conn,
        array $userIds,
        string $tipo,
        string $titolo,
        string $testo,
        string $link = '',
        array $pushExtra = []
    ): array {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, static function (int $id): bool {
            return $id > 0;
        }));
        if (empty($userIds)) {
            return ['stored' => 0, 'pushed' => 0];
        }

        tos_push_ensure_notifications_table($conn);

        $stored = 0;
        $stmt = $conn->prepare("INSERT INTO notifiche (utente_id, tipo, titolo, testo, link) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            foreach ($userIds as $uid) {
                $stmt->bind_param('issss', $uid, $tipo, $titolo, $testo, $link);
                if ($stmt->execute()) {
                    $stored++;
                }
            }
            $stmt->close();
        }

        $payload = tos_push_build_payload($titolo, $testo, $link, $pushExtra);
        $pushResult = tos_push_send_to_users($conn, $userIds, $payload);

        return [
            'stored' => $stored,
            'pushed' => (int)($pushResult['sent'] ?? 0),
        ];
    }
}

if (!function_exists('tos_push_send_to_users')) {
    function tos_push_send_to_users(mysqli $conn, array $userIds, array $payload): array
    {
        $config = tos_push_vapid_config();
        if (empty($config['configured'])) {
            return ['sent' => 0, 'failed' => 0, 'subscriptions' => 0];
        }

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, static function (int $id): bool {
            return $id > 0;
        }));
        if (empty($userIds)) {
            return ['sent' => 0, 'failed' => 0, 'subscriptions' => 0];
        }

        tos_push_ensure_subscriptions_table($conn);

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));
        $sql = "
            SELECT id, user_id, endpoint, p256dh, auth, content_encoding
            FROM push_subscriptions
            WHERE active = 1 AND user_id IN ($placeholders)
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['sent' => 0, 'failed' => 0, 'subscriptions' => 0];
        }

        $stmt->bind_param($types, ...$userIds);
        $subscriptions = [];
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $subscriptions[] = $row;
            }
        }
        $stmt->close();

        if (empty($subscriptions)) {
            return ['sent' => 0, 'failed' => 0, 'subscriptions' => 0];
        }

        $sent = 0;
        $failed = 0;
        foreach ($subscriptions as $subscription) {
            $result = tos_push_send_to_subscription($conn, $subscription, $payload, $config);
            if (!empty($result['success'])) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'subscriptions' => count($subscriptions)];
    }
}

if (!function_exists('tos_push_send_test')) {
    function tos_push_send_test(mysqli $conn, int $userId): array
    {
        $payload = tos_push_build_payload(
            'Test notifiche',
            'Le notifiche push di Tornei Old School sono attive su questo dispositivo.',
            '/account.php',
            ['tag' => 'tos-push-test']
        );

        return tos_push_send_to_users($conn, [$userId], $payload);
    }
}

if (!function_exists('tos_push_send_to_subscription')) {
    function tos_push_send_to_subscription(mysqli $conn, array $subscription, array $payload, array $config): array
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson) || $payloadJson === '') {
            return ['success' => false, 'error' => 'payload_json_invalid'];
        }

        if (strlen($payloadJson) > 3000) {
            $payloadJson = json_encode([
                'title' => $payload['title'] ?? 'Tornei Old School',
                'body' => tos_push_trim_utf8((string)($payload['body'] ?? ''), 180),
                'url' => $payload['url'] ?? '/account.php',
                'icon' => $payload['icon'] ?? '/img/logo_old_school.png',
                'badge' => $payload['badge'] ?? '/img/logo_old_school.png',
                'tag' => $payload['tag'] ?? 'tos-notification',
                'timestamp' => $payload['timestamp'] ?? (int)round(microtime(true) * 1000),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $encrypted = tos_push_encrypt_payload($payloadJson, $subscription);
        if (empty($encrypted['body'])) {
            tos_push_mark_subscription_failure($conn, (int)($subscription['id'] ?? 0), (string)($encrypted['error'] ?? 'encrypt_failed'));
            return ['success' => false, 'error' => $encrypted['error'] ?? 'encrypt_failed'];
        }

        $origin = tos_push_endpoint_origin((string)($subscription['endpoint'] ?? ''));
        if ($origin === '') {
            tos_push_mark_subscription_failure($conn, (int)($subscription['id'] ?? 0), 'invalid_endpoint_origin');
            return ['success' => false, 'error' => 'invalid_endpoint_origin'];
        }

        $jwt = tos_push_build_vapid_jwt($origin, $config);
        if ($jwt === '') {
            tos_push_mark_subscription_failure($conn, (int)($subscription['id'] ?? 0), 'vapid_jwt_failed');
            return ['success' => false, 'error' => 'vapid_jwt_failed'];
        }

        $headers = [
            'TTL: 86400',
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: vapid t=' . $jwt . ', k=' . $config['public_key'],
            'Urgency: normal',
        ];

        $response = tos_push_http_post((string)$subscription['endpoint'], $headers, $encrypted['body']);
        $status = (int)($response['status'] ?? 0);

        if ($status >= 200 && $status < 300) {
            tos_push_mark_subscription_success($conn, (int)($subscription['id'] ?? 0));
            return ['success' => true, 'status' => $status];
        }

        if ($status === 404 || $status === 410) {
            tos_push_delete_subscription_by_id($conn, (int)($subscription['id'] ?? 0));
        } else {
            $errorMessage = trim((string)($response['error'] ?? 'push_http_' . $status));
            tos_push_mark_subscription_failure($conn, (int)($subscription['id'] ?? 0), $errorMessage);
        }

        return ['success' => false, 'status' => $status, 'error' => $response['error'] ?? 'push_http_failed'];
    }
}

if (!function_exists('tos_push_encrypt_payload')) {
    function tos_push_encrypt_payload(string $payloadJson, array $subscription): array
    {
        $uaPublicRaw = tos_push_base64url_decode((string)($subscription['p256dh'] ?? ''));
        $authSecret = tos_push_base64url_decode((string)($subscription['auth'] ?? ''));
        if (strlen($uaPublicRaw) !== 65 || $uaPublicRaw[0] !== "\x04" || strlen($authSecret) < 16) {
            return ['body' => '', 'error' => 'subscription_keys_invalid'];
        }

        $serverKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if (!$serverKey) {
            return ['body' => '', 'error' => 'ecdh_keygen_failed'];
        }

        openssl_pkey_export($serverKey, $serverPrivatePem);
        $serverDetails = openssl_pkey_get_details($serverKey);
        if (
            !is_array($serverDetails)
            || !isset($serverDetails['ec']['x'], $serverDetails['ec']['y'])
            || !is_string($serverDetails['ec']['x'])
            || !is_string($serverDetails['ec']['y'])
        ) {
            return ['body' => '', 'error' => 'ecdh_key_details_failed'];
        }

        $serverPublicRaw = "\x04" . $serverDetails['ec']['x'] . $serverDetails['ec']['y'];
        $userPublicPem = tos_push_public_raw_to_pem($uaPublicRaw);
        if ($userPublicPem === '') {
            return ['body' => '', 'error' => 'user_public_pem_failed'];
        }

        $userPublicKey = openssl_pkey_get_public($userPublicPem);
        $serverPrivateKey = openssl_pkey_get_private($serverPrivatePem);
        if (!$userPublicKey || !$serverPrivateKey) {
            return ['body' => '', 'error' => 'ecdh_resource_failed'];
        }

        $ecdhSecret = openssl_pkey_derive($userPublicKey, $serverPrivateKey, 32);
        if (!is_string($ecdhSecret) || strlen($ecdhSecret) !== 32) {
            return ['body' => '', 'error' => 'ecdh_derive_failed'];
        }

        $salt = random_bytes(16);
        $prkKey = hash_hmac('sha256', $ecdhSecret, $authSecret, true);
        $keyInfo = "WebPush: info\0" . $uaPublicRaw . $serverPublicRaw;
        $ikm = hash_hmac('sha256', $keyInfo . "\x01", $prkKey, true);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

        $plaintext = $payloadJson . "\x02";
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if (!is_string($ciphertext) || $tag === '') {
            return ['body' => '', 'error' => 'aes128gcm_encrypt_failed'];
        }

        $rs = max(4096, strlen($plaintext) + 17);
        $body = $salt . pack('N', $rs) . chr(strlen($serverPublicRaw)) . $serverPublicRaw . $ciphertext . $tag;

        return ['body' => $body];
    }
}

if (!function_exists('tos_push_endpoint_origin')) {
    function tos_push_endpoint_origin(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $origin = strtolower((string)$parts['scheme']) . '://' . strtolower((string)$parts['host']);
        if (!empty($parts['port'])) {
            $origin .= ':' . (int)$parts['port'];
        }

        return $origin;
    }
}

if (!function_exists('tos_push_build_vapid_jwt')) {
    function tos_push_build_vapid_jwt(string $audience, array $config): string
    {
        if ($audience === '' || empty($config['private_key']) || empty($config['subject'])) {
            return '';
        }

        $header = tos_push_base64url_encode((string)json_encode([
            'typ' => 'JWT',
            'alg' => 'ES256',
        ], JSON_UNESCAPED_SLASHES));
        $payload = tos_push_base64url_encode((string)json_encode([
            'aud' => $audience,
            'exp' => time() + 3600,
            'sub' => $config['subject'],
        ], JSON_UNESCAPED_SLASHES));
        $signingInput = $header . '.' . $payload;

        $signatureDer = '';
        if (!openssl_sign($signingInput, $signatureDer, $config['private_key'], OPENSSL_ALGO_SHA256)) {
            return '';
        }

        $signatureJose = tos_push_der_to_jose($signatureDer, 64);
        if ($signatureJose === '') {
            return '';
        }

        return $signingInput . '.' . tos_push_base64url_encode($signatureJose);
    }
}

if (!function_exists('tos_push_http_post')) {
    function tos_push_http_post(string $endpoint, array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HEADER => false,
            ]);
            $responseBody = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            return [
                'status' => $status,
                'body' => is_string($responseBody) ? $responseBody : '',
                'error' => $error,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 20,
            ],
        ]);
        $responseBody = @file_get_contents($endpoint, false, $context);
        $status = 0;
        if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int)$matches[1];
        }

        return [
            'status' => $status,
            'body' => is_string($responseBody) ? $responseBody : '',
            'error' => '',
        ];
    }
}

if (!function_exists('tos_push_mark_subscription_success')) {
    function tos_push_mark_subscription_success(mysqli $conn, int $subscriptionId): void
    {
        if ($subscriptionId <= 0) {
            return;
        }

        $stmt = $conn->prepare("
            UPDATE push_subscriptions
            SET active = 1, last_success_at = NOW(), last_failure_at = NULL, last_error = NULL
            WHERE id = ?
        ");
        if ($stmt) {
            $stmt->bind_param('i', $subscriptionId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('tos_push_mark_subscription_failure')) {
    function tos_push_mark_subscription_failure(mysqli $conn, int $subscriptionId, string $error): void
    {
        if ($subscriptionId <= 0) {
            return;
        }

        $safeError = tos_push_trim_utf8($error, 500);
        $stmt = $conn->prepare("
            UPDATE push_subscriptions
            SET last_failure_at = NOW(), last_error = ?
            WHERE id = ?
        ");
        if ($stmt) {
            $stmt->bind_param('si', $safeError, $subscriptionId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('tos_push_delete_subscription_by_id')) {
    function tos_push_delete_subscription_by_id(mysqli $conn, int $subscriptionId): void
    {
        if ($subscriptionId <= 0) {
            return;
        }

        $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $subscriptionId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('tos_push_public_raw_to_pem')) {
    function tos_push_public_raw_to_pem(string $publicRaw): string
    {
        if (strlen($publicRaw) !== 65 || $publicRaw[0] !== "\x04") {
            return '';
        }

        $algorithmIdentifier = tos_push_asn1_sequence(
            tos_push_asn1_oid('1.2.840.10045.2.1') .
            tos_push_asn1_oid('1.2.840.10045.3.1.7')
        );
        $subjectPublicKeyInfo = tos_push_asn1_sequence(
            $algorithmIdentifier .
            tos_push_asn1_bit_string($publicRaw)
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }
}

if (!function_exists('tos_push_der_to_jose')) {
    function tos_push_der_to_jose(string $derSignature, int $partLength = 64): string
    {
        $offset = 0;
        if (!isset($derSignature[$offset]) || ord($derSignature[$offset]) !== 0x30) {
            return '';
        }
        $offset++;
        $sequenceLength = tos_push_read_asn1_length($derSignature, $offset);
        if ($sequenceLength < 0) {
            return '';
        }
        if (!isset($derSignature[$offset]) || ord($derSignature[$offset]) !== 0x02) {
            return '';
        }
        $offset++;
        $rLength = tos_push_read_asn1_length($derSignature, $offset);
        if ($rLength < 0) {
            return '';
        }
        $r = substr($derSignature, $offset, $rLength);
        $offset += $rLength;

        if (!isset($derSignature[$offset]) || ord($derSignature[$offset]) !== 0x02) {
            return '';
        }
        $offset++;
        $sLength = tos_push_read_asn1_length($derSignature, $offset);
        if ($sLength < 0) {
            return '';
        }
        $s = substr($derSignature, $offset, $sLength);

        $componentLength = (int)($partLength / 2);
        $r = str_pad(ltrim($r, "\x00"), $componentLength, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), $componentLength, "\x00", STR_PAD_LEFT);

        return strlen($r) === $componentLength && strlen($s) === $componentLength ? $r . $s : '';
    }
}

if (!function_exists('tos_push_read_asn1_length')) {
    function tos_push_read_asn1_length(string $value, int &$offset): int
    {
        if (!isset($value[$offset])) {
            return -1;
        }

        $length = ord($value[$offset]);
        $offset++;
        if (($length & 0x80) === 0) {
            return $length;
        }

        $octets = $length & 0x7F;
        if ($octets === 0 || $octets > 4) {
            return -1;
        }

        $length = 0;
        for ($i = 0; $i < $octets; $i++) {
            if (!isset($value[$offset])) {
                return -1;
            }
            $length = ($length << 8) | ord($value[$offset]);
            $offset++;
        }

        return $length;
    }
}

if (!function_exists('tos_push_asn1_sequence')) {
    function tos_push_asn1_sequence(string $value): string
    {
        return "\x30" . tos_push_asn1_length(strlen($value)) . $value;
    }
}

if (!function_exists('tos_push_asn1_bit_string')) {
    function tos_push_asn1_bit_string(string $value): string
    {
        return "\x03" . tos_push_asn1_length(strlen($value) + 1) . "\x00" . $value;
    }
}

if (!function_exists('tos_push_asn1_oid')) {
    function tos_push_asn1_oid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        if (count($parts) < 2) {
            return '';
        }

        $encoded = chr(($parts[0] * 40) + $parts[1]);
        foreach (array_slice($parts, 2) as $part) {
            $chunk = '';
            do {
                $chunk = chr($part & 0x7F) . $chunk;
                $part >>= 7;
            } while ($part > 0);

            $chunkLen = strlen($chunk);
            for ($i = 0; $i < $chunkLen - 1; $i++) {
                $chunk[$i] = chr(ord($chunk[$i]) | 0x80);
            }
            $encoded .= $chunk;
        }

        return "\x06" . tos_push_asn1_length(strlen($encoded)) . $encoded;
    }
}

if (!function_exists('tos_push_asn1_length')) {
    function tos_push_asn1_length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $octets = '';
        while ($length > 0) {
            $octets = chr($length & 0xFF) . $octets;
            $length >>= 8;
        }

        return chr(0x80 | strlen($octets)) . $octets;
    }
}
