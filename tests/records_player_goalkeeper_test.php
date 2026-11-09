<?php
require_once __DIR__.'/records_test.php';
$d = records_player_goalkeeper_compute([$p, $q, $six], [1=>$r], [1=>$fv, 2=>$w, 3=>$sv], '', '', 1);
check($d['tournaments'] === ['T1'], 'Solo tornei con presenze verificate in porta');
check($d['formats'] === ['Calcio a 5'], 'Solo formati giocati in porta');
check($d['groups']['Calcio a 5']['goalkeeper']['appearances'] === 1, 'Presenza effettiva in porta');
check(records_player_goalkeeper_compute([$p], [1=>$r], [1=>$fv], '', '', 2)['groups'] === [], 'Giocatore di movimento nello stesso tabellino escluso');
check(records_player_goalkeeper_compute([$p], [1=>$r], [1=>$fv], 'T2', '', 1)['groups'] === [], 'Torneo non giocato in porta escluso');
check(records_player_goalkeeper_compute([$p], [1=>$changed], [1=>$fv], '', '', 1)['tournaments'] === [], 'Attestazione invalidata esclusa dai tornei');
echo "Filtro statistiche portiere: tutti i controlli superati.\n";
