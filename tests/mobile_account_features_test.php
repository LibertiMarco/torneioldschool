<?php
require_once __DIR__ . '/../includi/mobile_account_features.php';
function features_expect(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function features_reject(Closure $action, string $code): void {
    try { $action(); } catch (MobileAuthError $error) {
        features_expect($error->errorCode === $code, 'Unexpected error: ' . $error->errorCode);
        return;
    }
    throw new RuntimeException('Expected ' . $code);
}
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY, email TEXT, nome TEXT, cognome TEXT, ruolo TEXT, avatar TEXT, password TEXT, email_verificata INTEGER, feature_flags TEXT);
CREATE TABLE mobile_auth_codes (code_hash TEXT PRIMARY KEY, user_id INTEGER, challenge TEXT, password_fingerprint TEXT, expires_at INTEGER);
CREATE TABLE mobile_sessions (id TEXT PRIMARY KEY, user_id INTEGER, password_fingerprint TEXT, access_hash TEXT UNIQUE, access_expires_at INTEGER, refresh_expires_at INTEGER, revoked_at INTEGER);
CREATE TABLE mobile_refresh_tokens (token_hash TEXT PRIMARY KEY, session_id TEXT, expires_at INTEGER, used_at INTEGER);');
$db->exec("INSERT INTO utenti VALUES (1, 'admin@example.test', 'Admin', 'Demo', 'admin', NULL, 'hash1', 1, NULL),
(2, 'user@example.test', 'User', 'Demo', 'user', NULL, 'hash2', 1, '{\"totocalcio\":false,\"fantacalcio\":true}')");
$auth = new MobileAuth($db);
$verifier = str_repeat('v', 64);
$issue = static fn(int $id): array => $auth->exchange($auth->authorize($id, MobileAuth::challenge($verifier)), $verifier);
$admin = $issue(1)['access_token'];
$user = $issue(2)['access_token'];
$service = new MobileAccountFeatures($db, $auth);
features_reject(static fn() => $service->get($user, 2), 'forbidden');
$before = $service->get($admin, 2);
features_expect(!isset($before['user']['password']), 'No private fields');
$saved = $service->save($admin, 2, ['totocalcio' => true, 'fantacalcio' => false], $before['revision']);
features_expect($saved['user']['feature_flags'] === ['totocalcio' => true, 'fantacalcio' => false], 'Saved flags');
features_expect($auth->authenticate($user)['feature_flags'] === $saved['user']['feature_flags'], 'Existing session sees change');
features_reject(static fn() => $service->save($admin, 2, ['totocalcio' => false, 'fantacalcio' => true], $before['revision']), 'conflict');
features_reject(static fn() => $service->save($admin, 2, ['totocalcio' => 'false', 'fantacalcio' => true], $saved['revision']), 'invalid_request');
features_reject(static fn() => $service->save($admin, 2, ['totocalcio' => false, 'fantacalcio' => true, 'admin' => true], $saved['revision']), 'invalid_request');
features_reject(static fn() => $service->save($admin, 2, ['totocalcio' => false], $saved['revision']), 'invalid_request');
features_reject(static fn() => $service->get($admin, 999), 'not_found');
$null = $service->get($admin, 1);
$service->save($admin, 1, ['totocalcio' => true, 'fantacalcio' => false], $null['revision']);
$db->exec("UPDATE utenti SET ruolo = 'grafico' WHERE id = 1");
features_reject(static fn() => $service->save($admin, 2, ['totocalcio' => false, 'fantacalcio' => true], $saved['revision']), 'forbidden');
echo "Mobile account features: all checks passed (SQLite fixture).\n";
