<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/security.php';
// Reuses blog_commenti, validation, notifications, delete permissions and spam guard.
mobile_run(static function (): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET','POST'], true)) throw new MobileAuthError('method_not_allowed', 'Metodo non consentito.', 405);
    $operation = $_GET['operation'] ?? 'list';
    if ($method === 'POST' || mobile_authorization_header() !== '') {
        $user = (new MobileAuth(mobile_db()))->authenticate(mobile_bearer());
        // Never persist a bearer identity into a browser cookie session.
        session_write_close();
        $_SESSION = ['user_id'=>$user['id'], 'ruolo'=>$user['ruolo']];
        define('TOS_MOBILE_COMMENTS_AUTHENTICATED', true);
    } else {
        session_write_close();
        $_SESSION = [];
    }
    if ($method === 'GET') $_GET['azione'] = 'commenti';
    elseif ($operation === 'create') $_GET['azione'] = 'commenti_salva';
    elseif ($operation === 'delete') $_GET['azione'] = 'commenti_elimina';
    else throw new MobileAuthError('invalid_request', 'Operazione non valida.', 400);
    $_GET['page'] ??= '1';
    require __DIR__ . '/../../blog.php';
    return [];
});
