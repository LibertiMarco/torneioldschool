<?php
require_once __DIR__ . '/../includi/import_giocatori.php';
function expect_import($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
foreach (['P', 'gk', 'Portiere'] as $gk) {
    foreach (['C', 'k', 'capitano'] as $captain) {
        $r = import_player_parse("1. Mario Rossi $gk $captain")[0];
        expect_import($r['nome'] === 'Mario' && $r['cognome'] === 'Rossi' && $r['portiere'] && $r['capitano'], 'Entrambi i ruoli esclusi dal cognome');
    }
}
$r = import_player_parse("Mario Rossi C GK\nLuca De Luca\nAntonio\n\nPaolo Bianchi (P)");
expect_import(count($r) === 4, 'Righe vuote ignorate');
expect_import($r[0]['portiere'] && $r[0]['capitano'], 'Ordine inverso ruoli');
expect_import($r[1]['cognome'] === 'De Luca', 'Cognome composto');
expect_import($r[2]['cognome'] === '', 'Riga incompleta correggibile');
expect_import($r[3]['portiere'], 'Sigla tra parentesi');
expect_import(import_player_key(' MARIO ', 'ROSSI') === import_player_key('Mario', ' Rossi '), 'Normalizzazione');
expect_import(import_player_key('Nicolò', 'Dè  Luca') === import_player_key('NICOLÒ', 'DÈ Luca'), 'Unicode');
$index = import_player_index([['id'=>1, 'nome'=>'Mario', 'cognome'=>'Rossi'], ['id'=>2, 'nome'=>' MARIO ', 'cognome'=>'ROSSI']]);
expect_import(count($index[import_player_key('Mario','Rossi')]) === 2, 'Omonimi preservati');
echo "OK: parser, ruoli, normalizzazione, omonimie\n";
if (in_array('--database', $argv, true)) {
    require __DIR__ . '/../includi/db.php';
    require_once __DIR__ . '/../api/crud/giocatore.php';
    require_once __DIR__ . '/../api/crud/SquadraGiocatore.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    // Only session-local temporary tables are written.
    $conn->query('CREATE TEMPORARY TABLE giocatori LIKE giocatori');
    $conn->query('CREATE TEMPORARY TABLE squadre_giocatori LIKE squadre_giocatori');
    $g = new Giocatore($conn); $pivot = new SquadraGiocatore($conn);
    $id = $g->crea('Mario', 'Rossi', '', 8, 3, 1, 0, null, '/img/giocatori/unknown.jpg');
    $other = $g->crea('Luca', 'Bianchi', '', 0, 0, 0, 0, null, '/img/giocatori/unknown.jpg');
    $conn->query("INSERT INTO squadre_giocatori (giocatore_id, squadra_id, ruolo, is_captain, reti) VALUES ($other, 123, 'Difensore', 1, 7)");
    expect_import($pivot->aggiungiDaImport($id, 123, true, true), 'Associazione');
    $a = $pivot->getAssociazione($id, 123);
    expect_import($a['ruolo'] === 'Portiere' && (int)$a['is_captain'] === 1, 'Entrambi gli attributi salvati');
    expect_import((int)$g->getById($id)['reti'] === 3, 'Statistiche globali preservate');
    $a = $pivot->getAssociazione($other, 123);
    expect_import((int)$a['is_captain'] === 1 && (int)$a['reti'] === 7, 'Altri capitani e statistiche preservati');
    expect_import($pivot->esisteAssociazione($id, 123), 'Duplicato riconosciuto');
    $conn->begin_transaction();
    $new = $g->crea('Test', 'Rollback', '', 0, 0, 0, 0, null, '/img/giocatori/unknown.jpg');
    $pivot->aggiungiDaImport($new, 456, false, false); $conn->rollback();
    expect_import(!$g->getById($new) && !$pivot->esisteAssociazione($new, 456), 'Rollback atomico');
    echo "OK: database temporaneo, conservazione dati, rollback\n";
}
