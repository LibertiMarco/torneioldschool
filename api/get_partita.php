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
    "torneo"         => $dati['torneo'] ?? '',
    "fase"           => $dati['fase'] ?? '',
    "fase_round"     => $dati['fase_round'] ?? null,
    "fase_leg"       => $dati['fase_leg'] ?? null,
    "squadra_casa"   => $dati['squadra_casa'] ?? '',
    "squadra_ospite" => $dati['squadra_ospite'] ?? '',
    "gol_casa"       => isset($dati['gol_casa']) ? (int)$dati['gol_casa'] : 0,
    "gol_ospite"     => isset($dati['gol_ospite']) ? (int)$dati['gol_ospite'] : 0,
    "data_partita"   => $dati['data_partita'] ?? '',
    "ora_partita"    => $dati['ora_partita'] ?? '',
    "campo"          => $dati['campo'] ?? '',
    "giornata"       => array_key_exists('giornata', $dati) && $dati['giornata'] !== null ? (int)$dati['giornata'] : null,
    "giocata"        => isset($dati['giocata']) ? (int)$dati['giocata'] : 0,
    "link_youtube"   => $dati['link_youtube'] ?? null,
    "link_instagram" => $dati['link_instagram'] ?? null,
    "arbitro"        => $dati['arbitro'] ?? ''
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
