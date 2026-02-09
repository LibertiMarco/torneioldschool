<?php
require_once __DIR__ . '/security.php';

$currentPath = $_SERVER['REQUEST_URI'] ?? '/admin_dashboard.php';

// Se non loggato, ricorda la destinazione e chiedi il login
if (!isset($_SESSION['user_id'])) {
    login_remember_redirect($currentPath, '/admin_dashboard.php');
    header('Location: /login.php');
    exit;
}

// Solo admin
if (($_SESSION['ruolo'] ?? '') !== 'admin') {
    header('Location: /index.php');
    exit;
}

// Evita cache ed indicizzazione
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Mon, 01 Jan 1990 00:00:00 GMT');
