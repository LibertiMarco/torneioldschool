<?php

if (!function_exists('partite_schema_extract_enum_values')) {
    function partite_schema_extract_enum_values(?string $type): array
    {
        $type = trim((string)$type);
        if ($type === '' || stripos($type, 'enum(') !== 0) {
            return [];
        }

        if (!preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches)) {
            return [];
        }

        return array_map(
            static fn(string $value): string => str_replace("\\'", "'", $value),
            $matches[1]
        );
    }
}

if (!function_exists('partite_schema_merge_enum_values')) {
    function partite_schema_merge_enum_values(array $currentValues, array $requiredValues): array
    {
        $merged = [];
        $seen = [];

        foreach ([$currentValues, $requiredValues] as $values) {
            foreach ($values as $value) {
                $rawValue = trim((string)$value);
                $normalized = strtoupper($rawValue);
                if ($normalized === '' || isset($seen[$normalized])) {
                    continue;
                }

                $merged[] = $rawValue;
                $seen[$normalized] = true;
            }
        }

        return $merged;
    }
}

if (!function_exists('ensure_partite_phase_schema')) {
    function ensure_partite_phase_schema(mysqli $conn): void
    {
        static $done = false;

        if ($done) {
            return;
        }
        $done = true;

        $columns = [
            'fase' => [
                'required' => ['REGULAR', 'SPAREGGIO', 'GOLD', 'SILVER', 'BRONZO'],
                'nullable' => false,
                'default' => 'REGULAR',
            ],
            'fase_round' => [
                'required' => ['TRENTADUESIMI', 'SEDICESIMI', 'OTTAVI', 'QUARTI', 'SEMIFINALE', 'FINALE'],
                'nullable' => true,
                'default' => null,
            ],
        ];

        foreach ($columns as $column => $config) {
            $columnName = $conn->real_escape_string($column);
            $res = $conn->query("SHOW COLUMNS FROM partite WHERE Field = '{$columnName}'");
            if (!$res instanceof mysqli_result || $res->num_rows === 0) {
                if ($res instanceof mysqli_result) {
                    $res->free();
                }
                continue;
            }

            $row = $res->fetch_assoc();
            $res->free();

            $type = (string)($row['Type'] ?? '');
            $currentValues = partite_schema_extract_enum_values($type);
            $mergedValues = partite_schema_merge_enum_values($currentValues, $config['required']);
            $hasEnumType = stripos($type, 'enum(') === 0;

            if ($hasEnumType && $mergedValues === $currentValues) {
                continue;
            }

            $enumValuesSql = implode(
                ',',
                array_map(
                    static fn(string $value): string => "'" . $conn->real_escape_string($value) . "'",
                    $mergedValues
                )
            );
            $nullSql = $config['nullable'] ? 'NULL' : 'NOT NULL';
            $defaultSql = $config['default'] === null
                ? ' DEFAULT NULL'
                : " DEFAULT '" . $conn->real_escape_string((string)$config['default']) . "'";
            $sql = "ALTER TABLE partite MODIFY COLUMN `{$column}` ENUM({$enumValuesSql}) {$nullSql}{$defaultSql}";

            if ($conn->query($sql) !== true) {
                error_log("Allineamento schema partite fallito per {$column}: " . $conn->error);
            }
        }
    }
}

if (!function_exists('ensure_partita_giocatore_team_schema')) {
    function ensure_partita_giocatore_team_schema(mysqli $conn): void
    {
        static $done = false;

        if ($done) {
            return;
        }
        $done = true;

        $columnRes = $conn->query("SHOW COLUMNS FROM partita_giocatore LIKE 'squadra_id'");
        $hasColumn = $columnRes instanceof mysqli_result && $columnRes->num_rows > 0;
        if ($columnRes instanceof mysqli_result) {
            $columnRes->free();
        }

        if (!$hasColumn) {
            if ($conn->query("ALTER TABLE partita_giocatore ADD COLUMN squadra_id INT UNSIGNED NULL AFTER giocatore_id") !== true) {
                error_log('Allineamento schema partita_giocatore fallito (colonna squadra_id): ' . $conn->error);
                return;
            }
        }

        $conn->query("
            UPDATE partita_giocatore pg
            JOIN (
                SELECT
                    pg2.id,
                    COALESCE(
                        MIN(CASE WHEN s.nome = p.squadra_casa THEN s.id END),
                        MIN(CASE WHEN s.nome = p.squadra_ospite THEN s.id END)
                    ) AS squadra_id
                FROM partita_giocatore pg2
                JOIN partite p ON p.id = pg2.partita_id
                LEFT JOIN squadre_giocatori sg ON sg.giocatore_id = pg2.giocatore_id
                LEFT JOIN squadre s
                  ON s.id = sg.squadra_id
                 AND s.torneo = p.torneo
                 AND s.nome IN (p.squadra_casa, p.squadra_ospite)
                GROUP BY pg2.id
            ) resolved ON resolved.id = pg.id
            SET pg.squadra_id = resolved.squadra_id
            WHERE pg.squadra_id IS NULL
        ");

        $indexRes = $conn->query("SHOW INDEX FROM partita_giocatore WHERE Key_name = 'idx_pg_squadra'");
        $hasIndex = $indexRes instanceof mysqli_result && $indexRes->num_rows > 0;
        if ($indexRes instanceof mysqli_result) {
            $indexRes->free();
        }
        if (!$hasIndex) {
            $conn->query("ALTER TABLE partita_giocatore ADD KEY idx_pg_squadra (squadra_id)");
        }
    }
}

if (!function_exists('partita_giocatore_resolved_team_expr')) {
    function partita_giocatore_resolved_team_expr(
        string $playerIdColumn = 'pg.giocatore_id',
        string $teamIdColumn = 'pg.squadra_id',
        string $tournamentColumn = 'p.torneo',
        string $homeTeamColumn = 'p.squadra_casa',
        string $awayTeamColumn = 'p.squadra_ospite'
    ): string {
        $playerIdColumn = trim($playerIdColumn) !== '' ? trim($playerIdColumn) : 'pg.giocatore_id';
        $teamIdColumn = trim($teamIdColumn) !== '' ? trim($teamIdColumn) : 'pg.squadra_id';
        $tournamentColumn = trim($tournamentColumn) !== '' ? trim($tournamentColumn) : 'p.torneo';
        $homeTeamColumn = trim($homeTeamColumn) !== '' ? trim($homeTeamColumn) : 'p.squadra_casa';
        $awayTeamColumn = trim($awayTeamColumn) !== '' ? trim($awayTeamColumn) : 'p.squadra_ospite';

        return "COALESCE(
            {$teamIdColumn},
            (
                SELECT MIN(s2.id)
                FROM squadre_giocatori sg2
                JOIN squadre s2 ON s2.id = sg2.squadra_id
                WHERE sg2.giocatore_id = {$playerIdColumn}
                  AND s2.torneo = {$tournamentColumn}
                  AND s2.nome IN ({$homeTeamColumn}, {$awayTeamColumn})
            )
        )";
    }
}
