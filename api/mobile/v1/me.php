<?php
require_once __DIR__ . '/_bootstrap.php';
mobile_run(static function (): array {
    mobile_method('GET');
    return ['user' => (new MobileAuth(mobile_db()))->authenticate(mobile_bearer())];
});
