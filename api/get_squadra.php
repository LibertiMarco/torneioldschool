<?php
require_once __DIR__ . '/crud/Squadra.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id']) || $_GET['id'] === '') {
    echo json_encode(['error' => 'ID non fornito']);
    exit;
}

$s = new Squadra();
$dati = $s->getById((int)$_GET['id']);

if (!$dati) {
    echo json_encode(['error' => 'Squadra non trovata']);
    exit;
}

// restituisco esattamente i nomi che lo script JS si aspetta
echo json_encode([
    'nome'            => $dati['nome'],
    'torneo'          => $dati['torneo'],
    'logo'            => $dati['logo'],
    'punti'           => $dati['punti'],
    'giocate'         => $dati['giocate'],
    'vinte'           => $dati['vinte'],
    'pareggiate'      => $dati['pareggiate'],
    'perse'           => $dati['perse'],
    'gol_fatti'       => $dati['gol_fatti'],
    'gol_subiti'      => $dati['gol_subiti'],
    'differenza_reti' => $dati['differenza_reti']
]);
