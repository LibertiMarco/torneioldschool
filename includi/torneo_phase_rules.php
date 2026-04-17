<?php

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
        return strtolower(trim((string)($config['formato'] ?? $config['formula_torneo'] ?? '')));
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
