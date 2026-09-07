<?php
require_once __DIR__ . '/../includi/all_in_one_calendar.php';
function check($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function teams(int $n, string $group = 'A', int $offset = 0): array {
    $teams = [];
    for ($i = 1; $i <= $n; $i++) $teams[] = ['id' => $offset + $i, 'nome' => "$group$i", 'girone' => $group];
    return $teams;
}
$start = new DateTimeImmutable('2026-09-07 20:00', new DateTimeZone('Europe/Rome'));
$teams = array_merge(teams(4), teams(4, 'B', 4));
$multiSlots = all_in_one_field_slots(['Impianto'], ['Impianto' => 2]);
$multiRows = all_in_one_calendar($teams, $multiSlots, $start);
check($multiRows[0]['campo'] === 'Impianto' && $multiRows[1]['campo'] === 'Impianto', 'Nome impianto mantenuto');
check($multiRows[0]['ora'] === $multiRows[1]['ora'] && $multiRows[2]['ora'] === '20:15:00', 'Due campi nello stesso impianto');
all_in_one_check_capacity($multiRows, [], $multiSlots);
$existing = [['campo' => 'Impianto', 'data' => '2026-09-07', 'ora' => '20:05:00', 'count' => 1]];
try {
    all_in_one_check_capacity($multiRows, $existing, $multiSlots);
    throw new LogicException('Sovrapposizione non rilevata');
} catch (RuntimeException $e) {
    check(strpos($e->getMessage(), 'Capienza superata') !== false, 'Errore capienza');
}
all_in_one_check_capacity(array_slice($multiRows, 0, 1), $existing, $multiSlots);
all_in_one_check_capacity(array_slice($multiRows, 0, 2), [['campo'=>'Impianto', 'data'=>'2026-09-07', 'ora'=>'19:45:00', 'count'=>2]], $multiSlots);
check(all_in_one_field_slots(['X','Y'], ['X'=>2,'Y'=>1]) === ['X','X','Y'], 'Capacita indipendente per impianto');
foreach ([0, -1, 33, 'abc', '1.5'] as $invalidCapacity) {
    try { all_in_one_field_slots(['X'], ['X'=>$invalidCapacity]); throw new LogicException('Capacita non validata'); }
    catch (InvalidArgumentException $e) {}
}
$rows = all_in_one_calendar($teams, ['Campo 1', 'Campo 2'], $start);
check(count($rows) === 12, 'Due gironi da quattro: dodici partite');
$expected = [['A1','A2'], ['A3','A4'], ['B1','B2'], ['B3','B4'], ['A1','A3'], ['A2','A4']];
foreach ($expected as $i => $pair) {
    check([$rows[$i]['casa'], $rows[$i]['ospite']] === $pair, 'Sequenza richiesta');
    check($rows[$i]['ora'] === $start->modify('+' . (intdiv($i, 2) * 15) . ' minutes')->format('H:i:s'), 'Fasce di 15 minuti');
}
check($rows[4]['giornata'] === 2 && $rows[8]['giornata'] === 3, 'Giornate progressive');
foreach ([1, 2, 3] as $fieldCount) {
    foreach (range(2, 11) as $n) {
        $rows = all_in_one_calendar(teams($n), array_slice(['C1','C2','C3'], 0, $fieldCount), $start);
        check(count($rows) === $n * ($n - 1) / 2, 'Tutte le coppie');
        $pairs = $slots = $teamSlots = [];
        foreach ($rows as $row) {
            $pair = [$row['casa'], $row['ospite']]; sort($pair);
            $key = implode('|', $pair);
            check(!isset($pairs[$key]), 'Nessuna coppia ripetuta'); $pairs[$key] = true;
            $slot = $row['data'] . $row['ora'];
            check(!isset($slots[$slot . $row['campo']]), 'Nessuna sovrapposizione campo'); $slots[$slot . $row['campo']] = true;
            foreach ($pair as $team) {
                check(!isset($teamSlots[$slot . $team]), 'Nessuna squadra in contemporanea'); $teamSlots[$slot . $team] = true;
            }
        }
    }
}
$rows = all_in_one_calendar($teams, ['C1'], $start->setTime(23, 45));
check($rows[1]['data'] === '2026-09-08' && $rows[1]['ora'] === '00:00:00', 'Passaggio mezzanotte');
check($rows[2]['girone'] === 'B' && $rows[2]['ora'] === '00:15:00', 'Un solo campo');
foreach ([[], [['id'=>1,'nome'=>'A','girone'=>'']], teams(1)] as $invalid) {
    try { all_in_one_calendar($invalid, ['C1'], $start); throw new LogicException('Validazione mancante'); }
    catch (InvalidArgumentException $e) {}
}
echo "OK: sequenza, completezza, unicità, campi, gironi dispari, mezzanotte e validazioni.\n";
