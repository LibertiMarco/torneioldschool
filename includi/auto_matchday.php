<?php

if (!function_exists('auto_matchday_has_column')) {
    function auto_matchday_has_column(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $cacheKey = strtolower($table . '.' . $column);

        if ($table === '' || $column === '') {
            return false;
        }

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            $res = $conn->query(sprintf(
                "SHOW COLUMNS FROM `%s` LIKE '%s'",
                $table,
                $conn->real_escape_string($column)
            ));
        } catch (Throwable $e) {
            $cache[$cacheKey] = false;
            return false;
        }

        $cache[$cacheKey] = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }

        return $cache[$cacheKey];
    }
}

if (!function_exists('auto_matchday_normalize_phase_expr')) {
    function auto_matchday_normalize_phase_expr(string $column = 'p.fase'): string
    {
        $column = trim($column) !== '' ? trim($column) : 'p.fase';
        return "UPPER(CASE WHEN TRIM(COALESCE({$column}, '')) IN ('', 'GIRONE') THEN 'REGULAR' ELSE TRIM(COALESCE({$column}, '')) END)";
    }
}

if (!function_exists('auto_matchday_slug_from_value')) {
    function auto_matchday_slug_from_value(?string $value): string
    {
        return preg_replace('/\.(html?|php)$/i', '', trim((string)$value));
    }
}

if (!function_exists('auto_matchday_json_error')) {
    function auto_matchday_json_error(string $message, int $status = 400, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'status' => $status,
            'message' => $message,
        ], $extra);
    }
}

if (!function_exists('auto_matchday_fetch_tournaments')) {
    function auto_matchday_fetch_tournaments(mysqli $conn): array
    {
        $hasSection = auto_matchday_has_column($conn, 'tornei', 'sezione');
        $hasConfig = auto_matchday_has_column($conn, 'tornei', 'config');

        $columns = ['id', 'nome', 'filetorneo', 'stato'];
        if ($hasSection) {
            $columns[] = 'sezione';
        }
        if ($hasConfig) {
            $columns[] = 'config';
        }

        $sql = "SELECT " . implode(', ', $columns) . " FROM tornei WHERE stato <> 'terminato' ORDER BY nome ASC";
        $res = $conn->query($sql);
        if (!$res) {
            return [];
        }

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $slug = auto_matchday_slug_from_value($row['filetorneo'] ?? $row['nome'] ?? '');
            if ($slug === '') {
                continue;
            }

            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'nome' => (string)($row['nome'] ?? $slug),
                'slug' => $slug,
                'stato' => (string)($row['stato'] ?? ''),
                'sezione' => $hasSection && strtolower(trim((string)($row['sezione'] ?? ''))) === 'esport' ? 'esport' : 'calcio',
                'config' => $hasConfig && !empty($row['config']) ? json_decode((string)$row['config'], true) : null,
            ];
        }

        return $items;
    }
}

if (!function_exists('auto_matchday_fetch_tournament_by_id')) {
    function auto_matchday_fetch_tournament_by_id(mysqli $conn, int $tournamentId): ?array
    {
        if ($tournamentId <= 0) {
            return null;
        }

        $hasSection = auto_matchday_has_column($conn, 'tornei', 'sezione');
        $hasConfig = auto_matchday_has_column($conn, 'tornei', 'config');

        $columns = ['id', 'nome', 'filetorneo', 'stato'];
        if ($hasSection) {
            $columns[] = 'sezione';
        }
        if ($hasConfig) {
            $columns[] = 'config';
        }

        $stmt = $conn->prepare("SELECT " . implode(', ', $columns) . " FROM tornei WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        $slug = auto_matchday_slug_from_value($row['filetorneo'] ?? $row['nome'] ?? '');
        if ($slug === '') {
            return null;
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'nome' => (string)($row['nome'] ?? $slug),
            'slug' => $slug,
            'stato' => (string)($row['stato'] ?? ''),
            'sezione' => $hasSection && strtolower(trim((string)($row['sezione'] ?? ''))) === 'esport' ? 'esport' : 'calcio',
            'config' => $hasConfig && !empty($row['config']) ? json_decode((string)$row['config'], true) : null,
        ];
    }
}

if (!function_exists('auto_matchday_fetch_fields')) {
    function auto_matchday_fetch_fields(mysqli $conn): array
    {
        $fields = [];
        $seen = [];

        if (auto_matchday_has_column($conn, 'campi_partite', 'nome')) {
            $orderBy = auto_matchday_has_column($conn, 'campi_partite', 'sort_order')
                ? 'sort_order ASC, nome ASC'
                : 'nome ASC';
            $res = $conn->query("SELECT nome FROM campi_partite ORDER BY {$orderBy}");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $name = trim((string)($row['nome'] ?? ''));
                    $key = strtolower($name);
                    if ($name === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $fields[] = $name;
                }
            }
        }

        $res = $conn->query("
            SELECT DISTINCT campo
            FROM partite
            WHERE campo IS NOT NULL AND TRIM(campo) <> ''
            ORDER BY campo ASC
        ");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)($row['campo'] ?? ''));
                $key = strtolower($name);
                if ($name === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $fields[] = $name;
            }
        }

        return $fields;
    }
}

if (!function_exists('auto_matchday_fetch_teams')) {
    function auto_matchday_fetch_teams(mysqli $conn, string $tournamentSlug): array
    {
        if ($tournamentSlug === '') {
            return [];
        }

        $hasGirone = auto_matchday_has_column($conn, 'squadre', 'girone');
        $columns = [
            'id',
            'nome',
            'torneo',
            'logo',
            'punti',
            'giocate',
            'vinte',
            'pareggiate',
            'perse',
            'gol_fatti',
            'gol_subiti',
            'differenza_reti',
        ];
        if ($hasGirone) {
            $columns[] = 'girone';
        }

        $stmt = $conn->prepare("
            SELECT " . implode(', ', $columns) . "
            FROM squadre
            WHERE torneo = ?
            ORDER BY punti DESC, differenza_reti DESC, gol_fatti DESC, gol_subiti ASC, nome ASC
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $tournamentSlug);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $teams = [];
        $position = 1;
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $teams[] = [
                'id' => (int)($row['id'] ?? 0),
                'nome' => (string)($row['nome'] ?? ''),
                'torneo' => (string)($row['torneo'] ?? ''),
                'logo' => (string)($row['logo'] ?? ''),
                'punti' => (int)($row['punti'] ?? 0),
                'giocate' => (int)($row['giocate'] ?? 0),
                'vinte' => (int)($row['vinte'] ?? 0),
                'pareggiate' => (int)($row['pareggiate'] ?? 0),
                'perse' => (int)($row['perse'] ?? 0),
                'gol_fatti' => (int)($row['gol_fatti'] ?? 0),
                'gol_subiti' => (int)($row['gol_subiti'] ?? 0),
                'differenza_reti' => (int)($row['differenza_reti'] ?? 0),
                'girone' => $hasGirone ? (string)($row['girone'] ?? '') : '',
                'posizione' => $position,
            ];
            $position++;
        }

        $stmt->close();
        return $teams;
    }
}

if (!function_exists('auto_matchday_fetch_regular_matches')) {
    function auto_matchday_fetch_regular_matches(mysqli $conn, string $tournamentSlug): array
    {
        if ($tournamentSlug === '') {
            return [];
        }

        $phaseExpr = auto_matchday_normalize_phase_expr('p.fase');
        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.torneo,
                p.fase,
                p.fase_round,
                p.fase_leg,
                p.squadra_casa,
                p.squadra_ospite,
                p.gol_casa,
                p.gol_ospite,
                p.data_partita,
                p.ora_partita,
                p.campo,
                p.giornata,
                p.giocata
            FROM partite p
            WHERE p.torneo = ?
              AND {$phaseExpr} = 'REGULAR'
            ORDER BY COALESCE(p.giornata, 0) ASC, p.data_partita ASC, p.ora_partita ASC, p.id ASC
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $tournamentSlug);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'torneo' => (string)($row['torneo'] ?? ''),
                'fase' => (string)($row['fase'] ?? 'REGULAR'),
                'fase_round' => (string)($row['fase_round'] ?? ''),
                'fase_leg' => (string)($row['fase_leg'] ?? ''),
                'squadra_casa' => (string)($row['squadra_casa'] ?? ''),
                'squadra_ospite' => (string)($row['squadra_ospite'] ?? ''),
                'gol_casa' => isset($row['gol_casa']) ? (int)$row['gol_casa'] : null,
                'gol_ospite' => isset($row['gol_ospite']) ? (int)$row['gol_ospite'] : null,
                'data_partita' => (string)($row['data_partita'] ?? ''),
                'ora_partita' => (string)($row['ora_partita'] ?? ''),
                'campo' => (string)($row['campo'] ?? ''),
                'giornata' => $row['giornata'] !== null ? (int)$row['giornata'] : null,
                'giocata' => (int)($row['giocata'] ?? 0),
            ];
        }

        $stmt->close();
        return $rows;
    }
}

if (!function_exists('auto_matchday_fetch_global_occupied_slots')) {
    function auto_matchday_fetch_global_occupied_slots(mysqli $conn): array
    {
        $slots = [];
        $res = $conn->query("
            SELECT id, torneo, squadra_casa, squadra_ospite, data_partita, ora_partita, campo
            FROM partite
            WHERE data_partita IS NOT NULL
              AND ora_partita IS NOT NULL
              AND campo IS NOT NULL
              AND TRIM(campo) <> ''
        ");
        if (!$res) {
            return $slots;
        }

        while ($row = $res->fetch_assoc()) {
            $date = trim((string)($row['data_partita'] ?? ''));
            $time = auto_matchday_normalize_time((string)($row['ora_partita'] ?? ''));
            $field = trim((string)($row['campo'] ?? ''));
            $key = auto_matchday_slot_key($date, $time, $field);
            if ($key === '') {
                continue;
            }

            $entry = [
                'id' => (int)($row['id'] ?? 0),
                'torneo' => (string)($row['torneo'] ?? ''),
                'squadra_casa' => (string)($row['squadra_casa'] ?? ''),
                'squadra_ospite' => (string)($row['squadra_ospite'] ?? ''),
                'data' => $date,
                'ora' => $time,
                'campo' => $field,
            ];

            if (!isset($slots[$key])) {
                $slots[$key] = [
                    'count' => 0,
                    'data' => $date,
                    'ora' => $time,
                    'campo' => $field,
                    'squadra_casa' => (string)($row['squadra_casa'] ?? ''),
                    'squadra_ospite' => (string)($row['squadra_ospite'] ?? ''),
                    'entries' => [],
                ];
            }

            $slots[$key]['count']++;
            $slots[$key]['entries'][] = $entry;
        }

        return $slots;
    }
}

if (!function_exists('auto_matchday_fetch_context')) {
    function auto_matchday_fetch_context(mysqli $conn, int $tournamentId): array
    {
        $tournament = auto_matchday_fetch_tournament_by_id($conn, $tournamentId);
        if (!$tournament) {
            return auto_matchday_json_error('Torneo non trovato.', 404);
        }

        $teams = auto_matchday_fetch_teams($conn, $tournament['slug']);
        $matches = auto_matchday_fetch_regular_matches($conn, $tournament['slug']);
        $fields = auto_matchday_fetch_fields($conn);

        $giornate = [];
        $maxGiornata = 0;
        foreach ($matches as $match) {
            if ($match['giornata'] === null) {
                continue;
            }
            $giornate[(string)$match['giornata']] = true;
            $maxGiornata = max($maxGiornata, (int)$match['giornata']);
        }

        $giornateList = array_map('intval', array_keys($giornate));
        sort($giornateList, SORT_NUMERIC);

        return [
            'success' => true,
            'data' => [
                'torneo' => $tournament,
                'squadre' => $teams,
                'classifica' => $teams,
                'partite_regular' => $matches,
                'giornate_regular' => $giornateList,
                'prossima_giornata' => max(1, $maxGiornata + 1),
                'campi' => $fields,
            ],
        ];
    }
}

if (!function_exists('auto_matchday_int_list')) {
    function auto_matchday_int_list($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            $id = (int)$item;
            if ($id > 0) {
                $result[$id] = $id;
            }
        }

        return array_values($result);
    }
}

if (!function_exists('auto_matchday_normalize_date')) {
    function auto_matchday_normalize_date(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $dt = date_create($value);
        return $dt ? $dt->format('Y-m-d') : '';
    }
}

if (!function_exists('auto_matchday_normalize_time')) {
    function auto_matchday_normalize_time(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        $dt = date_create($value);
        return $dt ? $dt->format('H:i:s') : '';
    }
}

if (!function_exists('auto_matchday_slot_key')) {
    function auto_matchday_slot_key(string $date, string $time, string $field): string
    {
        $date = auto_matchday_normalize_date($date);
        $time = auto_matchday_normalize_time($time);
        $field = trim($field);
        if ($date === '' || $time === '' || $field === '') {
            return '';
        }

        return strtolower($date . '|' . $time . '|' . preg_replace('/\s+/', ' ', $field));
    }
}

if (!function_exists('auto_matchday_split_slot_fields')) {
    function auto_matchday_split_slot_fields(string $value): array
    {
        $parts = preg_split('/[\r\n,;]+/', $value) ?: [];
        $result = [];
        $seen = [];

        foreach ($parts as $part) {
            $field = preg_replace('/\s+/', ' ', trim((string)$part));
            if ($field === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($field, 'UTF-8') : strtolower($field);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $field;
        }

        return $result;
    }
}

if (!function_exists('auto_matchday_extract_slot_fields')) {
    function auto_matchday_extract_slot_fields(array $row): array
    {
        $result = [];
        $seen = [];
        $sources = [];

        if (array_key_exists('campi', $row)) {
            $sources[] = $row['campi'];
        }
        if (array_key_exists('campo', $row)) {
            $sources[] = $row['campo'];
        }

        foreach ($sources as $source) {
            $fields = is_array($source) ? $source : auto_matchday_split_slot_fields((string)$source);
            foreach ($fields as $fieldValue) {
                $field = preg_replace('/\s+/', ' ', trim((string)$fieldValue));
                if ($field === '') {
                    continue;
                }

                $key = function_exists('mb_strtolower') ? mb_strtolower($field, 'UTF-8') : strtolower($field);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $result[] = $field;
            }
        }

        return $result;
    }
}

if (!function_exists('auto_matchday_normalize_slots')) {
    function auto_matchday_normalize_slots($value): array
    {
        $rows = is_array($value) ? $value : [];
        $result = [];
        $duplicateCount = 0;
        $slotCounters = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = auto_matchday_normalize_date($row['data'] ?? '');
            $time = auto_matchday_normalize_time($row['ora'] ?? '');
            $fields = auto_matchday_extract_slot_fields($row);
            $quantity = max(1, (int)($row['quantita'] ?? $row['quantity'] ?? 1));

            if (empty($fields) && trim((string)($row['campo'] ?? '')) !== '') {
                $fields = [trim((string)$row['campo'])];
            }

            foreach ($fields as $fieldIndex => $field) {
                $publicKey = auto_matchday_slot_key($date, $time, $field);

                if ($publicKey === '') {
                    continue;
                }

                for ($copyIndex = 0; $copyIndex < $quantity; $copyIndex++) {
                    $slotCounters[$publicKey] = ($slotCounters[$publicKey] ?? 0) + 1;
                    $instanceNumber = $slotCounters[$publicKey];

                    $result[] = [
                        'id' => 'slot_' . ($index + 1) . '_' . ($fieldIndex + 1) . '_' . ($copyIndex + 1),
                        'data' => $date,
                        'ora' => $time,
                        'campo' => $field,
                        'key' => $publicKey . '#' . $instanceNumber,
                        'public_key' => $publicKey,
                        'slot_number' => $instanceNumber,
                        'sort_index' => count($result),
                    ];
                }
            }
        }

        return [
            'slots' => $result,
            'duplicate_count' => $duplicateCount,
        ];
    }
}

if (!function_exists('auto_matchday_build_slot_capacity_map')) {
    function auto_matchday_build_slot_capacity_map(array $slots): array
    {
        $result = [];

        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $publicKey = (string)($slot['public_key'] ?? '');
            if ($publicKey === '') {
                $publicKey = auto_matchday_slot_key(
                    (string)($slot['data'] ?? ''),
                    (string)($slot['ora'] ?? ''),
                    (string)($slot['campo'] ?? '')
                );
            }
            if ($publicKey === '') {
                continue;
            }

            $result[$publicKey] = ($result[$publicKey] ?? 0) + 1;
        }

        return $result;
    }
}

if (!function_exists('auto_matchday_normalize_availability_rules')) {
    function auto_matchday_normalize_availability_rules($value): array
    {
        $result = [];
        if (!is_array($value)) {
            return $result;
        }

        foreach ($value as $teamId => $rules) {
            $teamId = (int)$teamId;
            if ($teamId <= 0 || !is_array($rules)) {
                continue;
            }

            foreach ($rules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $dateSource = [];
                if (isset($rule['dates']) && is_array($rule['dates'])) {
                    $dateSource = $rule['dates'];
                }

                $dates = [];
                foreach ($dateSource as $dateValue) {
                    $date = auto_matchday_normalize_date((string)$dateValue);
                    if ($date !== '' && !in_array($date, $dates, true)) {
                        $dates[] = $date;
                    }
                }

                $timeSource = [];
                if (isset($rule['times']) && is_array($rule['times'])) {
                    $timeSource = $rule['times'];
                }

                $times = [];
                foreach ($timeSource as $timeValue) {
                    $time = auto_matchday_normalize_time((string)$timeValue);
                    if ($time !== '' && !in_array($time, $times, true)) {
                        $times[] = $time;
                    }
                }

                $weekdaySource = [];
                if (isset($rule['weekdays']) && is_array($rule['weekdays'])) {
                    $weekdaySource = $rule['weekdays'];
                } elseif (isset($rule['weekday'])) {
                    $weekdaySource = [$rule['weekday']];
                }

                $weekdays = [];
                foreach ($weekdaySource as $weekdayValue) {
                    $weekday = (int)$weekdayValue;
                    if ($weekday >= 1 && $weekday <= 7 && !in_array($weekday, $weekdays, true)) {
                        $weekdays[] = $weekday;
                    }
                }

                $startTime = auto_matchday_normalize_time($rule['start_time'] ?? '');
                $endTime = auto_matchday_normalize_time($rule['end_time'] ?? '');

                if ($startTime !== '' && $endTime === '') {
                    $endTime = $startTime;
                }

                if (empty($dates) && empty($times) && empty($weekdays) && $startTime === '' && $endTime === '') {
                    continue;
                }

                $result[$teamId][] = [
                    'dates' => $dates,
                    'times' => $times,
                    'weekday' => $weekdays[0] ?? 0,
                    'weekdays' => $weekdays,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ];
            }
        }

        return $result;
    }
}

if (!function_exists('auto_matchday_time_to_minutes')) {
    function auto_matchday_time_to_minutes(string $time): ?int
    {
        $time = auto_matchday_normalize_time($time);
        if ($time === '') {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));
        return ($hours * 60) + $minutes;
    }
}

if (!function_exists('auto_matchday_slot_matches_team_rules')) {
    function auto_matchday_slot_matches_team_rules(array $slot, array $rules): array
    {
        if (empty($rules)) {
            return [
                'has_rules' => false,
                'matched' => true,
            ];
        }

        $slotDate = auto_matchday_normalize_date($slot['data'] ?? '');
        $slotTime = auto_matchday_normalize_time((string)($slot['ora'] ?? ''));
        $slotTimeMinutes = auto_matchday_time_to_minutes((string)($slot['ora'] ?? ''));
        if ($slotDate === '' || $slotTime === '' || $slotTimeMinutes === null) {
            return [
                'has_rules' => true,
                'matched' => false,
            ];
        }

        $slotWeekday = (int)date('N', strtotime($slotDate));

        foreach ($rules as $rule) {
            $dates = [];
            if (isset($rule['dates']) && is_array($rule['dates'])) {
                foreach ($rule['dates'] as $dateValue) {
                    $date = auto_matchday_normalize_date((string)$dateValue);
                    if ($date !== '' && !in_array($date, $dates, true)) {
                        $dates[] = $date;
                    }
                }
            }

            $times = [];
            if (isset($rule['times']) && is_array($rule['times'])) {
                foreach ($rule['times'] as $timeValue) {
                    $time = auto_matchday_normalize_time((string)$timeValue);
                    if ($time !== '' && !in_array($time, $times, true)) {
                        $times[] = $time;
                    }
                }
            }

            $weekdays = [];
            if (isset($rule['weekdays']) && is_array($rule['weekdays'])) {
                foreach ($rule['weekdays'] as $weekdayValue) {
                    $weekday = (int)$weekdayValue;
                    if ($weekday >= 1 && $weekday <= 7 && !in_array($weekday, $weekdays, true)) {
                        $weekdays[] = $weekday;
                    }
                }
            } else {
                $weekday = (int)($rule['weekday'] ?? 0);
                if ($weekday >= 1 && $weekday <= 7) {
                    $weekdays[] = $weekday;
                }
            }
            $startMinutes = auto_matchday_time_to_minutes((string)($rule['start_time'] ?? ''));
            $endMinutes = auto_matchday_time_to_minutes((string)($rule['end_time'] ?? ''));

            if (!empty($dates) && !in_array($slotDate, $dates, true)) {
                continue;
            }

            if (empty($dates) && !empty($weekdays) && !in_array($slotWeekday, $weekdays, true)) {
                continue;
            }

            if (!empty($times) && !in_array($slotTime, $times, true)) {
                continue;
            }

            if ($startMinutes !== null && $slotTimeMinutes < $startMinutes) {
                continue;
            }

            if ($endMinutes !== null && $slotTimeMinutes > $endMinutes) {
                continue;
            }

            return [
                'has_rules' => true,
                'matched' => true,
            ];
        }

        return [
            'has_rules' => true,
            'matched' => false,
        ];
    }
}

if (!function_exists('auto_matchday_build_team_maps')) {
    function auto_matchday_build_team_maps(array $teams): array
    {
        $byId = [];
        $byName = [];
        foreach ($teams as $team) {
            $id = (int)($team['id'] ?? 0);
            $name = trim((string)($team['nome'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $byId[$id] = $team;
            $byName[strtolower($name)] = $team;
        }

        return [
            'by_id' => $byId,
            'by_name' => $byName,
        ];
    }
}

if (!function_exists('auto_matchday_build_regular_history')) {
    function auto_matchday_build_regular_history(array $teams, array $matches): array
    {
        $maps = auto_matchday_build_team_maps($teams);
        $history = [];
        $pairCounts = [];
        $pairHistory = [];
        $teamRounds = [];

        foreach ($teams as $team) {
            $id = (int)($team['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $history[$id] = [
                'home_count' => 0,
                'away_count' => 0,
                'matches_count' => 0,
                'recent_venues' => [],
                'bye_count' => 0,
                'position' => (int)($team['posizione'] ?? 999),
                'points' => (int)($team['punti'] ?? 0),
            ];
        }

        foreach ($matches as $match) {
            $homeName = strtolower(trim((string)($match['squadra_casa'] ?? '')));
            $awayName = strtolower(trim((string)($match['squadra_ospite'] ?? '')));
            if (!isset($maps['by_name'][$homeName], $maps['by_name'][$awayName])) {
                continue;
            }

            $homeId = (int)$maps['by_name'][$homeName]['id'];
            $awayId = (int)$maps['by_name'][$awayName]['id'];
            $giornata = $match['giornata'] !== null ? (int)$match['giornata'] : null;

            $history[$homeId]['home_count']++;
            $history[$homeId]['matches_count']++;
            $history[$homeId]['recent_venues'][] = 'H';

            $history[$awayId]['away_count']++;
            $history[$awayId]['matches_count']++;
            $history[$awayId]['recent_venues'][] = 'A';

            if ($giornata !== null) {
                $teamRounds[$homeId][$giornata] = true;
                $teamRounds[$awayId][$giornata] = true;
            }

            $pairKey = auto_matchday_pair_key($homeId, $awayId);
            $pairCounts[$pairKey] = ($pairCounts[$pairKey] ?? 0) + 1;
            $pairHistory[$pairKey][] = [
                'home_id' => $homeId,
                'away_id' => $awayId,
            ];
        }

        $allRounds = [];
        foreach ($teamRounds as $rounds) {
            foreach (array_keys($rounds) as $round) {
                $allRounds[(int)$round] = true;
            }
        }
        $allRoundNumbers = array_keys($allRounds);

        foreach ($history as $teamId => &$teamHistory) {
            if (count($teamHistory['recent_venues']) > 3) {
                $teamHistory['recent_venues'] = array_slice($teamHistory['recent_venues'], -3);
            }

            $byeCount = 0;
            foreach ($allRoundNumbers as $round) {
                if (empty($teamRounds[$teamId][$round])) {
                    $byeCount++;
                }
            }
            $teamHistory['bye_count'] = $byeCount;
        }
        unset($teamHistory);

        return [
            'team_history' => $history,
            'pair_counts' => $pairCounts,
            'pair_history' => $pairHistory,
            'rounds' => $allRoundNumbers,
        ];
    }
}

if (!function_exists('auto_matchday_pair_key')) {
    function auto_matchday_pair_key(int $teamA, int $teamB): string
    {
        if ($teamA <= 0 || $teamB <= 0) {
            return '';
        }
        $first = min($teamA, $teamB);
        $second = max($teamA, $teamB);
        return $first . ':' . $second;
    }
}

if (!function_exists('auto_matchday_team_unpaired_penalty')) {
    function auto_matchday_team_unpaired_penalty(array $team, array $history, bool $expectedBye): int
    {
        $teamId = (int)($team['id'] ?? 0);
        $byeCount = (int)($history[$teamId]['bye_count'] ?? 0);
        $matchesCount = (int)($history[$teamId]['matches_count'] ?? 0);
        $position = (int)($team['posizione'] ?? 999);

        $base = $expectedBye ? 150 : 1000;
        return $base + ($byeCount * 60) - ($matchesCount * 4) + max(0, 40 - $position);
    }
}

if (!function_exists('auto_matchday_orientation_penalty')) {
    function auto_matchday_orientation_penalty(array $team, array $history, string $desiredVenue): int
    {
        $teamId = (int)($team['id'] ?? 0);
        $teamHistory = $history[$teamId] ?? [
            'home_count' => 0,
            'away_count' => 0,
            'recent_venues' => [],
        ];
        $homeCount = (int)($teamHistory['home_count'] ?? 0);
        $awayCount = (int)($teamHistory['away_count'] ?? 0);
        $recent = $teamHistory['recent_venues'] ?? [];

        $penalty = 0;
        if ($desiredVenue === 'H') {
            $penalty += abs(($homeCount + 1) - $awayCount);
        } else {
            $penalty += abs($homeCount - ($awayCount + 1));
        }

        if (!empty($recent) && end($recent) === $desiredVenue) {
            $penalty += 6;
        }

        if (count($recent) >= 2) {
            $lastTwo = array_slice($recent, -2);
            if (count(array_unique($lastTwo)) === 1 && $lastTwo[0] === $desiredVenue) {
                $penalty += 10;
            }
        }

        return $penalty;
    }
}

if (!function_exists('auto_matchday_choose_orientation')) {
    function auto_matchday_choose_orientation(
        array $teamA,
        array $teamB,
        array $history,
        array $pairHistory,
        bool $isReturnAllowed
    ): array {
        $pairKey = auto_matchday_pair_key((int)$teamA['id'], (int)$teamB['id']);
        $previousMatches = $pairHistory[$pairKey] ?? [];

        if ($isReturnAllowed && count($previousMatches) === 1) {
            $previous = $previousMatches[0];
            if ((int)$previous['home_id'] === (int)$teamA['id']) {
                return [
                    'home' => $teamB,
                    'away' => $teamA,
                    'penalty' => 0,
                ];
            }

            return [
                'home' => $teamA,
                'away' => $teamB,
                'penalty' => 0,
            ];
        }

        $optionOnePenalty =
            auto_matchday_orientation_penalty($teamA, $history, 'H') +
            auto_matchday_orientation_penalty($teamB, $history, 'A');
        $optionTwoPenalty =
            auto_matchday_orientation_penalty($teamB, $history, 'H') +
            auto_matchday_orientation_penalty($teamA, $history, 'A');

        if ($optionOnePenalty <= $optionTwoPenalty) {
            return [
                'home' => $teamA,
                'away' => $teamB,
                'penalty' => $optionOnePenalty,
            ];
        }

        return [
            'home' => $teamB,
            'away' => $teamA,
            'penalty' => $optionTwoPenalty,
        ];
    }
}

if (!function_exists('auto_matchday_candidate_pair')) {
    function auto_matchday_candidate_pair(
        array $teamA,
        array $teamB,
        array $history,
        array $pairCounts,
        array $pairHistory,
        bool $allowReturn
    ): ?array {
        $pairKey = auto_matchday_pair_key((int)$teamA['id'], (int)$teamB['id']);
        if ($pairKey === '') {
            return null;
        }

        $existingCount = (int)($pairCounts[$pairKey] ?? 0);
        $maxAllowed = $allowReturn ? 2 : 1;
        if ($existingCount >= $maxAllowed) {
            return null;
        }

        $rankGap = abs((int)($teamA['posizione'] ?? 999) - (int)($teamB['posizione'] ?? 999));
        $pointsGap = abs((int)($teamA['punti'] ?? 0) - (int)($teamB['punti'] ?? 0));
        $orientation = auto_matchday_choose_orientation($teamA, $teamB, $history, $pairHistory, $allowReturn && $existingCount === 1);

        $score = ($rankGap * 100) + ($pointsGap * 20) + (int)$orientation['penalty'];
        if ($existingCount === 1) {
            $score += 40;
        }

        return [
            'pair_key' => $pairKey,
            'team_a' => $teamA,
            'team_b' => $teamB,
            'home' => $orientation['home'],
            'away' => $orientation['away'],
            'existing_count' => $existingCount,
            'rank_gap' => $rankGap,
            'points_gap' => $pointsGap,
            'score' => $score,
        ];
    }
}

if (!function_exists('auto_matchday_sort_candidate_pairs')) {
    function auto_matchday_sort_candidate_pairs(array &$pairs): void
    {
        usort($pairs, static function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $left['score'] <=> $right['score'];
            }
            if ($left['rank_gap'] !== $right['rank_gap']) {
                return $left['rank_gap'] <=> $right['rank_gap'];
            }
            if ($left['points_gap'] !== $right['points_gap']) {
                return $left['points_gap'] <=> $right['points_gap'];
            }
            return strcmp((string)$left['pair_key'], (string)$right['pair_key']);
        });
    }
}

if (!function_exists('auto_matchday_find_best_pairing')) {
    function auto_matchday_find_best_pairing(
        array $remainingTeamIds,
        array $teamsById,
        array $history,
        array $pairCounts,
        array $pairHistory,
        bool $allowReturn,
        int &$visitedNodes,
        int $nodeLimit = 12000
    ): array {
        $remainingTeamIds = array_values(array_unique(array_map('intval', $remainingTeamIds)));
        sort($remainingTeamIds, SORT_NUMERIC);

        if (empty($remainingTeamIds)) {
            return [
                'pairs' => [],
                'unpaired' => [],
                'score' => 0,
            ];
        }

        if ($visitedNodes >= $nodeLimit) {
            return [
                'pairs' => [],
                'unpaired' => $remainingTeamIds,
                'score' => 50000 + (count($remainingTeamIds) * 1000),
            ];
        }

        $visitedNodes++;

        $pivotId = 0;
        $pivotCandidates = [];
        $pivotCount = null;

        foreach ($remainingTeamIds as $teamId) {
            $team = $teamsById[$teamId] ?? null;
            if (!$team) {
                continue;
            }

            $candidatePairs = [];
            foreach ($remainingTeamIds as $otherId) {
                if ($otherId === $teamId) {
                    continue;
                }
                $other = $teamsById[$otherId] ?? null;
                if (!$other) {
                    continue;
                }
                $candidate = auto_matchday_candidate_pair($team, $other, $history, $pairCounts, $pairHistory, $allowReturn);
                if ($candidate !== null) {
                    $candidatePairs[] = $candidate;
                }
            }

            auto_matchday_sort_candidate_pairs($candidatePairs);
            $candidateCount = count($candidatePairs);
            if ($pivotCount === null || $candidateCount < $pivotCount) {
                $pivotId = $teamId;
                $pivotCandidates = $candidatePairs;
                $pivotCount = $candidateCount;
            }
        }

        if ($pivotId <= 0 || !isset($teamsById[$pivotId])) {
            return [
                'pairs' => [],
                'unpaired' => $remainingTeamIds,
                'score' => 50000 + (count($remainingTeamIds) * 1000),
            ];
        }

        $pivotTeam = $teamsById[$pivotId];
        $expectedBye = count($remainingTeamIds) % 2 === 1;
        $best = null;

        foreach ($pivotCandidates as $candidate) {
            $nextRemaining = array_values(array_filter(
                $remainingTeamIds,
                static fn(int $id): bool => $id !== (int)$candidate['team_a']['id'] && $id !== (int)$candidate['team_b']['id']
            ));

            $branch = auto_matchday_find_best_pairing(
                $nextRemaining,
                $teamsById,
                $history,
                $pairCounts,
                $pairHistory,
                $allowReturn,
                $visitedNodes,
                $nodeLimit
            );

            $result = [
                'pairs' => array_merge([$candidate], $branch['pairs']),
                'unpaired' => $branch['unpaired'],
                'score' => $candidate['score'] + (int)$branch['score'],
            ];

            if ($best === null || auto_matchday_compare_pairing_results($result, $best) < 0) {
                $best = $result;
            }
        }

        $skipRemaining = array_values(array_filter(
            $remainingTeamIds,
            static fn(int $id): bool => $id !== $pivotId
        ));
        $skipBranch = auto_matchday_find_best_pairing(
            $skipRemaining,
            $teamsById,
            $history,
            $pairCounts,
            $pairHistory,
            $allowReturn,
            $visitedNodes,
            $nodeLimit
        );
        $skipResult = [
            'pairs' => $skipBranch['pairs'],
            'unpaired' => array_merge([$pivotId], $skipBranch['unpaired']),
            'score' => auto_matchday_team_unpaired_penalty($pivotTeam, $history, $expectedBye) + (int)$skipBranch['score'],
        ];

        if ($best === null || auto_matchday_compare_pairing_results($skipResult, $best) < 0) {
            $best = $skipResult;
        }

        return $best ?? [
            'pairs' => [],
            'unpaired' => $remainingTeamIds,
            'score' => 50000 + (count($remainingTeamIds) * 1000),
        ];
    }
}

if (!function_exists('auto_matchday_compare_pairing_results')) {
    function auto_matchday_compare_pairing_results(array $left, array $right): int
    {
        $leftUnpaired = count($left['unpaired'] ?? []);
        $rightUnpaired = count($right['unpaired'] ?? []);
        if ($leftUnpaired !== $rightUnpaired) {
            return $leftUnpaired <=> $rightUnpaired;
        }

        $leftScore = (int)($left['score'] ?? 0);
        $rightScore = (int)($right['score'] ?? 0);
        if ($leftScore !== $rightScore) {
            return $leftScore <=> $rightScore;
        }

        return 0;
    }
}

if (!function_exists('auto_matchday_pair_alternative_warning')) {
    function auto_matchday_pair_alternative_warning(
        array $homeTeam,
        array $awayTeam,
        array $selectedTeamsById,
        array $pairCounts,
        bool $allowReturn
    ): ?string {
        $homeId = (int)($homeTeam['id'] ?? 0);
        $awayId = (int)($awayTeam['id'] ?? 0);
        if ($homeId <= 0 || $awayId <= 0) {
            return null;
        }

        $chosenGap = abs((int)($homeTeam['posizione'] ?? 999) - (int)($awayTeam['posizione'] ?? 999));
        $chosenPointsGap = abs((int)($homeTeam['punti'] ?? 0) - (int)($awayTeam['punti'] ?? 0));
        $maxAllowed = $allowReturn ? 2 : 1;

        foreach ($selectedTeamsById as $teamId => $team) {
            $teamId = (int)$teamId;
            if ($teamId <= 0 || $teamId === $homeId || $teamId === $awayId) {
                continue;
            }

            $candidateGap = abs((int)($homeTeam['posizione'] ?? 999) - (int)($team['posizione'] ?? 999));
            $candidatePointsGap = abs((int)($homeTeam['punti'] ?? 0) - (int)($team['punti'] ?? 0));
            if ($candidateGap > $chosenGap || ($candidateGap === $chosenGap && $candidatePointsGap >= $chosenPointsGap)) {
                continue;
            }

            $pairKey = auto_matchday_pair_key($homeId, $teamId);
            $existingCount = (int)($pairCounts[$pairKey] ?? 0);
            if ($existingCount >= $maxAllowed) {
                return 'Abbinamento alternativo perché quello migliore era già presente';
            }
        }

        return null;
    }
}

if (!function_exists('auto_matchday_assign_slots')) {
    function auto_matchday_assign_slots(
        array $pairs,
        array $slots,
        array $availabilityRules,
        array $teamsById,
        array $pairCounts,
        bool $allowReturn
    ): array {
        $result = [];
        $usedSlotKeys = [];

        $pairsForAssignment = $pairs;
        foreach ($pairsForAssignment as &$pair) {
            $perfectCount = 0;
            foreach ($slots as $slot) {
                $homeRules = $availabilityRules[(int)$pair['home']['id']] ?? [];
                $awayRules = $availabilityRules[(int)$pair['away']['id']] ?? [];
                $homeAvailability = auto_matchday_slot_matches_team_rules($slot, $homeRules);
                $awayAvailability = auto_matchday_slot_matches_team_rules($slot, $awayRules);

                if ($homeAvailability['matched'] && $awayAvailability['matched']) {
                    $perfectCount++;
                }
            }
            $pair['perfect_slot_count'] = $perfectCount;
        }
        unset($pair);

        usort($pairsForAssignment, static function (array $left, array $right): int {
            if (($left['perfect_slot_count'] ?? 0) !== ($right['perfect_slot_count'] ?? 0)) {
                return ($left['perfect_slot_count'] ?? 0) <=> ($right['perfect_slot_count'] ?? 0);
            }
            return ($left['score'] ?? 0) <=> ($right['score'] ?? 0);
        });

        foreach ($pairsForAssignment as $pair) {
            $bestSlot = null;
            $bestScore = null;
            $bestWarnings = [];
            $homeRules = $availabilityRules[(int)$pair['home']['id']] ?? [];
            $awayRules = $availabilityRules[(int)$pair['away']['id']] ?? [];

            foreach ($slots as $slot) {
                if (isset($usedSlotKeys[$slot['key']])) {
                    continue;
                }

                $homeAvailability = auto_matchday_slot_matches_team_rules($slot, $homeRules);
                $awayAvailability = auto_matchday_slot_matches_team_rules($slot, $awayRules);
                $slotWarnings = [];
                $score = (int)($slot['sort_index'] ?? 0);

                if ($homeAvailability['has_rules'] && !$homeAvailability['matched']) {
                    $score += 200;
                    $slotWarnings[] = 'Disponibilità non perfettamente rispettata';
                }
                if ($awayAvailability['has_rules'] && !$awayAvailability['matched']) {
                    $score += 200;
                    $slotWarnings[] = 'Disponibilità non perfettamente rispettata';
                }

                $slotWarnings = array_values(array_unique($slotWarnings));

                if ($bestScore === null || $score < $bestScore) {
                    $bestSlot = $slot;
                    $bestScore = $score;
                    $bestWarnings = $slotWarnings;
                }
            }

            $row = [
                'home_team_id' => (int)$pair['home']['id'],
                'away_team_id' => (int)$pair['away']['id'],
                'data' => '',
                'ora' => '',
                'campo' => '',
                'generated_signature' => (int)$pair['home']['id'] . ':' . (int)$pair['away']['id'],
                'generated_warnings' => [],
            ];

            $alternativeWarning = auto_matchday_pair_alternative_warning(
                $pair['home'],
                $pair['away'],
                $teamsById,
                $pairCounts,
                $allowReturn
            );
            if ($alternativeWarning !== null) {
                $row['generated_warnings'][] = $alternativeWarning;
            }

            if ($bestSlot !== null) {
                $usedSlotKeys[$bestSlot['key']] = true;
                $row['data'] = $bestSlot['data'];
                $row['ora'] = $bestSlot['ora'];
                $row['campo'] = $bestSlot['campo'];
                $row['generated_warnings'] = array_values(array_unique(array_merge($row['generated_warnings'], $bestWarnings)));
            } else {
                $row['generated_warnings'][] = 'Numero di slot insufficiente';
                $row['generated_warnings'][] = 'Slot non disponibile';
            }

            $result[] = $row;
        }

        return $result;
    }
}

if (!function_exists('auto_matchday_validate_preview_rows')) {
    function auto_matchday_validate_preview_rows(
        array $rows,
        array $selectedTeamsById,
        array $pairCounts,
        array $pairHistory,
        array $occupiedSlots,
        bool $allowReturn,
        int $giornata,
        array $availabilityRules,
        array $slotCapacityMap = []
    ): array {
        $validatedRows = [];
        $previewPairCounts = [];
        $previewTeamUsage = [];
        $previewSlotUsage = [];
        $globalMessages = [];

        foreach ($rows as $index => $row) {
            $homeTeamId = (int)($row['home_team_id'] ?? 0);
            $awayTeamId = (int)($row['away_team_id'] ?? 0);
            $date = auto_matchday_normalize_date($row['data'] ?? '');
            $time = auto_matchday_normalize_time($row['ora'] ?? '');
            $field = trim((string)($row['campo'] ?? ''));
            $generatedWarnings = [];
            if (
                isset($row['generated_signature'], $row['generated_warnings']) &&
                is_array($row['generated_warnings']) &&
                (string)$row['generated_signature'] === ($homeTeamId . ':' . $awayTeamId)
            ) {
                $generatedWarnings = array_values(array_filter(array_map('strval', $row['generated_warnings'])));
            }

            $entry = [
                'row_index' => $index,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'home_team_name' => $selectedTeamsById[$homeTeamId]['nome'] ?? '',
                'away_team_name' => $selectedTeamsById[$awayTeamId]['nome'] ?? '',
                'data' => $date,
                'ora' => $time,
                'campo' => $field,
                'giornata' => $giornata,
                'fase' => 'REGULAR',
                'warnings' => [],
                'errors' => [],
                'generated_signature' => $homeTeamId . ':' . $awayTeamId,
                'generated_warnings' => $generatedWarnings,
            ];

            if ($homeTeamId <= 0 || !isset($selectedTeamsById[$homeTeamId])) {
                $entry['errors'][] = 'Squadra casa non valida o fuori dal torneo selezionato';
            }
            if ($awayTeamId <= 0 || !isset($selectedTeamsById[$awayTeamId])) {
                $entry['errors'][] = 'Squadra ospite non valida o fuori dal torneo selezionato';
            }
            if ($homeTeamId > 0 && $awayTeamId > 0 && $homeTeamId === $awayTeamId) {
                $entry['errors'][] = 'Squadra casa e squadra ospite non possono coincidere';
            }
            if ($date === '' || $time === '' || $field === '') {
                $entry['errors'][] = 'Data, ora e campo sono obbligatori';
            }

            if ($homeTeamId > 0) {
                $previewTeamUsage[$homeTeamId][] = $index;
            }
            if ($awayTeamId > 0) {
                $previewTeamUsage[$awayTeamId][] = $index;
            }

            $pairKey = auto_matchday_pair_key($homeTeamId, $awayTeamId);
            if ($pairKey !== '') {
                $previewPairCounts[$pairKey][] = $index;
            }

            $slotKey = auto_matchday_slot_key($date, $time, $field);
            if ($slotKey !== '') {
                $previewSlotUsage[$slotKey][] = $index;
                if (isset($occupiedSlots[$slotKey])) {
                    $occupied = $occupiedSlots[$slotKey];
                    $entry['errors'][] = 'Slot non disponibile';
                    $globalMessages[] = sprintf(
                        'Lo slot %s %s - %s è già occupato da %s vs %s.',
                        $occupied['data'],
                        substr($occupied['ora'], 0, 5),
                        $occupied['campo'],
                        (string)($occupied['squadra_casa'] ?? ''),
                        (string)($occupied['squadra_ospite'] ?? '')
                    );
                }
            }

            if ($pairKey !== '' && isset($selectedTeamsById[$homeTeamId], $selectedTeamsById[$awayTeamId])) {
                $homeRules = $availabilityRules[$homeTeamId] ?? [];
                $awayRules = $availabilityRules[$awayTeamId] ?? [];
                $homeAvailability = auto_matchday_slot_matches_team_rules(['data' => $date, 'ora' => $time, 'campo' => $field], $homeRules);
                $awayAvailability = auto_matchday_slot_matches_team_rules(['data' => $date, 'ora' => $time, 'campo' => $field], $awayRules);
                if (
                    ($homeAvailability['has_rules'] && !$homeAvailability['matched']) ||
                    ($awayAvailability['has_rules'] && !$awayAvailability['matched'])
                ) {
                    $entry['warnings'][] = 'Disponibilità non perfettamente rispettata';
                }
            }

            $entry['warnings'] = array_values(array_unique(array_merge($entry['warnings'], $generatedWarnings)));
            $validatedRows[$index] = $entry;
        }

        foreach ($previewTeamUsage as $teamId => $rowIndexes) {
            if (count($rowIndexes) <= 1) {
                continue;
            }
            $teamName = $selectedTeamsById[$teamId]['nome'] ?? ('ID ' . $teamId);
            foreach ($rowIndexes as $rowIndex) {
                $validatedRows[$rowIndex]['errors'][] = 'La squadra ' . $teamName . ' compare in più partite della stessa preview';
            }
        }

        foreach ($previewPairCounts as $pairKey => $rowIndexes) {
            $existingCount = (int)($pairCounts[$pairKey] ?? 0);
            $plannedCount = count($rowIndexes);
            $maxAllowed = $allowReturn ? 2 : 1;

            foreach ($rowIndexes as $rowIndex) {
                if ($plannedCount > 1) {
                    $validatedRows[$rowIndex]['errors'][] = 'La stessa coppia compare più volte nella preview';
                }

                if (($existingCount + $plannedCount) > $maxAllowed) {
                    if (!$allowReturn) {
                        $validatedRows[$rowIndex]['errors'][] = 'Partita già presente nella regular season';
                    } else {
                        $validatedRows[$rowIndex]['errors'][] = 'Limite massimo di due partite già raggiunto nella regular season';
                    }
                } elseif ($allowReturn && $existingCount === 1) {
                    $validatedRows[$rowIndex]['warnings'][] = 'Partita di ritorno';
                }
            }
        }

        $globalMessages = [];
        foreach ($previewSlotUsage as $slotKey => $rowIndexes) {
            $configuredCapacity = max(1, (int)($slotCapacityMap[$slotKey] ?? 1));
            $occupiedCount = (int)($occupiedSlots[$slotKey]['count'] ?? 0);
            $remainingCapacity = max(0, $configuredCapacity - $occupiedCount);
            $plannedCount = count($rowIndexes);

            foreach ($rowIndexes as $rowIndex) {
                $validatedRows[$rowIndex]['errors'] = array_values(array_filter(
                    $validatedRows[$rowIndex]['errors'],
                    static function (string $error): bool {
                        return $error !== 'Slot duplicato all\'interno della preview'
                            && $error !== 'Slot non disponibile';
                    }
                ));
            }

            if ($plannedCount <= $remainingCapacity) {
                continue;
            }

            foreach ($rowIndexes as $position => $rowIndex) {
                if ($position < $remainingCapacity) {
                    continue;
                }

                $validatedRows[$rowIndex]['errors'][] = $configuredCapacity > 1
                    ? 'Capienza contemporanea del campo superata'
                    : 'Slot non disponibile';
            }

            $sampleRow = $validatedRows[$rowIndexes[0]] ?? null;
            if (!$sampleRow) {
                continue;
            }

            if ($occupiedCount > 0) {
                $globalMessages[] = sprintf(
                    'Lo slot %s %s - %s ha gia %d partita/e presenti su %d disponibilita totali.',
                    $sampleRow['data'],
                    substr($sampleRow['ora'], 0, 5),
                    $sampleRow['campo'],
                    $occupiedCount,
                    $configuredCapacity
                );
            } else {
                $globalMessages[] = sprintf(
                    'Lo slot %s %s - %s supera la capienza massima di %d partita/e contemporanee.',
                    $sampleRow['data'],
                    substr($sampleRow['ora'], 0, 5),
                    $sampleRow['campo'],
                    $configuredCapacity
                );
            }
        }

        $isValid = true;
        foreach ($validatedRows as &$row) {
            $row['warnings'] = array_values(array_unique($row['warnings']));
            $row['errors'] = array_values(array_unique($row['errors']));
            if (!empty($row['errors'])) {
                $isValid = false;
            }
        }
        unset($row);

        return [
            'valid' => $isValid,
            'rows' => array_values($validatedRows),
            'messages' => array_values(array_unique($globalMessages)),
        ];
    }
}

if (!function_exists('auto_matchday_prepare_selected_teams')) {
    function auto_matchday_prepare_selected_teams(array $allTeams, array $selectedTeamIds): array
    {
        $maps = auto_matchday_build_team_maps($allTeams);
        $selected = [];
        foreach ($selectedTeamIds as $teamId) {
            $teamId = (int)$teamId;
            if ($teamId > 0 && isset($maps['by_id'][$teamId])) {
                $selected[$teamId] = $maps['by_id'][$teamId];
            }
        }

        uasort($selected, static function (array $left, array $right): int {
            $leftPosition = (int)($left['posizione'] ?? 999);
            $rightPosition = (int)($right['posizione'] ?? 999);
            if ($leftPosition !== $rightPosition) {
                return $leftPosition <=> $rightPosition;
            }
            return strcmp((string)($left['nome'] ?? ''), (string)($right['nome'] ?? ''));
        });

        return $selected;
    }
}

if (!function_exists('auto_matchday_generate_preview')) {
    function auto_matchday_generate_preview(mysqli $conn, array $payload): array
    {
        $tournamentId = (int)($payload['tournament_id'] ?? 0);
        $selectedTeamIds = auto_matchday_int_list($payload['selected_team_ids'] ?? []);
        $giornata = max(1, (int)($payload['giornata'] ?? 0));
        $allowReturn = !empty($payload['allow_return']);
        $normalizedSlots = auto_matchday_normalize_slots($payload['slots'] ?? []);
        $slotCapacityMap = auto_matchday_build_slot_capacity_map($normalizedSlots['slots']);
        $availabilityRules = auto_matchday_normalize_availability_rules($payload['availability'] ?? []);

        if ($tournamentId <= 0) {
            return auto_matchday_json_error('ID torneo non valido.', 400);
        }
        if (count($selectedTeamIds) < 2) {
            return auto_matchday_json_error('Seleziona almeno due squadre.', 400);
        }

        $context = auto_matchday_fetch_context($conn, $tournamentId);
        if (empty($context['success'])) {
            return $context;
        }

        $allTeams = $context['data']['squadre'] ?? [];
        $selectedTeams = auto_matchday_prepare_selected_teams($allTeams, $selectedTeamIds);
        if (count($selectedTeams) < 2) {
            return auto_matchday_json_error('Le squadre selezionate non appartengono al torneo indicato.', 400);
        }

        $regularMatches = $context['data']['partite_regular'] ?? [];
        $historyBundle = auto_matchday_build_regular_history($allTeams, $regularMatches);
        $teamHistory = $historyBundle['team_history'];
        $pairCounts = $historyBundle['pair_counts'];
        $pairHistory = $historyBundle['pair_history'];

        $selectedTeamMap = [];
        foreach ($selectedTeams as $team) {
            $selectedTeamMap[(int)$team['id']] = $team;
        }

        $visitedNodes = 0;
        $pairingResult = auto_matchday_find_best_pairing(
            array_keys($selectedTeamMap),
            $selectedTeamMap,
            $teamHistory,
            $pairCounts,
            $pairHistory,
            $allowReturn,
            $visitedNodes
        );

        $generatedRows = auto_matchday_assign_slots(
            $pairingResult['pairs'] ?? [],
            $normalizedSlots['slots'],
            $availabilityRules,
            $selectedTeamMap,
            $pairCounts,
            $allowReturn
        );

        foreach (($pairingResult['unpaired'] ?? []) as $teamId) {
            $team = $selectedTeamMap[(int)$teamId] ?? null;
            if (!$team) {
                continue;
            }
            $generatedRows[] = [
                'home_team_id' => (int)$team['id'],
                'away_team_id' => 0,
                'data' => '',
                'ora' => '',
                'campo' => '',
                'generated_signature' => (int)$team['id'] . ':0',
                'generated_warnings' => [count($selectedTeamMap) % 2 === 1
                    ? 'Squadra a riposo'
                    : 'Impossibile creare un abbinamento valido per questa squadra'],
            ];
        }

        $occupiedSlots = auto_matchday_fetch_global_occupied_slots($conn);
        $validation = auto_matchday_validate_preview_rows(
            $generatedRows,
            $selectedTeamMap,
            $pairCounts,
            $pairHistory,
            $occupiedSlots,
            $allowReturn,
            $giornata,
            $availabilityRules,
            $slotCapacityMap
        );

        $messages = $validation['messages'];
        if ($normalizedSlots['duplicate_count'] > 0) {
            $messages[] = 'Sono stati ignorati ' . $normalizedSlots['duplicate_count'] . ' slot duplicati.';
        }
        if (count($normalizedSlots['slots']) < count($pairingResult['pairs'] ?? [])) {
            $messages[] = 'Numero di slot insufficiente per tutte le partite generate.';
        }

        if (count($pairingResult['unpaired'] ?? []) === 1 && count($selectedTeamMap) % 2 === 1) {
            $restTeamId = (int)$pairingResult['unpaired'][0];
            if (isset($selectedTeamMap[$restTeamId])) {
                $messages[] = 'Squadra a riposo: ' . $selectedTeamMap[$restTeamId]['nome'];
            }
        }

        if (count($pairingResult['unpaired'] ?? []) > 1) {
            $names = [];
            foreach ($pairingResult['unpaired'] as $teamId) {
                if (isset($selectedTeamMap[(int)$teamId])) {
                    $names[] = $selectedTeamMap[(int)$teamId]['nome'];
                }
            }
            if (!empty($names)) {
                $messages[] = 'Impossibile completare un abbinamento valido per: ' . implode(', ', $names);
            }
        }

        return [
            'success' => true,
            'data' => [
                'tournament' => $context['data']['torneo'],
                'giornata' => $giornata,
                'allow_return' => $allowReturn,
                'selected_team_ids' => array_values(array_keys($selectedTeamMap)),
                'rows' => $validation['rows'],
                'messages' => array_values(array_unique($messages)),
                'valid' => $validation['valid'],
            ],
        ];
    }
}

if (!function_exists('auto_matchday_validate_payload')) {
    function auto_matchday_validate_payload(mysqli $conn, array $payload): array
    {
        $tournamentId = (int)($payload['tournament_id'] ?? 0);
        $selectedTeamIds = auto_matchday_int_list($payload['selected_team_ids'] ?? []);
        $giornata = max(1, (int)($payload['giornata'] ?? 0));
        $allowReturn = !empty($payload['allow_return']);
        $normalizedSlots = auto_matchday_normalize_slots($payload['slots'] ?? []);
        $slotCapacityMap = auto_matchday_build_slot_capacity_map($normalizedSlots['slots']);
        $availabilityRules = auto_matchday_normalize_availability_rules($payload['availability'] ?? []);
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        if ($tournamentId <= 0) {
            return auto_matchday_json_error('ID torneo non valido.', 400);
        }
        if (empty($rows)) {
            return auto_matchday_json_error('Nessuna partita da validare.', 400);
        }
        if (count($selectedTeamIds) < 2) {
            return auto_matchday_json_error('Nessuna squadra selezionata.', 400);
        }

        $context = auto_matchday_fetch_context($conn, $tournamentId);
        if (empty($context['success'])) {
            return $context;
        }

        $allTeams = $context['data']['squadre'] ?? [];
        $selectedTeams = auto_matchday_prepare_selected_teams($allTeams, $selectedTeamIds);
        if (empty($selectedTeams)) {
            return auto_matchday_json_error('Le squadre selezionate non appartengono al torneo indicato.', 400);
        }

        $regularMatches = $context['data']['partite_regular'] ?? [];
        $historyBundle = auto_matchday_build_regular_history($allTeams, $regularMatches);
        $occupiedSlots = auto_matchday_fetch_global_occupied_slots($conn);

        $validation = auto_matchday_validate_preview_rows(
            $rows,
            $selectedTeams,
            $historyBundle['pair_counts'],
            $historyBundle['pair_history'],
            $occupiedSlots,
            $allowReturn,
            $giornata,
            $availabilityRules,
            $slotCapacityMap
        );

        return [
            'success' => true,
            'data' => [
                'valid' => $validation['valid'],
                'giornata' => $giornata,
                'rows' => $validation['rows'],
                'messages' => $validation['messages'],
            ],
        ];
    }
}

if (!function_exists('auto_matchday_save_matches')) {
    function auto_matchday_save_matches(mysqli $conn, array $payload): array
    {
        $validation = auto_matchday_validate_payload($conn, $payload);
        if (empty($validation['success'])) {
            return $validation;
        }

        if (empty($validation['data']['valid'])) {
            return auto_matchday_json_error(
                'La preview contiene errori. Correggili prima di creare le partite.',
                422,
                ['data' => $validation['data']]
            );
        }

        $tournamentId = (int)($payload['tournament_id'] ?? 0);
        $tournament = auto_matchday_fetch_tournament_by_id($conn, $tournamentId);
        if (!$tournament) {
            return auto_matchday_json_error('Torneo non trovato.', 404);
        }

        $rows = $validation['data']['rows'] ?? [];
        if (empty($rows)) {
            return auto_matchday_json_error('Nessuna partita da creare.', 400);
        }

        $maps = auto_matchday_build_team_maps(auto_matchday_fetch_teams($conn, $tournament['slug']));
        $insertSql = "
            INSERT INTO partite (
                torneo,
                fase,
                fase_round,
                fase_leg,
                squadra_casa,
                squadra_ospite,
                gol_casa,
                gol_ospite,
                data_partita,
                ora_partita,
                campo,
                giornata,
                giocata,
                link_youtube,
                link_instagram,
                created_at
            ) VALUES (?, 'REGULAR', NULL, NULL, ?, ?, 0, 0, ?, ?, ?, ?, 0, NULL, NULL, NOW())
        ";
        $stmt = $conn->prepare($insertSql);
        if (!$stmt) {
            return auto_matchday_json_error('Errore database durante la preparazione del salvataggio.', 500);
        }

        $created = 0;
        $createdIds = [];
        $conn->begin_transaction();

        try {
            foreach ($rows as $row) {
                $homeId = (int)($row['home_team_id'] ?? 0);
                $awayId = (int)($row['away_team_id'] ?? 0);
                if ($homeId <= 0 || $awayId <= 0) {
                    throw new RuntimeException('Riga preview non valida: squadra mancante.');
                }
                if (!isset($maps['by_id'][$homeId], $maps['by_id'][$awayId])) {
                    throw new RuntimeException('Riga preview non valida: squadra fuori dal torneo.');
                }

                $homeName = (string)$maps['by_id'][$homeId]['nome'];
                $awayName = (string)$maps['by_id'][$awayId]['nome'];
                $date = auto_matchday_normalize_date($row['data'] ?? '');
                $time = auto_matchday_normalize_time($row['ora'] ?? '');
                $field = trim((string)($row['campo'] ?? ''));
                $giornata = (int)($row['giornata'] ?? $validation['data']['giornata'] ?? 0);

                if ($date === '' || $time === '' || $field === '' || $giornata <= 0) {
                    throw new RuntimeException('Riga preview non valida: dati obbligatori mancanti.');
                }

                $stmt->bind_param(
                    'ssssssi',
                    $tournament['slug'],
                    $homeName,
                    $awayName,
                    $date,
                    $time,
                    $field,
                    $giornata
                );

                if (!$stmt->execute()) {
                    throw new RuntimeException('Errore database durante la creazione della partita.');
                }

                $created++;
                $createdIds[] = (int)$stmt->insert_id;
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            $stmt->close();
            return auto_matchday_json_error($e->getMessage(), 500, ['data' => $validation['data']]);
        }

        $stmt->close();

        return [
            'success' => true,
            'data' => [
                'created' => $created,
                'created_ids' => $createdIds,
                'message' => $created === 1 ? '1 partita creata correttamente.' : $created . ' partite create correttamente.',
            ],
        ];
    }
}
