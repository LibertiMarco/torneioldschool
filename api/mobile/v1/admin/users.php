<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../../includi/account_roles.php';
mobile_run(static function (): array {
    mobile_method('GET');
    (new MobileAuth(mobile_db()))->requirePermission(mobile_bearer(), 'admin');
    return account_users_list(mobile_db(),$_GET);
});
