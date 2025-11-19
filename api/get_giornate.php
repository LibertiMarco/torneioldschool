<?php
header("Content-Type: application/json; charset=UTF-8");

// --- CONNESSIONE DATABASE ---
require_once __DIR__ . '/../includi/db.php';

$torneo = $_GET['torneo'] ?? '';
$fase = strtoupper($_GET['fase'] ?? '');
$fasiAmmesse = ['REGULAR','GOLD','SILVER'];

if(!$torneo){
    echo json_encode(["error" => "Parametro 'torneo' mancante."], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$colonna = "giornata";
$order = "giornata ASC";
if ($fase && in_array($fase, $fasiAmmesse, true) && $fase !== 'REGULAR') {
    $colonna = "fase_round";
    $order = "FIELD(fase_round, 'OTTAVI','QUARTI','SEMIFINALE','FINALE')";
}

$query = "SELECT DISTINCT {$colonna} AS valore FROM partite WHERE torneo=?";
$types = "s";
$params = [$torneo];

if ($fase && in_array($fase, $fasiAmmesse, true)) {
    $query .= " AND fase=?";
    $types .= "s";
    $params[] = $fase;
}

if ($colonna === 'fase_round') {
    $query .= " AND fase_round IS NOT NULL";
}

$query .= " ORDER BY {$order}";

$st = $conn->prepare($query);
$st->bind_param($types, ...$params);
$st->execute();
$r = $st->get_result();

$giornate = [];
while($row = $r->fetch_assoc()){
    if ($colonna === 'giornata') {
        $giornate[] = (int)$row['valore'];
    } else {
        $giornate[] = $row['valore'];
    }
}

echo json_encode($giornate, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
