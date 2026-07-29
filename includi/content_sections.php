<?php

if (!function_exists('normalize_content_section')) {
    function normalize_content_section(?string $value): string
    {
        return strtolower(trim((string)$value)) === 'esport' ? 'esport' : 'calcio';
    }
}

if (!function_exists('content_request_host')) {
    function content_request_host(): string
    {
        $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
        return preg_replace('/:\d+$/', '', $host) ?? $host;
    }
}

if (!function_exists('content_is_esport_host')) {
    function content_is_esport_host(): bool
    {
        $configuredHost = strtolower(trim((string)(getenv('ESPORT_HOST') ?: 'esport.torneioldschool.it')));
        return content_request_host() === $configuredHost;
    }
}

if (!function_exists('content_current_section')) {
    function content_current_section(): string
    {
        return content_is_esport_host() ? 'esport' : 'calcio';
    }
}

if (!function_exists('content_site_origin')) {
    function content_site_origin(string $section): string
    {
        $section = normalize_content_section($section);
        $envKey = $section === 'esport' ? 'ESPORT_BASE_URL' : 'SPORT_BASE_URL';
        $fallback = $section === 'esport'
            ? 'https://esport.torneioldschool.it'
            : 'https://torneioldschool.it';
        return rtrim((string)(getenv($envKey) ?: $fallback), '/');
    }
}

if (!function_exists('content_url_for_section')) {
    function content_url_for_section(string $section, string $path = '/'): string
    {
        return content_site_origin($section) . '/' . ltrim($path, '/');
    }
}

if (!function_exists('content_section_label')) {
    function content_section_label(string $section): string
    {
        return normalize_content_section($section) === 'esport' ? 'ESPORT' : 'Calcio';
    }
}

if (!function_exists('content_table_has_column')) {
    function content_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        $tableEscaped = $conn->real_escape_string($table);
        $columnEscaped = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('ensure_content_section_column')) {
    function ensure_content_section_column(mysqli $conn, string $table, string $afterColumn = ''): bool
    {
        if (content_table_has_column($conn, $table, 'sezione')) {
            return true;
        }

        $tableEscaped = $conn->real_escape_string($table);
        $afterSql = '';
        if ($afterColumn !== '') {
            $afterEscaped = $conn->real_escape_string($afterColumn);
            $afterSql = " AFTER `{$afterEscaped}`";
        }

        if (@$conn->query("ALTER TABLE `{$tableEscaped}` ADD COLUMN `sezione` VARCHAR(20) NOT NULL DEFAULT 'calcio'{$afterSql}")) {
            return true;
        }

        return content_table_has_column($conn, $table, 'sezione');
    }
}

if (!function_exists('ensure_blog_post_section_column')) {
    function ensure_blog_post_section_column(mysqli $conn): bool
    {
        return ensure_content_section_column($conn, 'blog_post', 'immagine');
    }
}

if (!function_exists('ensure_albo_section_column')) {
    function ensure_albo_section_column(mysqli $conn): bool
    {
        return ensure_content_section_column($conn, 'albo', 'link_torneo');
    }
}

if (!function_exists('ensure_tornei_section_column')) {
    function ensure_tornei_section_column(mysqli $conn): bool
    {
        return ensure_content_section_column($conn, 'tornei', 'categoria');
    }
}
