<?php
require __DIR__ . '/includi/db.php';
$sql = "SELECT pg.id, pg.partita_id, pg.giocatore_id, pg.squadra_id, p.torneo, p.squadra_casa, p.squadra_ospite, g.nome, g.cognome FROM partita_giocatore pg JOIN partite p ON p.id = pg.partita_id JOIN giocatori g ON g.id = pg.giocatore_id WHERE pg.squadra_id IS NULL OR pg.squadra_id = 0 LIMIT 20";
$res = $conn->query($sql);
$out = [];
while ($res && ($row = $res->fetch_assoc())) $out[] = $row;
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
