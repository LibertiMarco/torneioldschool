<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../../includi/account_roles.php';
mobile_run(static function():array {
    mobile_method('POST');$db=mobile_db();
    $actor=(new MobileAuth($db))->requirePermission(mobile_bearer(),'admin');
    return account_role_save($db,(int)$actor['id'],mobile_input());
});
