<?php

require_once __DIR__ . '/user_features.php';

if (!function_exists('totocalcio_default_competition_name')) {
    function totocalcio_default_competition_name(): string
    {
        return 'Totocalcio Mondiale Fascia B';
    }
}

if (!function_exists('totocalcio_default_competition_slug')) {
    function totocalcio_default_competition_slug(): string
    {
        return 'totocalcio-mondiale-fascia-b';
    }
}

if (!function_exists('totocalcio_slugify')) {
    function totocalcio_slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'competizione';
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }

        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'competizione';
    }
}

if (!function_exists('totocalcio_stmt_bind_params')) {
    function totocalcio_stmt_bind_params(mysqli_stmt $stmt, string $types, array $params): bool
    {
        if ($types === '' || empty($params)) {
            return true;
        }

        $bindArgs = [$types];
        foreach ($params as $index => $value) {
            $bindArgs[] = &$params[$index];
        }

        return (bool)call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    }
}

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
            && !empty($match['competizione_attiva'])
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

if (!function_exists('totocalcio_table_column_definition')) {
    function totocalcio_table_column_definition(mysqli $conn, string $table, string $column): ?array
    {
        if (!totocalcio_table_exists($conn, $table)) {
            return null;
        }

        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if (!($result instanceof mysqli_result)) {
            return null;
        }

        $row = $result->fetch_assoc();
        $result->close();

        return $row ?: null;
    }
}

if (!function_exists('totocalcio_table_has_index')) {
    function totocalcio_table_has_index(mysqli $conn, string $table, string $index): bool
    {
        if (!totocalcio_table_exists($conn, $table)) {
            return false;
        }

        $safeTable = $conn->real_escape_string($table);
        $safeIndex = $conn->real_escape_string($index);
        $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
        if (!($result instanceof mysqli_result)) {
            return false;
        }

        $exists = $result->num_rows > 0;
        $result->close();

        return $exists;
    }
}

if (!function_exists('totocalcio_table_index_columns')) {
    function totocalcio_table_index_columns(mysqli $conn, string $table, string $index): array
    {
        if (!totocalcio_table_exists($conn, $table)) {
            return [];
        }

        $safeTable = $conn->real_escape_string($table);
        $safeIndex = $conn->real_escape_string($index);
        $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
        if (!($result instanceof mysqli_result)) {
            return [];
        }

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $position = (int)($row['Seq_in_index'] ?? 0);
            $columns[$position] = (string)($row['Column_name'] ?? '');
        }
        $result->close();

        ksort($columns);

        return array_values($columns);
    }
}

if (!function_exists('totocalcio_table_has_constraint')) {
    function totocalcio_table_has_constraint(mysqli $conn, string $table, string $constraint): bool
    {
        $sql = "SELECT 1
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $table, $constraint);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result instanceof mysqli_result && $result->num_rows > 0;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return $exists;
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

if (!function_exists('totocalcio_ensure_default_competition_row')) {
    function totocalcio_ensure_default_competition_row(mysqli $conn): int
    {
        $defaultName = totocalcio_default_competition_name();
        $defaultSlug = totocalcio_default_competition_slug();

        $stmt = $conn->prepare(
            "INSERT INTO totocalcio_competizioni (nome, slug, attiva, ordine)
             VALUES (?, ?, 1, 0)
             ON DUPLICATE KEY UPDATE
                nome = VALUES(nome)"
        );

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('ss', $defaultName, $defaultSlug);
        $saved = $stmt->execute();
        $stmt->close();

        if (!$saved) {
            return 0;
        }

        $stmt = $conn->prepare(
            "SELECT id
             FROM totocalcio_competizioni
             WHERE slug = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('s', $defaultSlug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return (int)($row['id'] ?? 0);
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
            "CREATE TABLE IF NOT EXISTS totocalcio_competizioni (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(150) NOT NULL,
                slug VARCHAR(180) NOT NULL,
                attiva TINYINT(1) NOT NULL DEFAULT 1,
                accesso_pubblico TINYINT(1) NOT NULL DEFAULT 1,
                ordine INT NOT NULL DEFAULT 0,
                creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_totocalcio_competizione_slug (slug),
                KEY idx_totocalcio_competizioni_attive (attiva, ordine)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS totocalcio_partite (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                competizione_id INT UNSIGNED NOT NULL,
                partita_id INT UNSIGNED NOT NULL,
                ordine INT NOT NULL DEFAULT 0,
                attiva TINYINT(1) NOT NULL DEFAULT 1,
                creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_totocalcio_partita_reale (competizione_id, partita_id),
                KEY idx_totocalcio_competizione_attiva_ordine (competizione_id, attiva, ordine),
                KEY idx_totocalcio_partita (partita_id),
                CONSTRAINT fk_totocalcio_partita_competizione FOREIGN KEY (competizione_id) REFERENCES totocalcio_competizioni(id) ON DELETE CASCADE,
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
            "CREATE TABLE IF NOT EXISTS totocalcio_competizioni_accessi (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                competizione_id INT UNSIGNED NOT NULL,
                utente_id INT UNSIGNED NOT NULL,
                creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_totocalcio_competizione_utente (competizione_id, utente_id),
                KEY idx_totocalcio_accesso_utente (utente_id),
                CONSTRAINT fk_totocalcio_accesso_competizione FOREIGN KEY (competizione_id) REFERENCES totocalcio_competizioni(id) ON DELETE CASCADE,
                CONSTRAINT fk_totocalcio_accesso_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $query) {
            if ($conn->query($query) !== true) {
                error_log('totocalcio: errore creazione tabelle - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        $defaultCompetitionId = totocalcio_ensure_default_competition_row($conn);
        if ($defaultCompetitionId <= 0) {
            error_log('totocalcio: impossibile creare o recuperare la competizione di default');
            $ready = false;
            return false;
        }

        if (!totocalcio_table_has_column($conn, 'totocalcio_competizioni', 'accesso_pubblico')) {
            if ($conn->query(
                "ALTER TABLE totocalcio_competizioni
                 ADD COLUMN accesso_pubblico TINYINT(1) NOT NULL DEFAULT 1 AFTER attiva"
            ) !== true) {
                error_log('totocalcio: impossibile aggiungere accesso_pubblico - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        $competitionColumn = totocalcio_table_column_definition($conn, 'totocalcio_partite', 'competizione_id');
        if ($competitionColumn === null) {
            if ($conn->query("ALTER TABLE totocalcio_partite ADD COLUMN competizione_id INT UNSIGNED NULL AFTER id") !== true) {
                error_log('totocalcio: impossibile aggiungere competizione_id - ' . $conn->error);
                $ready = false;
                return false;
            }

            $competitionColumn = totocalcio_table_column_definition($conn, 'totocalcio_partite', 'competizione_id');
        }

        if ($conn->query("UPDATE totocalcio_partite SET competizione_id = {$defaultCompetitionId} WHERE competizione_id IS NULL OR competizione_id = 0") !== true) {
            error_log('totocalcio: impossibile valorizzare competizione_id - ' . $conn->error);
            $ready = false;
            return false;
        }

        if ($competitionColumn !== null && strtoupper((string)($competitionColumn['Null'] ?? 'YES')) !== 'NO') {
            if ($conn->query("ALTER TABLE totocalcio_partite MODIFY competizione_id INT UNSIGNED NOT NULL") !== true) {
                error_log('totocalcio: impossibile rendere obbligatoria competizione_id - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        if (totocalcio_table_index_columns($conn, 'totocalcio_partite', 'idx_totocalcio_partita') !== ['partita_id']) {
            if (totocalcio_table_has_index($conn, 'totocalcio_partite', 'idx_totocalcio_partita')
                && $conn->query("ALTER TABLE totocalcio_partite DROP INDEX idx_totocalcio_partita") !== true) {
                error_log('totocalcio: impossibile aggiornare idx_totocalcio_partita - ' . $conn->error);
                $ready = false;
                return false;
            }

            if ($conn->query("ALTER TABLE totocalcio_partite ADD INDEX idx_totocalcio_partita (partita_id)") !== true) {
                error_log('totocalcio: impossibile creare idx_totocalcio_partita - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        if (totocalcio_table_index_columns($conn, 'totocalcio_partite', 'uq_totocalcio_partita_reale') !== ['competizione_id', 'partita_id']) {
            if (totocalcio_table_has_index($conn, 'totocalcio_partite', 'uq_totocalcio_partita_reale')
                && $conn->query("ALTER TABLE totocalcio_partite DROP INDEX uq_totocalcio_partita_reale") !== true) {
                error_log('totocalcio: impossibile eliminare uq_totocalcio_partita_reale - ' . $conn->error);
                $ready = false;
                return false;
            }

            if ($conn->query("ALTER TABLE totocalcio_partite ADD UNIQUE KEY uq_totocalcio_partita_reale (competizione_id, partita_id)") !== true) {
                error_log('totocalcio: impossibile creare uq_totocalcio_partita_reale - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        if (totocalcio_table_has_index($conn, 'totocalcio_partite', 'idx_totocalcio_attiva_ordine')) {
            if ($conn->query("ALTER TABLE totocalcio_partite DROP INDEX idx_totocalcio_attiva_ordine") !== true) {
                error_log('totocalcio: impossibile eliminare idx_totocalcio_attiva_ordine - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        if (totocalcio_table_index_columns($conn, 'totocalcio_partite', 'idx_totocalcio_competizione_attiva_ordine') !== ['competizione_id', 'attiva', 'ordine']) {
            if (totocalcio_table_has_index($conn, 'totocalcio_partite', 'idx_totocalcio_competizione_attiva_ordine')
                && $conn->query("ALTER TABLE totocalcio_partite DROP INDEX idx_totocalcio_competizione_attiva_ordine") !== true) {
                error_log('totocalcio: impossibile aggiornare idx_totocalcio_competizione_attiva_ordine - ' . $conn->error);
                $ready = false;
                return false;
            }

            if ($conn->query("ALTER TABLE totocalcio_partite ADD INDEX idx_totocalcio_competizione_attiva_ordine (competizione_id, attiva, ordine)") !== true) {
                error_log('totocalcio: impossibile creare idx_totocalcio_competizione_attiva_ordine - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        if (!totocalcio_table_has_constraint($conn, 'totocalcio_partite', 'fk_totocalcio_partita_competizione')) {
            if ($conn->query(
                "ALTER TABLE totocalcio_partite
                 ADD CONSTRAINT fk_totocalcio_partita_competizione
                 FOREIGN KEY (competizione_id) REFERENCES totocalcio_competizioni(id) ON DELETE CASCADE"
            ) !== true) {
                error_log('totocalcio: impossibile creare fk_totocalcio_partita_competizione - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('totocalcio_fetch_competitions')) {
    function totocalcio_fetch_competitions(mysqli $conn, bool $onlyActive = true): array
    {
        if (!totocalcio_ensure_tables($conn)) {
            return [];
        }

        $sql = "SELECT
                    tc.id,
                    tc.nome,
                    tc.slug,
                    tc.attiva,
                    tc.accesso_pubblico,
                    tc.ordine,
                    tc.creato_il,
                    tc.aggiornato_il,
                    COALESCE(ms.total_matches, 0) AS total_matches,
                    COALESCE(ms.active_matches, 0) AS active_matches,
                    COALESCE(ps.total_predictions, 0) AS total_predictions,
                    COALESCE(ga.granted_users, 0) AS granted_users
                FROM totocalcio_competizioni tc
                LEFT JOIN (
                    SELECT
                        competizione_id,
                        COUNT(*) AS total_matches,
                        SUM(CASE WHEN attiva = 1 THEN 1 ELSE 0 END) AS active_matches
                    FROM totocalcio_partite
                    GROUP BY competizione_id
                ) ms ON ms.competizione_id = tc.id
                LEFT JOIN (
                    SELECT
                        tp.competizione_id,
                        COUNT(*) AS total_predictions
                    FROM totocalcio_pronostici pr
                    INNER JOIN totocalcio_partite tp
                        ON tp.id = pr.partita_id
                    GROUP BY tp.competizione_id
                ) ps ON ps.competizione_id = tc.id
                LEFT JOIN (
                    SELECT
                        competizione_id,
                        COUNT(*) AS granted_users
                    FROM totocalcio_competizioni_accessi
                    GROUP BY competizione_id
                ) ga ON ga.competizione_id = tc.id";

        if ($onlyActive) {
            $sql .= " WHERE tc.attiva = 1";
        }

        $sql .= " ORDER BY tc.ordine ASC, tc.nome ASC, tc.id ASC";

        $result = $conn->query($sql);
        if (!($result instanceof mysqli_result)) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)($row['id'] ?? 0);
            $row['attiva'] = (int)($row['attiva'] ?? 0);
            $row['accesso_pubblico'] = (int)($row['accesso_pubblico'] ?? 1);
            $row['ordine'] = (int)($row['ordine'] ?? 0);
            $row['total_matches'] = (int)($row['total_matches'] ?? 0);
            $row['active_matches'] = (int)($row['active_matches'] ?? 0);
            $row['total_predictions'] = (int)($row['total_predictions'] ?? 0);
            $row['granted_users'] = (int)($row['granted_users'] ?? 0);
            $rows[] = $row;
        }
        $result->close();

        return $rows;
    }
}

if (!function_exists('totocalcio_fetch_competition_by_slug')) {
    function totocalcio_fetch_competition_by_slug(mysqli $conn, string $slug, bool $onlyActive = false): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !totocalcio_ensure_tables($conn)) {
            return null;
        }

        $sql = "SELECT
                    id,
                    nome,
                    slug,
                    attiva,
                    accesso_pubblico,
                    ordine,
                    creato_il,
                    aggiornato_il
                FROM totocalcio_competizioni
                WHERE slug = ?";

        if ($onlyActive) {
            $sql .= " AND attiva = 1";
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('totocalcio_fetch_competition_by_id')) {
    function totocalcio_fetch_competition_by_id(mysqli $conn, int $competitionId): ?array
    {
        if ($competitionId <= 0 || !totocalcio_ensure_tables($conn)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT
                id,
                nome,
                slug,
                attiva,
                accesso_pubblico,
                ordine,
                creato_il,
                aggiornato_il
             FROM totocalcio_competizioni
             WHERE id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $competitionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('totocalcio_fetch_default_competition')) {
    function totocalcio_fetch_default_competition(mysqli $conn, bool $onlyActive = true): ?array
    {
        if (!totocalcio_ensure_tables($conn)) {
            return null;
        }

        $sql = "SELECT
                    id,
                    nome,
                    slug,
                    attiva,
                    accesso_pubblico,
                    ordine,
                    creato_il,
                    aggiornato_il
                FROM totocalcio_competizioni";

        if ($onlyActive) {
            $sql .= " WHERE attiva = 1";
        }

        $sql .= " ORDER BY ordine ASC, id ASC LIMIT 1";

        $result = $conn->query($sql);
        if (!($result instanceof mysqli_result)) {
            return null;
        }

        $row = $result->fetch_assoc();
        $result->close();

        return $row ?: null;
    }
}

if (!function_exists('totocalcio_generate_unique_competition_slug')) {
    function totocalcio_generate_unique_competition_slug(mysqli $conn, string $slugSource, int $ignoreCompetitionId = 0): string
    {
        $baseSlug = totocalcio_slugify($slugSource);
        $slug = $baseSlug;
        $suffix = 2;

        while (true) {
            $existing = totocalcio_fetch_competition_by_slug($conn, $slug, false);
            if ($existing === null || (int)($existing['id'] ?? 0) === $ignoreCompetitionId) {
                return $slug;
            }

            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
    }
}

if (!function_exists('totocalcio_create_competition')) {
    function totocalcio_create_competition(
        mysqli $conn,
        string $name,
        string $slugSource = '',
        int $order = 0,
        bool $active = true,
        bool $publicAccess = true
    ): ?array {
        $name = trim($name);
        if ($name === '' || !totocalcio_ensure_tables($conn)) {
            return null;
        }

        $finalSlug = totocalcio_generate_unique_competition_slug($conn, $slugSource !== '' ? $slugSource : $name);
        $activeValue = $active ? 1 : 0;
        $publicValue = $publicAccess ? 1 : 0;

        $stmt = $conn->prepare(
            "INSERT INTO totocalcio_competizioni (nome, slug, attiva, accesso_pubblico, ordine)
             VALUES (?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('ssiii', $name, $finalSlug, $activeValue, $publicValue, $order);
        $saved = $stmt->execute();
        $newId = $saved ? (int)$stmt->insert_id : 0;
        $stmt->close();

        if (!$saved || $newId <= 0) {
            return null;
        }

        return totocalcio_fetch_competition_by_id($conn, $newId);
    }
}

if (!function_exists('totocalcio_update_competition')) {
    function totocalcio_update_competition(
        mysqli $conn,
        int $competitionId,
        string $name,
        string $slugSource = '',
        int $order = 0,
        bool $active = true,
        bool $publicAccess = true
    ): bool {
        $name = trim($name);
        if ($competitionId <= 0 || $name === '' || !totocalcio_ensure_tables($conn)) {
            return false;
        }

        if (!totocalcio_fetch_competition_by_id($conn, $competitionId)) {
            return false;
        }

        $baseSlug = trim($slugSource) !== '' ? $slugSource : $name;
        $finalSlug = totocalcio_generate_unique_competition_slug($conn, $baseSlug, $competitionId);
        $activeValue = $active ? 1 : 0;
        $publicValue = $publicAccess ? 1 : 0;

        $stmt = $conn->prepare(
            "UPDATE totocalcio_competizioni
             SET nome = ?, slug = ?, attiva = ?, accesso_pubblico = ?, ordine = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ssiiii', $name, $finalSlug, $activeValue, $publicValue, $order, $competitionId);
        $saved = $stmt->execute();
        $stmt->close();

        return $saved;
    }
}

if (!function_exists('totocalcio_fetch_user_granted_competition_ids')) {
    function totocalcio_fetch_user_granted_competition_ids(mysqli $conn, int $userId): array
    {
        if ($userId <= 0 || !totocalcio_ensure_tables($conn)) {
            return [];
        }

        $stmt = $conn->prepare(
            "SELECT competizione_id
             FROM totocalcio_competizioni_accessi
             WHERE utente_id = ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $ids[] = (int)($row['competizione_id'] ?? 0);
            }
            $result->close();
        }

        $stmt->close();

        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('totocalcio_fetch_competition_granted_user_ids')) {
    function totocalcio_fetch_competition_granted_user_ids(mysqli $conn, int $competitionId): array
    {
        if ($competitionId <= 0 || !totocalcio_ensure_tables($conn)) {
            return [];
        }

        $stmt = $conn->prepare(
            "SELECT utente_id
             FROM totocalcio_competizioni_accessi
             WHERE competizione_id = ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $competitionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $ids[] = (int)($row['utente_id'] ?? 0);
            }
            $result->close();
        }

        $stmt->close();

        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('totocalcio_replace_competition_access')) {
    function totocalcio_replace_competition_access(mysqli $conn, int $competitionId, array $userIds): bool
    {
        if ($competitionId <= 0 || !totocalcio_ensure_tables($conn)) {
            return false;
        }

        if (!totocalcio_fetch_competition_by_id($conn, $competitionId)) {
            return false;
        }

        $normalizedUserIds = [];
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId > 0) {
                $normalizedUserIds[] = $userId;
            }
        }
        $normalizedUserIds = array_values(array_unique($normalizedUserIds));

        if (!$conn->begin_transaction()) {
            return false;
        }

        try {
            $stmtDelete = $conn->prepare(
                "DELETE FROM totocalcio_competizioni_accessi
                 WHERE competizione_id = ?"
            );

            if (!$stmtDelete) {
                throw new RuntimeException('delete access prepare failed');
            }

            $stmtDelete->bind_param('i', $competitionId);
            if (!$stmtDelete->execute()) {
                $stmtDelete->close();
                throw new RuntimeException('delete access execute failed');
            }
            $stmtDelete->close();

            if (!empty($normalizedUserIds)) {
                $stmtInsert = $conn->prepare(
                    "INSERT INTO totocalcio_competizioni_accessi (competizione_id, utente_id)
                     VALUES (?, ?)"
                );

                if (!$stmtInsert) {
                    throw new RuntimeException('insert access prepare failed');
                }

                foreach ($normalizedUserIds as $userId) {
                    $stmtInsert->bind_param('ii', $competitionId, $userId);
                    if (!$stmtInsert->execute()) {
                        $stmtInsert->close();
                        throw new RuntimeException('insert access execute failed');
                    }
                }

                $stmtInsert->close();
            }

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('totocalcio: impossibile aggiornare accessi competizione - ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('totocalcio_fetch_access_accounts')) {
    function totocalcio_fetch_access_accounts(mysqli $conn): array
    {
        if (!totocalcio_ensure_tables($conn)) {
            return [];
        }

        $result = $conn->query(
            "SELECT id, nome, cognome, email, ruolo, feature_flags
             FROM utenti
             ORDER BY
                CASE ruolo
                    WHEN 'sysadmin' THEN 0
                    WHEN 'admin' THEN 1
                    ELSE 2
                END,
                cognome ASC,
                nome ASC,
                email ASC"
        );

        if (!($result instanceof mysqli_result)) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)($row['id'] ?? 0);
            $row['feature_flags'] = normalize_user_feature_flags($row['feature_flags'] ?? null);
            $row['display_name'] = totocalcio_user_name($row);
            $rows[] = $row;
        }
        $result->close();

        return $rows;
    }
}

if (!function_exists('totocalcio_user_can_access_competition')) {
    function totocalcio_user_can_access_competition(array $competition, bool $hasAdminAccess, bool $hasGlobalFeature, array $grantedCompetitionIds = []): bool
    {
        if ($hasAdminAccess) {
            return true;
        }

        $competitionId = (int)($competition['id'] ?? 0);
        if ($competitionId <= 0 || empty($competition['attiva'])) {
            return false;
        }

        if (in_array($competitionId, $grantedCompetitionIds, true)) {
            return true;
        }

        return !empty($competition['accesso_pubblico']) && $hasGlobalFeature;
    }
}

if (!function_exists('totocalcio_user_has_any_explicit_access')) {
    function totocalcio_user_has_any_explicit_access(mysqli $conn, int $userId, string $role = 'user'): bool
    {
        if (user_has_admin_access($role)) {
            return true;
        }

        return !empty(totocalcio_fetch_user_granted_competition_ids($conn, $userId));
    }
}

if (!function_exists('totocalcio_fetch_match_by_id')) {
    function totocalcio_fetch_match_by_id(mysqli $conn, int $matchId, int $competitionId = 0): ?array
    {
        if ($matchId <= 0 || !totocalcio_ensure_tables($conn)) {
            return null;
        }

        $sql = "SELECT
                    tp.id,
                    tp.competizione_id,
                    tc.nome AS competizione_nome,
                    tc.slug AS competizione_slug,
                    tc.attiva AS competizione_attiva,
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
                INNER JOIN totocalcio_competizioni tc
                    ON tc.id = tp.competizione_id
                INNER JOIN partite p
                    ON p.id = tp.partita_id
                WHERE tp.id = ?";

        $types = 'i';
        $params = [$matchId];

        if ($competitionId > 0) {
            $sql .= " AND tp.competizione_id = ?";
            $types .= 'i';
            $params[] = $competitionId;
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        if (!totocalcio_stmt_bind_params($stmt, $types, $params)) {
            $stmt->close();
            return null;
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('totocalcio_fetch_matches')) {
    function totocalcio_fetch_matches(mysqli $conn, bool $onlyVisible = true, int $userId = 0, int $competitionId = 0): array
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
                    tp.competizione_id,
                    tc.nome AS competizione_nome,
                    tc.slug AS competizione_slug,
                    tc.attiva AS competizione_attiva,
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
                INNER JOIN totocalcio_competizioni tc
                    ON tc.id = tp.competizione_id
                INNER JOIN partite p
                    ON p.id = tp.partita_id
                LEFT JOIN (
                    SELECT partita_id, COUNT(*) AS total_predictions
                    FROM totocalcio_pronostici
                    GROUP BY partita_id
                ) pc ON pc.partita_id = tp.id";

        $types = '';
        $params = [];

        if ($userId > 0) {
            $sql .= "
                LEFT JOIN totocalcio_pronostici pr
                    ON pr.partita_id = tp.id
                   AND pr.utente_id = ?";
            $types .= 'i';
            $params[] = $userId;
        }

        $conditions = [];
        if ($onlyVisible) {
            $conditions[] = "tp.attiva = 1";
        }
        if ($competitionId > 0) {
            $conditions[] = "tp.competizione_id = ?";
            $types .= 'i';
            $params[] = $competitionId;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
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

        if (!totocalcio_stmt_bind_params($stmt, $types, $params)) {
            $stmt->close();
            return [];
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
    function totocalcio_fetch_candidate_matches(mysqli $conn, int $competitionId): array
    {
        if ($competitionId <= 0 || !totocalcio_ensure_tables($conn)) {
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
                   AND tp.competizione_id = ?
                WHERE p.giocata = 0
                  AND tp.id IS NULL
                ORDER BY
                    CASE WHEN p.data_partita IS NULL THEN 1 ELSE 0 END ASC,
                    p.data_partita ASC,
                    CASE WHEN p.ora_partita IS NULL THEN 1 ELSE 0 END ASC,
                    p.ora_partita ASC,
                    p.torneo ASC,
                    p.id ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $competitionId);
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

if (!function_exists('totocalcio_add_match')) {
    function totocalcio_add_match(mysqli $conn, int $competitionId, int $partitaId, int $ordine = 0): bool
    {
        if ($competitionId <= 0 || $partitaId <= 0 || !totocalcio_ensure_tables($conn)) {
            return false;
        }

        if (!totocalcio_fetch_competition_by_id($conn, $competitionId)) {
            return false;
        }

        $stmtCheck = $conn->prepare("SELECT id, giocata FROM partite WHERE id = ? LIMIT 1");
        if (!$stmtCheck) {
            return false;
        }

        $stmtCheck->bind_param('i', $partitaId);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmtCheck->close();

        if (!$row || (int)($row['giocata'] ?? 0) === 1) {
            return false;
        }

        $stmt = $conn->prepare(
            "INSERT INTO totocalcio_partite (competizione_id, partita_id, ordine, attiva)
             VALUES (?, ?, ?, 1)"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iii', $competitionId, $partitaId, $ordine);
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

if (!function_exists('totocalcio_fetch_prediction_matrix')) {
    function totocalcio_fetch_prediction_matrix(mysqli $conn, int $competitionId): array
    {
        if ($competitionId <= 0 || !totocalcio_ensure_tables($conn)) {
            return [];
        }

        $stmt = $conn->prepare(
            "SELECT
                pr.utente_id,
                pr.partita_id,
                pr.segno,
                pr.gol_casa_previsti,
                pr.gol_trasferta_previsti,
                pr.creato_il,
                pr.aggiornato_il
             FROM totocalcio_pronostici pr
             INNER JOIN totocalcio_partite tp
                ON tp.id = pr.partita_id
             INNER JOIN totocalcio_competizioni tc
                ON tc.id = tp.competizione_id
             WHERE tp.competizione_id = ?
               AND tp.attiva = 1
               AND tc.attiva = 1"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $competitionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $matrix = [];

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $userId = (int)($row['utente_id'] ?? 0);
                $matchId = (int)($row['partita_id'] ?? 0);
                if ($userId <= 0 || $matchId <= 0) {
                    continue;
                }

                $matrix[$userId][$matchId] = [
                    'segno' => (string)($row['segno'] ?? ''),
                    'gol_casa_previsti' => isset($row['gol_casa_previsti']) ? (int)$row['gol_casa_previsti'] : null,
                    'gol_trasferta_previsti' => isset($row['gol_trasferta_previsti']) ? (int)$row['gol_trasferta_previsti'] : null,
                    'creato_il' => $row['creato_il'] ?? null,
                    'aggiornato_il' => $row['aggiornato_il'] ?? null,
                ];
            }
            $result->close();
        }

        $stmt->close();

        return $matrix;
    }
}

if (!function_exists('totocalcio_fetch_leaderboard')) {
    function totocalcio_fetch_leaderboard(mysqli $conn, int $competitionId = 0): array
    {
        if (!totocalcio_ensure_tables($conn)) {
            return [];
        }

        $grantedUserIds = $competitionId > 0
            ? totocalcio_fetch_competition_granted_user_ids($conn, $competitionId)
            : [];

        $competitionFilterToken = '__TOTOCALCIO_COMP_FILTER__';
        $competitionFilterSql = $competitionId > 0
            ? ' AND tp.competizione_id = ?'
            : '';

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
                                 AND tc.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                 AND (
                                    (p.gol_casa > p.gol_ospite AND pr.segno = '1')
                                    OR (p.gol_casa = p.gol_ospite AND pr.segno = 'X')
                                    OR (p.gol_casa < p.gol_ospite AND pr.segno = '2')
                                 ){$competitionFilterToken}
                                THEN 1
                                ELSE 0
                            END
                        ) AS punti_esito,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND tc.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                 AND pr.gol_casa_previsti = p.gol_casa
                                 AND pr.gol_trasferta_previsti = p.gol_ospite{$competitionFilterToken}
                                THEN 3
                                ELSE 0
                            END
                        ) AS punti_risultato,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND tc.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                 AND (
                                    (p.gol_casa > p.gol_ospite AND pr.segno = '1')
                                    OR (p.gol_casa = p.gol_ospite AND pr.segno = 'X')
                                    OR (p.gol_casa < p.gol_ospite AND pr.segno = '2')
                                 ){$competitionFilterToken}
                                THEN 1
                                ELSE 0
                            END
                        ) AS esiti_corretti,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND tc.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL
                                 AND pr.gol_casa_previsti = p.gol_casa
                                 AND pr.gol_trasferta_previsti = p.gol_ospite{$competitionFilterToken}
                                THEN 1
                                ELSE 0
                            END
                        ) AS risultati_esatti,
                        SUM(
                            CASE
                                WHEN tp.attiva = 1
                                 AND tc.attiva = 1
                                 AND p.giocata = 1
                                 AND p.gol_casa IS NOT NULL
                                 AND p.gol_ospite IS NOT NULL{$competitionFilterToken}
                                THEN 1
                                ELSE 0
                            END
                        ) AS pronostici_valutati
                    FROM totocalcio_pronostici pr
                    INNER JOIN totocalcio_partite tp
                        ON tp.id = pr.partita_id
                    INNER JOIN totocalcio_competizioni tc
                        ON tc.id = tp.competizione_id
                    INNER JOIN partite p
                        ON p.id = tp.partita_id
                    GROUP BY pr.utente_id
                ) score ON score.utente_id = u.id";

        $result = null;
        $stmt = null;

        $sql = str_replace($competitionFilterToken, $competitionFilterSql, $sql);

        if ($competitionId > 0) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('iiiii', $competitionId, $competitionId, $competitionId, $competitionId, $competitionId);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

        if (!($result instanceof mysqli_result)) {
            if ($stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $flags = normalize_user_feature_flags($row['feature_flags'] ?? null);
            $userId = (int)($row['id'] ?? 0);
            $hasExplicitAccess = $competitionId > 0 && in_array($userId, $grantedUserIds, true);
            if (empty($flags['totocalcio']) && !$hasExplicitAccess) {
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

        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

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
