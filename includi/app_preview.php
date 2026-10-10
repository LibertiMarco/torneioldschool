<?php
require_once __DIR__ . '/mobile_auth.php';

function preview_admin(PDO $db, array $session): array
{
    $id = (int)($session['user_id'] ?? 0);
    if ($id <= 0) { throw new MobileAuthError('invalid_session', 'Accedi con il tuo account del sito.', 401); }
    $stmt = $db->prepare('SELECT id, email, nome, cognome, ruolo, avatar, feature_flags, email_verificata FROM utenti WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || empty($user['email_verificata'])) {
        throw new MobileAuthError('invalid_session', 'Accedi nuovamente con un account verificato.', 401);
    }
    if (!user_has_admin_access((string)$user['ruolo'])) {
        throw new MobileAuthError('forbidden', 'Anteprima riservata agli amministratori.', 403);
    }
    return MobileAuth::publicUser($user);
}
