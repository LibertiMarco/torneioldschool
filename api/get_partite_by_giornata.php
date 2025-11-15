<?php
header("Content-Type: application/json; charset=UTF-8");

// --- CONNESSIONE DATABASE ---
require_once __DIR__ . '/../includi/db.php';

$torneo   = $_GET['torneo']   ?? '';
$giornata = $_GET['giornata'] ?? '';

if(!$torneo || !$giornata){
    echo json_encode(["error" => "Parametri 'torneo' o 'giornata' mancanti."], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$q = "SELECT * FROM partite 
      WHERE torneo=? AND giornata=?
      ORDER BY data_partita ASC, ora_partita ASC";

$st = $conn->prepare($q);
$st->bind_param("si", $torneo, $giornata);
$st->execute();
$r = $st->get_result();

$partite = [];

while($row = $r->fetch_assoc()){
    $partite[] = [
        "id"             => (int)$row['id'],
        "squadra_casa"   => $row['squadra_casa'],
        "squadra_ospite" => $row['squadra_ospite'],
        "gol_casa"       => (int)$row['gol_casa'],
        "gol_ospite"     => (int)$row['gol_ospite'],
        "data_partita"   => $row['data_partita'],
        "ora_partita"    => $row['ora_partita'],
        "campo"          => $row['campo'],
        "giornata"       => (int)$row['giornata'],
        "link_youtube"   => $row['link_youtube'],
        "link_instagram" => $row['link_instagram']
    ];
}

echo json_encode($partite, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
