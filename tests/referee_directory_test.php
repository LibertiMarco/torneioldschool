<?php
require_once __DIR__ . '/../includi/referees.php';
function referee_expect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE staff (nome TEXT, ruolo TEXT, categoria TEXT)");
$db->exec("CREATE TABLE partite (arbitro TEXT)");
$db->exec("INSERT INTO partite VALUES ('Arbitro Storico')");
$service = new RefereeDirectory($db);
$service->remove('Vincenzo Amoroso');
$service->remove('Arbitro Storico');
referee_expect(!in_array('Vincenzo Amoroso', $service->names(), true), 'Defaults stay removed');
referee_expect(!in_array('Arbitro Storico', (new RefereeDirectory($db))->names(), true), 'History cannot restore removed entries');
referee_expect($service->create('Arbitro Storico') === 'Arbitro Storico', 'Explicit add restores removed names');
$service->rename('Arbitro Storico', 'Arbitro Corretto');
referee_expect(!in_array('Arbitro Storico', $service->names(), true), 'Rename suppresses old history');
referee_expect(in_array('Arbitro Corretto', $service->names(), true), 'Renamed entry available');
referee_expect($db->query("SELECT arbitro FROM partite")->fetchColumn() === 'Arbitro Storico', 'Match history unchanged');
$service->rename('Arbitro Corretto', 'ARBITRO Corretto');
referee_expect(in_array('ARBITRO Corretto', $service->names(), true), 'Spelling changes supported');
try {
    $service->rename('ARBITRO Corretto', 'Alessio Scognamiglio');
    throw new RuntimeException('Expected duplicate rejection');
} catch (InvalidArgumentException $expected) {}
referee_expect(in_array('ARBITRO Corretto', $service->names(), true), 'Failed edits preserve directory');
$service->remove('ARBITRO Corretto');
referee_expect(!in_array('ARBITRO Corretto', (new RefereeDirectory($db))->names(), true), 'Removed staff names stay removed');
try {
    $service->create('<script>');
    throw new RuntimeException('Expected invalid name rejection');
} catch (InvalidArgumentException $expected) {}
echo "Referee directory: removal, restore, rename, duplicates and historical matches passed.\n";
