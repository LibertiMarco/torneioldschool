<?php
require_once __DIR__ . '/../includi/db.php';

$partita = $_GET["partita"] ?? 0;

// ✅ Recupero la partita (squadra casa / ospite)
$stmt = $conn->prepare("SELECT squadra_casa, squadra_ospite FROM partite WHERE id = ?");
$stmt->bind_param("i", $partita);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();

if (!$match) {
    echo json_encode([]);
    exit;
}

$squadra_casa   = $match["squadra_casa"];
$squadra_ospite = $match["squadra_ospite"];

// ✅ Recupero gli eventi dei giocatori
$stmt2 = $conn->prepare("
    SELECT 
        pg.goal,
        pg.cartellino_giallo,
        pg.cartellino_rosso,
        pg.voto,
        g.id AS giocatore_id,
        g.nome,
        g.cognome,
        g.foto,
        g.squadra AS squadra_giocatore
    FROM partita_giocatore pg
    JOIN giocatori g ON g.id = pg.giocatore_id
    WHERE pg.partita_id = ?
");
$stmt2->bind_param("i", $partita);
$stmt2->execute();

$res = $stmt2->get_result();

$eventi = [];

while ($row = $res->fetch_assoc()) {

    // ✅ Determina se è squadra casa o ospite
    if ($row["squadra_giocatore"] === $squadra_casa) {
        $row["squadra"] = $squadra_casa;
    } else if ($row["squadra_giocatore"] === $squadra_ospite) {
        $row["squadra"] = $squadra_ospite;
    } else {
        $row["squadra"] = null;
    }

    $eventi[] = $row;
}

echo json_encode($eventi);
