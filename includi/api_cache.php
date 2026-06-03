<?php
declare(strict_types=1);

if (!function_exists('tos_api_cache_base_dir')) {
    function tos_api_cache_base_dir(): string
    {
        return __DIR__ . '/../cache/api';
    }
}

if (!function_exists('tos_api_cache_build_key')) {
    function tos_api_cache_build_key(string $prefix, array $params = []): string
    {
        ksort($params);
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix) . '_' . sha1(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('tos_api_cache_path')) {
    function tos_api_cache_path(string $cacheKey): string
    {
        return tos_api_cache_base_dir() . '/' . $cacheKey . '.json';
    }
}

if (!function_exists('tos_api_cache_read')) {
    function tos_api_cache_read(string $cacheKey, int $ttlSeconds): ?string
    {
        $path = tos_api_cache_path($cacheKey);
        if (!is_file($path)) {
            return null;
        }

        $modifiedAt = @filemtime($path);
        if (!$modifiedAt || (time() - $modifiedAt) > $ttlSeconds) {
            return null;
        }

        $payload = @file_get_contents($path);
        return is_string($payload) && $payload !== '' ? $payload : null;
    }
}

if (!function_exists('tos_api_cache_write')) {
    function tos_api_cache_write(string $cacheKey, string $payload): void
    {
        $path = tos_api_cache_path($cacheKey);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $tmpPath = $path . '.' . uniqid('tmp_', true);
        if (@file_put_contents($tmpPath, $payload, LOCK_EX) === false) {
            return;
        }

        @rename($tmpPath, $path);
    }
}
