<?php
header('Content-Type: application/json; charset=utf-8');

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';

$torneo = $_GET['torneo'] ?? '';
if(!$torneo){ echo json_encode(['error'=>"Parametro 'torneo' mancante"]); exit; }

$sql="SELECT id, giornata, data_partita, ora_partita, squadra_casa, squadra_ospite, gol_casa, gol_ospite, giocata
      FROM partite WHERE torneo=? ORDER BY giornata ASC, data_partita ASC, ora_partita ASC";
$st=$conn->prepare($sql); $st->bind_param("s",$torneo); $st->execute(); $res=$st->get_result();

$out=[]; while($r=$res->fetch_assoc()){ $g=$r['giornata']; if(!isset($out[$g])) $out[$g]=[]; $out[$g][]=$r; }
echo json_encode($out, JSON_UNESCAPED_UNICODE);
