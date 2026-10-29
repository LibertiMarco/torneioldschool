<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/mobile_logo_settings.php';
mobile_run(static function (): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        return mobile_logo_settings();
    }
    mobile_method('POST');
    (new MobileAuth(mobile_db()))->requirePermission(mobile_bearer(), 'admin');
    return mobile_logo_settings(mobile_input());
});
