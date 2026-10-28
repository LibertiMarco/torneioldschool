<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/security.php';
require_once __DIR__ . '/../../../includi/mobile_account_delete.php';
mobile_run(static function (): array {
    mobile_method('POST');
    $db = mobile_db();
    $user = (new MobileAuth($db))->authenticate(mobile_bearer());
    if (!rate_limit_allow('mobile_delete_account_' . (int)$user['id'], 5, 3600)) {
        throw new MobileAuthError('rate_limited', 'Troppi tentativi. Riprova piu tardi.', 429);
    }
    return mobile_delete_account($db, (int)$user['id'], mobile_input());
});
