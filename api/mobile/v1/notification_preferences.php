<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/notification_preferences.php';
mobile_run(static function (): array {
    $user = (new MobileAuth(mobile_db()))->authenticate(mobile_bearer());
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) mobile_method('GET');
    return notification_preferences_handle((int)$user['id'], $method === 'POST' ? mobile_input() : null);
});
