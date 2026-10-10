<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../../includi/mobile_account_features.php';
mobile_run(static function (): array {
    $db = mobile_db();
    $service = new MobileAccountFeatures($db, new MobileAuth($db));
    $access = mobile_bearer();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) { throw new MobileAuthError('invalid_request', 'Account non valido.', 400); }
        return $service->get($access, $id);
    }
    mobile_method('POST');
    $body = mobile_input();
    if (!is_int($body['id'] ?? null) || !is_array($body['feature_flags'] ?? null) || !is_string($body['revision'] ?? null)) {
        throw new MobileAuthError('invalid_request', 'Richiesta non valida.', 400);
    }
    return $service->save($access, $body['id'], $body['feature_flags'], $body['revision']);
});
