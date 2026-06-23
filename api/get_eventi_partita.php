<?php
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/partite_schema.php';

if (!function_exists('partita_giocatore_resolved_team_expr')) {
    echo json_encode([]);
    exit;
}

$partita = $_GET["partita"] ?? 0;

$stmtCheck = $conn->prepare("SELECT id FROM partite WHERE id = ?");
$stmtCheck->bind_param("i", $partita);
$stmtCheck->execute();
$match = $stmtCheck->get_result()->fetch_assoc();

if (!$match) {
    echo json_encode([]);
    exit;
}

$teamIdExpr = partita_giocatore_team_id_expr($conn, 'pg.squadra_id');
$resolvedTeamExpr = partita_giocatore_resolved_team_expr('pg.giocatore_id', $teamIdExpr, 'p.torneo', 'p.squadra_casa', 'p.squadra_ospite');

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
    JOIN partite p ON p.id = pg.partita_id
    LEFT JOIN squadre s ON s.id = {$resolvedTeamExpr}
    LEFT JOIN squadre_giocatori sg ON sg.squadra_id = s.id AND sg.giocatore_id = g.id
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
