<?php
/**
 * Carica le variabili da includi/env.local.php (non versione in git)
 * e le espone via getenv()/$_ENV/$_SERVER.
 */
function tos_load_env(): array
{
    static $loaded = false;
    if ($loaded) {
        return $_ENV;
    }

    $envFile = __DIR__ . '/env.local.php';
    if (file_exists($envFile)) {
        // In alcuni ambienti Windows is_readable() può restituire false anche se il file è accessibile
        $data = @include $envFile;
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === '' || $value === null) {
                    continue;
                }
                if (getenv($key) === false || getenv($key) === '') {
                    $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
                    putenv("$key=$stringValue");
                    $_ENV[$key] = $stringValue;
                    $_SERVER[$key] = $stringValue;
                }
            }
        } else {
            error_log('env_loader: env.local.php non restituisce un array, variabili non caricate.');
        }
    }

    $loaded = true;
    return $_ENV;
}

tos_load_env();

if (!function_exists('tos_sanitize_host')) {
    function tos_sanitize_host(?string $host): ?string
    {
        $host = trim((string)$host);
        if ($host === '') {
            return null;
        }

        if (preg_match('/^\[([0-9a-f:.]+)\](?::(\d{1,5}))?$/i', $host, $matches)) {
            $hostname = strtolower($matches[1]);
            $port = isset($matches[2]) ? (int)$matches[2] : null;
            if (!filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return null;
            }
            if ($port !== null && ($port < 1 || $port > 65535)) {
                return null;
            }
            return $port !== null ? '[' . $hostname . ']:' . $port : '[' . $hostname . ']';
        }

        if (!preg_match('/^([a-z0-9.-]+)(?::(\d{1,5}))?$/i', $host, $matches)) {
            return null;
        }

        $hostname = strtolower($matches[1]);
        $port = isset($matches[2]) ? (int)$matches[2] : null;

        if ($hostname === '' || strpos($hostname, '..') !== false) {
            return null;
        }

        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        return $port !== null ? $hostname . ':' . $port : $hostname;
    }
}

if (!function_exists('tos_normalize_base_url')) {
    function tos_normalize_base_url(?string $url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $rawHost = (string)($parts['host'] ?? '');
        $rawPort = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($rawPort !== null) {
            $rawHost = strpos($rawHost, ':') !== false && $rawHost[0] !== '['
                ? '[' . $rawHost . ']:' . $rawPort
                : $rawHost . ':' . $rawPort;
        }

        $host = tos_sanitize_host($rawHost);
        if ($host === null) {
            return null;
        }

        $path = isset($parts['path']) ? trim((string)$parts['path']) : '';
        $path = $path === '' || $path === '/' ? '' : '/' . trim($path, '/');

        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('tos_origin_url')) {
    function tos_origin_url(): string
    {
        $configured = tos_normalize_base_url(getenv('APP_BASE_URL') ?: '');
        if ($configured !== null) {
            $parts = parse_url($configured);
            if (is_array($parts)) {
                $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
                $rawHost = (string)($parts['host'] ?? 'localhost');
                $rawPort = isset($parts['port']) ? (int)$parts['port'] : null;
                if ($rawPort !== null) {
                    $rawHost = strpos($rawHost, ':') !== false && $rawHost[0] !== '['
                        ? '[' . $rawHost . ']:' . $rawPort
                        : $rawHost . ':' . $rawPort;
                }
                $host = tos_sanitize_host($rawHost) ?? 'localhost';
                return $scheme . '://' . $host;
            }
        }

        $hostCandidates = [
            $_SERVER['SERVER_NAME'] ?? '',
            $_SERVER['HTTP_HOST'] ?? '',
            'localhost',
        ];
        $host = 'localhost';
        foreach ($hostCandidates as $candidate) {
            $normalized = tos_sanitize_host($candidate);
            if ($normalized !== null) {
                $host = $normalized;
                break;
            }
        }

        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https' : 'http';

        return $scheme . '://' . $host;
    }
}

if (!function_exists('tos_detect_base_path')) {
    function tos_detect_base_path(): string
    {
        $configured = tos_normalize_base_url(getenv('APP_BASE_URL') ?: '');
        if ($configured !== null) {
            $parts = parse_url($configured);
            $path = trim((string)($parts['path'] ?? ''));
            return ($path === '' || $path === '/') ? '' : '/' . trim($path, '/');
        }

        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');

        if ($docRoot !== '' && $projectRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
            $relative = trim(substr($projectRoot, strlen($docRoot)), '/');
            return $relative === '' ? '' : '/' . $relative;
        }

        return '';
    }
}

if (!function_exists('tos_base_url')) {
    function tos_base_url(): string
    {
        $configured = tos_normalize_base_url(getenv('APP_BASE_URL') ?: '');
        if ($configured !== null) {
            return $configured;
        }

        return rtrim(tos_origin_url(), '/') . tos_detect_base_path();
    }
}

if (!function_exists('tos_absolute_url')) {
    function tos_absolute_url(string $path): string
    {
        return rtrim(tos_base_url(), '/') . '/' . ltrim($path, '/');
    }
}

/** Check boundaries before touching storage, including existing symlink ancestors. */
function tos_path_allowed(string $path): bool
{
    $normalize = static function(string $value): string {
        $value = str_replace('\\', '/', $value);
        $parts = [];
        foreach (explode('/', $value) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') array_pop($parts); else $parts[] = $part;
        }
        return (str_starts_with($value, '/') ? '/' : '') . implode('/', $parts);
    };
    $path = $normalize($path);
    if (!preg_match('~^(?:/|[A-Za-z]:/)~', $path)) return false;
    $restrictions = (string)ini_get('open_basedir');
    if ($restrictions === '') return true;
    foreach (explode(PATH_SEPARATOR, $restrictions) as $root) {
        if ($root === '.') $root = (string)getcwd();
        $root = rtrim($normalize($root), '/');
        $candidate = $path;
        if (PHP_OS_FAMILY === 'Windows') {$root = strtolower($root);$candidate = strtolower($candidate);}
        if ($candidate !== $root && !str_starts_with($candidate, $root.'/')) continue;
        $ancestor = $path;
        while (($resolved = @realpath($ancestor)) === false && dirname($ancestor) !== $ancestor) $ancestor = dirname($ancestor);
        if ($resolved === false) return false;
        $resolved = $normalize($resolved);
        if (PHP_OS_FAMILY === 'Windows') $resolved = strtolower($resolved);
        return $resolved === $root || str_starts_with($resolved, $root.'/');
    }
    return false;
}

function tos_runtime_resolve(string $configured, string $default): string
{
    $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($configured) ?: $default), DIRECTORY_SEPARATOR);
    if (!tos_path_allowed($path)) {
        // Fail before filesystem operations; do not silently switch session/token storage.
        throw new RuntimeException('Directory runtime fuori dai percorsi consentiti: correggere TOS_RUNTIME_DIR sul server.');
    }
    return $path;
}

if (!function_exists('tos_runtime_root')) {
    function tos_runtime_root(): string
    {
        $configured = trim((string)(getenv('TOS_RUNTIME_DIR') ?: ''));
        return tos_runtime_resolve($configured, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'torneioldschool-runtime');
    }
}

if (!function_exists('tos_runtime_path')) {
    function tos_runtime_path(string $relativePath = ''): string
    {
        $base = rtrim(tos_runtime_root(), DIRECTORY_SEPARATOR);
        if ($relativePath === '') {
            return $base;
        }

        $normalized = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        return $base . DIRECTORY_SEPARATOR . $normalized;
    }
}

if (!function_exists('tos_ensure_parent_dir')) {
    function tos_ensure_parent_dir(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
