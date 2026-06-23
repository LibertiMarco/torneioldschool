<?php
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/partite_schema.php';

ensure_partita_giocatore_team_schema($conn);

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
        pg.autogol,
        pg.assist,
        pg.cartellino_giallo,
        pg.cartellino_rosso,
        pg.voto,
        g.id AS giocatore_id,
        g.nome,
        g.cognome,
        COALESCE(sg.foto, g.foto) AS foto,
        s.nome AS squadra,
        sg.ruolo,
        COALESCE(sg.is_captain, 0) AS is_captain
    FROM partita_giocatore pg
    JOIN giocatori g ON g.id = pg.giocatore_id
    LEFT JOIN squadre s ON s.id = pg.squadra_id
    LEFT JOIN squadre_giocatori sg ON sg.squadra_id = pg.squadra_id AND sg.giocatore_id = g.id
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
