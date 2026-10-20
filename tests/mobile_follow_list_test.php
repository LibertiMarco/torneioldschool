<?php
require_once __DIR__ . '/../includi/mobile_auth.php';
require_once __DIR__ . '/../includi/mobile_follow.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('CONCAT', static fn(...$parts) => implode('', $parts));
$db->exec("CREATE TABLE tornei (id INTEGER PRIMARY KEY, filetorneo TEXT, nome TEXT, img TEXT, sezione TEXT);
CREATE TABLE squadre (id INTEGER PRIMARY KEY, nome TEXT, torneo TEXT, logo TEXT);
CREATE TABLE seguiti (utente_id INTEGER, tipo TEXT, torneo_slug TEXT, squadra_nome TEXT);
INSERT INTO tornei VALUES (1, 'summer.php', 'Summer', '/summer.png', 'calcio'),
 (2, 'winter.html', 'Winter', '/winter.png', 'calcio'), (3, 'ea.php', 'EA', '', 'esport');
INSERT INTO squadre VALUES (1, 'Alpha', 'summer', '/alpha-summer.png'),
 (2, 'Alpha', 'winter', '/alpha-winter.png'), (3, 'Alpha', 'winter', '/duplicate.png');
INSERT INTO seguiti VALUES (1, 'torneo', 'summer', NULL), (1, 'torneo', 'summer', NULL),
 (1, 'squadra', 'summer', 'Alpha'), (1, 'squadra', 'winter', 'Alpha'),
 (1, 'squadra', 'winter', 'Removed'), (1, 'torneo', 'deleted', NULL),
 (1, 'torneo', 'ea', NULL), (2, 'torneo', 'winter', NULL);");
function favorite_expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$before = $db->query('SELECT COUNT(*) FROM seguiti')->fetchColumn();
$rows = mobile_follow_handle($db, 1, ['list' => '1', 'section' => 'calcio'], false)['items'];
favorite_expect(count($rows) === 2, 'Deduplicate tournament follows, suppress legacy teams and removed entities');
favorite_expect($rows[0]['tipo'] === 'torneo' && $rows[0]['torneo_slug'] === 'summer', 'Tournament shortcut');
favorite_expect($rows[1]['tipo'] === 'squadra' && $rows[1]['squadra_logo'] === '/alpha-winter.png', 'Resolve team logo within the right tournament, once');
$other = mobile_follow_handle($db, 2, ['list' => '1'], false)['items'];
favorite_expect(count($other) === 1 && $other[0]['torneo_slug'] === 'winter', 'Account isolation');
$esport = mobile_follow_handle($db, 1, ['list' => '1', 'section' => 'esport'], false)['items'];
favorite_expect(count($esport) === 1 && $esport[0]['torneo_slug'] === 'ea', 'Section isolation');
foreach ([['list' => '1', 'section' => []], ['list' => '1', 'section' => 'invalid']] as $input) {
    try { mobile_follow_handle($db, 1, $input, false); throw new RuntimeException('Invalid section accepted'); }
    catch (MobileAuthError $error) { favorite_expect($error->status === 422, 'Validation'); }
}
try { mobile_follow_handle($db, 0, ['list' => '1'], false); throw new RuntimeException('Guest accepted'); }
catch (MobileAuthError $error) { favorite_expect($error->status === 401, 'Authentication'); }
favorite_expect($before === $db->query('SELECT COUNT(*) FROM seguiti')->fetchColumn(), 'Listing never changes subscriptions');
echo "Favorites listing: account/section isolation, logos, duplicates, removed entities and read-only behavior passed.\n";
