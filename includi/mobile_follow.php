<?php
function mobile_follow_handle(PDO $db, int $userId, array $input, bool $save): array
{
    if ($userId < 1) throw new MobileAuthError('invalid_session', 'Accesso richiesto.', 401);
    if (!$save && ($input['list'] ?? null) === '1') {
        return mobile_follow_list($db, $userId, $input['section'] ?? 'calcio');
    }
    $slug = $input['torneo'] ?? '';
    if (!is_string($slug) || $slug === '' || strlen($slug) > 255) {
        throw new MobileAuthError('invalid_request', 'Torneo non valido.', 422);
    }
    $stmt = $db->prepare('SELECT id FROM tornei WHERE filetorneo IN (?, ?, ?, ?)');
    $stmt->execute([$slug, $slug . '.php', $slug . '.html', $slug . '.htm']);
    if (!$stmt->fetchColumn()) { throw new MobileAuthError('not_found', 'Torneo non trovato.', 404); }
    $stmt = $db->prepare('SELECT nome, logo FROM squadre WHERE torneo = ? ORDER BY nome, logo');
    $stmt->execute([$slug]);
    $teamRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $available = array_values(array_unique(array_column($teamRows, 'nome')));
    $logos = [];
    foreach ($teamRows as $team) {
        $name = $team['nome'];
        if (empty($logos[$name])) $logos[$name] = (string)($team['logo'] ?? '');
    }
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
        'teams' => $all ? $available : $selected, 'available_teams' => $available,
        'team_logos' => (object)$logos];
}

/** Account-owned shortcuts; reuse existing follows without creating subscriptions. */
function mobile_follow_list(PDO $db, int $userId, mixed $section): array
{
    if (!in_array($section, ['calcio', 'esport'], true)) {
        throw new MobileAuthError('invalid_request', 'Sezione non valida.', 422);
    }
    $stmt = $db->prepare("SELECT DISTINCT f.tipo, f.torneo_slug, f.squadra_nome,
        t.id AS torneo_id, t.nome AS torneo_nome, t.img AS torneo_logo, t.filetorneo, t.sezione,
        s.id AS squadra_id, s.logo AS squadra_logo
        FROM seguiti f
        JOIN tornei t ON t.filetorneo IN (f.torneo_slug, CONCAT(f.torneo_slug, '.php'), CONCAT(f.torneo_slug, '.html'), CONCAT(f.torneo_slug, '.htm'))
        LEFT JOIN squadre s ON s.id = (SELECT MIN(s2.id) FROM squadre s2 WHERE s2.nome = f.squadra_nome AND s2.torneo = f.torneo_slug)
        WHERE f.utente_id = ? AND COALESCE(t.sezione, 'calcio') = ?
          AND (f.tipo = 'torneo' OR (f.tipo = 'squadra' AND s.id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM seguiti all_f WHERE all_f.utente_id = f.utente_id
              AND all_f.torneo_slug = f.torneo_slug AND all_f.tipo = 'torneo')))
        ORDER BY t.nome, f.tipo, f.squadra_nome");
    $stmt->execute([$userId, $section]);
    return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}
