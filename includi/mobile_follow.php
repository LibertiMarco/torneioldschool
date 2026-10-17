<?php
function mobile_follow_handle(PDO $db, int $userId, array $input, bool $save): array
{
    $slug = $input['torneo'] ?? '';
    if (!is_string($slug) || $slug === '' || strlen($slug) > 255) {
        throw new MobileAuthError('invalid_request', 'Torneo non valido.', 422);
    }
    $stmt = $db->prepare('SELECT id FROM tornei WHERE filetorneo IN (?, ?, ?, ?)');
    $stmt->execute([$slug, $slug . '.php', $slug . '.html', $slug . '.htm']);
    if (!$stmt->fetchColumn()) { throw new MobileAuthError('not_found', 'Torneo non trovato.', 404); }
    $stmt = $db->prepare('SELECT nome FROM squadre WHERE torneo = ? ORDER BY nome');
    $stmt->execute([$slug]);
    $available = array_values(array_unique($stmt->fetchAll(PDO::FETCH_COLUMN)));
    if ($save) {
        if (!is_bool($input['followed'] ?? null)) {
            throw new MobileAuthError('invalid_request', 'Preferenza non valida.', 422);
        }
        $teams = $input['teams'] ?? null; // null follows the whole tournament, including future teams.
        if ($teams !== null && (!is_array($teams) || !array_is_list($teams)
            || count($teams) > count($available)
            || array_filter($teams, static fn($name) => !is_string($name) || !in_array($name, $available, true)))) {
            throw new MobileAuthError('invalid_request', 'Selezione squadre non valida.', 422);
        }
        $teams = $teams === null ? null : array_values(array_unique($teams));
        // Serialize changes for this account; the existing nullable unique key
        // does not prevent duplicate tournament follows.
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id FROM utenti WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $stmt = $db->prepare("DELETE FROM seguiti WHERE utente_id = ? AND torneo_slug = ? AND tipo IN ('torneo', 'squadra')");
            $stmt->execute([$userId, $slug]);
            if ($input['followed'] && $teams === null) {
                $stmt = $db->prepare("INSERT INTO seguiti (utente_id, tipo, torneo_slug, squadra_nome) VALUES (?, 'torneo', ?, NULL)");
                $stmt->execute([$userId, $slug]);
            } elseif ($input['followed']) {
                $stmt = $db->prepare("INSERT INTO seguiti (utente_id, tipo, torneo_slug, squadra_nome) VALUES (?, 'squadra', ?, ?)");
                foreach ($teams as $name) { $stmt->execute([$userId, $slug, $name]); }
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
    $stmt = $db->prepare("SELECT tipo, squadra_nome FROM seguiti WHERE utente_id = ? AND torneo_slug = ?");
    $stmt->execute([$userId, $slug]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $all = in_array('torneo', array_column($rows, 'tipo'), true);
    $selected = array_values(array_intersect($available, array_column($rows, 'squadra_nome')));
    return ['followed' => $all || count($selected) > 0, 'all' => $all,
        'teams' => $all ? $available : $selected, 'available_teams' => $available];
}
