<?php
session_start();
$userId = $_SESSION['user_id'] ?? null;

$cookieDomain = '';
if (!empty($_SERVER['HTTP_HOST'])) {
    $host = strtolower((string)explode(':', $_SERVER['HTTP_HOST'])[0]);
    if ($host !== 'localhost' && !filter_var($host, FILTER_VALIDATE_IP)) {
        $cookieDomain = preg_replace('/^www\./', '', $host);
    }
}

if ($userId) {
    $dbPath = __DIR__ . '/includi/db.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("UPDATE utenti SET remember_selector = NULL, remember_token_hash = NULL, remember_expires_at = NULL WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

session_unset();
session_destroy();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    $domain = $params['domain'] ?? '';
    if ($domain === '') {
        $domain = $cookieDomain;
    }

    setcookie(session_name(), '', time() - 42000, [
        'path' => $params['path'] ?? '/',
        'domain' => $domain,
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);

    $rememberCookieName = defined('REMEMBER_COOKIE_NAME') ? REMEMBER_COOKIE_NAME : 'tos_keep_login';
    setcookie($rememberCookieName, '', time() - 42000, [
        'path' => $params['path'] ?? '/',
        'domain' => $domain,
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

$currentDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$basePath = rtrim(str_replace('\\', '/', $currentDir), '/');
$redirect = ($basePath === '' || $basePath === '.') ? '/index.php' : $basePath . '/index.php';
header("Location: {$redirect}");
exit;
?>
