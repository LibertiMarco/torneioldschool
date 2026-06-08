<?php

if (!function_exists('normalize_content_section')) {
    function normalize_content_section(?string $value): string
    {
        return strtolower(trim((string)$value)) === 'esport' ? 'esport' : 'calcio';
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
