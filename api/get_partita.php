<?php
header("Content-Type: application/json; charset=UTF-8");

// --- CONNESSIONE DATABASE + MODEL ---
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/crud/Partita.php';

$partita = new Partita();

$id = $_GET['id'] ?? '';

if(!$id){
    echo json_encode(["error" => "Parametro 'id' mancante."], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$dati = $partita->getById((int)$id);

if(!$dati){
    echo json_encode(["error" => "Partita non trovata."], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

// Output in stile identico agli altri
echo json_encode([
    "id"             => (int)$dati['id'],
    "torneo"         => $dati['torneo'],
    "squadra_casa"   => $dati['squadra_casa'],
    "squadra_ospite" => $dati['squadra_ospite'],
    "gol_casa"       => (int)$dati['gol_casa'],
    "gol_ospite"     => (int)$dati['gol_ospite'],
    "data_partita"   => $dati['data_partita'],
    "ora_partita"    => $dati['ora_partita'],
    "campo"          => $dati['campo'],
    "giornata"       => (int)$dati['giornata'],
    "link_youtube"   => $dati['link_youtube'] ?? null,
    "link_instagram" => $dati['link_instagram'] ?? null
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
