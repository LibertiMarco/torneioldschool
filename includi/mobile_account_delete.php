<?php
require_once __DIR__ . '/mobile_auth.php';

// The caller supplies an identity verified by the bearer, never by the input.
function mobile_delete_account(PDO $db, int $userId, array $input): array
{
    if (($input['confirm_delete'] ?? null) !== true ||
        !is_string($input['password'] ?? null) || $input['password'] === '') {
        throw new MobileAuthError('invalid_request', 'Conferma la cancellazione e inserisci la password.', 422);
    }
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT password FROM utenti WHERE id = ?' .
            ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($input['password'], $hash)) {
            throw new MobileAuthError('invalid_credentials', 'Password non corretta.', 403);
        }
        // Same account/consent deletion as the website, with explicit mobile cleanup.
        foreach ([
            'DELETE FROM consensi_log WHERE user_id = ?',
            'DELETE FROM mobile_refresh_tokens WHERE session_id IN (SELECT id FROM mobile_sessions WHERE user_id = ?)',
            'DELETE FROM mobile_sessions WHERE user_id = ?',
            'DELETE FROM mobile_auth_codes WHERE user_id = ?',
            'DELETE FROM utenti WHERE id = ?',
        ] as $sql) {
            $db->prepare($sql)->execute([$userId]);
        }
        $db->commit();
        return ['success' => true];
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
