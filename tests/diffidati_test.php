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
echo "Diffidati: tutti i controlli superati\n";
