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
    $action = $body['action'] ?? 'create';
    if ($action === 'remove') {
        $service->remove($body['name']);
        mobile_json(['referees'=>$service->names()]);
    }
    if ($action === 'rename') {
        if (!is_string($body['old_name'] ?? null)) throw new InvalidArgumentException('Seleziona un arbitro.');
        $name = $service->rename($body['old_name'], $body['name']);
    } elseif ($action === 'create') {
        $name = $service->create($body['name']);
    } else {
        throw new InvalidArgumentException('Operazione non valida.');
    }
    mobile_json(['name'=>$name, 'referees'=>$service->names()]);
} catch (InvalidArgumentException $error) {
    mobile_json(['error'=>['message'=>$error->getMessage()]], 400);
} catch (MobileAuthError $error) {
    mobile_json(['error'=>['message'=>$error->getMessage()]], $error->status);
} catch (Throwable $error) {
    error_log('Referee directory: request failed');
    mobile_json(['error'=>['message'=>'Anagrafica arbitri non disponibile.']], 503);
}
