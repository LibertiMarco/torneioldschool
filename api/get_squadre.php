<?php
require_once __DIR__ . '/crud/Partita.php';
$partita = new Partita();

header('Content-Type: application/json; charset=UTF-8');

// 🔹 Controllo parametro
if (!isset($_GET['torneo']) || empty(trim($_GET['torneo']))) {
    echo json_encode(['error' => 'Torneo non specificato']);
    exit;
}

// 🔹 Normalizza nome torneo: rimuove eventuali suffissi _gold o _silver
$torneo = trim($_GET['torneo']);
$torneo = preg_replace('/_(gold|silver)$/i', '', $torneo);

// 🔹 Recupero squadre dal DB
$squadre = $partita->getSquadre($torneo);

if (!$squadre) {
    echo json_encode(['error' => 'Errore nel recupero delle squadre']);
    exit;
}

$result = [];
while ($row = $squadre->fetch_assoc()) {
    $result[] = $row['nome'];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
