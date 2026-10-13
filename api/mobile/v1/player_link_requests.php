<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/player_link_requests.php';
mobile_run(static function (): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) throw new MobileAuthError('method_not_allowed', 'Metodo non consentito.', 405);
    $user = (new MobileAuth(mobile_db()))->authenticate(mobile_bearer());
    return player_link_requests_handle(player_link_requests_db(), (int)$user['id'], $_GET, $method === 'POST' ? mobile_input() : null);
});
