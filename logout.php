<?php
session_start();
session_unset();
session_destroy();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, [
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);

    $rememberCookieName = defined('REMEMBER_COOKIE_NAME') ? REMEMBER_COOKIE_NAME : 'tos_keep_login';
    setcookie($rememberCookieName, '', time() - 42000, [
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

header("Location: index.php");
exit;
?>
