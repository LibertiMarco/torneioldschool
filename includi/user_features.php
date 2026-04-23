<?php

if (!function_exists('user_feature_definitions')) {
    function user_feature_definitions(): array
    {
        return [
            'totocalcio' => [
                'label' => 'Totocalcio',
                'menu_label' => 'Totocalcio',
                'path' => '/totocalcio.php',
                'description' => 'Mostra la funzione nascosta Totocalcio nel menu utente.',
            ],
            'fantacalcio' => [
                'label' => 'Fantacalcio',
                'menu_label' => 'Fantacalcio',
                'path' => '/fantacalcio.php',
                'description' => 'Mostra la funzione nascosta Fantacalcio nel menu utente.',
            ],
        ];
    }
}

if (!function_exists('normalize_user_feature_flags')) {
    function normalize_user_feature_flags($rawFlags): array
    {
        $decoded = [];

        if (is_string($rawFlags) && trim($rawFlags) !== '') {
            $json = json_decode($rawFlags, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        } elseif (is_array($rawFlags)) {
            $decoded = $rawFlags;
        }

        $normalized = [];
        foreach (user_feature_definitions() as $featureKey => $featureConfig) {
            $normalized[$featureKey] = !empty($decoded[$featureKey]);
        }

        return $normalized;
    }
}

if (!function_exists('encode_user_feature_flags')) {
    function encode_user_feature_flags(array $flags): string
    {
        return json_encode(
            normalize_user_feature_flags($flags),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}

if (!function_exists('ensure_user_feature_flags_column')) {
    function ensure_user_feature_flags_column(mysqli $conn): bool
    {
        static $checked = false;
        static $ready = false;

        if ($checked) {
            return $ready;
        }

        $checked = true;
        $check = $conn->query("SHOW COLUMNS FROM utenti LIKE 'feature_flags'");
        if ($check instanceof mysqli_result) {
            $ready = $check->num_rows > 0;
            $check->close();
        }

        if ($ready) {
            return true;
        }

        $ready = $conn->query(
            "ALTER TABLE utenti ADD COLUMN feature_flags LONGTEXT NULL AFTER ruolo"
        ) === true;

        if (!$ready) {
            error_log('user_features: impossibile aggiungere la colonna feature_flags - ' . $conn->error);
        }

        return $ready;
    }
}

if (!function_exists('load_user_feature_flags')) {
    function load_user_feature_flags(mysqli $conn, int $userId): array
    {
        $defaults = normalize_user_feature_flags([]);
        if ($userId <= 0) {
            return $defaults;
        }

        if (!ensure_user_feature_flags_column($conn)) {
            return $defaults;
        }

        $stmt = $conn->prepare("SELECT feature_flags FROM utenti WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return $defaults;
        }

        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            return $defaults;
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return normalize_user_feature_flags($row['feature_flags'] ?? null);
    }
}

if (!function_exists('save_user_feature_flags')) {
    function save_user_feature_flags(mysqli $conn, int $userId, array $flags): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (!ensure_user_feature_flags_column($conn)) {
            return false;
        }

        $encodedFlags = encode_user_feature_flags($flags);
        $stmt = $conn->prepare("UPDATE utenti SET feature_flags = ? WHERE id = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("si", $encodedFlags, $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

if (!function_exists('extract_user_feature_flags_from_request')) {
    function extract_user_feature_flags_from_request(array $source, string $field = 'feature_flags'): array
    {
        $selected = $source[$field] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }

        $flags = [];
        foreach (user_feature_definitions() as $featureKey => $featureConfig) {
            $flags[$featureKey] = in_array($featureKey, $selected, true);
        }

        return $flags;
    }
}

if (!function_exists('user_feature_enabled')) {
    function user_feature_enabled(array $flags, string $featureKey): bool
    {
        return !empty($flags[$featureKey]);
    }
}

if (!function_exists('user_has_admin_access')) {
    function user_has_admin_access(string $role): bool
    {
        return in_array(trim($role), ['admin', 'sysadmin'], true);
    }
}

if (!function_exists('user_can_access_feature')) {
    function user_can_access_feature(mysqli $conn, int $userId, string $role, string $featureKey): bool
    {
        $definitions = user_feature_definitions();
        if (!isset($definitions[$featureKey])) {
            return false;
        }

        if (user_has_admin_access($role)) {
            return true;
        }

        $flags = load_user_feature_flags($conn, $userId);
        return user_feature_enabled($flags, $featureKey);
    }
}
