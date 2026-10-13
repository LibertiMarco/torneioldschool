<?php

function esport_account_name_key(string $name): string {
    $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

function esport_account_has_unique_name(array $user, array $accounts): bool {
    if (trim((string)($user['nome'] ?? '')) === '' || trim((string)($user['cognome'] ?? '')) === '') return false;
    $key = esport_account_name_key($user['nome'] . ' ' . $user['cognome']);
    $matches = 0;
    foreach ($accounts as $account) {
        if (esport_account_name_key(($account['nome'] ?? '') . ' ' . ($account['cognome'] ?? '')) === $key) $matches++;
    }
    return $matches === 1;
}

function esport_account_matching_teams(array $user, array $teams): array {
    if (trim((string)($user['nome'] ?? '')) === '' || trim((string)($user['cognome'] ?? '')) === '') return [];
    $key = esport_account_name_key($user['nome'] . ' ' . $user['cognome']);
    return array_values(array_filter($teams, static fn(array $team): bool =>
        ($team['sezione'] ?? '') === 'esport' && esport_account_name_key((string)$team['nome']) === $key));
}

function esport_account_profile(array $user, array $teams, bool $unique): array {
    return ['player' => ['id' => $user['player_id'] ?? 0, 'nome' => $user['nome'] ?? '',
        'cognome' => $user['cognome'] ?? '', 'foto' => $user['avatar'] ?? '', 'ruolo' => 'Esport', 'sezione' => 'esport'],
        'teams' => $unique ? array_map(static fn(array $team): array => array_merge($team,
            ['is_captain' => 0, 'diffidato' => false]), esport_account_matching_teams($user, $teams)) : [], 'awards' => [],
        'association_ambiguous' => !$unique];
}

function esport_account_stats_fetch(mysqli $conn, int $userId, ?string $type = null, int $page = 1): array {
    $stmt = $conn->prepare('SELECT nome, cognome, avatar FROM utenti WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) return ['player' => null, 'teams' => []];
    $accounts = $conn->query('SELECT nome, cognome FROM utenti')->fetch_all(MYSQLI_ASSOC);
    $unique = esport_account_has_unique_name($user, $accounts);
    if (!$unique) return esport_account_profile($user, [], false);
    $stmt = $conn->prepare('SELECT id, foto FROM giocatori WHERE utente_id = ? ORDER BY id LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $linked = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($linked) {
        $user['player_id'] = (int)$linked['id'];
        if (!empty($linked['foto'])) $user['avatar'] = $linked['foto'];
    }
    $rows = $conn->query("SELECT DISTINCT s.id, s.nome, s.torneo, s.logo, t.nome AS torneo_nome,
        t.filetorneo AS torneo_file, t.sezione FROM squadre s JOIN tornei t
        ON (t.filetorneo = s.torneo OR t.filetorneo = CONCAT(s.torneo, '.php') OR t.nome = s.torneo)
        WHERE t.sezione = 'esport' ORDER BY s.id DESC")->fetch_all(MYSQLI_ASSOC);
    $result = esport_account_profile($user, $rows, true);
    if (function_exists('player_awards_fetch_all')) {
        $result['awards'] = player_awards_fetch_all($conn, $result['player'], $result['teams'], 'esport');
    }
    return $result;
}
