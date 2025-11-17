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
        "foto" => $row["foto_squadra"] ?? $row["foto"] ?? null
    ];
}

echo json_encode($lista);
?>
