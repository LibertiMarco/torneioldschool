<?php

function consent_bool($value): int
{
    return !empty($value) ? 1 : 0;
}

function consent_truncate_ip(?string $ip): ?string
{
    if (!$ip) {
        return null;
    }
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

function consent_get_user_email(mysqli $conn, int $userId): ?string
{
    $stmt = $conn->prepare("SELECT email FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row && !empty($row['email']) ? $row['email'] : null;
}

function consent_current_snapshot(mysqli $conn, int $userId, string $email = ''): array
{
    $default = [
        'marketing' => 0,
        'newsletter' => 0,
        'terms' => 0,
        'tracking' => 0,
        'updated_at' => null,
    ];

    $stmt = $conn->prepare("SELECT marketing, newsletter, terms, tracking, updated_at FROM consensi_utenti WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && ($row = $result->fetch_assoc())) {
        $default['marketing'] = (int)$row['marketing'];
        $default['newsletter'] = (int)$row['newsletter'];
        $default['terms'] = (int)$row['terms'];
        $default['tracking'] = (int)$row['tracking'];
        $default['updated_at'] = $row['updated_at'];
    } elseif ($email !== '') {
        // fallback by email if present
        $stmtEmail = $conn->prepare("SELECT marketing, newsletter, terms, tracking, updated_at FROM consensi_utenti WHERE email = ? LIMIT 1");
        if ($stmtEmail) {
            $stmtEmail->bind_param("s", $email);
            $stmtEmail->execute();
            $resEmail = $stmtEmail->get_result();
            if ($resEmail && ($rowEmail = $resEmail->fetch_assoc())) {
                $default['marketing'] = (int)$rowEmail['marketing'];
                $default['newsletter'] = (int)$rowEmail['newsletter'];
                $default['terms'] = (int)$rowEmail['terms'];
                $default['tracking'] = (int)$rowEmail['tracking'];
                $default['updated_at'] = $rowEmail['updated_at'];
            }
            $stmtEmail->close();
        }
    }
    $stmt->close();
    return $default;
}

function consent_log(mysqli $conn, int $userId, string $email, array $consents, string $source = 'manual', string $action = 'update'): void
{
    $ip = consent_truncate_ip($_SERVER['REMOTE_ADDR'] ?? null);
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (strlen($ua) > 255) {
        $ua = substr($ua, 0, 255);
    }

    $stmt = $conn->prepare("INSERT INTO consensi_log (user_id, email, marketing, newsletter, terms, tracking, action, source, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return;
    }
    $marketing = consent_bool($consents['marketing'] ?? 0);
    $newsletter = consent_bool($consents['newsletter'] ?? 0);
    $terms = consent_bool($consents['terms'] ?? 0);
    $tracking = consent_bool($consents['tracking'] ?? 0);
    $stmt->bind_param(
        "isiiiissss",
        $userId,
        $email,
        $marketing,
        $newsletter,
        $terms,
        $tracking,
        $action,
        $source,
        $ip,
        $ua
    );
    $stmt->execute();
    $stmt->close();
}

function consent_save(mysqli $conn, int $userId, string $email, array $payload, string $source = 'manual', string $action = 'update'): array
{
    $current = consent_current_snapshot($conn, $userId, $email);
    $next = [
        'marketing' => array_key_exists('marketing', $payload) ? consent_bool($payload['marketing']) : $current['marketing'],
        'newsletter' => array_key_exists('newsletter', $payload) ? consent_bool($payload['newsletter']) : $current['newsletter'],
        'terms' => array_key_exists('terms', $payload) ? consent_bool($payload['terms']) : $current['terms'],
        'tracking' => array_key_exists('tracking', $payload) ? consent_bool($payload['tracking']) : $current['tracking'],
    ];

    $stmt = $conn->prepare("INSERT INTO consensi_utenti (user_id, email, marketing, newsletter, terms, tracking) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE marketing = VALUES(marketing), newsletter = VALUES(newsletter), terms = VALUES(terms), tracking = VALUES(tracking), updated_at = CURRENT_TIMESTAMP");
    if ($stmt) {
        $stmt->bind_param(
            "isiiii",
            $userId,
            $email,
            $next['marketing'],
            $next['newsletter'],
            $next['terms'],
            $next['tracking']
        );
        $stmt->execute();
        $stmt->close();
    }

    consent_log($conn, $userId, $email, $next, $source, $action);

    $next['updated_at'] = date('Y-m-d H:i:s');
    return $next;
}
