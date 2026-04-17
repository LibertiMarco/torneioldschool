<?php

if (!function_exists('torneo_stats_table_has_column')) {
    function torneo_stats_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];

        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') {
            $cache[$cacheKey] = false;
            return false;
        }

        $sql = sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $table, $conn->real_escape_string($column));
        $res = $conn->query($sql);
        $cache[$cacheKey] = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }

        return $cache[$cacheKey];
    }
}

if (!function_exists('torneo_stats_get_config')) {
    function torneo_stats_get_config(mysqli $conn, string $torneo): array
    {
        static $cache = [];

        $torneo = trim($torneo);
        if ($torneo === '') {
            return [];
        }

        $slug = preg_replace('/\.(html?|php)$/i', '', $torneo);
        $cacheKey = strtolower($slug);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if (!torneo_stats_table_has_column($conn, 'tornei', 'config')) {
            $cache[$cacheKey] = [];
            return [];
        }

        $filePhp = $slug . '.php';
        $fileHtml = $slug . '.html';
        $stmt = $conn->prepare("
            SELECT config
            FROM tornei
            WHERE filetorneo IN (?, ?)
               OR nome IN (?, ?)
            ORDER BY (filetorneo = ?) DESC, (filetorneo = ?) DESC
            LIMIT 1
        ");
        if (!$stmt) {
            $cache[$cacheKey] = [];
            return [];
        }

        $stmt->bind_param('ssssss', $filePhp, $fileHtml, $slug, $torneo, $filePhp, $fileHtml);
        if (!$stmt->execute()) {
            $stmt->close();
            $cache[$cacheKey] = [];
            return [];
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || empty($row['config'])) {
            $cache[$cacheKey] = [];
            return [];
        }

        $decoded = json_decode((string)$row['config'], true);
        $cache[$cacheKey] = is_array($decoded) ? $decoded : [];
        return $cache[$cacheKey];
    }
}

if (!function_exists('torneo_stats_get_format')) {
    function torneo_stats_get_format(mysqli $conn, string $torneo): string
    {
        $config = torneo_stats_get_config($conn, $torneo);
        $format = strtolower(trim((string)($config['formato'] ?? $config['formula_torneo'] ?? '')));
        if ($format !== '') {
            return $format;
        }

        static $fallbackCache = [];
        $cacheKey = strtolower(trim(preg_replace('/\.(html?|php)$/i', '', $torneo)));
        if (array_key_exists($cacheKey, $fallbackCache)) {
            return $fallbackCache[$cacheKey];
        }

        $slug = preg_replace('/\.(html?|php)$/i', '', trim($torneo));
        if ($slug === '') {
            $fallbackCache[$cacheKey] = '';
            return '';
        }

        $hasGironeColumn = torneo_stats_table_has_column($conn, 'squadre', 'girone');
        if ($hasGironeColumn) {
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT NULLIF(TRIM(COALESCE(girone, '')), '')) AS gruppi
                FROM squadre
                WHERE torneo = ?
            ");
            if ($stmt) {
                $stmt->bind_param('s', $slug);
                if ($stmt->execute()) {
                    $row = $stmt->get_result()->fetch_assoc();
                    $groups = (int)($row['gruppi'] ?? 0);
                    $stmt->close();
                    if ($groups > 1) {
                        $fallbackCache[$cacheKey] = 'girone';
                        return 'girone';
                    }
                } else {
                    $stmt->close();
                }
            }
        }

        $fallbackCache[$cacheKey] = 'campionato';
        return 'campionato';
    }
}

if (!function_exists('torneo_stats_normalized_phase_expr')) {
    function torneo_stats_normalized_phase_expr(string $phaseColumn = 'p.fase'): string
    {
        return "UPPER(CASE WHEN TRIM(COALESCE($phaseColumn, '')) IN ('', 'GIRONE') THEN 'REGULAR' ELSE TRIM(COALESCE($phaseColumn, '')) END)";
    }
}

if (!function_exists('torneo_stats_team_include_finals')) {
    function torneo_stats_team_include_finals(mysqli $conn, string $torneo): bool
    {
        if (strcasecmp(trim($torneo), 'Coppadafrica') === 0) {
            return true;
        }

        return torneo_stats_get_format($conn, $torneo) === 'campionato';
    }
}

if (!function_exists('torneo_stats_team_phase_clause')) {
    function torneo_stats_team_phase_clause(mysqli $conn, string $torneo, string $phaseColumn = 'p.fase'): string
    {
        if (torneo_stats_team_include_finals($conn, $torneo)) {
            return '';
        }

        return " AND " . torneo_stats_normalized_phase_expr($phaseColumn) . " = 'REGULAR'";
    }
}

if (!function_exists('torneo_stats_empty_player_totals')) {
    function torneo_stats_empty_player_totals(): array
    {
        return [
            'presenze' => 0,
            'reti' => 0,
            'assist' => 0,
            'gialli' => 0,
            'rossi' => 0,
            'media_voti' => null,
        ];
    }
}

if (!function_exists('torneo_stats_fetch_player_team_totals')) {
    function torneo_stats_fetch_player_team_totals(mysqli $conn, int $giocatoreId, string $torneo, string $teamName): array
    {
        $torneo = trim($torneo);
        $teamName = trim($teamName);
        if ($giocatoreId <= 0 || $torneo === '' || $teamName === '') {
            return torneo_stats_empty_player_totals();
        }

        $phaseClause = torneo_stats_team_phase_clause($conn, $torneo, 'p.fase');
        $sql = "
            SELECT
                COALESCE(SUM(CASE WHEN pg.presenza = 1 THEN 1 ELSE 0 END), 0) AS presenze,
                COALESCE(SUM(pg.goal), 0) AS reti,
                COALESCE(SUM(pg.assist), 0) AS assist,
                COALESCE(SUM(pg.cartellino_giallo), 0) AS gialli,
                COALESCE(SUM(pg.cartellino_rosso), 0) AS rossi,
                SUM(CASE WHEN pg.voto IS NOT NULL THEN pg.voto ELSE 0 END) AS somma_voti,
                SUM(CASE WHEN pg.voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
            FROM partita_giocatore pg
            JOIN partite p ON p.id = pg.partita_id
            WHERE pg.giocatore_id = ?
              AND p.giocata = 1
              AND p.torneo = ?
              AND (p.squadra_casa = ? OR p.squadra_ospite = ?)
              $phaseClause
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return torneo_stats_empty_player_totals();
        }

        $stmt->bind_param('isss', $giocatoreId, $torneo, $teamName, $teamName);
        if (!$stmt->execute()) {
            $stmt->close();
            return torneo_stats_empty_player_totals();
        }

        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $numVoti = (int)($row['num_voti'] ?? 0);
        return [
            'presenze' => (int)($row['presenze'] ?? 0),
            'reti' => (int)($row['reti'] ?? 0),
            'assist' => (int)($row['assist'] ?? 0),
            'gialli' => (int)($row['gialli'] ?? 0),
            'rossi' => (int)($row['rossi'] ?? 0),
            'media_voti' => $numVoti > 0 ? round(((float)($row['somma_voti'] ?? 0)) / $numVoti, 2) : null,
        ];
    }
}

if (!function_exists('torneo_stats_rebuild_all_player_aggregates')) {
    function torneo_stats_rebuild_all_player_aggregates(mysqli $conn): array
    {
        $result = [
            'giocatori_globali' => 0,
            'associazioni_squadra' => 0,
        ];

        $sqlGlobal = "
            UPDATE giocatori g
            LEFT JOIN (
                SELECT
                    pg.giocatore_id,
                    SUM(pg.goal) AS goal,
                    SUM(pg.assist) AS assist,
                    SUM(pg.cartellino_giallo) AS gialli,
                    SUM(pg.cartellino_rosso) AS rossi,
                    SUM(CASE WHEN pg.presenza = 1 THEN 1 ELSE 0 END) AS presenze,
                    SUM(CASE WHEN pg.voto IS NOT NULL THEN pg.voto ELSE 0 END) AS somma_voti,
                    SUM(CASE WHEN pg.voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
                FROM partita_giocatore pg
                JOIN partite p ON p.id = pg.partita_id
                WHERE p.giocata = 1
                GROUP BY pg.giocatore_id
            ) agg ON agg.giocatore_id = g.id
            SET
                g.presenze = COALESCE(agg.presenze, 0),
                g.reti = COALESCE(agg.goal, 0),
                g.assist = COALESCE(agg.assist, 0),
                g.gialli = COALESCE(agg.gialli, 0),
                g.rossi = COALESCE(agg.rossi, 0),
                g.media_voti = CASE WHEN COALESCE(agg.num_voti, 0) > 0 THEN ROUND(agg.somma_voti / agg.num_voti, 2) ELSE NULL END
        ";
        if (!$conn->query($sqlGlobal)) {
            throw new RuntimeException('Ricalcolo aggregati giocatori fallito: ' . $conn->error);
        }
        $result['giocatori_globali'] = max(0, (int)$conn->affected_rows);

        $select = $conn->prepare("
            SELECT sg.id, sg.giocatore_id, s.torneo, s.nome
            FROM squadre_giocatori sg
            JOIN squadre s ON s.id = sg.squadra_id
            ORDER BY sg.id ASC
        ");
        if (!$select) {
            throw new RuntimeException('Preparazione lettura squadre_giocatori fallita: ' . $conn->error);
        }
        if (!$select->execute()) {
            $select->close();
            throw new RuntimeException('Lettura squadre_giocatori fallita: ' . $select->error);
        }

        $rows = $select->get_result();
        $update = $conn->prepare("
            UPDATE squadre_giocatori
            SET presenze = ?, reti = ?, assist = ?, gialli = ?, rossi = ?, media_voti = ?
            WHERE id = ?
        ");
        if (!$update) {
            $select->close();
            throw new RuntimeException('Preparazione update squadre_giocatori fallita: ' . $conn->error);
        }

        while ($row = $rows->fetch_assoc()) {
            $stats = torneo_stats_fetch_player_team_totals(
                $conn,
                (int)$row['giocatore_id'],
                (string)$row['torneo'],
                (string)$row['nome']
            );

            $presenze = $stats['presenze'];
            $reti = $stats['reti'];
            $assist = $stats['assist'];
            $gialli = $stats['gialli'];
            $rossi = $stats['rossi'];
            $mediaVoti = $stats['media_voti'];
            $associationId = (int)$row['id'];

            $update->bind_param('iiiiidi', $presenze, $reti, $assist, $gialli, $rossi, $mediaVoti, $associationId);
            if (!$update->execute()) {
                $update->close();
                $select->close();
                throw new RuntimeException('Update associazione #' . $associationId . ' fallito: ' . $update->error);
            }

            $result['associazioni_squadra']++;
        }

        $update->close();
        $select->close();

        return $result;
    }
}
