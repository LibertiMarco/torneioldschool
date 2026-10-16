<?php
require_once __DIR__ . '/../includi/mobile_push.php';
function push_expect(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('SHA2', static fn($value, $bits) => hash('sha256', $value), 2);
$db->exec('CREATE TABLE mobile_push_devices (token_hash TEXT, token TEXT, user_id INTEGER, session_id TEXT, active INTEGER);
CREATE TABLE mobile_sessions (id TEXT, user_id INTEGER, revoked_at INTEGER, refresh_expires_at INTEGER, password_fingerprint TEXT);
CREATE TABLE utenti (id INTEGER, password TEXT, email_verificata INTEGER);');
$db->exec("INSERT INTO utenti VALUES (1, 'password-hash', 1), (2, 'other-hash', 1)");
$stmt = $db->prepare('INSERT INTO mobile_sessions VALUES (?, ?, NULL, ?, ?)');
$stmt->execute(['s1', 1, time() + 3600, hash('sha256', 'password-hash')]);
$stmt->execute(['s2', 2, time() + 3600, hash('sha256', 'other-hash')]);
$db->exec("INSERT INTO mobile_push_devices VALUES ('h1', 'token1', 1, 's1', 1), ('h2', 'token2', 2, 's2', 1)");
$recipients = static function () use ($db): array {
    $stmt = $db->prepare(tos_fcm_recipients_sql('?'));
    $stmt->execute([1, time()]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
};
push_expect($recipients() === ['token1'], 'Only requested account receives push');
$db->exec('UPDATE mobile_sessions SET revoked_at = 1 WHERE user_id = 1');
push_expect($recipients() === [], 'Logout blocks delivery');
$db->exec('UPDATE mobile_sessions SET revoked_at = NULL, refresh_expires_at = 1 WHERE user_id = 1');
push_expect($recipients() === [], 'Expired session blocks delivery');
$db->exec('UPDATE mobile_sessions SET refresh_expires_at = 9999999999 WHERE user_id = 1');
$db->exec("UPDATE utenti SET password = 'changed' WHERE id = 1");
push_expect($recipients() === [], 'Password change blocks stale session delivery');
$db->exec("UPDATE utenti SET password = 'password-hash', email_verificata = 0 WHERE id = 1");
push_expect($recipients() === [], 'Unverified account receives nothing');
$db->exec('UPDATE utenti SET email_verificata = 1 WHERE id = 1');
$db->exec('UPDATE mobile_push_devices SET active = 0 WHERE user_id = 1');
push_expect($recipients() === [], 'Disabled device receives nothing');
foreach (['https://evil.test/path', '//evil.test', 'http://torneioldschool.it', 'https://torneioldschool.it:444/'] as $url) {
    $message = tos_fcm_message('test-token', ['url' => $url]);
    push_expect($message['message']['data']['url'] === 'https://torneioldschool.it/account.php', 'Unsafe link rejected');
}
$message = tos_fcm_message('test-token', ['url' => '/articolo.php?id=4', 'title' => 'Risposta']);
push_expect($message['message']['data']['url'] === 'https://torneioldschool.it/articolo.php?id=4', 'Relative site link retained');
push_expect($message['message']['android']['restricted_package_name'] === 'it.torneioldschool.tornei_old_school', 'Package restricted');
push_expect(!tos_fcm_unregistered(['error' => ['code' => 404]]), 'Generic 404 does not delete token');
push_expect(tos_fcm_unregistered(['error' => ['details' => [[
    '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED',
]]]]), 'Only FCM UNREGISTERED removes token');
push_expect(tos_fcm_token_valid(str_repeat('a', 140) . ':token'), 'FCM token accepted');
push_expect(!tos_fcm_token_valid("bad\nheader") && !tos_fcm_token_valid(str_repeat('a', 4097)), 'Invalid tokens rejected');
echo "Mobile push: recipient isolation, session revocation, links and FCM failures verified.\n";
