<?php
require_once __DIR__ . '/../includi/mobile_account_delete.php';
function check_delete(bool $ok): void { if (!$ok) throw new RuntimeException('Delete assertion failed'); }
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY, password TEXT);
CREATE TABLE consensi_log (user_id INTEGER);
CREATE TABLE mobile_auth_codes (user_id INTEGER);
CREATE TABLE mobile_sessions (id TEXT PRIMARY KEY, user_id INTEGER);
CREATE TABLE mobile_refresh_tokens (session_id TEXT);');
$db->prepare('INSERT INTO utenti VALUES (1, ?), (2, ?)')->execute([
    password_hash(' exact password ', PASSWORD_DEFAULT), password_hash('other', PASSWORD_DEFAULT)
]);
foreach (['consensi_log', 'mobile_auth_codes'] as $table) $db->exec("INSERT INTO $table VALUES (1), (2)");
$db->exec("INSERT INTO mobile_sessions VALUES ('one', 1), ('two', 2);
INSERT INTO mobile_refresh_tokens VALUES ('one'), ('two');");
foreach ([['password'=>'wrong', 'confirm_delete'=>true], ['password'=>' exact password ', 'confirm_delete'=>false]] as $input) {
    try { mobile_delete_account($db, 1, $input); throw new RuntimeException('Expected rejection'); }
    catch (MobileAuthError $error) { check_delete((int)$db->query('SELECT COUNT(*) FROM utenti')->fetchColumn() === 2); }
}
// A database failure must roll back every prior deletion.
$db->exec("CREATE TRIGGER fail_delete BEFORE DELETE ON utenti BEGIN SELECT RAISE(ABORT, 'fixture'); END");
try { mobile_delete_account($db, 1, ['password'=>' exact password ', 'confirm_delete'=>true]); }
catch (PDOException $error) {}
check_delete((int)$db->query('SELECT COUNT(*) FROM mobile_sessions')->fetchColumn() === 2);
$db->exec('DROP TRIGGER fail_delete');
check_delete(mobile_delete_account($db, 1, ['password'=>' exact password ', 'confirm_delete'=>true])['success']);
foreach (['utenti', 'consensi_log', 'mobile_auth_codes', 'mobile_sessions', 'mobile_refresh_tokens'] as $table) {
    check_delete((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn() === 1);
}
check_delete((int)$db->query('SELECT id FROM utenti')->fetchColumn() === 2);
echo "Mobile account deletion tests passed\n";
