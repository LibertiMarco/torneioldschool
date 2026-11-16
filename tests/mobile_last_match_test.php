<?php
require_once __DIR__ . '/../includi/mobile_player_stats.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('CONCAT', static fn(...$parts) => implode('', $parts));
$db->exec('CREATE TABLE tornei (filetorneo TEXT, nome TEXT, sezione TEXT);
CREATE TABLE partite (id INTEGER PRIMARY KEY, torneo TEXT, giocata INTEGER, data_partita TEXT, ora_partita TEXT);
CREATE TABLE partita_giocatore (id INTEGER PRIMARY KEY, partita_id INTEGER, giocatore_id INTEGER, presenza INTEGER, goal INTEGER, assist INTEGER, voto REAL);
INSERT INTO tornei VALUES ("old.php", "Torneo precedente", "calcio"), ("game.php", "Virtuale", "esport");
INSERT INTO partite VALUES
 (1,"old",1,"2026-11-10","20:00"),
 (2,"old",1,"2026-11-11","20:00"),
 (3,"old",0,"2026-11-12","20:00"),
 (4,"old",1,"2026-11-13","20:00"),
 (5,"game",1,"2026-11-14","20:00"),
 (6,"old",1,NULL,NULL);
INSERT INTO partita_giocatore VALUES
 (1,1,10,1,1,0,6), (2,2,10,1,2,1,7.5), (3,3,10,1,0,0,0),
 (4,4,10,0,0,0,0), (5,5,10,1,3,0,8), (6,6,10,1,0,0,0),
 (7,4,99,1,1,0,6);');
$db->exec('ALTER TABLE partite ADD squadra_casa TEXT DEFAULT "Casa";
ALTER TABLE partite ADD squadra_ospite TEXT DEFAULT "Ospite";
CREATE TABLE squadre (id INTEGER PRIMARY KEY, nome TEXT, torneo TEXT, logo TEXT);
INSERT INTO squadre VALUES (1,"Casa","other","wrong.png"), (2,"Casa","old","casa.png"), (3,"Ospite","old","ospite.png");');
$stmt = $db->prepare(mobile_player_last_match_sql());
$stmt->execute([10, 'calcio']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ((int)$row['id'] !== 2 || (int)$row['goal'] !== 2 || $row['torneo_nome'] !== 'Torneo precedente' || $row['logo_casa'] !== 'casa.png' || $row['logo_ospite'] !== 'ospite.png') {
    throw new RuntimeException('Expected latest played appearance, preserving historical team and excluding unplayed, absent, esports and undated games.');
}
$stmt->execute([123, 'calcio']);
if ($stmt->fetch() !== false) throw new RuntimeException('Another player must not leak into the result.');
$db->exec('INSERT INTO partite (id,torneo,giocata,data_partita,ora_partita) VALUES (7,"old",1,"2026-11-11","21:00"); INSERT INTO partita_giocatore VALUES (8,7,10,1,0,0,6);');
$stmt->execute([10, 'calcio']);
if ((int)$stmt->fetchColumn() !== 7) throw new RuntimeException('Kickoff time must break same-day ties.');
echo "Last personal match: selection, participation, identity and empty state passed.\n";
