<?php
require_once __DIR__ . '/../includi/db.php';

$partita = $_GET["partita"] ?? 0;

$stmtCheck = $conn->prepare("SELECT id FROM partite WHERE id = ?");
$stmtCheck->bind_param("i", $partita);
$stmtCheck->execute();
$match = $stmtCheck->get_result()->fetch_assoc();

if (!$match) {
    echo json_encode([]);
    exit;
}

$stmt2 = $conn->prepare("
    SELECT 
        pg.goal,
        pg.cartellino_giallo,
        pg.cartellino_rosso,
        pg.voto,
        g.id AS giocatore_id,
        g.nome,
        g.cognome,
        COALESCE(sg.foto, g.foto) AS foto,
        s.nome AS squadra
    FROM partita_giocatore pg
    JOIN giocatori g ON g.id = pg.giocatore_id
    JOIN partite p ON p.id = pg.partita_id
    JOIN squadre s ON s.torneo = p.torneo AND s.nome IN (p.squadra_casa, p.squadra_ospite)
    JOIN squadre_giocatori sg ON sg.squadra_id = s.id AND sg.giocatore_id = g.id
    WHERE pg.partita_id = ?
");
$stmt2->bind_param("i", $partita);
$stmt2->execute();

$res = $stmt2->get_result();
$eventi = [];

while ($row = $res->fetch_assoc()) {
    $eventi[] = $row;
}

echo json_encode($eventi);
