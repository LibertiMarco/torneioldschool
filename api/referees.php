<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/mobile/v1/_bootstrap.php';
require_once __DIR__ . '/../includi/referees.php';
try {
    $service = new RefereeDirectory(mobile_db());
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') mobile_json(['referees'=>$service->names()]);
    mobile_method('POST');
    $body = mobile_input();
    if (!csrf_is_valid(is_string($body['_csrf'] ?? null) ? $body['_csrf'] : null, 'referee_directory')) {
        throw new MobileAuthError('invalid_csrf', 'Sessione scaduta. Ricarica la pagina.', 403);
    }
    if (!is_string($body['name'] ?? null)) throw new InvalidArgumentException('Inserisci nome e cognome.');
    mobile_json(['name'=>$service->create($body['name'])]);
} catch (InvalidArgumentException $error) {
    mobile_json(['error'=>['message'=>$error->getMessage()]], 400);
} catch (MobileAuthError $error) {
    mobile_json(['error'=>['message'=>$error->getMessage()]], $error->status);
} catch (Throwable $error) {
    error_log('Referee directory: request failed');
    mobile_json(['error'=>['message'=>'Anagrafica arbitri non disponibile.']], 503);
}
