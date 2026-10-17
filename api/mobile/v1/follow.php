<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/mobile_follow.php';
mobile_run(static function (): array {
    $db = mobile_db();
    $user = (new MobileAuth($db))->authenticate(mobile_bearer());
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) { mobile_method('GET'); }
    return mobile_follow_handle($db, (int)$user['id'], $method === 'POST' ? mobile_input() : $_GET, $method === 'POST');
});