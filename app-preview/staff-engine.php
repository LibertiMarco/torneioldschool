<?php
require_once __DIR__ . '/../api/mobile/v1/_bootstrap.php';
require_once __DIR__ . '/../includi/security.php';
require_once __DIR__ . '/../includi/app_preview.php';
require_once __DIR__ . '/../includi/staff_engine.php';
try {
    mobile_method('GET');
    $authorization = mobile_authorization_header();
    if ($authorization !== '') {
        if (!mobile_api_enabled()) throw new MobileAuthError('unavailable', 'Accesso mobile non ancora attivo.', 503);
        $https = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
        if (!$https) throw new MobileAuthError('https_required', 'È richiesta una connessione HTTPS.', 400);
        $user = (new MobileAuth(mobile_db()))->authenticate(mobile_bearer());
        // This WebView session belongs to the same verified bearer identity.
        staff_engine_source($_GET, $user['permissions'] ?? []);
        session_regenerate_id(true);
        $_SESSION['__created_at'] = time();
        foreach (['id' => 'user_id', 'email' => 'email', 'nome' => 'nome', 'cognome' => 'cognome', 'ruolo' => 'ruolo', 'avatar' => 'avatar'] as $key => $sessionKey) {
            $_SESSION[$sessionKey] = $user[$key] ?? null;
        }
        $_SESSION['remember_me'] = false;
    } else {
        if (empty($_SESSION['user_id'])) throw new MobileAuthError('invalid_session', 'Accedi nuovamente al tuo account.', 401);
        $user = preview_user(mobile_db(), $_SESSION);
        // Refresh the website controller guard from the verified database role.
        $_SESSION['ruolo'] = $user['ruolo'];
    }
    $source = staff_engine_source($_GET, $user['permissions'] ?? []);
    $nonce = $_GET['channel'] ?? '';
    if (!is_string($nonce) || !preg_match('/^[A-Za-z0-9_-]{24,64}$/D', $nonce)) throw new MobileAuthError('invalid_request', 'Sessione strumento non valida.', 400);
    session_write_close(); // AJAX requests and form submissions can use this session.
    header('Content-Type: text/html; charset=utf-8');
} catch (MobileAuthError $error) {
    mobile_json(['error' => ['code' => $error->errorCode, 'message' => $error->getMessage()]], $error->status);
} catch (Throwable $error) {
    error_log('Staff engine: ' . get_class($error) . ' request failed');
    mobile_json(['error' => ['code' => 'server_error', 'message' => 'Strumento temporaneamente non disponibile.']], 503);
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Strumento staff</title></head>
<body style="margin:0"><iframe id="staff-source" title="Controller staff" style="border:0;width:100%;height:100vh" src="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"></iframe>
<script>window.tosStaffChannel=<?= json_encode($nonce, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="/app-preview/staff-engine.js?v=<?= substr(hash_file('sha256', __DIR__ . '/staff-engine.js'), 0, 16) ?>"></script></body></html>
