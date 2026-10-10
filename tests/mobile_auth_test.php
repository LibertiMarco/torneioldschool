<?php
require_once __DIR__ . '/../includi/mobile_auth.php';
function mobile_expect(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function mobile_rejected(Closure $action, string $code): void {
    try { $action(); } catch (MobileAuthError $error) {
        mobile_expect($error->errorCode === $code, 'Expected ' . $code . ', received ' . $error->errorCode);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $code);
}
// In-memory fixture: never touches the configured site database.
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY, email TEXT, nome TEXT, cognome TEXT, ruolo TEXT, avatar TEXT, password TEXT, email_verificata INTEGER, feature_flags TEXT);
CREATE TABLE mobile_auth_codes (code_hash TEXT PRIMARY KEY, user_id INTEGER, challenge TEXT, password_fingerprint TEXT, expires_at INTEGER);
CREATE TABLE mobile_sessions (id TEXT PRIMARY KEY, user_id INTEGER, password_fingerprint TEXT, access_hash TEXT UNIQUE, access_expires_at INTEGER, refresh_expires_at INTEGER, revoked_at INTEGER);
CREATE TABLE mobile_refresh_tokens (token_hash TEXT PRIMARY KEY, session_id TEXT, expires_at INTEGER, used_at INTEGER);');
$db->exec("INSERT INTO utenti VALUES (1, 'fixture@example.test', 'Test', 'User', 'user', NULL, 'test-password-hash', 1, '{\"totocalcio\":true}')");
$now = 10000;
$auth = new MobileAuth($db, static function () use (&$now): int { return $now; });
$verifier = str_repeat('a', 64);
$challenge = MobileAuth::challenge($verifier);
mobile_expect(MobileAuth::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk') === 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', 'RFC 7636 S256 vector');
$request = ['client_id' => 'tos-mobile', 'redirect_uri' => MobileAuth::CALLBACK, 'response_type' => 'code',
    'code_challenge_method' => 'S256', 'code_challenge' => $challenge, 'state' => str_repeat('s', 43)];
MobileAuth::validateAuthorization($request);
mobile_rejected(static fn() => MobileAuth::validateAuthorization(array_replace($request, ['redirect_uri' => 'https://evil.example'])), 'invalid_request');
mobile_rejected(static fn() => MobileAuth::validateAuthorization(array_replace($request, ['code_challenge_method' => 'plain'])), 'invalid_request');
$code = $auth->authorize(1, $challenge);
mobile_rejected(static fn() => $auth->exchange($code, str_repeat('b', 64)), 'invalid_grant');
$tokens = $auth->exchange($code, $verifier);
mobile_rejected(static fn() => $auth->exchange($code, $verifier), 'invalid_grant');
$user = $auth->authenticate($tokens['access_token']);
mobile_expect($user['feature_flags']['totocalcio'] && !$user['permissions']['admin'], 'Current flags and role');
mobile_expect(!isset($user['password']) && !isset($user['password_fingerprint']), 'Private fields excluded');
mobile_rejected(static fn() => $auth->requirePermission($tokens['access_token'], 'admin'), 'forbidden');
$db->exec("UPDATE utenti SET ruolo = 'admin' WHERE id = 1");
mobile_expect($auth->requirePermission($tokens['access_token'], 'admin')['permissions']['admin'], 'Promotion effective without new login');
$db->exec("UPDATE utenti SET ruolo = 'grafico' WHERE id = 1");
mobile_rejected(static fn() => $auth->requirePermission($tokens['access_token'], 'admin'), 'forbidden');
mobile_expect($auth->requirePermission($tokens['access_token'], 'graphics')['permissions']['graphics'], 'Graphics-only role');
$now += MobileAuth::ACCESS_TTL;
mobile_rejected(static fn() => $auth->authenticate($tokens['access_token']), 'invalid_session');
$next = $auth->refresh($tokens['refresh_token']);
mobile_rejected(static fn() => $auth->authenticate($tokens['access_token']), 'invalid_session');
$auth->authenticate($next['access_token']);
$third = $auth->refresh($next['refresh_token']);
mobile_rejected(static fn() => $auth->refresh($tokens['refresh_token']), 'invalid_grant');
mobile_rejected(static fn() => $auth->authenticate($third['access_token']), 'invalid_session');
mobile_rejected(static fn() => $auth->refresh($third['refresh_token']), 'invalid_grant');
$issue = static fn(): array => $auth->exchange($auth->authorize(1, $challenge), $verifier);
$tokens = $issue();
$auth->logout($tokens['access_token']);
mobile_rejected(static fn() => $auth->refresh($tokens['refresh_token']), 'invalid_grant');
$tokens = $issue();
$db->exec("UPDATE utenti SET password = 'changed' WHERE id = 1");
mobile_rejected(static fn() => $auth->authenticate($tokens['access_token']), 'invalid_session');
mobile_rejected(static fn() => $auth->refresh($tokens['refresh_token']), 'invalid_session');
$tokens = $issue();
$db->exec('UPDATE utenti SET email_verificata = 0 WHERE id = 1');
mobile_rejected(static fn() => $auth->authenticate($tokens['access_token']), 'invalid_session');
mobile_rejected(static fn() => $auth->authorize(1, $challenge), 'invalid_session');
$db->exec('UPDATE utenti SET email_verificata = 1 WHERE id = 1');
$code = $auth->authorize(1, $challenge);
$now += 120;
mobile_rejected(static fn() => $auth->exchange($code, $verifier), 'invalid_grant');
$tokens = $issue();
$now += MobileAuth::REFRESH_TTL;
mobile_rejected(static fn() => $auth->refresh($tokens['refresh_token']), 'invalid_grant');
$tokens = $issue();
$db->exec('DELETE FROM utenti WHERE id = 1');
mobile_rejected(static fn() => $auth->authenticate($tokens['access_token']), 'invalid_session');
mobile_rejected(static fn() => $auth->refresh($tokens['refresh_token']), 'invalid_session');
$persisted = $db->query('SELECT access_hash FROM mobile_sessions')->fetchAll(PDO::FETCH_COLUMN);
mobile_expect(!in_array($tokens['access_token'], $persisted, true), 'Access tokens not stored in clear');
echo "Mobile auth: all checks passed (SQLite fixture).\n";
