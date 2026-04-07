<?php

require_once __DIR__ . '/user_features.php';

if (!function_exists('totocalcio_user_name')) {
    function totocalcio_user_name(array $row): string
    {
        $fullName = trim((string)($row['nome'] ?? '') . ' ' . (string)($row['cognome'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        return (string)($row['email'] ?? 'Utente');
    }
}

if (!function_exists('totocalcio_compute_sign')) {
    function totocalcio_compute_sign(int $golCasa, int $golTrasferta): string
    {
        if ($golCasa > $golTrasferta) {
            return '1';
        }

        if ($golCasa < $golTrasferta) {
            return '2';
        }

        return 'X';
    }
}

if (!function_exists('totocalcio_is_result_available')) {
    function totocalcio_is_result_available(array $match): bool
    {
        return (int)($match['giocata'] ?? 0) === 1
            && isset($match['gol_casa_reale'], $match['gol_trasferta_reale'])
            && $match['gol_casa_reale'] !== null
            && $match['gol_trasferta_reale'] !== null
            && $match['gol_casa_reale'] !== ''
            && $match['gol_trasferta_reale'] !== '';
    }
}

if (!function_exists('totocalcio_is_match_open')) {
    function totocalcio_is_match_open(array $match): bool
    {
        return !empty($match['visibile'])
            && (int)($match['giocata'] ?? 0) !== 1;
    }
}

if (!function_exists('totocalcio_evaluate_prediction')) {
    function totocalcio_evaluate_prediction(array $match, ?array $prediction): array
    {
        $empty = [
            'has_prediction' => $prediction !== null,
            'is_scored' => false,
            'esito_corretto' => false,
            'risultato_esatto' => false,
            'punti_esito' => 0,
            'punti_risultato' => 0,
            'punti_totali' => 0,
        ];

        if ($prediction === null || !totocalcio_is_result_available($match)) {
            return $empty;
        }

        $realHome = (int)$match['gol_casa_reale'];
        $realAway = (int)$match['gol_trasferta_reale'];
        $predHome = (int)$prediction['gol_casa_previsti'];
        $predAway = (int)$prediction['gol_trasferta_previsti'];
        $predSign = (string)$prediction['segno'];

        $exact = $predHome === $realHome && $predAway === $realAway;
        $outcome = $predSign === totocalcio_compute_sign($realHome, $realAway);
        $pointsOutcome = $outcome ? 1 : 0;
        $pointsExact = $exact ? 3 : 0;

        return [
            'has_prediction' => true,
            'is_scored' => true,
            'esito_corretto' => $outcome,
            'risultato_esatto' => $exact,
            'punti_esito' => $pointsOutcome,
            'punti_risultato' => $pointsExact,
            'punti_totali' => $pointsOutcome + $pointsExact,
        ];
    }
}

if (!function_exists('totocalcio_table_exists')) {
    function totocalcio_table_exists(mysqli $conn, string $table): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        if (!($result instanceof mysqli_result)) {
            return false;
        }

        $exists = $result->num_rows > 0;
        $result->close();

        return $exists;
    }
}

if (!function_exists('totocalcio_table_has_column')) {
    function totocalcio_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        if (!totocalcio_table_exists($conn, $table)) {
            return false;
        }

        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if (!($result instanceof mysqli_result)) {
            return false;
        }

        $hasColumn = $result->num_rows > 0;
        $result->close();

        return $hasColumn;
    }
}

if (!function_exists('totocalcio_backup_legacy_tables')) {
    function totocalcio_backup_legacy_tables(mysqli $conn): void
    {
        if (totocalcio_table_exists($conn, 'totocalcio_pronostici') && !totocalcio_table_exists($conn, 'totocalcio_pronostici_legacy_backup')) {
            $conn->query("CREATE TABLE totocalcio_pronostici_legacy_backup AS SELECT * FROM totocalcio_pronostici");
        }

        if (totocalcio_table_exists($conn, 'totocalcio_partite') && !totocalcio_table_exists($conn, 'totocalcio_partite_legacy_backup')) {
            $conn->query("CREATE TABLE totocalcio_partite_legacy_backup AS SELECT * FROM totocalcio_partite");
        }
    }
}

if (!function_exists('totocalcio_ensure_tables')) {
    function totocalcio_ensure_tables(mysqli $conn): bool
    {
        static $checked = false;
        static $ready = false;

        if ($checked) {
            return $ready;
        }

        $checked = true;

        $hasLegacySelectionTable = totocalcio_table_exists($conn, 'totocalcio_partite')
            && !totocalcio_table_has_column($conn, 'totocalcio_partite', 'partita_id');

        if ($hasLegacySelectionTable) {
            totocalcio_backup_legacy_tables($conn);
            $conn->query("DROP TABLE IF EXISTS totocalcio_pronostici");
            $conn->query("DROP TABLE IF EXISTS totocalcio_partite");
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS totocalcio_partite (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                partita_id INT UNSIGNED NOT NULL,
                ordine INT NOT NULL DEFAULT 0,
                attiva TINYINT(1) NOT NULL DEFAULT 1,
                creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_totocalcio_partita_reale (partita_id),
                KEY idx_totocalcio_attiva_ordine (attiva, ordine),
                CONSTRAINT fk_totocalcio_match_partita FOREIGN KEY (partita_id) REFERENCES partite(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS totocalcio_pronostici (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                partita_id INT UNSIGNED NOT NULL,
                utente_id INT UNSIGNED NOT NULL,
                segno ENUM('1','X','2') NOT NULL,
                gol_casa_previsti TINYINT UNSIGNED NOT NULL,
                gol_trasferta_previsti TINYINT UNSIGNED NOT NULL,
                creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_totocalcio_pronostico (partita_id, utente_id),
                KEY idx_totocalcio_utente (utente_id),
                CONSTRAINT fk_totocalcio_pronostico_partita FOREIGN KEY (partita_id) REFERENCES totocalcio_partite(id) ON DELETE CASCADE,
                CONSTRAINT fk_totocalcio_pronostico_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $query) {
            if ($conn->query($query) !== true) {
                error_log('totocalcio: errore creazione tabelle - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('totocalcio_fetch_match_by_id')) {
    function totocalcio_fetch_match_by_id(mysqli $conn, int $matchId): ?array
    {
        if ($matchId <= 0 || !totocalcio_ensure_tables($conn)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT
                tp.id,
                tp.partita_id,
                tp.ordine,
                tp.attiva AS visibile,
                p.torneo,
                p.fase,
                p.fase_round,
                p.fase_leg,
                p.giornata,
                p.squadra_casa,
                p.squadra_ospite AS squadra_trasferta,
                p.data_partita,
                p.ora_partita,
                p.campo,
                p.gol_casa AS gol_casa_reale,
                p.gol_ospite AS gol_trasferta_reale,
                p.giocata,
                tp.creato_il,
                tp.aggiornato_il
             FROM totocalcio_partite tp
             INNER JOIN partite p ON p.id = tp.partita_id
             WHERE tp.id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $matchId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('totocalcio_fetch_matches')) {
    function totocalcio_fetch_matches(mysqli $conn, bool $onlyVisible = true, int $userId = 0): array
    {
        if (!totocalcio_ensure_tables($conn)) {
            return [];
        }

        $predictionFields = $userId > 0
            ? ",
                pr.segno AS user_segno,
                pr.gol_casa_previsti AS user_gol_casa_previsti,
                pr.gol_trasferta_previsti AS user_gol_trasferta_previsti,
                pr.creato_il AS user_prediction_created_at,
                pr.aggiornato_il AS user_prediction_updated_at"
            : ",
                NULL AS user_segno,
                NULL AS user_gol_casa_previsti,
                NULL AS user_gol_trasferta_previsti,
                NULL AS user_prediction_created_at,
                NULL AS user_prediction_updated_at";

        $sql = "SELECT
                    tp.id,
                    tp.partita_id,
                    tp.ordine,
                    tp.attiva AS visibile,
                    p.torneo,
                    p.fase,
                    p.fase_round,
                    p.fase_leg,
                    p.giornata,
                    p.squadra_casa,
                    p.squadra_ospite AS squadra_trasferta,
                    p.data_partita,
                    p.ora_partita,
                    p.campo,
                    p.gol_casa AS gol_casa_reale,
                    p.gol_ospite AS gol_trasferta_reale,
                    p.giocata,
                    COALESCE(pc.total_predictions, 0) AS total_predictions
                    {$predictionFields}
                FROM totocalcio_partite tp
                INNER JOIN partite p ON p.id = tp.partita_id
                LEFT JOIN (
                    SELECT partita_id, COUNT(*) AS total_predictions
                    FROM totocalcio_pronostici
                    GROUP BY partita_id
                ) pc ON pc.partita_id = tp.id";

        if ($userId > 0) {
            $sql .= "
                LEFT JOIN totocalcio_pronostici pr
                    ON pr.partita_id = tp.id
                   AND pr.utente_id = ?";
        }

        if ($onlyVisible) {
            $sql .= " WHERE tp.attiva = 1";
        }

        $sql .= "
            ORDER BY
                tp.ordine ASC,
                CASE WHEN p.data_partita IS NULL THEN 1 ELSE 0 END ASC,
                p.data_partita ASC,
                CASE WHEN p.ora_partita IS NULL THEN 1 ELSE 0 END ASC,
                p.ora_partita ASC,
                tp.id ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if ($userId > 0) {
            $stmt->bind_param('i', $userId);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->close();
        }

        $stmt->close();

        return $rows;
    }
}

if (!function_exists('totocalcio_fetch_candidate_matches')) {
    function totocalcio_fetch_candidate_matches(mysqli $conn): array
    {
        if (!totocalcio_ensure_tables($conn)) {
            return [];
        }

        $sql = "SELECT
                    p.id,
                    p.torneo,
                    p.fase,
                    p.fase_round,
                    p.fase_leg,
                    p.giornata,
                    p.squadra_casa,
                    p.squadra_ospite AS squadra_trasferta,
                    p.data_partita,
                    p.ora_partita,
                    p.campo
                FROM partite p
                LEFT JOIN totocalcio_partite tp
                    ON tp.partita_id = p.id
                WHERE p.giocata = 0
                  AND tp.id IS NULL
                ORDER BY
                    CASE WHEN p.data_partita IS NULL THEN 1 ELSE 0 END ASC,
                    p.data_partita ASC,
                    CASE WHEN p.ora_partita IS NULL THEN 1 ELSE 0 END ASC,
                    p.ora_partita ASC,
                    p.torneo ASC,
                    p.id ASC";

        $result = $conn->query($sql);
        if (!($result instanceof mysqli_result)) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->close();

        return $rows;
    }
}

if (!function_exists('totocalcio_add_match')) {
    function totocalcio_add_match(mysqli $conn, int $partitaId, int $ordine = 0): bool
    {
        if ($partitaId <= 0 || !totocalcio_ensure_tables($conn)) {
            return false;
        }

        $stmtCheck = $conn->prepare("SELECT id, giocata FROM partite WHERE id = ? LIMIT 1");
        if (!$stmtCheck) {
            return false;
        }

        $stmtCheck->bind_param('i', $partitaId);
        $stmtCheck->execute();
        $row = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if (!$row || (int)($row['giocata'] ?? 0) === 1) {
            return false;
        }

        $stmt = $conn->prepare(
            "INSERT INTO totocalcio_partite (partita_id, ordine, attiva)
             VALUES (?, ?, 1)"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $partitaId, $ordine);
        $saved = $stmt->execute();
        $stmt->close();

        return $saved;
    }
}

if (!function_exists('totocalcio_update_match')) {
    function totocalcio_update_match(mysqli $conn, int $selectionId, int $ordine, bool $attiva): bool
    {
        if ($selectionId <= 0 || !totocalcio_ensure_tables($conn)) {
            return false;
        }

        $activeValue = $attiva ? 1 : 0;
        $stmt = $conn->prepare(
            "UPDATE totocalcio_partite
             SET ordine = ?, attiva = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iii', $ordine, $activeValue, $selectionId);
        $saved = $stmt->execute();
        $stmt->close();

        return $saved;
    }
}

if (!function_exists('totocalcio_delete_match')) {
    function totocalcio_delete_match(mysqli $conn, int $matchId): bool
    {
        if ($matchId <= 0 || !totocalcio_ensure_tables($conn)) {
            return false;
        }

        $stmt = $conn->prepare("DELETE FROM totocalcio_partite WHERE id = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $matchId);
        $deleted = $stmt->execute();
        $stmt->close();

        return $deleted;
    }
}

if (!function_exists('totocalcio_save_prediction')) {
    function totocalcio_save_prediction(
        mysqli $conn,
        int $matchId,
        int $userId,
        string $sign,
        int $predHome,
        int $predAway
    ): bool {
        if ($matchId <= 0 || $userId <= 0 || !totocalcio_ensure_tables($conn)) {
            return false;
        }

        $match = totocalcio_fetch_match_by_id($conn, $matchId);
        if (!$match || !totocalcio_is_match_open($match)) {
            return false;
        }

        $stmt = $conn->prepare(
            "INSERT INTO totocalcio_pronostici
                (partita_id, utente_id, segno, gol_casa_previsti, gol_trasferta_previsti)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                segno = VALUES(segno),
                gol_casa_previsti = VALUES(gol_casa_previsti),
                gol_trasferta_previsti = VALUES(gol_trasferta_previsti),
                aggiornato_il = CURRENT_TIMESTAMP"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iisii', $matchId, $userId, $sign, $predHome, $predAway);
        $saved = $stmt->execute();
        $stmt->close();

        return $saved;
    }
}

if (!function_exists('totocalcio_fetch_leaderboard')) {
    function totocalcio_fetch_leaderboard(mysqli $conn): array
    {
        if (!totocalcio_ensure_tables($conn)) {
            return [];
        }

        $sql = "SELECT
                    u.id,
                    u.nome,
                    u.cognome,
                    u.email,
                    u.feature_flags,
                    COALESCE(score.punti_esito, 0) AS punti_esito,
                    COALESCE(score.punti_risultato, 0) AS punti_risultato,
                    COALESCE(score.esiti_corretti, 0) AS esiti_corretti,
                    COALESCE(score.risultati_esatti, 0) AS risultati_esatti,
                    COALESCE(score.pronostici_valutati, 0) AS pronostici_valutati
                FROM utenti u
                LEFT JOIN (
                    SELECT
                        pr.utente_id,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                 AND (
                                    (p.gol_casa > p.gol_ospite AND pr.segno = '1')
                                    OR (p.gol_casa = p.gol_ospite AND pr.segno = 'X')
                                    OR (p.gol_casa < p.gol_ospite AND pr.segno = '2')
                                 )
                                THEN 1
                                ELSE 0
                            END
                        ) AS punti_esito,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                 AND pr.gol_casa_previsti = p.gol_casa
                                 AND pr.gol_trasferta_previsti = p.gol_ospite
                                THEN 3
                                ELSE 0
                            END
                        ) AS punti_risultato,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                 AND (
                                    (p.gol_casa > p.gol_ospite AND pr.segno = '1')
                                    OR (p.gol_casa = p.gol_ospite AND pr.segno = 'X')
                                    OR (p.gol_casa < p.gol_ospite AND pr.segno = '2')
                                 )
                                THEN 1
                                ELSE 0
                            END
                        ) AS esiti_corretti,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                 AND pr.gol_casa_previsti = p.gol_casa
                                 AND pr.gol_trasferta_previsti = p.gol_ospite
                                THEN 1
                                ELSE 0
                            END
                        ) AS risultati_esatti,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                THEN 1
                                ELSE 0
                            END
                        ) AS pronostici_valutati
                    FROM totocalcio_pronostici pr
                    INNER JOIN totocalcio_partite tp
                        ON tp.id = pr.partita_id
                    INNER JOIN partite p
                        ON p.id = tp.partita_id
                    GROUP BY pr.utente_id
                ) score ON score.utente_id = u.id";

        $result = $conn->query($sql);
        if (!($result instanceof mysqli_result)) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $flags = normalize_user_feature_flags($row['feature_flags'] ?? null);
            if (empty($flags['totocalcio'])) {
                continue;
            }

            $row['punti_esito'] = (int)($row['punti_esito'] ?? 0);
            $row['punti_risultato'] = (int)($row['punti_risultato'] ?? 0);
            $row['esiti_corretti'] = (int)($row['esiti_corretti'] ?? 0);
            $row['risultati_esatti'] = (int)($row['risultati_esatti'] ?? 0);
            $row['pronostici_valutati'] = (int)($row['pronostici_valutati'] ?? 0);
            $row['punti_totali'] = $row['punti_esito'] + $row['punti_risultato'];
            $row['display_name'] = totocalcio_user_name($row);
            $rows[] = $row;
        }
        $result->close();

        usort($rows, static function (array $a, array $b): int {
            $byPoints = $b['punti_totali'] <=> $a['punti_totali'];
            if ($byPoints !== 0) {
                return $byPoints;
            }

            $byExact = $b['risultati_esatti'] <=> $a['risultati_esatti'];
            if ($byExact !== 0) {
                return $byExact;
            }

            $byOutcome = $b['esiti_corretti'] <=> $a['esiti_corretti'];
            if ($byOutcome !== 0) {
                return $byOutcome;
            }

            return strcasecmp((string)$a['display_name'], (string)$b['display_name']);
        });

        return $rows;
    }
}
