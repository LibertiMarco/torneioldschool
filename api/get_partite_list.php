<?php
header('Content-Type: application/json; charset=utf-8');

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';

$torneo = $_GET['torneo'] ?? '';
$fase = strtoupper($_GET['fase'] ?? '');
$fasiAmmesse = ['REGULAR','GOLD','SILVER'];
if(!$torneo){ echo json_encode(['error'=>"Parametro 'torneo' mancante"]); exit; }

$sql="SELECT id, giornata, data_partita, ora_partita, squadra_casa, squadra_ospite, gol_casa, gol_ospite, giocata, fase, fase_round, fase_leg
      FROM partite WHERE torneo=?";
$types = "s";
$params = [$torneo];
if ($fase && in_array($fase, $fasiAmmesse, true)) {
    $sql .= " AND fase=?";
    $types .= "s";
    $params[] = $fase;
}
$sql .= " ORDER BY 
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
$st=$conn->prepare($sql); $st->bind_param($types, ...$params); $st->execute(); $res=$st->get_result();

$roundMap = [
    'FINALE' => 1,
    'SEMIFINALE' => 2,
    'QUARTI' => 3,
    'OTTAVI' => 4
];
$out=[];
while($r=$res->fetch_assoc()){
    $g=$r['giornata'];
    if ($r['fase'] !== 'REGULAR') {
        $round = strtoupper($r['fase_round'] ?? '');
        $g = $roundMap[$round] ?? $round ?: 'KO';
    }
    if(!isset($out[$g])) $out[$g]=[];
    $out[$g][]=$r;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
