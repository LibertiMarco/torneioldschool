<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../includi/mobile_push.php';
mobile_run(static function (): array {
    $db = mobile_db();
    $bearer = mobile_bearer();
    $user = (new MobileAuth($db))->authenticate($bearer);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        return ['configured' => tos_fcm_credentials() !== null];
    }
    mobile_method('POST');
    $input = mobile_input();
    $action = $input['action'] ?? '';
    $token = is_string($input['token'] ?? null) ? $input['token'] : '';
    if (!tos_fcm_token_valid($token) || !in_array($action, ['subscribe', 'unsubscribe', 'test'], true)) {
        throw new MobileAuthError('invalid_request', 'Richiesta notifiche non valida.', 422);
    }
    $hash = hash('sha256', $token);
    if ($action === 'unsubscribe') {
        $stmt = $db->prepare('DELETE FROM mobile_push_devices WHERE token_hash = ? AND user_id = ?');
        $stmt->execute([$hash, $user['id']]);
        return ['success' => true];
    }
    $credentials = tos_fcm_credentials();
    if (!$credentials) {
        throw new MobileAuthError('push_unavailable', 'Le notifiche sono in preparazione. Riprova più tardi.', 503);
    }
    $stmt = $db->prepare('SELECT id FROM mobile_sessions WHERE access_hash = ? AND user_id = ? AND revoked_at IS NULL');
    $stmt->execute([hash('sha256', $bearer), $user['id']]);
    $session = $stmt->fetchColumn();
    if (!$session) { throw new MobileAuthError('invalid_session', 'Accedi nuovamente.'); }
    if ($action === 'subscribe') {
        $stmt = $db->prepare('INSERT INTO mobile_push_devices (token_hash, token, user_id, session_id, active, updated_at)
            VALUES (?, ?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE
            token = VALUES(token), user_id = VALUES(user_id), session_id = VALUES(session_id), active = 1, updated_at = VALUES(updated_at)');
        $stmt->execute([$hash, $token, $user['id'], $session, time()]);
        return ['success' => true];
    }
    // Test only the current device, never every device belonging to the user.
    $stmt = $db->prepare('UPDATE mobile_push_devices SET last_test_at = ?
        WHERE token_hash = ? AND user_id = ? AND session_id = ? AND active = 1 AND last_test_at <= ?');
    $stmt->execute([time(), $hash, $user['id'], $session, time() - 60]);
    if (!$stmt->rowCount()) {
        throw new MobileAuthError('push_test_limited', 'Attiva le notifiche e attendi un minuto tra due test.', 429);
    }
    $access = tos_fcm_access_token($credentials);
    $sent = $access ? tos_fcm_deliver($token, ['title' => 'Test notifiche',
        'body' => 'Le notifiche di Tornei Old School sono attive su questo dispositivo.',
        'url' => '/account.php', 'tag' => 'tos-push-test'], $credentials, $access) : ['success' => false];
    if (!$sent['success']) {
        throw new MobileAuthError('push_failed', 'Invio del test non riuscito. Riprova più tardi.', 503);
    }
    return ['success' => true];
});
