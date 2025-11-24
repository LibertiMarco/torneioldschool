<?php
header("Content-Type: application/json");

if (!isset($_GET['id'])) {
    echo json_encode(["error" => "ID mancante"]);
    exit;
}

$id = (int)$_GET['id'];

require_once __DIR__ . '/crud/giocatore.php';
$giocatore = new Giocatore();

$dati = $giocatore->getById($id);

if (!$dati) {
    echo json_encode(["error" => "Giocatore non trovato"]);
    exit;
}

echo json_encode($dati);
?>
