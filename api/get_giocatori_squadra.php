<?php
header("Content-Type: application/json");

if (!isset($_GET['torneo']) && !isset($_GET['squadra_id']) && !isset($_GET['squadra'])) {
    echo json_encode([]);
    exit;
}

$torneo = isset($_GET['torneo']) ? trim($_GET['torneo']) : null;
$squadra = isset($_GET['squadra']) ? trim($_GET['squadra']) : null;
$squadraId = isset($_GET['squadra_id']) ? (int)$_GET['squadra_id'] : null;

require_once __DIR__ . '/crud/Giocatore.php';
$giocatore = new Giocatore();

$result = $giocatore->getGiocatoriBySquadra($squadra, $torneo, $squadraId);

$lista = [];

while ($row = $result->fetch_assoc()) {
    $lista[] = [
        "id" => $row["id"],
        "nome" => $row["nome"],
        "cognome" => $row["cognome"],
        "squadra" => $row["squadra"],
        "torneo" => $row["torneo"],
        "foto" => $row["foto_squadra"] ?? $row["foto"] ?? '/torneioldschool/img/giocatori/unknown.jpg',
        "presenze" => (int)($row["presenze_squadra"] ?? 0),
        "reti" => (int)($row["reti_squadra"] ?? 0),
        "assist" => (int)($row["assist_squadra"] ?? 0),
        "gialli" => (int)($row["gialli_squadra"] ?? 0),
        "rossi" => (int)($row["rossi_squadra"] ?? 0),
        "media_voti" => isset($row["media_squadra"]) ? $row["media_squadra"] : null,
        "is_captain" => (int)($row["is_captain"] ?? 0)
    ];
}

echo json_encode($lista);
?>
