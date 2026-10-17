<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/security.php';
require_once __DIR__ . '/../../../includi/recaptcha.php';
mobile_run(static function (): array {
    mobile_method('POST');
    $body = mobile_input();
    if (($body['client_id'] ?? '') !== 'tos-mobile') throw new MobileAuthError('invalid_client', 'Client non valido.', 400);
    if (!rate_limit_allow('login_attempt', 5, 900)) {
        header('Retry-After: ' . rate_limit_retry_after('login_attempt', 900));
        throw new MobileAuthError('rate_limited', 'Troppi tentativi. Riprova più tardi.', 429);
    }
    $siteKey = getenv('RECAPTCHA_SITE_KEY') ?: '';
    $secret = getenv('RECAPTCHA_SECRET_KEY') ?: '';
    if ($siteKey === '' || $secret === '') throw new MobileAuthError('unavailable', 'Verifica di sicurezza non configurata.', 503);
    if (!is_string($body['captcha'] ?? null) || !verify_recaptcha($secret, $body['captcha'], $_SERVER['REMOTE_ADDR'] ?? '')) {
        throw new MobileAuthError('invalid_challenge', 'Ripeti la verifica di sicurezza.', 400);
    }
    if (!is_string($body['email'] ?? null) || !is_string($body['password'] ?? null)) throw new MobileAuthError('invalid_request', 'Inserisci email e password.', 400);
    return (new MobileAuth(mobile_db()))->loginCredentials(trim($body['email']), $body['password']);
});
