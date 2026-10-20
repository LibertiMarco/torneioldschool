<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../includi/db.php';
require __DIR__ . '/../includi/night_appearances.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$nightTeam = torneo_stats_is_night_expr('s.torneo');
$nightMatch = torneo_stats_is_night_expr('p.torneo');
$nightCount = torneo_stats_night_count_expr('g.id');
$conn->begin_transaction();
try {
    $conn->query("UPDATE squadre_giocatori sg JOIN squadre s ON s.id = sg.squadra_id SET sg.presenze = 1 WHERE $nightTeam");
    $teams = $conn->affected_rows;
    $conn->query("UPDATE giocatori g SET g.presenze = (SELECT COUNT(*) FROM partita_giocatore pg JOIN partite p ON p.id = pg.partita_id WHERE pg.giocatore_id = g.id AND pg.presenza = 1 AND NOT $nightMatch) + $nightCount");
    $players = $conn->affected_rows;
    $conn->commit();
    echo "Aggiornate $teams associazioni e $players totali giocatore.\n";
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
