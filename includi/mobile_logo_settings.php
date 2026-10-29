<?php
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/mobile_auth.php';

function mobile_logo_settings(?array $body = null): array
{
    $path = tos_runtime_path('mobile/original-logos');
    if ($body !== null) {
        if (!is_bool($body['original_logos'] ?? null)) {
            throw new MobileAuthError('invalid_request', 'Impostazione loghi non valida.', 400);
        }
        tos_ensure_parent_dir($path);
        if (file_put_contents($path, $body['original_logos'] ? '1' : '0', LOCK_EX) === false) {
            throw new RuntimeException('Impossibile salvare i loghi.');
        }
    }
    // Default: original emblems, even before administrators save a preference.
    $enabled = false;
    if (is_file($path)) {
        $file = fopen($path, 'rb');
        if ($file !== false) {
            if (flock($file, LOCK_SH)) {
                $enabled = trim((string)stream_get_contents($file)) === '1';
                flock($file, LOCK_UN);
            }
            fclose($file);
        }
    }
    return ['original_logos' => $enabled];
}
