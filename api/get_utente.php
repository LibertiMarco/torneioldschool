<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/crud/utente.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID mancante']);
    exit;
}

$utente = new Utente();
$id = (int)$_GET['id'];
$dati = $utente->getById($id);

if ($dati) {
    echo json_encode([
        'id' => (int)($dati['id'] ?? 0),
        'email' => $dati['email'] ?? '',
        'nome' => $dati['nome'] ?? '',
        'cognome' => $dati['cognome'] ?? '',
        'ruolo' => $dati['ruolo'] ?? 'user',
        'feature_flags' => $dati['feature_flags'] ?? [],
    ]);
} else {
    echo json_encode(['error' => 'Utente non trovato']);
}
?>
