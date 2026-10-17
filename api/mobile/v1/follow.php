<?php
require_once __DIR__ . '/_bootstrap.php';
mobile_run(static function (): array {
    $db = mobile_db();
    $user = (new MobileAuth($db))->authenticate(mobile_bearer());
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) { mobile_method('GET'); }
    $input = $method === 'POST' ? mobile_input() : $_GET;
    $slug = $input['torneo'] ?? '';
    if (!is_string($slug) || $slug === '' || strlen($slug) > 255) {
        throw new MobileAuthError('invalid_request', 'Torneo non valido.', 422);
    }
    $stmt = $db->prepare('SELECT id FROM tornei WHERE filetorneo IN (?, ?, ?, ?)');
    $stmt->execute([$slug, $slug . '.php', $slug . '.html', $slug . '.htm']);
    if (!$stmt->fetchColumn()) { throw new MobileAuthError('not_found', 'Torneo non trovato.', 404); }
    if ($method === 'POST') {
        if (!is_bool($input['followed'] ?? null)) {
            throw new MobileAuthError('invalid_request', 'Preferenza non valida.', 422);
        }
        // Serialize changes for this account; the existing nullable unique key
        // does not prevent duplicate tournament follows.
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id FROM utenti WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);
            $stmt = $db->prepare("DELETE FROM seguiti WHERE utente_id = ? AND tipo = 'torneo' AND torneo_slug = ?");
            $stmt->execute([$user['id'], $slug]);
            if ($input['followed']) {
                $stmt = $db->prepare("INSERT INTO seguiti (utente_id, tipo, torneo_slug, squadra_nome) VALUES (?, 'torneo', ?, NULL)");
                $stmt->execute([$user['id'], $slug]);
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
    $stmt = $db->prepare("SELECT 1 FROM seguiti WHERE utente_id = ? AND tipo = 'torneo' AND torneo_slug = ? LIMIT 1");
    $stmt->execute([$user['id'], $slug]);
    return ['followed' => (bool)$stmt->fetchColumn()];
});
