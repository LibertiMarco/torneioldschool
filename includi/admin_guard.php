<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/user_features.php';
require_once __DIR__ . '/content_sections.php';

$adminSection = content_current_section();
$adminIsEsport = $adminSection === 'esport';

$currentPath = $_SERVER['REQUEST_URI'] ?? '/admin_dashboard.php';

// Se non loggato, ricorda la destinazione e chiedi il login
if (!isset($_SESSION['user_id'])) {
    login_remember_redirect($currentPath, login_with_base_path('/admin_dashboard.php'));
    header('Location: ' . login_with_base_path('/login.php'));
    exit;
}

// Solo admin o sysadmin. La singola pagina che espone una vista limitata ai
// grafici puo abilitarli esplicitamente prima di includere questo guard.
$currentRole = trim((string)($_SESSION['ruolo'] ?? ''));
$graphicsAccessAllowed = !empty($allowGraphicsAccess) && $currentRole === 'grafico';
if (!user_has_admin_access($currentRole) && !$graphicsAccessAllowed) {
    header('Location: ' . login_with_base_path('/index.php'));
    exit;
}

// Evita cache ed indicizzazione
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Mon, 01 Jan 1990 00:00:00 GMT');
