<?php
require_once __DIR__ . '/albo_archive.php';
function resolve_torneo_link(?string $value): string {
    $value = trim((string)$value);
    if ($value === '' || $value === '0') {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    if (preg_match('#^/[A-Za-z0-9/_\\-.?=&%#]+$#', $value)) {
        return $value;
    }

    $value = ltrim($value, '/');
    $slug = preg_replace('/\.(html?|php)$/i', '', $value);
    $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $slug);
    if ($slug === '') {
        return '';
    }

    return '/tornei/' . $slug . '.php';
}

function premio_normalize(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    if (function_exists('iconv')) {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) $value = $ascii;
    }
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
}

function premio_competizione_matches_team(string $competizione, array $team): bool {
    $competitionKey = premio_normalize($competizione);
    if ($competitionKey === '') return false;

    $aliases = [
        (string)($team['torneo'] ?? ''),
        (string)($team['torneo_nome'] ?? ''),
        (string)($team['torneo_file'] ?? ''),
    ];
    foreach ($aliases as $alias) {
        $alias = preg_replace('/\.(php|html?)$/i', '', basename(str_replace('\\', '/', $alias))) ?? $alias;
        $aliasKey = premio_normalize($alias);
        if ($aliasKey === '') continue;
        if ($competitionKey === $aliasKey) return true;
        if (strlen($aliasKey) >= 5 && (str_contains($competitionKey, $aliasKey) || str_contains($aliasKey, $competitionKey))) {
            return true;
        }
    }
    return false;
}

function premio_team_category(string $premio): string {
    $key = premio_normalize($premio);
    if (str_contains($key, 'regular season')) return 'Regular Season';
    if (str_contains($key, 'coppa gold')) return 'Coppa Gold';
    if (str_contains($key, 'coppa silver')) return 'Coppa Silver';
    if (str_contains($key, 'coppa bronzo') || str_contains($key, 'coppa bronze')) return 'Coppa Bronzo';
    return '';
}

function fetch_player_cup_awards(mysqli $conn, array $teams): array {
    if (empty($teams)) return [];
    $clauses = [];
    $params = [];
    foreach ($teams as $team) {
        $torneo = trim((string)($team['torneo'] ?? ''));
        $teamName = trim((string)($team['nome'] ?? ''));
        if ($torneo === '' || $teamName === '') continue;
        $clauses[] = '(p.torneo = ? AND (p.squadra_casa = ? OR p.squadra_ospite = ?))';
        array_push($params, $torneo, $teamName, $teamName);
    }
    if (!$clauses) return [];

    $sql = "SELECT p.id, p.torneo, p.fase, p.fase_leg, p.squadra_casa, p.squadra_ospite,
                   p.gol_casa, p.gol_ospite, p.decisa_rigori, p.rigori_casa, p.rigori_ospite,
                   p.data_partita, t.nome AS torneo_nome, t.filetorneo AS torneo_file
            FROM partite p
            LEFT JOIN tornei t ON (t.filetorneo = p.torneo OR t.filetorneo = CONCAT(p.torneo, '.php') OR t.nome = p.torneo)
            WHERE p.giocata = 1
              AND p.fase IN ('GOLD', 'SILVER', 'BRONZO')
              AND p.fase_round = 'FINALE'
              AND (" . implode(' OR ', $clauses) . ")
            ORDER BY p.data_partita DESC, p.id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    // Raggruppa andata e ritorno della stessa finale e calcola il risultato aggregato.
    $finals = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $pair = [(string)$row['squadra_casa'], (string)$row['squadra_ospite']];
        sort($pair, SORT_NATURAL | SORT_FLAG_CASE);
        $key = premio_normalize((string)$row['torneo']) . ':' . (string)$row['fase'] . ':' . premio_normalize(implode('|', $pair));
        if (!isset($finals[$key])) {
            $finals[$key] = ['rows' => [], 'goals' => [], 'latest' => $row];
        }
        $finals[$key]['rows'][] = $row;
        $home = (string)$row['squadra_casa'];
        $away = (string)$row['squadra_ospite'];
        $finals[$key]['goals'][$home] = ($finals[$key]['goals'][$home] ?? 0) + (int)$row['gol_casa'];
        $finals[$key]['goals'][$away] = ($finals[$key]['goals'][$away] ?? 0) + (int)$row['gol_ospite'];
    }
    $stmt->close();

    $awards = [];
    foreach ($finals as $final) {
        $goals = $final['goals'];
        if (count($goals) !== 2) continue;
        arsort($goals);
        $names = array_keys($goals);
        $scores = array_values($goals);
        $winner = $scores[0] > $scores[1] ? $names[0] : '';

        // In parita, il risultato ai rigori della gara decisiva stabilisce la vincitrice.
        if ($winner === '') {
            foreach ($final['rows'] as $row) {
                if ((int)$row['decisa_rigori'] !== 1 || $row['rigori_casa'] === null || $row['rigori_ospite'] === null) continue;
                if ((int)$row['rigori_casa'] > (int)$row['rigori_ospite']) $winner = (string)$row['squadra_casa'];
                if ((int)$row['rigori_ospite'] > (int)$row['rigori_casa']) $winner = (string)$row['squadra_ospite'];
            }
        }
        if ($winner === '') continue;

        foreach ($teams as $team) {
            if (premio_normalize((string)($team['nome'] ?? '')) !== premio_normalize($winner)) continue;
            if (trim((string)($team['torneo'] ?? '')) !== trim((string)$final['latest']['torneo'])) continue;
            $phase = strtoupper((string)$final['latest']['fase']);
            $labels = ['GOLD' => 'Coppa Gold', 'SILVER' => 'Coppa Silver', 'BRONZO' => 'Coppa Bronzo'];
            $awards[] = [
                'id' => 'final-' . (int)$final['latest']['id'],
                'tipo' => 'Squadra',
                'premio' => $labels[$phase] ?? ('Coppa ' . ucfirst(strtolower($phase))),
                'competizione' => (string)($final['latest']['torneo_nome'] ?: $final['latest']['torneo']),
                'squadra' => (string)$team['nome'],
                'data_riconoscimento' => $final['latest']['data_partita'] ?? '',
                'anno' => !empty($final['latest']['data_partita']) ? date('Y', strtotime((string)$final['latest']['data_partita'])) : '',
                'logo' => (string)($team['logo'] ?? ''),
                'link' => resolve_torneo_link((string)($final['latest']['torneo_file'] ?: $final['latest']['torneo'])),
            ];
            break;
        }
    }
    return $awards;
}

function fetch_player_regular_season_awards(mysqli $conn, array $teams): array {
    $awards = [];
    $processedTournaments = [];
    foreach ($teams as $playerTeam) {
        $torneo = trim((string)($playerTeam['torneo'] ?? ''));
        if ($torneo === '' || isset($processedTournaments[$torneo])) continue;
        $processedTournaments[$torneo] = true;

        // La Regular Season e conclusa quando e gia iniziata una fase successiva
        // oppure quando il torneo e stato marcato come terminato.
        $completionStmt = $conn->prepare("SELECT
                EXISTS(SELECT 1 FROM partite p WHERE p.torneo = ? AND p.fase <> 'REGULAR' LIMIT 1) AS fase_successiva,
                COALESCE((SELECT MAX(t.stato = 'terminato') FROM tornei t
                          WHERE t.filetorneo = ? OR t.filetorneo = CONCAT(?, '.php') OR t.nome = ?), 0) AS torneo_terminato,
                (SELECT MAX(p2.data_partita) FROM partite p2
                 WHERE p2.torneo = ? AND p2.fase = 'REGULAR' AND p2.giocata = 1) AS ultima_regular");
        if (!$completionStmt) continue;
        $completionStmt->bind_param('sssss', $torneo, $torneo, $torneo, $torneo, $torneo);
        $completionStmt->execute();
        $completion = $completionStmt->get_result()->fetch_assoc();
        $completionStmt->close();
        if (!$completion || (!(int)$completion['fase_successiva'] && !(int)$completion['torneo_terminato'])) continue;

        $leaderStmt = $conn->prepare("SELECT s.id, s.nome, s.logo, s.punti, s.giocate,
                                            t.nome AS torneo_nome, t.filetorneo AS torneo_file
                                     FROM squadre s
                                     LEFT JOIN tornei t ON (t.filetorneo = s.torneo OR t.filetorneo = CONCAT(s.torneo, '.php') OR t.nome = s.torneo)
                                     WHERE s.torneo = ? AND s.giocate > 0
                                     ORDER BY s.punti DESC, s.differenza_reti DESC, s.gol_fatti DESC,
                                              s.gol_subiti ASC, s.nome ASC
                                     LIMIT 1");
        if (!$leaderStmt) continue;
        $leaderStmt->bind_param('s', $torneo);
        $leaderStmt->execute();
        $leader = $leaderStmt->get_result()->fetch_assoc();
        $leaderStmt->close();
        if (!$leader) continue;

        foreach ($teams as $team) {
            if ((int)($team['id'] ?? 0) !== (int)$leader['id']) continue;
            $lastRegular = trim((string)($completion['ultima_regular'] ?? ''));
            $awards[] = [
                'id' => 'regular-' . (int)$leader['id'],
                'tipo' => 'Squadra',
                'premio' => 'Regular Season',
                'competizione' => (string)($leader['torneo_nome'] ?: $torneo),
                'squadra' => (string)$leader['nome'],
                'data_riconoscimento' => $lastRegular,
                'anno' => $lastRegular !== '' ? date('Y', strtotime($lastRegular)) : '',
                'logo' => (string)($leader['logo'] ?? ''),
                'link' => resolve_torneo_link((string)($leader['torneo_file'] ?: $torneo)),
            ];
            break;
        }
    }
    return $awards;
}

function fetch_player_awards(mysqli $conn, array $giocatore, array $teams, string $section): array {
    if (empty($teams)) return [];
    $check = $conn->query("SHOW TABLES LIKE 'albo'");
    if (!$check || $check->num_rows === 0) return [];

    $sql = "SELECT id, competizione, premio, vincitrice, vincitrice_logo, torneo_logo,
                   link_torneo, inizio_anno, fine_anno
            FROM albo
            WHERE COALESCE(sezione, 'calcio') = ?
            ORDER BY COALESCE(fine_anno, inizio_anno, 0) DESC, id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('s', $section);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $playerName = premio_normalize(trim((string)($giocatore['nome'] ?? '') . ' ' . (string)($giocatore['cognome'] ?? '')));
    $awards = [];
    $seen = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $bound = albo_bind_edition([...$row,'sezione'=>$section,'anno'=>(string)($row['fine_anno'] ?: $row['inizio_anno'] ?: '')],albo_backfill()['editions'] ?? []);
        $winnerKey = premio_normalize(preg_replace('/\([^)]*\)/', '', (string)($row['vincitrice'] ?? '')) ?? '');
        $teamCategory = premio_team_category((string)($row['premio'] ?? ''));
        foreach ($teams as $team) {
            if (!premio_competizione_matches_team((string)$row['competizione'], $team)) continue;
            $teamNameKey = premio_normalize((string)($team['nome'] ?? ''));
            $isTeamAward = $teamCategory !== '' && $teamNameKey !== '' && $winnerKey === $teamNameKey;
            $isIndividualAward = $teamCategory === '' && $playerName !== '' && $winnerKey === $playerName;
            if (!$isTeamAward && !$isIndividualAward) continue;

            $dedupeKey = (int)$row['id'] . ':' . ($isTeamAward ? 'team' : 'individual');
            if (isset($seen[$dedupeKey])) break;
            $seen[$dedupeKey] = true;
            $awards[] = [
                'id' => (int)$row['id'],
                'tipo' => $isTeamAward ? 'Squadra' : 'Individuale',
                'premio' => $isTeamAward ? $teamCategory : (string)$row['premio'],
                'premio_originale' => (string)$row['premio'],
                'competizione' => (string)($bound['competizione'] ?? $row['competizione']),
                'squadra' => (string)($team['nome'] ?? ''),
                'data_riconoscimento' => ($bound['data_evento'] ?? '') ?: ($bound['data_fine'] ?? ''),
                'anno' => (string)($row['fine_anno'] ?: $row['inizio_anno'] ?: ''),
                'logo' => $isTeamAward
                    ? ((string)($row['vincitrice_logo'] ?? '') ?: (string)($team['logo'] ?? ''))
                    : ((string)($row['torneo_logo'] ?? '') ?: (string)($giocatore['foto'] ?? '')),
                'link' => resolve_torneo_link((string)($row['link_torneo'] ?? '')),
            ];
            break;
        }
    }
    $stmt->close();
    return $awards;
}

function deduplicate_player_awards(array $awards): array {
    $result = [];
    $seen = [];
    foreach ($awards as $award) {
        $key = premio_normalize((string)($award['tipo'] ?? '')) . ':'
            . albo_kind((string)($award['premio'] ?? '')) . ':'
            . premio_normalize((string)($award['competizione'] ?? '')) . ':'
            . (string)($award['anno'] ?? '') . ':'
            . premio_normalize((string)($award['squadra'] ?? ''));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $result[] = $award;
    }
    return $result;
}

function player_award_recipient_type(array $record, array $player, array $teams): ?string {
    $playerId = (int)($player['id'] ?? 0);
    $esportAccount = ($player['sezione'] ?? '') === 'esport';
    if ($playerId <= 0 && !$esportAccount) return null;
    // Individual awards never propagate through the winner's team or roster.
    $individual = !empty($record['giocatore_id'])
        || ($record['tipo'] ?? '') === 'Individuale'
        || ($record['kind'] ?? '') === 'SCORER';
    if ($individual) {
        return $playerId > 0 && (int)($record['giocatore_id'] ?? 0) === $playerId ? 'Individuale' : null;
    }
    if (($record['tipo'] ?? '') !== 'Squadra'
        && premio_team_category((string)($record['premio'] ?? '')) === '') return null;
    $teamIds = array_map('intval', array_column($teams, 'id'));
    $member = !$esportAccount && isset($record['members'])
        ? in_array($playerId, array_map('intval', $record['members']), true)
        : (!empty($record['squadra_id']) && in_array((int)$record['squadra_id'], $teamIds, true));
    return $member ? 'Squadra' : null;
}

function player_awards_fetch_all(mysqli $conn, array $player, array $teams, string $section): array {
    $official=[]; $covered=[];
    foreach (albo_archive_read($conn,$section) as $record) {
        $edition=(int)($record['torneo_id'] ?? 0);
        $recipientType=player_award_recipient_type($record,$player,$teams);
        if ($edition) $covered[albo_slug((string)($record['link_torneo'] ?? '')) . ':' . $record['kind']]=true;
        if ($recipientType === null) continue;
        $team=''; foreach ($teams as $item) if ((int)$item['id'] === (int)($record['squadra_id'] ?? 0)) { $team=(string)$item['nome']; break; }
        $official[]=['id'=>$record['source_key'] ?? $record['record_id'],'tipo'=>$recipientType,
            'premio'=>$record['premio'],'competizione'=>$record['competizione'],'squadra'=>$team,
            'data_riconoscimento'=>($record['data_evento'] ?? '') ?: ($record['data_fine'] ?? ''),
            'anno'=>$record['anno'],'logo'=>$record['logo_vincitrice'] ?? '', 'link'=>$record['link_torneo'] ?? '',
            'torneo_id'=>$edition,'giocatore_id'=>$record['giocatore_id'] ?? null];
    }
    $inferred=array_merge(fetch_player_regular_season_awards($conn,$teams),fetch_player_cup_awards($conn,$teams));
    $inferred=array_values(array_filter($inferred,static fn($award)=>!isset($covered[albo_slug((string)$award['link']) . ':' . albo_kind((string)$award['premio'])])));
    return sort_player_awards(deduplicate_player_awards(array_merge(
        $official,
        $inferred,
        fetch_player_awards($conn, $player, $teams, $section)
    )));
}

/** Stable order by recognition date/edition year; never use insertion date or ID. */
function sort_player_awards(array $awards): array {
    $indexed = [];
    foreach ($awards as $index => $award) {
        $date = trim((string)($award['data_riconoscimento'] ?? ''));
        $time = $date !== '' ? (strtotime($date) ?: 0) : 0;
        if (!$time && preg_match('/(?:19|20)\d{2}/', (string)($award['anno'] ?? ''), $year)) {
            preg_match_all('/(?:19|20)\d{2}/', (string)$award['anno'], $years);
            $time = strtotime(max($years[0]) . '-01-01') ?: 0;
        }
        $indexed[] = ['award'=>$award, 'time'=>$time, 'index'=>$index];
    }
    usort($indexed, static fn($a,$b) => ($b['time'] <=> $a['time']) ?: ($a['index'] <=> $b['index']));
    return array_column($indexed, 'award');
}
