<?php
require_once __DIR__ . '/_bootstrap.php';
mobile_run(static function (): array {
    mobile_method('POST');
    $body = mobile_input();
    if (($body['client_id'] ?? '') !== 'tos-mobile') {
        throw new MobileAuthError('invalid_client', 'Client non valido.', 400);
    }
    $auth = new MobileAuth(mobile_db());
    if (($body['grant_type'] ?? '') === 'authorization_code' && ($body['redirect_uri'] ?? '') === MobileAuth::CALLBACK) {
        return $auth->exchange((string)($body['code'] ?? ''), (string)($body['code_verifier'] ?? ''));
    }
    if (($body['grant_type'] ?? '') === 'refresh_token') {
        return $auth->refresh((string)($body['refresh_token'] ?? ''));
    }
    throw new MobileAuthError('invalid_request', 'Richiesta non valida.', 400);
});
