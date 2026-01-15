<?php
// Session hardening: set secure cookie flags and strict mode before starting
if (!defined('REMEMBER_COOKIE_NAME')) {
    define('REMEMBER_COOKIE_NAME', 'tos_keep_login');
}
if (!defined('REMEMBER_COOKIE_LIFETIME')) {
    define('REMEMBER_COOKIE_LIFETIME', 60 * 60 * 24 * 30); // 30 giorni
}

$isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

if (session_status() === PHP_SESSION_NONE) {
    $rememberRequested = !empty($_COOKIE[REMEMBER_COOKIE_NAME]);
    $cookieLifetime = $rememberRequested ? REMEMBER_COOKIE_LIFETIME : 0;

    // Mantieni i file di sessione per almeno 30 giorni (allineato al cookie remember)
    ini_set('session.gc_maxlifetime', (string)REMEMBER_COOKIE_LIFETIME);

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
        $cookieParams = [
            'path' => ($params['path'] ?? '/') ?: '/',
            'domain' => is_string($params['domain'] ?? '') ? ($params['domain'] ?? '') : '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        setcookie(session_name(), session_id(), array_merge($cookieParams, ['expires' => $expires]));

        $rememberValue = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '1';
        setcookie(REMEMBER_COOKIE_NAME, $rememberValue, array_merge($cookieParams, ['expires' => $expires]));
    }
}

// Helpers per remember-me: evitiamo 500 se il DB non e' disponibile
function tos_cookie_params(bool $isHttps): array
{
    $params = session_get_cookie_params();
    $samesite = $params['samesite'] ?? 'Lax';
    $samesite = is_string($samesite) ? strtolower($samesite) : 'lax';
    $allowed = ['lax', 'strict', 'none'];
    if (!in_array($samesite, $allowed, true)) {
        $samesite = 'lax';
    }
    $samesite = ucfirst($samesite);

    $path = $params['path'] ?? '/';
    $domain = $params['domain'] ?? '';

    return [
        'path' => $path === '' ? '/' : $path,
        'domain' => is_string($domain) ? $domain : '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $samesite,
    ];
}

function tos_clear_remember_cookie(bool $isHttps): void
{
    $params = tos_cookie_params($isHttps);
    setcookie(REMEMBER_COOKIE_NAME, '', time() - 3600, $params);
}

function tos_remember_db_connect()
{
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        return $conn;
    }

    $envLoader = __DIR__ . '/env_loader.php';
    if (file_exists($envLoader)) {
        require_once $envLoader;
    }
    if (!function_exists('env_or_default')) {
        function env_or_default(string $key, string $default = ''): string
        {
            $value = getenv($key);
            return ($value !== false && $value !== '') ? $value : $default;
        }
    }

    $host = env_or_default('DB_HOST', 'localhost');
    $user = env_or_default('DB_USER', '');
    $pass = env_or_default('DB_PASSWORD', '');
    $dbname = env_or_default('DB_NAME', '');

    if ($user === '' || $dbname === '') {
        error_log('remember auto-login: configurazione DB mancante, salto auto-login.');
        return null;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    try {
        $tmp = @new mysqli($host, $user, $pass, $dbname);
    } catch (Throwable $e) {
        error_log('remember auto-login: connessione DB fallita - ' . $e->getMessage());
        return null;
    }

    if ($tmp->connect_error) {
        error_log('remember auto-login: connessione DB fallita - ' . $tmp->connect_error);
        return null;
    }

    $tmp->set_charset('utf8mb4');
    $conn = $tmp;
    return $conn;
}

// Auto-login da cookie "ricorda" se la sessione e' scaduta ma esiste un token valido
if (!isset($_SESSION['user_id']) && !empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
    $rawRemember = (string)$_COOKIE[REMEMBER_COOKIE_NAME];
    if (strpos($rawRemember, ':') !== false) {
        [$selector, $validator] = explode(':', $rawRemember, 2);
        if ($selector !== '' && $validator !== '' && ctype_xdigit($selector) && ctype_xdigit($validator)) {
            $conn = tos_remember_db_connect();

            if ($conn instanceof mysqli) {
                try {
                    $stmt = $conn->prepare("SELECT id, email, nome, cognome, ruolo, avatar, remember_token_hash, remember_expires_at FROM utenti WHERE remember_selector = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('s', $selector);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $user = $res && $res->num_rows === 1 ? $res->fetch_assoc() : null;
                        $stmt->close();

                        $expiresAt = $user && !empty($user['remember_expires_at']) ? strtotime((string)$user['remember_expires_at']) : 0;
                        $tokenMatches = $user && !empty($user['remember_token_hash']) ? hash_equals($user['remember_token_hash'], hash('sha256', $validator)) : false;

                        if ($user && $expiresAt > time() && $tokenMatches) {
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['nome'] = $user['nome'];
                            $_SESSION['cognome'] = $user['cognome'];
                            $_SESSION['ruolo'] = $user['ruolo'];
                            $_SESSION['avatar'] = $user['avatar'] ?? null;
                            $_SESSION['remember_me'] = true;

                            $cookieParams = tos_cookie_params($isHttps);
                            $cookieLifetime = REMEMBER_COOKIE_LIFETIME;
                            $newSelector = bin2hex(random_bytes(9));
                            $newValidator = bin2hex(random_bytes(32));
                            $newHash = hash('sha256', $newValidator);
                            $newExpires = date('Y-m-d H:i:s', time() + $cookieLifetime);

                            $saveNewToken = $conn->prepare("UPDATE utenti SET remember_selector = ?, remember_token_hash = ?, remember_expires_at = ? WHERE id = ?");
                            if ($saveNewToken) {
                                $saveNewToken->bind_param('sssi', $newSelector, $newHash, $newExpires, $user['id']);
                                $saveNewToken->execute();
                                $saveNewToken->close();
                            }

                            $cookieExpires = time() + $cookieLifetime;
                            setcookie(session_name(), session_id(), array_merge($cookieParams, [
                                'expires' => $cookieExpires,
                            ]));
                            setcookie(REMEMBER_COOKIE_NAME, $newSelector . ':' . $newValidator, array_merge($cookieParams, [
                                'expires' => $cookieExpires,
                            ]));
                        } else {
                            $forget = $conn->prepare("UPDATE utenti SET remember_selector = NULL, remember_token_hash = NULL, remember_expires_at = NULL WHERE remember_selector = ?");
                            if ($forget) {
                                $forget->bind_param('s', $selector);
                                $forget->execute();
                                $forget->close();
                            }
                            tos_clear_remember_cookie($isHttps);
                        }
                    }
                } catch (Throwable $e) {
                    error_log('remember auto-login: ' . $e->getMessage());
                    tos_clear_remember_cookie($isHttps);
                }
            } else {
                tos_clear_remember_cookie($isHttps);
            }
        } else {
            tos_clear_remember_cookie($isHttps);
        }
    } else {
        tos_clear_remember_cookie($isHttps);
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
