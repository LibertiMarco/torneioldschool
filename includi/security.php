<?php
// Session hardening: set secure cookie flags and strict mode before starting
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    if (function_exists('session_set_cookie_params')) {
        session_set_cookie_params([
            'lifetime' => 0,
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
