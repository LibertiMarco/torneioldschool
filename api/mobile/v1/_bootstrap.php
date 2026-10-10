<?php
require_once __DIR__ . '/../../../includi/env_loader.php';
require_once __DIR__ . '/../../../includi/mobile_auth.php';
require_once __DIR__ . '/../../../includi/mobile_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
ini_set('display_errors', '0');

function mobile_json(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function mobile_run(Closure $handler): never
{
    try {
        if (!mobile_api_enabled()) {
            throw new MobileAuthError('unavailable', 'Accesso mobile non ancora attivo.', 503);
        }
        $https = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
        $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        if (!$https && !($local && getenv('MOBILE_ALLOW_LOCAL_HTTP') === '1')) {
            throw new MobileAuthError('https_required', 'È richiesta una connessione HTTPS.', 400);
        }
        mobile_json($handler());
    } catch (MobileAuthError $error) {
        mobile_json(['error' => ['code' => $error->errorCode, 'message' => $error->getMessage()]], $error->status);
    } catch (Throwable $error) {
        error_log('Mobile API: ' . get_class($error) . ' request failed');
        mobile_json(['error' => ['code' => 'server_error', 'message' => 'Servizio temporaneamente non disponibile.']], 503);
    }
}

function mobile_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        throw new MobileAuthError('method_not_allowed', 'Metodo non consentito.', 405);
    }
}

function mobile_input(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if (strlen($raw) > 8192) { throw new MobileAuthError('invalid_request', 'Richiesta troppo grande.', 413); }
    try { $body = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
    catch (JsonException $error) { throw new MobileAuthError('invalid_request', 'Richiesta JSON non valida.', 400); }
    if (!is_array($body)) { throw new MobileAuthError('invalid_request', 'Richiesta non valida.', 400); }
    return $body;
}

function mobile_bearer(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D', $header, $match)) {
        throw new MobileAuthError('invalid_session', 'Accesso richiesto.');
    }
    return $match[1];
}

function mobile_db(): PDO
{
    static $db;
    if (!$db) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $name = getenv('DB_NAME') ?: '';
        if (!preg_match('/^[A-Za-z0-9_.-]+$/D', $host) || !preg_match('/^[A-Za-z0-9_-]+$/D', $name)) {
            throw new RuntimeException('Invalid database configuration');
        }
        $port = (int)(getenv('DB_PORT') ?: 3306);
        $db = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $db;
}
