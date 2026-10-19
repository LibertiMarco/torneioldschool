<?php
require_once __DIR__ . '/../includi/referees.php';
require_once __DIR__ . '/../includi/player_awards.php';
function revision_expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE staff(id INTEGER PRIMARY KEY, nome TEXT, ruolo TEXT, categoria TEXT);
CREATE TABLE partite(arbitro TEXT);
INSERT INTO staff(nome,ruolo,categoria) VALUES ('Mario Rossi','Arbitro','arbitro');
INSERT INTO partite VALUES ('  MARIO   ROSSI  ');");
$directory = new RefereeDirectory($db);
revision_expect(count($directory->names()) === 7, 'Seeds plus existing registry, deduplicated');
revision_expect($directory->create(' mario   ROSSI ') === 'Mario Rossi', 'Case/space variants reuse existing identity');
$directory->create('Nuovo Arbitro');
revision_expect($directory->create(' NUOVO  arbitro ') === 'Nuovo Arbitro', 'New value persists and is deduplicated');
revision_expect((int)$db->query('SELECT COUNT(*) FROM staff')->fetchColumn() === 2, 'No duplicate records');
try { $directory->create('SoloNome'); throw new RuntimeException('Invalid name accepted'); } catch (InvalidArgumentException $expected) {}
$awards = sort_player_awards([
    ['id'=>999,'anno'=>'2020','created_at'=>'2030-01-01'],
    ['id'=>1,'anno'=>'2026','data_riconoscimento'=>'2026-08-01'],
    ['id'=>2,'anno'=>'2026','data_riconoscimento'=>'2026-10-01'],
    ['id'=>3,'anno'=>'2026','data_riconoscimento'=>'2026-10-01'],
    ['id'=>4,'anno'=>''],
]);
revision_expect(array_column($awards,'id') === [2,3,1,999,4], 'Recognition chronology, stable ties and unknown dates last; insertion date ignored');
echo "App revision: referee persistence, normalization and award chronology passed.\n";
