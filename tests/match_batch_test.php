<?php
require_once __DIR__ . '/../includi/match_batch.php';
function check_batch(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$tournaments = [['slug' => 'coppa', 'sezione' => 'calcio'], ['slug' => 'esport', 'sezione' => 'esport']];
$teams = ['coppa' => ['A', 'B', 'C', 'D'], 'esport' => ['A', 'B', 'C', 'D']];
$fields = [['nome' => 'Campo 1']];
$input = ['torneo' => 'coppa', 'fase' => 'REGULAR', 'campo' => 'Campo 1', 'data_partita' => '2026-11-09', 'giornata' => '1', 'partite_count' => '2', 'partite' => [
    ['squadra_casa' => 'A', 'squadra_ospite' => 'B', 'ora_partita' => '18:30'],
    ['squadra_casa' => 'C', 'squadra_ospite' => 'D', 'ora_partita' => '19:30'],
]];
$validate = static fn($value) => match_batch_inputs($value, $tournaments, $teams, $fields);
$rows = $validate($input);
check_batch(count($rows) === 2 && $rows[1]['campo'] === 'Campo 1' && $rows[1]['squadra_casa'] === 'C', 'Shared context and match rows');
$invalid = [];
$value = $input; $value['partite'][1]['squadra_casa'] = 'A'; $invalid[] = $value;
$value = $input; $value['partite'][1]['squadra_casa'] = 'X'; $invalid[] = $value;
$value = $input; $value['partite'][1]['squadra_casa'] = 'D'; $invalid[] = $value;
$value = $input; $value['partite'][1]['ora_partita'] = '18:30'; $invalid[] = $value;
$value = $input; $value['partite'][1]['ora_partita'] = '25:99'; $invalid[] = $value;
$value = $input; $value['data_partita'] = '2026-02-30'; $invalid[] = $value;
$value = $input; $value['torneo'] = 'archiviato'; $invalid[] = $value;
$value = $input; $value['fase'] = 'INVALID'; $invalid[] = $value;
$value = $input; $value['campo'] = 'Sconosciuto'; $invalid[] = $value;
$value = $input; $value['partite_count'] = '3'; $invalid[] = $value;
$value = $input; $value['partite'] = []; $invalid[] = $value;
$value = $input; $value['partite'] = array_fill(0, 51, $input['partite'][0]); $invalid[] = $value;
$value = $input; $value['partite'][0]['ora_partita'] = ['18:30']; $invalid[] = $value;
foreach ($invalid as $index => $value) {
    try { $validate($value); throw new RuntimeException('Accepted invalid case ' . $index); }
    catch (InvalidArgumentException $expected) { }
}
$online = $input; $online['torneo'] = 'esport'; $online['campo'] = 'Online'; $online['partite'][1]['ora_partita'] = '18:30';
check_batch(count($validate($online)) === 2, 'Online tournaments permit simultaneous starts');

// Actual database transactions in an isolated in-memory database; no website data.
$db = new class {
    public PDO $pdo;
    function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->exec('CREATE TABLE matches (home TEXT, away TEXT)'); }
    function begin_transaction(): bool { return $this->pdo->beginTransaction(); }
    function commit(): bool { return $this->pdo->commit(); }
    function rollback(): bool { return $this->pdo->rollBack(); }
};
$insert = static function ($row) use ($db): string {
    $db->pdo->prepare('INSERT INTO matches VALUES (?, ?)')->execute([$row['squadra_casa'], $row['squadra_ospite']]);
    return '';
};
match_batch_save($db, $rows, $insert);
check_batch((int)$db->pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn() === 2, 'All rows committed');
try {
    match_batch_save($db, $rows, static function ($row) use ($insert): string {
        $insert($row); // Also models automatic return inserts performed by the controller.
        return $row['squadra_casa'] === 'C' ? 'Conflitto sulla seconda partita' : '';
    });
    throw new RuntimeException('Conflict not rejected');
} catch (InvalidArgumentException $expected) { }
check_batch((int)$db->pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn() === 2, 'Conflict rolls back the entire batch');
try {
    match_batch_save($db, $rows, static function ($row) use ($insert): string { $insert($row); throw new RuntimeException('Database failure'); });
} catch (RuntimeException $expected) { }
check_batch((int)$db->pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn() === 2, 'Exceptions also roll back');
echo "Batch validation and atomic transactions passed.\n";
