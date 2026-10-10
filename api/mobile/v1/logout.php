<?php
require_once __DIR__ . '/_bootstrap.php';
mobile_run(static function (): array {
    mobile_method('POST');
    (new MobileAuth(mobile_db()))->logout(mobile_bearer());
    return ['success' => true];
});
