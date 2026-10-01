<?php
declare(strict_types=1);

require_once __DIR__ . '/env_loader.php';

/** Store token state outside the web root so PHP can refresh it without exposing it. */
function tos_instagram_token_state_path(): string
{
    $path = tos_runtime_path('instagram/token-state.json');
    $normalizedPath = str_replace('\\', '/', $path);
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($documentRoot !== false && $documentRoot !== '') {
        $normalizedRoot = rtrim(str_replace('\\', '/', $documentRoot), '/') . '/';
        if (stripos($normalizedPath, $normalizedRoot) === 0) {
            $path = dirname($documentRoot) . DIRECTORY_SEPARATOR . 'torneioldschool-runtime'
                . DIRECTORY_SEPARATOR . 'instagram' . DIRECTORY_SEPARATOR . 'token-state.json';
        }
    }
    return $path;
}

function tos_load_instagram_token_state(): ?array
{
    $path = tos_instagram_token_state_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '' || strlen($raw) > 32768) {
        return null;
    }
    $state = json_decode($raw, true);
    if (!is_array($state) || trim((string)($state['access_token'] ?? '')) === ''
        || trim((string)($state['user_id'] ?? '')) === '') {
        return null;
    }
    return $state;
}

function tos_save_instagram_token_state(array $state): bool
{
    $path = tos_instagram_token_state_path();
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return false;
    }
    if (!is_writable($directory)) {
        return false;
    }
    $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }
    $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    if (@file_put_contents($temporary, $json, LOCK_EX) === false) {
        return false;
    }
    @chmod($temporary, 0600);
    if (!@rename($temporary, $path)) {
        // Windows does not replace an existing destination with rename().
        @unlink($path);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            return false;
        }
    }
    @chmod($path, 0600);
    return true;
}
