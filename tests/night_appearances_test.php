<?php
require __DIR__ . '/../includi/db.php';
require __DIR__ . '/../includi/night_appearances.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Connection-local fixtures; persistent tables and data are untouched.
$conn->query('CREATE TEMPORARY TABLE tornei (filetorneo VARCHAR(100), nome VARCHAR(100), sezione VARCHAR(20))');
$conn->query('CREATE TEMPORARY TABLE squadre (id INT, torneo VARCHAR(100))');
$conn->query('CREATE TEMPORARY TABLE squadre_giocatori (giocatore_id INT, squadra_id INT)');
$conn->query('CREATE TEMPORARY TABLE partite (id INT, torneo VARCHAR(100))');
$conn->query('CREATE TEMPORARY TABLE partita_giocatore (giocatore_id INT, partita_id INT, presenza INT)');
$conn->query("INSERT INTO tornei VALUES ('night.php','AllInOneNight Test','calcio')");
$conn->query("INSERT INTO squadre VALUES (1,'AllInOneNightSerieA'),(2,'night'),(3,'SerieA'),(4,'AllInOneNightSerieA')");
$conn->query('INSERT INTO squadre_giocatori VALUES (1,1),(2,1),(3,1),(3,2),(3,4),(4,3)');
$conn->query("INSERT INTO partite VALUES (1,'AllInOneNightSerieA'),(2,'AllInOneNightSerieA'),(3,'SerieA')");
$conn->query('INSERT INTO partita_giocatore VALUES (2,1,0),(3,1,1),(3,2,1),(3,2,1),(3,2,1),(3,2,1),(3,2,1),(4,3,1)');
$night = torneo_stats_is_night_expr('p.torneo');
foreach ([1=>1, 2=>1, 3=>2, 4=>1] as $player=>$expected) {
    $count = torneo_stats_night_count_expr((string)$player);
    $sql = "SELECT COALESCE(SUM(CASE WHEN pg.presenza = 1 AND NOT $night THEN 1 ELSE 0 END),0) + $count AS n FROM partita_giocatore pg JOIN partite p ON p.id = pg.partita_id WHERE pg.giocatore_id = $player";
    $actual = (int)$conn->query($sql)->fetch_assoc()['n'];
    if ($actual !== $expected) throw new RuntimeException("Player $player: $actual instead of $expected");
}
echo "OK: missing rows, zero, six appearances, distinct tournaments and ordinary matches.\n";
