<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/mobile_player_stats.php';
mobile_run(static function (): array {
    mobile_method('GET');
    $user = (new MobileAuth(mobile_db()))->authenticate(mobile_bearer());
    return mobile_player_stats_handle((int)$user['id'], $_GET);
});
