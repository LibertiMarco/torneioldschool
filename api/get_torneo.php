<?php
require_once __DIR__ . '/crud/torneo.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $torneo = new Torneo();
    $id = (int) $_GET['id'];
    $dati = $torneo->getById($id);

    if ($dati) {
        echo json_encode($dati);
    } else {
        echo json_encode(['error' => 'Torneo non trovato']);
    }
} else {
    echo json_encode(['error' => 'ID non fornito']);
}
