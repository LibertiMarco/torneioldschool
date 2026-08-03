<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/user_features.php';

$currentPath = $_SERVER['REQUEST_URI'] ?? '/api/grafiche_settimana.php';

if (!isset($_SESSION['user_id'])) {
    login_remember_redirect($currentPath, login_with_base_path('/index.php'));
    header('Location: ' . login_with_base_path('/login.php'));
    exit;
}

if (!user_has_graphics_access((string)($_SESSION['ruolo'] ?? ''))) {
    header('Location: ' . login_with_base_path('/index.php'));
    exit;
}

header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Mon, 01 Jan 1990 00:00:00 GMT');
