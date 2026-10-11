<?php
require_once __DIR__ . '/../includi/diffidati.php';

function expect_diffida(array $actual, array $expected, string $message): void {
    if ($actual !== $expected) throw new RuntimeException($message);
}
function yellow(array $state, int $day, int $count = 1): array {
    return diffidati_apply_event($state, ['giornata' => $day, 'cartellino_giallo' => $count]);
}
$state = yellow([], 3);
expect_diffida($state, [3], 'Primo giallo');
$state = yellow($state, 7);
expect_diffida($state, [3, 7], 'Diffida con giornate delle ammonizioni');
expect_diffida(yellow($state, 8), [], 'Un altro giallo sconta la diffida');
expect_diffida(diffidati_apply_event($state, ['cartellino_rosso' => 1]), [], 'Espulsione sconta la diffida');
expect_diffida(diffidati_apply_event($state, ['cartellino_rosso' => 1, 'cartellino_giallo' => 1]), [], 'Espulsione e giallo azzerano la diffida');
expect_diffida(diffidati_apply_event($state, []), [3, 7], 'Partita senza cartellini mantiene la diffida');
expect_diffida(yellow(yellow(yellow($state, 8), 9), 10), [9, 10], 'Nuovo ciclo senza vecchie giornate');
expect_diffida(yellow([3], 7, 2), [], 'Due gialli nella stessa partita completano il ciclo');
foreach ([
    [['giornata' => 2, 'fase' => 'GOLD', 'fase_round' => 'SEMIFINALE'], 'SEMIFINALE GOLD'],
    [['giornata' => 2, 'fase' => 'SILVER', 'fase_round' => 'QUARTI'], 'QUARTI SILVER'],
    [['giornata' => 2, 'fase' => ' gold ', 'fase_round' => null], 'SEMIFINALE GOLD'],
    [['giornata' => 3, 'fase' => 'SILVER'], 'QUARTI SILVER'],
    [['giornata' => 1, 'fase' => 'BRONZO'], 'FINALE BRONZO'],
    [['giornata' => 7, 'fase' => 'REGULAR', 'fase_round' => 'FINALE'], 'Giornata 7'],
    [['fase' => 'GOLD'], 'COPPA GOLD'],
    [[], 'Giornata non indicata'],
] as [$event, $label]) {
    expect_diffida([diffidati_event_label($event)], [$label], 'Etichetta corretta del turno');
}
$regular = ['giornata' => 7, 'fase' => 'REGULAR', 'cartellino_giallo' => 1];
$gold = ['giornata' => 2, 'fase' => 'GOLD', 'fase_round' => 'SEMIFINALE', 'cartellino_giallo' => 1];
$silver = ['giornata' => 2, 'fase' => 'SILVER', 'fase_round' => 'QUARTI', 'cartellino_giallo' => 1];
$labels = diffidati_apply_event(diffidati_apply_event([], $regular, true), $gold, true);
expect_diffida($labels, ['Giornata 7', 'SEMIFINALE GOLD'], 'Ciclo misto campionato e coppa');
expect_diffida(diffidati_apply_event($labels, $silver, true), [], 'Terzo giallo azzera anche i turni');
expect_diffida(diffidati_apply_event($labels, ['cartellino_rosso' => 1], true), [], 'Rosso azzera anche i turni');
expect_diffida(diffidati_apply_event($labels, [], true), $labels, 'Nessun cartellino conserva i turni');
echo "Diffidati: tutti i controlli superati\n";
