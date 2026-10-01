<?php
declare(strict_types=1);

require_once __DIR__ . '/env_loader.php';

function tos_instagram_token_refresh_interval_seconds(): int
{
    return 30 * 86400;
}

/** Normalize older token records to the monthly refresh cadence. */
function tos_instagram_token_refresh_schedule(array $state, ?int $now = null): array
{
    $now ??= time();
    $original = $state;
    $retryAt = (int)($state['refresh_retry_at'] ?? 0);
    if ($retryAt > $now) {
        return ['state' => $state, 'due' => false, 'changed' => false];
    }
    if ($retryAt > 0) {
        unset($state['refresh_retry_at']);
    }

    $issuedAt = (int)($state['issued_at'] ?? 0);
    $refreshAfter = (int)($state['refresh_after'] ?? 0);
    if ($issuedAt > 0) {
        $monthlyRefresh = $issuedAt + tos_instagram_token_refresh_interval_seconds();
        if ($refreshAfter <= 0 || $refreshAfter > $monthlyRefresh) {
            $refreshAfter = $monthlyRefresh;
        }
    } elseif ($refreshAfter <= 0) {
        // For a token supplied through server environment settings its issue date
        // is unknown; begin the 30-day cadence from when this server first sees it.
        $refreshAfter = $now + tos_instagram_token_refresh_interval_seconds();
    }

    $state['refresh_after'] = $refreshAfter;
    return [
        'state' => $state,
        'due' => $refreshAfter <= $now,
        'changed' => $state !== $original,
    ];
}

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

/** Return private storage locations in preferred order without placing tokens in the web root. */
function tos_instagram_token_state_paths(): array
{
    $paths = [];
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($documentRoot !== false && $documentRoot !== '') {
        $paths[] = dirname($documentRoot) . DIRECTORY_SEPARATOR . 'torneioldschool-runtime'
            . DIRECTORY_SEPARATOR . 'instagram' . DIRECTORY_SEPARATOR . 'token-state.json';
    }
    $paths[] = tos_instagram_token_state_path();

    // Some shared hosts allow PHP to write only to their private temp directory.
    // Keep this fallback private and stable for this installation.
    $temporaryRoot = trim((string)sys_get_temp_dir());
    if ($temporaryRoot !== '') {
        $normalizedTemporaryRoot = strtolower(rtrim(str_replace('\\', '/', $temporaryRoot), '/') . '/');
        $normalizedDocumentRoot = $documentRoot !== false && $documentRoot !== ''
            ? strtolower(rtrim(str_replace('\\', '/', $documentRoot), '/') . '/')
            : '';
        if ($normalizedDocumentRoot === '' || strpos($normalizedTemporaryRoot, $normalizedDocumentRoot) !== 0) {
            $installation = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
            $installationKey = substr(hash('sha256', str_replace('\\', '/', $installation)), 0, 16);
            $paths[] = rtrim($temporaryRoot, '/\\') . DIRECTORY_SEPARATOR . 'torneioldschool-runtime-' . $installationKey
                . DIRECTORY_SEPARATOR . 'instagram' . DIRECTORY_SEPARATOR . 'token-state.json';
        }
    }

    $unique = [];
    foreach ($paths as $path) {
        $key = strtolower(str_replace('\\', '/', $path));
        $unique[$key] = $path;
    }
    return array_values($unique);
}

function tos_load_instagram_token_state(): ?array
{
    $latestState = null;
    $latestIssuedAt = -1;
    $latestSavedAt = -1.0;
    foreach (tos_instagram_token_state_paths() as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '' || strlen($raw) > 32768) {
            continue;
        }
        $state = json_decode($raw, true);
        if (!is_array($state) || trim((string)($state['access_token'] ?? '')) === ''
            || trim((string)($state['user_id'] ?? '')) === '') {
            continue;
        }
        $issuedAt = (int)($state['issued_at'] ?? 0);
        $savedAt = (float)($state['state_saved_at'] ?? 0);
        if ($latestState === null || $issuedAt > $latestIssuedAt
            || ($issuedAt === $latestIssuedAt && $savedAt > $latestSavedAt)) {
            $latestState = $state;
            $latestIssuedAt = $issuedAt;
            $latestSavedAt = $savedAt;
        }
    }
    return $latestState;
}

function tos_save_instagram_token_state(array $state): bool
{
    $state['state_saved_at'] = microtime(true);
    $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }

    foreach (tos_instagram_token_state_paths() as $path) {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            continue;
        }
        @chmod($directory, 0700);
        if (!is_writable($directory)) {
            continue;
        }

        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($temporary, $json, LOCK_EX) === false) {
            continue;
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) {
            // Windows does not replace an existing destination with rename().
            if (is_file($path) && @unlink($path) && @rename($temporary, $path)) {
                @chmod($path, 0600);
                return true;
            }
            @unlink($temporary);
            continue;
        }
        @chmod($path, 0600);
        return true;
    }

    return false;
}
