<?php

/** Aggiorna il ruolo prima che menu e guard leggano la sessione. */
function tos_refresh_session_role(mysqli $conn, array &$session): bool
{
    if (!isset($session['user_id'])) {
        return false;
    }

    $userId = (int)$session['user_id'];
    // Non conservare autorizzazioni obsolete se la verifica fallisce.
    unset($session['ruolo']);
    $stmt = $conn->prepare('SELECT ruolo FROM utenti WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Preparazione verifica ruolo fallita.');
    }

    try {
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Verifica ruolo fallita.');
        }
        $result = $stmt->get_result();
        if (!$result) {
            throw new RuntimeException('Risultato verifica ruolo non disponibile.');
        }
        $user = $result->fetch_assoc();
        $result->free();
    } finally {
        $stmt->close();
    }

    if (!$user) {
        // Un account eliminato non deve mantenere una sessione autenticata.
        $session = [];
        return false;
    }

    $session['ruolo'] = trim((string)$user['ruolo']);
    return true;
}
