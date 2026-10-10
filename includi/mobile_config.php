<?php
require_once __DIR__ . '/env_loader.php';

function mobile_api_enabled(): bool
{
    $configured = getenv('MOBILE_API_ENABLED');
    if ($configured !== false && $configured !== '') { return $configured === '1'; }
    $flag = tos_runtime_path('mobile/api-enabled');
    return is_file($flag) && trim((string)file_get_contents($flag)) === '1';
}

function mobile_api_set_enabled(bool $enabled): void
{
    $configured = getenv('MOBILE_API_ENABLED');
    if ($configured !== false && $configured !== '') {
        throw new RuntimeException('Configurazione gestita tramite variabile server.');
    }
    $flag = tos_runtime_path('mobile/api-enabled');
    tos_ensure_parent_dir($flag);
    if (file_put_contents($flag, $enabled ? '1' : '0', LOCK_EX) === false) {
        throw new RuntimeException('Impossibile salvare la configurazione mobile.');
    }
}
