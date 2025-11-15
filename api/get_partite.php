<?php
header("Content-Type: application/json; charset=UTF-8");

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';

$torneo = $_GET['torneo'] ?? '';
if(!$torneo){ echo json_encode(["error"=>"Parametro 'torneo' mancante."]); exit; }

$q = "SELECT * FROM partite WHERE torneo=? ORDER BY giornata ASC, data_partita ASC, ora_partita ASC";
$st=$conn->prepare($q); $st->bind_param("s",$torneo); $st->execute(); $r=$st->get_result();

$giornate=[];
while($row=$r->fetch_assoc()){
  $g=$row['giornata'];
  if(!isset($giornate[$g])) $giornate[$g]=[];
  $giornate[$g][]=[
    "id"=>$row['id'],
    "squadra_casa"=>$row['squadra_casa'],
    "squadra_ospite"=>$row['squadra_ospite'],
    "gol_casa"=>$row['gol_casa'],
    "gol_ospite"=>$row['gol_ospite'],
    "data_partita"=>$row['data_partita'],
    "ora_partita"=>$row['ora_partita'],
    "campo"=>$row['campo'],
    "giornata"=>$row['giornata'],
    "link_youtube"=>$row['link_youtube'],
    "link_instagram"=>$row['link_instagram']
  ];
}
echo json_encode($giornate, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

