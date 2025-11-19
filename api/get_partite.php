<?php
header("Content-Type: application/json; charset=UTF-8");

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';

$torneo = $_GET['torneo'] ?? '';
$fase = strtoupper($_GET['fase'] ?? '');
$fasiAmmesse = ['REGULAR','GOLD','SILVER'];
if(!$torneo){ echo json_encode(["error"=>"Parametro 'torneo' mancante."]); exit; }

$logoMap = [];
$logoStmt = $conn->prepare("SELECT nome, logo FROM squadre WHERE torneo=?");
$logoStmt->bind_param("s", $torneo);
$logoStmt->execute();
$logoRes = $logoStmt->get_result();
while ($logoRow = $logoRes->fetch_assoc()) {
  $logoMap[$logoRow['nome']] = $logoRow['logo'];
}
$logoStmt->close();

$query = "SELECT * FROM partite WHERE torneo=?";
$types = "s";
$params = [$torneo];
if ($fase && in_array($fase, $fasiAmmesse, true)) {
  $query .= " AND fase=?";
  $types .= "s";
  $params[] = $fase;
}
$query .= " ORDER BY 
  CASE 
    WHEN fase = 'REGULAR' THEN COALESCE(giornata, 0)
    WHEN fase_round = 'OTTAVI' THEN 1
    WHEN fase_round = 'QUARTI' THEN 2
    WHEN fase_round = 'SEMIFINALE' THEN 3
    WHEN fase_round = 'FINALE' THEN 4
    ELSE 5
  END,
  data_partita ASC,
  ora_partita ASC";
$st=$conn->prepare($query); $st->bind_param($types, ...$params); $st->execute(); $r=$st->get_result();

$giornate=[];
$roundOrder = [
  'FINALE' => 1,
  'SEMIFINALE' => 2,
  'QUARTI' => 3,
  'OTTAVI' => 4
];
while($row=$r->fetch_assoc()){
  $key = $row['giornata'];
  if ($row['fase'] !== 'REGULAR') {
    $faseRound = strtoupper($row['fase_round'] ?? '');
    $key = $roundOrder[$faseRound] ?? $faseRound ?: 'KO';
  } elseif ($key === null) {
    $key = 0;
  }
  if(!isset($giornate[$key])) $giornate[$key]=[];
  $giornate[$key][]=[
    "id"=>$row['id'],
    "squadra_casa"=>$row['squadra_casa'],
    "squadra_ospite"=>$row['squadra_ospite'],
    "logo_casa"=>$logoMap[$row['squadra_casa']] ?? null,
    "logo_ospite"=>$logoMap[$row['squadra_ospite']] ?? null,
    "gol_casa"=>$row['gol_casa'],
    "gol_ospite"=>$row['gol_ospite'],
    "data_partita"=>$row['data_partita'],
    "ora_partita"=>$row['ora_partita'],
    "campo"=>$row['campo'],
    "giornata"=>$row['giornata'],
    "fase"=>$row['fase'],
    "fase_round"=>$row['fase_round'],
    "fase_leg"=>$row['fase_leg'],
    "link_youtube"=>$row['link_youtube'],
    "link_instagram"=>$row['link_instagram']
  ];
}
echo json_encode($giornate, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

