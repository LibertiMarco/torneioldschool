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
