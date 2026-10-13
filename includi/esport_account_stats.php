<?php

// Esport participants are teams named after the account, not football players.
function esport_account_name_key(string $name): string {
    $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

function esport_account_matching_teams(array $user, array $teams): array {
    if (trim((string)($user['nome'] ?? '')) === '' || trim((string)($user['cognome'] ?? '')) === '') return [];
    $key = esport_account_name_key($user['nome'] . ' ' . $user['cognome']);
    return array_values(array_filter($teams, static fn(array $team): bool =>
        ($team['sezione'] ?? '') === 'esport' && esport_account_name_key((string)$team['nome']) === $key));
}

function esport_account_stats_build(array $user, array $teams, array $matches, ?string $type = null, int $page = 1): array {
    $teams = esport_account_matching_teams($user, $teams);
    $totals = ['presenze' => 0, 'reti' => 0, 'assist' => 0, 'gialli' => 0, 'rossi' => 0, 'media_voti' => null];
    $history = [];
    $seen = [];
    foreach ($teams as &$team) {
        $team = array_merge($team, $totals);
        $team['is_captain'] = 0;
        $team['diffidato'] = false;
        $teamSeen = [];
        foreach ($matches as $match) {
            if ($match['torneo'] !== $team['torneo'] || (int)($match['giocata'] ?? 0) !== 1
                || $match['gol_casa'] === null || $match['gol_ospite'] === null) continue;
            $home = esport_account_name_key((string)$match['squadra_casa']) === esport_account_name_key($team['nome']);
            $away = esport_account_name_key((string)$match['squadra_ospite']) === esport_account_name_key($team['nome']);
            if (!$home && !$away) continue;
            $matchId = (int)($match['partita_id'] ?? $match['id']);
            if (isset($teamSeen[$matchId])) continue;
            $teamSeen[$matchId] = true;
            $goals = (int)$match[$home ? 'gol_casa' : 'gol_ospite'];
            $team['presenze']++;
            $team['reti'] += $goals;
            if (isset($seen[$matchId])) continue;
            $seen[$matchId] = true;
            $totals['presenze']++;
            $totals['reti'] += $goals;
            if ($type !== 'gol' || $goals > 0) {
                $history[] = array_merge($match, ['partita_id' => $matchId, 'goal' => $goals, 'assist' => 0,
                    'cartellino_giallo' => 0, 'cartellino_rosso' => 0, 'voto' => null]);
            }
        }
    }
    unset($team);
    $player = array_merge(['id' => $user['player_id'] ?? 0, 'nome' => $user['nome'] ?? '', 'cognome' => $user['cognome'] ?? '',
        'foto' => $user['avatar'] ?? '', 'ruolo' => 'Esport', 'sezione' => 'esport'], $totals);
    if ($type !== null) {
        usort($history, static fn(array $a, array $b): int =>
            [($b['data_partita'] ?? ''), ($b['ora_partita'] ?? ''), $b['partita_id']]
            <=> [($a['data_partita'] ?? ''), ($a['ora_partita'] ?? ''), $a['partita_id']]);
        $player['totali'] = ['gol' => $totals['reti'], 'presenze' => $totals['presenze']];
        $offset = ($page - 1) * 100;
        return ['player' => $player, 'matches' => array_slice($history, $offset, 100),
            'pagination' => ['page' => $page, 'has_more' => count($history) > $offset + 100]];
    }
    return ['player' => $player, 'teams' => $teams, 'awards' => []];
}

function esport_account_stats_fetch(mysqli $conn, int $userId, ?string $type = null, int $page = 1): array {
    $stmt = $conn->prepare('SELECT nome, cognome, avatar FROM utenti WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) return ['player' => null, 'teams' => []];
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
    $teams = esport_account_matching_teams($user, $rows);
    $matches = [];
    if ($teams) {
        $clauses = [];
        $params = [];
        foreach (array_unique(array_column($teams, 'torneo')) as $tournament) {
            $clauses[] = 'p.torneo = ?';
            $params[] = $tournament;
        }
        $stmt = $conn->prepare("SELECT DISTINCT p.*, p.id AS partita_id, t.nome AS torneo_nome,
            sc.logo AS logo_casa, so.logo AS logo_ospite FROM partite p
            JOIN tornei t ON (t.filetorneo = p.torneo OR t.filetorneo = CONCAT(p.torneo, '.php') OR t.nome = p.torneo)
            LEFT JOIN squadre sc ON sc.torneo = p.torneo AND sc.nome = p.squadra_casa
            LEFT JOIN squadre so ON so.torneo = p.torneo AND so.nome = p.squadra_ospite
            WHERE t.sezione = 'esport' AND (" . implode(' OR ', $clauses) . ')');
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $result = esport_account_stats_build($user, $teams, $matches, $type, $page);
    if ($type === null && function_exists('player_awards_fetch_all')) {
        $result['awards'] = player_awards_fetch_all($conn, $result['player'], $result['teams'], 'esport');
    }
    return $result;
}
