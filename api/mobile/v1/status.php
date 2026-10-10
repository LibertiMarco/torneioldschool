<?php
require_once __DIR__ . '/_bootstrap.php';
mobile_run(static function (): array {
    mobile_method('GET');
    // Verify the additive migration without exposing accounts or configuration.
    foreach (['mobile_auth_codes', 'mobile_sessions', 'mobile_refresh_tokens'] as $table) {
        mobile_db()->query('SELECT 1 FROM ' . $table . ' LIMIT 0');
    }
    return ['service' => 'tornei-old-school-mobile', 'version' => 1, 'ready' => true];
});
