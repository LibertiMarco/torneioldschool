<?php
require_once __DIR__ . '/torneo_phase_rules.php';
require_once __DIR__ . '/diffidati.php';
require_once __DIR__ . '/player_awards.php';
require_once __DIR__ . '/esport_account_stats.php';

function mobile_player_stats_query(array $query): array {
    foreach ($query as $key => $value) {
        if (!in_array($key, ['section', 'type', 'page', 'action'], true)) {
            throw new MobileAuthError('invalid_request', 'Parametro non consentito.', 400);
        }
    }
    $section = $query['section'] ?? 'calcio';
    $type = $query['type'] ?? null;
    $page = filter_var($query['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    if (!in_array($section, ['calcio', 'esport'], true) || !in_array($type, [null, 'gol', 'presenze'], true) || $page === false) {
        throw new MobileAuthError('invalid_request', 'Filtro statistiche non valido.', 400);
    }
    return [$section, $type, $page];
}

function mobile_player_stats_handle(int $userId, array $query): array {
    if ($userId <= 0) throw new MobileAuthError('invalid_session', 'Accesso richiesto.', 401);
    [$section, $type, $page] = mobile_player_stats_query($query);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '', getenv('DB_NAME') ?: '', (int)(getenv('DB_PORT') ?: 3306));
    $conn->set_charset('utf8mb4');
    try {
        if ($section === 'esport') return esport_account_stats_fetch($conn, $userId, $type, $page);
        // The player is resolved exclusively from the authenticated account.
        $stmt = $conn->prepare('SELECT id, nome, cognome, ruolo, foto FROM giocatori WHERE utente_id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $player = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$player) return ['player' => null, 'teams' => []];
        $id = (int)$player['id'];
        $totals = torneo_stats_fetch_player_global_totals($conn, $id, $section);
        $player = array_merge($player, $totals);
        if ($type !== null) {
            $teamExpr = partita_giocatore_team_id_expr($conn, 'pg.squadra_id');
            $resolved = partita_giocatore_resolved_team_expr('pg.giocatore_id', $teamExpr, 'p.torneo', 'p.squadra_casa', 'p.squadra_ospite');
            $filter = $type === 'gol' ? 'pg.goal > 0' : 'pg.presenza = 1';
            $offset = ($page - 1) * 100;
            $stmt = $conn->prepare("SELECT p.id AS partita_id, p.torneo, p.squadra_casa, p.squadra_ospite, p.gol_casa, p.gol_ospite, p.data_partita, p.ora_partita, p.giornata, p.fase, p.fase_round, p.fase_leg, p.giocata, p.decisa_rigori, p.rigori_casa, p.rigori_ospite, pg.goal, pg.assist, pg.cartellino_giallo, pg.cartellino_rosso, pg.voto,
                COALESCE(t.nome, p.torneo) AS torneo_nome, sc.logo AS logo_casa, so.logo AS logo_ospite
                FROM partita_giocatore pg JOIN partite p ON p.id = pg.partita_id
                JOIN squadre s ON s.id = {$resolved}
                JOIN squadre_giocatori sg ON sg.squadra_id = s.id AND sg.giocatore_id = pg.giocatore_id
                LEFT JOIN tornei t ON (t.filetorneo = p.torneo OR t.filetorneo = CONCAT(p.torneo, '.php') OR t.nome = p.torneo)
                LEFT JOIN squadre sc ON sc.nome = p.squadra_casa AND sc.torneo = p.torneo
                LEFT JOIN squadre so ON so.nome = p.squadra_ospite AND so.torneo = p.torneo
                WHERE pg.giocatore_id = ? AND COALESCE(t.sezione, 'calcio') = ? AND {$filter}
                ORDER BY p.data_partita DESC, p.ora_partita DESC, p.id DESC, pg.id DESC LIMIT 101 OFFSET ?");
            $stmt->bind_param('isi', $id, $section, $offset);
            $stmt->execute();
            $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $player['totali'] = ['gol' => $totals['reti'], 'presenze' => $totals['presenze']];
            return ['player' => $player, 'matches' => array_slice($matches, 0, 100), 'pagination' => ['page' => $page, 'has_more' => count($matches) > 100]];
        }
        $stmt = $conn->prepare("SELECT s.id, s.nome, s.torneo, s.logo, sg.is_captain, t.nome AS torneo_nome, t.filetorneo AS torneo_file
            FROM squadre_giocatori sg JOIN squadre s ON s.id = sg.squadra_id
            LEFT JOIN tornei t ON (t.filetorneo = s.torneo OR t.filetorneo = CONCAT(s.torneo, '.php') OR t.nome = s.torneo)
            WHERE sg.giocatore_id = ? AND COALESCE(t.sezione, 'calcio') = ? ORDER BY sg.created_at DESC");
        $stmt->bind_param('is', $id, $section);
        $stmt->execute();
        $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($teams as &$team) {
            $stats = torneo_stats_fetch_player_team_totals($conn, $id, $team['torneo'], $team['nome']);
            $stats['reti'] += giocatore_goal_extra_fetch_team_total($conn, $id, (int)$team['id']);
            $team = array_merge($team, $stats, ['diffidato' => count(diffidati_fetch($conn, $team['torneo'], $id, (int)$team['id'])) > 0]);
        }
        unset($team);
        return ['player' => $player, 'teams' => $teams, 'awards' => player_awards_fetch_all($conn, $player, $teams, $section)];
    } finally {
        $conn->close();
    }
}
