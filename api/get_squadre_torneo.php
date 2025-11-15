<?php
require_once __DIR__ . '/crud/Squadra.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['torneo']) || $_GET['torneo'] === '') {
    echo json_encode([]);
    exit;
}

$s = new Squadra();
$torneo = $_GET['torneo'];
$result = $s->getByTorneo($torneo);

$squadre = [];
while ($row = $result->fetch_assoc()) {
    // ritorno solo quello che serve per riempire la tendina
    $squadre[] = [
        'id'   => $row['id'],
        'nome' => $row['nome']
    ];
}

echo json_encode($squadre);
