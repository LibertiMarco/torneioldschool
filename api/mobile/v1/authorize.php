<?php
require_once __DIR__ . '/_bootstrap.php';
mobile_run(static function (): array {
    mobile_method('GET');
    $request = MobileAuth::validateAuthorization($_GET);
    require_once __DIR__ . '/../../../includi/security.php';
    if (empty($_SESSION['user_id'])) {
        $returnPath = login_with_base_path('/api/mobile/v1/authorize.php') . '?' . http_build_query($_GET);
        login_remember_redirect($returnPath, login_with_base_path('/index.php'));
        header('Location: ' . login_with_base_path('/login.php'));
        exit;
    }
    $code = (new MobileAuth(mobile_db()))->authorize((int)$_SESSION['user_id'], $request['challenge']);
    header('Location: ' . MobileAuth::CALLBACK . '?' . http_build_query(['code' => $code, 'state' => $request['state']]));
    exit;
});
