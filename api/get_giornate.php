<?php
header("Content-Type: application/json; charset=UTF-8");

// --- CONNESSIONE DATABASE ---
require_once __DIR__ . '/../includi/db.php';

$torneo = $_GET['torneo'] ?? '';
if(!$torneo){
    echo json_encode(["error" => "Parametro 'torneo' mancante."], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$q = "SELECT DISTINCT giornata FROM partite WHERE torneo=? ORDER BY giornata ASC";
$st = $conn->prepare($q);
$st->bind_param("s", $torneo);
$st->execute();
$r = $st->get_result();

$giornate = [];
while($row = $r->fetch_assoc()){
    $giornate[] = (int)$row['giornata'];
}

echo json_encode($giornate, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
