<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/security.php';
require_once __DIR__ . '/../../../includi/recaptcha.php';
require_once __DIR__ . '/../../../includi/password_recovery.php';
require_once __DIR__ . '/../../../includi/mail_helper.php';
mobile_run(static function (): array {
    mobile_method('POST');
    $body = mobile_input();
    if (!rate_limit_allow('forgot_password', 3, 60)) throw new MobileAuthError('rate_limited', 'Troppi tentativi. Riprova tra un minuto.', 429);
    $secret = getenv('RECAPTCHA_SECRET_KEY') ?: '';
    if ($secret === '') throw new MobileAuthError('unavailable', 'Verifica non configurata.', 503);
    if (!is_string($body['captcha'] ?? null) || !verify_recaptcha($secret, $body['captcha'], $_SERVER['REMOTE_ADDR'] ?? '')) throw new MobileAuthError('invalid_challenge', 'Ripeti la verifica di sicurezza.', 400);
    $email = is_string($body['email'] ?? null) ? trim($body['email']) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new MobileAuthError('invalid_request', 'Inserisci una email valida.', 400);
    require __DIR__ . '/../../../includi/db.php';
    $stmt = $conn->prepare('SELECT id, nome FROM utenti WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email); $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        if (!create_reset_token($conn, (int)$user['id'], $token, new DateTime('+1 hour')) ||
            !inviaEmailResetPassword($email, $user['nome'] ?? '', $token)) {
            throw new MobileAuthError('unavailable', 'Invio non riuscito. Riprova più tardi.', 503);
        }
    }
    return ['message'=>"Se l’email è registrata, riceverai un link per reimpostare la password."];
});
