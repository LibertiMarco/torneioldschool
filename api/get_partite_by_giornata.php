<?php
header("Content-Type: application/json; charset=UTF-8");

// --- CONNESSIONE DATABASE ---
require_once __DIR__ . '/../includi/db.php';

$torneo   = $_GET['torneo']   ?? '';
$giornataParam = $_GET['giornata'] ?? '';
$fase     = strtoupper($_GET['fase'] ?? '');
$fasiAmmesse = ['REGULAR','GOLD','SILVER'];

if(!$torneo){
    echo json_encode(["error" => "Parametro 'torneo' mancante."], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$usaRound = $fase && in_array($fase, $fasiAmmesse, true) && $fase !== 'REGULAR';
$types = "s";
$params = [$torneo];
$condizione = "";

if ($usaRound) {
    $round = strtoupper(trim($giornataParam));
    if (!$round) {
        echo json_encode(["error" => "Parametro 'giornata' (fase) mancante."], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        exit;
    }
    $condizione = " AND fase = ? AND fase_round = ?";
    $types .= "ss";
    $params[] = $fase;
    $params[] = $round;
} else {
    if ($giornataParam === '' || !is_numeric($giornataParam)) {
        echo json_encode(["error" => "Parametro 'giornata' mancante o non valido."], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        exit;
    }
    $giornata = (int)$giornataParam;
    $condizione = " AND giornata = ?";
    $types .= "i";
    $params[] = $giornata;
    if ($fase && in_array($fase, $fasiAmmesse, true)) {
        $condizione .= " AND fase = ?";
        $types .= "s";
        $params[] = $fase;
    }
}

$query = "SELECT * FROM partite WHERE torneo=? {$condizione} ORDER BY data_partita ASC, ora_partita ASC";

$st = $conn->prepare($query);
$st->bind_param($types, ...$params);
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
        "giornata"       => $row['giornata'] !== null ? (int)$row['giornata'] : null,
        "fase"           => $row['fase'],
        "fase_round"     => $row['fase_round'],
        "fase_leg"       => $row['fase_leg'],
        "link_youtube"   => $row['link_youtube'],
        "link_instagram" => $row['link_instagram']
    ];
}

echo json_encode($partite, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
