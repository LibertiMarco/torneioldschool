<?php
header("Content-Type: application/json");

if (!isset($_GET['squadra']) || !isset($_GET['torneo'])) {
    echo json_encode([]);
    exit;
}

$squadra = trim($_GET['squadra']);
$torneo = trim($_GET['torneo']);

require_once __DIR__ . '/crud/Giocatore.php';
$giocatore = new Giocatore();

$result = $giocatore->getGiocatoriBySquadra($squadra, $torneo);

$lista = [];

while ($row = $result->fetch_assoc()) {
    $lista[] = [
        "id" => $row["id"],
        "nome" => $row["nome"],
        "cognome" => $row["cognome"]
    ];
}

echo json_encode($lista);
?>
