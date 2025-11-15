<?php
header('Content-Type: application/json');
require_once __DIR__ . '/crud/utente.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID mancante']);
    exit;
}

$utente = new Utente();
$id = (int)$_GET['id'];
$dati = $utente->getById($id);

if ($dati) {
    echo json_encode($dati);
} else {
    echo json_encode(['error' => 'Utente non trovato']);
}
?>
