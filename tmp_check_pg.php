<?php
require __DIR__ . '/includi/db.php';
$res = $conn->query("SELECT COUNT(*) AS totale, SUM(CASE WHEN squadra_id IS NULL OR squadra_id = 0 THEN 1 ELSE 0 END) AS mancanti FROM partita_giocatore");
var_export($res ? $res->fetch_assoc() : ['error' => $conn->error]);
