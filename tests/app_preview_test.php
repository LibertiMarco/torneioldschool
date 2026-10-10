<?php
require_once __DIR__ . '/../includi/app_preview.php';
require_once __DIR__ . '/../includi/mobile_account_features.php';
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY, email TEXT, nome TEXT, cognome TEXT, ruolo TEXT, avatar TEXT, email_verificata INTEGER, feature_flags TEXT)');
$db->exec("INSERT INTO utenti VALUES (1, 'admin@example.test', 'Admin', 'Fixture', 'admin', NULL, 1, NULL), (2, 'user@example.test', 'User', 'Fixture', 'user', NULL, 1, NULL)");
function preview_expect(bool $value, string $message): void {
    if (!$value) { throw new RuntimeException($message); }
}
function preview_reject(Closure $action, string $code): void {
    try { $action(); } catch (MobileAuthError $error) {
        preview_expect($error->errorCode === $code, 'Expected ' . $code);
        return;
    }
    throw new RuntimeException('Expected rejection ' . $code);
}
preview_reject(static fn() => preview_admin($db, []), 'invalid_session');
preview_reject(static fn() => preview_admin($db, ['user_id' => 2, 'ruolo' => 'admin']), 'forbidden');
preview_expect(preview_admin($db, ['user_id' => 1])['permissions']['admin'], 'Current admin accepted');
$service = new MobileAccountFeatures($db, static fn() => preview_admin($db, ['user_id' => 1]));
$before = $service->get('', 2);
$saved = $service->save('', 2, ['totocalcio' => true, 'fantacalcio' => false], $before['revision']);
preview_expect($saved['user']['feature_flags']['totocalcio'], 'Cookie guard uses shared account operations');
$db->exec("UPDATE utenti SET ruolo = 'grafico' WHERE id = 1");
preview_reject(static fn() => $service->save('', 2, ['totocalcio' => false, 'fantacalcio' => false], $saved['revision']), 'forbidden');
$db->exec("UPDATE utenti SET ruolo = 'sysadmin', email_verificata = 0 WHERE id = 1");
preview_reject(static fn() => preview_admin($db, ['user_id' => 1]), 'invalid_session');
$db->exec('DELETE FROM utenti WHERE id = 1');
preview_reject(static fn() => preview_admin($db, ['user_id' => 1]), 'invalid_session');
echo "App preview: all checks passed (SQLite fixture).\n";
