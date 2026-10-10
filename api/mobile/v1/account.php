<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/mobile_profile.php';
mobile_run(static function (): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new MobileAuthError('method_not_allowed', 'Metodo non consentito.', 405);
    }
    $user = (new MobileAuth(mobile_db()))->authenticate(mobile_bearer());
    $multipart = str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
    $input = $method === 'GET' ? null : ($multipart ? $_POST : mobile_input());
    return mobile_profile_handle((int)$user['id'], $input, $_FILES);
});
