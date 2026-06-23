<?php

if (!function_exists('ensure_giocatore_goal_extra_schema')) {
    function ensure_giocatore_goal_extra_schema(mysqli $conn): void
    {
        static $done = false;

        if ($done) {
            return;
        }
        $done = true;

        $sql = "
            CREATE TABLE IF NOT EXISTS giocatore_goal_extra (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                giocatore_id INT UNSIGNED NOT NULL,
                squadra_id INT UNSIGNED DEFAULT NULL,
                goal INT UNSIGNED NOT NULL DEFAULT 1,
                note VARCHAR(255) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_gge_player (giocatore_id),
                KEY idx_gge_team (squadra_id),
                KEY idx_gge_creator (created_by),
                CONSTRAINT fk_gge_player FOREIGN KEY (giocatore_id) REFERENCES giocatori(id) ON DELETE CASCADE,
                CONSTRAINT fk_gge_team FOREIGN KEY (squadra_id) REFERENCES squadre(id) ON DELETE SET NULL,
                CONSTRAINT fk_gge_creator FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        if ($conn->query($sql) !== true) {
            error_log('Creazione schema giocatore_goal_extra fallita: ' . $conn->error);
            return;
        }

        if (function_exists('giocatore_goal_extra_table_exists')) {
            giocatore_goal_extra_table_exists($conn, true);
        }
    }
}

if (!function_exists('giocatore_goal_extra_table_exists')) {
    function giocatore_goal_extra_table_exists(mysqli $conn, bool $refresh = false): bool
    {
        static $cache = [];

        $cacheKey = spl_object_hash($conn) . ':giocatore_goal_extra';
        if (!$refresh && array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $res = $conn->query("SHOW TABLES LIKE 'giocatore_goal_extra'");
        $cache[$cacheKey] = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }

        return $cache[$cacheKey];
    }
}

if (!function_exists('giocatore_goal_extra_global_expr')) {
    function giocatore_goal_extra_global_expr(mysqli $conn, string $playerIdColumn = 'g.id'): string
    {
        if (!giocatore_goal_extra_table_exists($conn)) {
            return '0';
        }

        $playerIdColumn = trim($playerIdColumn) !== '' ? trim($playerIdColumn) : 'g.id';
        return "COALESCE((SELECT SUM(gge.goal) FROM giocatore_goal_extra gge WHERE gge.giocatore_id = {$playerIdColumn}), 0)";
    }
}

if (!function_exists('giocatore_goal_extra_team_expr')) {
    function giocatore_goal_extra_team_expr(mysqli $conn, string $playerIdColumn = 'g.id', string $teamIdColumn = 's.id'): string
    {
        if (!giocatore_goal_extra_table_exists($conn)) {
            return '0';
        }

        $playerIdColumn = trim($playerIdColumn) !== '' ? trim($playerIdColumn) : 'g.id';
        $teamIdColumn = trim($teamIdColumn) !== '' ? trim($teamIdColumn) : 's.id';
        return "COALESCE((SELECT SUM(gge.goal) FROM giocatore_goal_extra gge WHERE gge.giocatore_id = {$playerIdColumn} AND gge.squadra_id = {$teamIdColumn}), 0)";
    }
}

if (!function_exists('giocatore_goal_extra_tournament_expr')) {
    function giocatore_goal_extra_tournament_expr(mysqli $conn, string $playerIdColumn = 'g.id', string $tournamentColumn = 'p.torneo'): string
    {
        if (!giocatore_goal_extra_table_exists($conn)) {
            return '0';
        }

        $playerIdColumn = trim($playerIdColumn) !== '' ? trim($playerIdColumn) : 'g.id';
        $tournamentColumn = trim($tournamentColumn) !== '' ? trim($tournamentColumn) : 'p.torneo';
        return "COALESCE((
            SELECT SUM(gge.goal)
            FROM giocatore_goal_extra gge
            JOIN squadre gges ON gges.id = gge.squadra_id
            WHERE gge.giocatore_id = {$playerIdColumn}
              AND gges.torneo = {$tournamentColumn}
        ), 0)";
    }
}

if (!function_exists('giocatore_goal_extra_fetch_global_total')) {
    function giocatore_goal_extra_fetch_global_total(mysqli $conn, int $giocatoreId): int
    {
        if ($giocatoreId <= 0 || !giocatore_goal_extra_table_exists($conn)) {
            return 0;
        }

        $stmt = $conn->prepare("SELECT COALESCE(SUM(goal), 0) AS totale FROM giocatore_goal_extra WHERE giocatore_id = ?");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $giocatoreId);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return (int)($row['totale'] ?? 0);
    }
}

if (!function_exists('giocatore_goal_extra_fetch_unassigned_total')) {
    function giocatore_goal_extra_fetch_unassigned_total(mysqli $conn, int $giocatoreId): int
    {
        if ($giocatoreId <= 0 || !giocatore_goal_extra_table_exists($conn)) {
            return 0;
        }

        $stmt = $conn->prepare("SELECT COALESCE(SUM(goal), 0) AS totale FROM giocatore_goal_extra WHERE giocatore_id = ? AND squadra_id IS NULL");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $giocatoreId);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return (int)($row['totale'] ?? 0);
    }
}

if (!function_exists('giocatore_goal_extra_fetch_team_total')) {
    function giocatore_goal_extra_fetch_team_total(mysqli $conn, int $giocatoreId, int $squadraId): int
    {
        if ($giocatoreId <= 0 || $squadraId <= 0 || !giocatore_goal_extra_table_exists($conn)) {
            return 0;
        }

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(goal), 0) AS totale
            FROM giocatore_goal_extra
            WHERE giocatore_id = ? AND squadra_id = ?
        ");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('ii', $giocatoreId, $squadraId);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return (int)($row['totale'] ?? 0);
    }
}

if (!function_exists('giocatore_goal_extra_fetch_named_team_total')) {
    function giocatore_goal_extra_fetch_named_team_total(mysqli $conn, int $giocatoreId, string $torneo, string $teamName): int
    {
        $torneo = trim($torneo);
        $teamName = trim($teamName);
        if ($giocatoreId <= 0 || $torneo === '' || $teamName === '' || !giocatore_goal_extra_table_exists($conn)) {
            return 0;
        }

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(gge.goal), 0) AS totale
            FROM giocatore_goal_extra gge
            JOIN squadre s ON s.id = gge.squadra_id
            WHERE gge.giocatore_id = ?
              AND s.torneo = ?
              AND s.nome = ?
        ");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('iss', $giocatoreId, $torneo, $teamName);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return (int)($row['totale'] ?? 0);
    }
}

if (!function_exists('giocatore_goal_extra_create')) {
    function giocatore_goal_extra_create(mysqli $conn, int $giocatoreId, ?int $squadraId, int $goal, string $note = '', ?int $createdBy = null): ?int
    {
        ensure_giocatore_goal_extra_schema($conn);
        if ($giocatoreId <= 0 || $goal <= 0 || !giocatore_goal_extra_table_exists($conn)) {
            return null;
        }

        $squadraId = ($squadraId ?? 0) > 0 ? (int)$squadraId : null;
        $createdBy = ($createdBy ?? 0) > 0 ? (int)$createdBy : null;
        $note = trim($note);
        $note = $note !== '' ? $note : null;

        $stmt = $conn->prepare("
            INSERT INTO giocatore_goal_extra (giocatore_id, squadra_id, goal, note, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('iiisi', $giocatoreId, $squadraId, $goal, $note, $createdBy);
        $ok = $stmt->execute();
        $insertId = $ok ? (int)$conn->insert_id : 0;
        $stmt->close();

        return $ok ? $insertId : null;
    }
}

if (!function_exists('giocatore_goal_extra_delete')) {
    function giocatore_goal_extra_delete(mysqli $conn, int $entryId): ?array
    {
        if ($entryId <= 0 || !giocatore_goal_extra_table_exists($conn)) {
            return null;
        }

        $select = $conn->prepare("SELECT id, giocatore_id, squadra_id, goal FROM giocatore_goal_extra WHERE id = ? LIMIT 1");
        if (!$select) {
            return null;
        }

        $select->bind_param('i', $entryId);
        if (!$select->execute()) {
            $select->close();
            return null;
        }

        $row = $select->get_result()->fetch_assoc();
        $select->close();
        if (!$row) {
            return null;
        }

        $delete = $conn->prepare("DELETE FROM giocatore_goal_extra WHERE id = ?");
        if (!$delete) {
            return null;
        }

        $delete->bind_param('i', $entryId);
        $ok = $delete->execute();
        $delete->close();

        return $ok ? $row : null;
    }
}

if (!function_exists('giocatore_goal_extra_list')) {
    function giocatore_goal_extra_list(mysqli $conn): array
    {
        if (!giocatore_goal_extra_table_exists($conn)) {
            return [];
        }

        $sql = "
            SELECT
                gge.id,
                gge.giocatore_id,
                gge.squadra_id,
                gge.goal,
                gge.note,
                gge.created_at,
                g.nome,
                g.cognome,
                s.nome AS squadra_nome,
                s.torneo AS torneo_slug
            FROM giocatore_goal_extra gge
            JOIN giocatori g ON g.id = gge.giocatore_id
            LEFT JOIN squadre s ON s.id = gge.squadra_id
            ORDER BY gge.created_at DESC, gge.id DESC
        ";

        $res = $conn->query($sql);
        if (!$res instanceof mysqli_result) {
            return [];
        }

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
        return $rows;
    }
}
