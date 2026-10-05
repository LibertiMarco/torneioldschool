<?php
$source = file_get_contents(__DIR__ . '/../api/gestione_partite.php');
if (!preg_match('/function round_supports_two_legs\([^\n]+\).*?\r?\n}\r?\n/s', $source, $match)) throw new RuntimeException('Funzione non trovata');
eval($match[0]);
function expect_silver($actual, bool $expected, string $message): void {
    if ($actual !== $expected) throw new RuntimeException($message);
}
expect_silver(round_supports_two_legs('FINALE', 'McLeague', 'SILVER'), true, 'Finale Silver McLeague');
expect_silver(round_supports_two_legs('finale', 'mc-league', 'silver'), true, 'Normalizzazione');
expect_silver(round_supports_two_legs('FINALE', 'McLeague', 'GOLD'), false, 'Finale Gold invariata');
expect_silver(round_supports_two_legs('FINALE', 'SerieA', 'SILVER'), false, 'Altri tornei invariati');
expect_silver(round_supports_two_legs('SEMIFINALE', 'McLeague', 'GOLD'), true, 'Semifinali esistenti');
expect_silver(round_supports_two_legs(null, 'McLeague', 'SILVER'), false, 'Turno mancante');
echo "OK: regola andata/ritorno finale Silver McLeague\n";
