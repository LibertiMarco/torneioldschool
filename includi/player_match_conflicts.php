<?php

/** Add non-blocking warnings using player IDs, including matches in other tournaments. */
function player_match_conflict_warnings(array $rows, array $memberships, array $matches): array
{
    $rosters = [];
    $teamIds = [];
    foreach ($memberships as $member) {
        $teamId = (int)$member['squadra_id'];
        $key = mb_strtolower(trim($member['torneo']) . '|' . trim($member['squadra_nome']), 'UTF-8');
        $teamIds[$key] = $teamId;
        $role = mb_strtolower(trim((string)$member['ruolo']), 'UTF-8');
        if (in_array($role, ['portiere', 'portieri', 'p', 'por', 'gk'], true)) continue;
        $rosters[$teamId][(int)$member['giocatore_id']] = trim($member['nome'] . ' ' . $member['cognome']);
    }
    $scheduled = [];
    foreach ($matches as $match) {
        $ids = [];
        foreach (['squadra_casa', 'squadra_ospite'] as $side) {
            $key = mb_strtolower(trim($match['torneo']) . '|' . trim($match[$side]), 'UTF-8');
            if (isset($teamIds[$key])) $ids[] = $teamIds[$key];
        }
        $scheduled[] = [
            'ids' => $ids, 'date' => $match['data_partita'], 'time' => $match['ora_partita'],
            'label' => $match['squadra_casa'] . ' - ' . $match['squadra_ospite'] . ' (' . $match['torneo'] . ')',
        ];
    }
    foreach ($rows as $index => $row) {
        $scheduled[] = [
            'ids' => [(int)$row['home_team_id'], (int)$row['away_team_id']],
            'date' => $row['data'], 'time' => $row['ora'], 'index' => $index,
            'label' => $row['home_team_name'] . ' - ' . $row['away_team_name'] . ' (proposta)',
        ];
    }
    foreach ($rows as $index => &$row) {
        $timestamp = strtotime($row['data'] . ' ' . $row['ora']);
        if (!$timestamp || !$row['data'] || !$row['ora']) continue;
        foreach ([(int)$row['home_team_id'], (int)$row['away_team_id']] as $teamId) {
            foreach ($scheduled as $other) {
                if (($other['index'] ?? null) === $index || !$other['date'] || !$other['time']) continue;
                $otherTimestamp = strtotime($other['date'] . ' ' . $other['time']);
                if (!$otherTimestamp || abs($timestamp - $otherTimestamp) > 90 * 60) continue;
                foreach ($other['ids'] as $otherTeamId) {
                    if ($otherTeamId === $teamId) continue;
                    $shared = array_intersect_key($rosters[$teamId] ?? [], $rosters[$otherTeamId] ?? []);
                    foreach ($shared as $name) {
                        $minutes = (int)(abs($timestamp - $otherTimestamp) / 60);
                        $row['warnings'][] = $name . ': altra partita con un’altra squadra, ' . $other['label']
                            . ', ' . date('d/m/Y H:i', $otherTimestamp)
                            . ($minutes === 0 ? ' (stesso orario).' : ' (' . $minutes . ' minuti di distanza).');
                    }
                }
            }
        }
        $row['warnings'] = array_values(array_unique($row['warnings']));
    }
    unset($row);
    return $rows;
}

function player_match_conflicts_for_preview(mysqli $conn, array $rows): array
{
    if (!$rows) return $rows;
    $dates = array_filter(array_column($rows, 'data'));
    if (!$dates) return $rows;
    $start = date('Y-m-d', strtotime(min($dates) . ' -1 day'));
    $end = date('Y-m-d', strtotime(max($dates) . ' +1 day'));
    $stmt = $conn->prepare('SELECT torneo, squadra_casa, squadra_ospite, data_partita, ora_partita FROM partite WHERE data_partita BETWEEN ? AND ? AND ora_partita IS NOT NULL');
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $result = $conn->query("SELECT sg.squadra_id, sg.giocatore_id, s.nome AS squadra_nome, s.torneo,
        g.nome, g.cognome, COALESCE(NULLIF(TRIM(sg.ruolo), ''), g.ruolo, '') AS ruolo
        FROM squadre_giocatori sg JOIN squadre s ON s.id = sg.squadra_id JOIN giocatori g ON g.id = sg.giocatore_id");
    $memberships = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return player_match_conflict_warnings($rows, $memberships, $matches);
}
