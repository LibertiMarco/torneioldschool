<?php
require_once __DIR__ . '/crud/torneo.php';
header('Content-Type: application/json');

$torneo = new Torneo();

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $dati = $torneo->getById($id);
    echo $dati ? json_encode($dati) : json_encode(['error' => 'Torneo non trovato']);
    exit;
}

if (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $dati = $torneo->getBySlug($slug);
    echo $dati ? json_encode($dati) : json_encode(['error' => 'Torneo non trovato']);
    exit;
}

echo json_encode(['error' => 'ID o slug non fornito']);
