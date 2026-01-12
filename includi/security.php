<?php
// Session hardening: set secure cookie flags and strict mode before starting
if (!defined('REMEMBER_COOKIE_NAME')) {
    define('REMEMBER_COOKIE_NAME', 'tos_keep_login');
}
if (!defined('REMEMBER_COOKIE_LIFETIME')) {
    define('REMEMBER_COOKIE_LIFETIME', 60 * 60 * 24 * 30); // 30 giorni
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $rememberRequested = !empty($_COOKIE[REMEMBER_COOKIE_NAME]);
    $cookieLifetime = $rememberRequested ? REMEMBER_COOKIE_LIFETIME : 0;

    if ($rememberRequested) {
        ini_set('session.gc_maxlifetime', (string)REMEMBER_COOKIE_LIFETIME);
    }

    if (function_exists('session_set_cookie_params')) {
        session_set_cookie_params([
            'lifetime' => $cookieLifetime,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    if (!ini_get('session.use_strict_mode')) {
        ini_set('session.use_strict_mode', '1');
    }
    session_start();
    // regenerate periodically to reduce fixation
    $created = $_SESSION['__created_at'] ?? time();
    if (($created + 1800) < time()) {
        session_regenerate_id(true);
        $created = time();
    }
    $_SESSION['__created_at'] = $created;

    // Mantieni viva la sessione se l'utente ha richiesto "ricorda accesso"
    if (!empty($_SESSION['remember_me'])) {
        $params = session_get_cookie_params();
        $expires = time() + REMEMBER_COOKIE_LIFETIME;

        setcookie(session_name(), session_id(), [
            'expires' => $expires,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);

        if (empty($_COOKIE[REMEMBER_COOKIE_NAME]) || $_COOKIE[REMEMBER_COOKIE_NAME] !== '1') {
            setcookie(REMEMBER_COOKIE_NAME, '1', [
                'expires' => $expires,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
    }
}

// Basic CSRF utilities
if (!function_exists('csrf_get_token')) {
    function csrf_get_token(string $key = 'default'): string
    {
        if (!isset($_SESSION['_csrf_tokens'])) {
            $_SESSION['_csrf_tokens'] = [];
        }
        if (empty($_SESSION['_csrf_tokens'][$key])) {
            $_SESSION['_csrf_tokens'][$key] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_tokens'][$key];
    }
}

if (!function_exists('csrf_is_valid')) {
    function csrf_is_valid(?string $token, string $key = 'default'): bool
    {
        if (!$token || !isset($_SESSION['_csrf_tokens'][$key])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_tokens'][$key], $token);
    }
}

if (!function_exists('csrf_require')) {
    function csrf_require(string $key = 'default'): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!csrf_is_valid($token, $key)) {
            http_response_code(400);
            exit('CSRF token non valido o mancante.');
        }
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(string $key = 'default'): string
    {
        $token = csrf_get_token($key);
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

// Simple rate limiting (session + IP based)
if (!function_exists('rate_limit_allow')) {
    function rate_limit_allow(string $action, int $limit, int $windowSeconds): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = $action . ':' . $ip;
        $now = time();

        if (!isset($_SESSION['_rate_limits'][$key])) {
            $_SESSION['_rate_limits'][$key] = ['start' => $now, 'count' => 0];
        }

        $bucket = &$_SESSION['_rate_limits'][$key];

        if (($now - $bucket['start']) >= $windowSeconds) {
            $bucket = ['start' => $now, 'count' => 0];
        }

        if ($bucket['count'] >= $limit) {
            return false;
        }

        $bucket['count']++;
        return true;
    }
}

if (!function_exists('rate_limit_retry_after')) {
    function rate_limit_retry_after(string $action, int $windowSeconds): int
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = $action . ':' . $ip;
        if (!isset($_SESSION['_rate_limits'][$key])) {
            return 0;
        }
        $bucket = $_SESSION['_rate_limits'][$key];
        $elapsed = time() - ($bucket['start'] ?? 0);
        $remaining = $windowSeconds - $elapsed;
        return $remaining > 0 ? $remaining : 0;
    }
}

// Lightweight captcha for public forms
if (!function_exists('captcha_generate')) {
    function captcha_generate(string $formKey): string
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $_SESSION['_captcha'][$formKey] = [
            'answer' => $a + $b,
            'expires' => time() + 600,
        ];
        return "{$a} + {$b}";
    }
}

if (!function_exists('captcha_is_valid')) {
    function captcha_is_valid(string $formKey, $answer): bool
    {
        $captcha = $_SESSION['_captcha'][$formKey] ?? null;
        unset($_SESSION['_captcha'][$formKey]);

        if (!$captcha || ($captcha['expires'] ?? 0) < time()) {
            return false;
        }

        $given = is_numeric($answer) ? (int)$answer : null;
        return $given !== null && $given === (int)$captcha['answer'];
    }
}

if (!function_exists('honeypot_triggered')) {
    function honeypot_triggered(string $fieldName = 'hp_field'): bool
    {
        return !empty($_POST[$fieldName]);
    }
}

// Gestione della pagina di ritorno dopo il login con sanitizzazione di base
if (!function_exists('login_sanitize_redirect')) {
    function login_sanitize_redirect(?string $target): ?string
    {
        if (!$target) {
            return null;
        }

        $trimmed = trim($target);
        if ($trimmed === '' || stripos($trimmed, 'javascript:') === 0) {
            return null;
        }

        $parts = parse_url($trimmed);
        if ($parts === false) {
            return null;
        }

        if (isset($parts['scheme'])) {
            $scheme = strtolower((string)$parts['scheme']);
            if (!in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            if (!empty($parts['host']) && $currentHost !== '' && strcasecmp($parts['host'], $currentHost) !== 0) {
                return null; // blocca redirect verso host esterni
            }

            $trimmed = $parts['path'] ?? '/';
            if (isset($parts['query'])) {
                $trimmed .= '?' . $parts['query'];
            }
            if (isset($parts['fragment'])) {
                $trimmed .= '#' . $parts['fragment'];
            }
        } elseif (substr($trimmed, 0, 2) === '//') {
            return null; // blocca schemi impliciti tipo //evil.com
        }

        if (strpos($trimmed, "\n") !== false || strpos($trimmed, "\r") !== false) {
            return null;
        }

        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] !== '/') {
            $trimmed = '/' . ltrim($trimmed, '/');
        }

        $path = parse_url($trimmed, PHP_URL_PATH) ?: '';
        if (preg_match('#^/login\.php$#i', $path)) {
            return null; // evita loop su login.php
        }

        return $trimmed;
    }
}

if (!function_exists('login_remember_redirect')) {
    function login_remember_redirect(?string $target = null, string $fallback = '/index.php'): string
    {
        $safe = login_sanitize_redirect($target) ?: $fallback;
        $_SESSION['login_redirect'] = $safe;
        return $safe;
    }
}

if (!function_exists('login_get_redirect')) {
    function login_get_redirect(string $fallback = '/index.php'): string
    {
        return login_sanitize_redirect($_SESSION['login_redirect'] ?? null) ?: $fallback;
    }
}
