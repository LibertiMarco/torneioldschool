<?php
require_once __DIR__ . '/../includi/all_in_one_semifinals.php';
function expect_aio(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
foreach (['22:52:00'=>'22:55:00','22:55:00'=>'22:55:00','22:55:01'=>'23:00:00','22:59:59'=>'23:00:00'] as $from=>$to) {
    expect_aio(aio_next_five_minutes(new DateTimeImmutable('2026-09-07 ' . $from, new DateTimeZone('Europe/Rome')))->format('H:i:s') === $to, 'Arrotondamento ' . $from);
}
expect_aio(aio_next_five_minutes(new DateTimeImmutable('2026-09-07 23:58', new DateTimeZone('Europe/Rome')))->format('Y-m-d H:i') === '2026-09-08 00:00', 'Cambio data');
$teams = [];
foreach (['A','B'] as $group) for ($i=1;$i<=4;$i++) $teams[] = ['id'=>count($teams)+1,'nome'=>$group.$i,'girone'=>$group];
$matches = [];
foreach (all_in_one_calendar($teams, ['Centro','Centro'], new DateTimeImmutable('2026-09-07 20:00')) as $row) {
    $matches[] = ['squadra_casa'=>$row['casa'],'squadra_ospite'=>$row['ospite'],'gol_casa'=>1,'gol_ospite'=>0,'giocata'=>1,'campo'=>$row['campo'],'data_partita'=>$row['data'],'ora_partita'=>$row['ora']];
}
$ranked = aio_completed_groups($teams,$matches,'AllInOneNightSerieA');
expect_aio($ranked === ['A'=>['A1','A2','A3','A4'],'B'=>['B1','B2','B3','B4']], 'Classifica');
$pending = $matches; $pending[11]['giocata']=0;
expect_aio(aio_completed_groups($teams,$pending,'AllInOneNightSerieA') === [], 'Aspetta ultima partita');
expect_aio(aio_completed_groups($teams,array_slice($matches,0,11),'AllInOneNightSerieA') === [], 'Calendario incompleto');
$duplicate = $matches; $duplicate[11]=$duplicate[0];
expect_aio(aio_completed_groups($teams,$duplicate,'AllInOneNightSerieA') === [], 'Doppioni non completano un girone');
$fields = aio_semifinal_fields($matches);
expect_aio($fields === ['Centro','Centro'], 'Capienza stesso impianto');
$now = new DateTimeImmutable('2026-09-07 22:52:00',new DateTimeZone('Europe/Rome'));
$rows = aio_semifinal_rows($ranked,$fields,$now);
expect_aio([$rows[0]['casa'],$rows[0]['ospite'],$rows[1]['casa'],$rows[1]['ospite']] === ['A1','B2','B1','A2'], 'Incroci semifinali');
expect_aio($rows[0]['ora'] === '22:55:00' && $rows[1]['ora'] === '22:55:00','Semifinali contemporanee');
$single = aio_semifinal_rows($ranked,['Centro'],$now);
expect_aio($single[1]['ora'] === '23:10:00','Campo unico: seconda semifinale dopo 15 minuti');
// Tre squadre a pari punti: A2 supera A1 grazie alla classifica avulsa.
$tie = [];
foreach (['A','B'] as $group) {
    foreach ([[1,2,0,3],[1,3,3,0],[2,3,0,1],[1,4,10,0],[2,4,1,0],[3,4,1,0]] as [$a,$b,$gf,$gs]) {
        $tie[]=['squadra_casa'=>$group.$a,'squadra_ospite'=>$group.$b,'gol_casa'=>$gf,'gol_ospite'=>$gs,'giocata'=>1];
    }
}
expect_aio(aio_completed_groups($teams,$tie,'AllInOneNightSerieA')['A'] === ['A2','A1','A3','A4'], 'Scontri diretti prima della differenza reti generale');
expect_aio(aio_completed_groups($teams,$tie,'AllInOneNightMondiale')['A'][0] === 'A1', 'Criteri Mondiale originale');
echo "OK: classifica, scontri diretti, completamento gironi, incroci, campi e orari.\n";
$semifinals = [
    ['squadra_casa'=>'A1','squadra_ospite'=>'B2','gol_casa'=>3,'gol_ospite'=>1,'giocata'=>1],
    ['squadra_casa'=>'B1','squadra_ospite'=>'A2','gol_casa'=>2,'gol_ospite'=>2,'giocata'=>1,'decisa_rigori'=>1,'rigori_casa'=>3,'rigori_ospite'=>4],
];
expect_aio(aio_finalists($semifinals) === ['A1','A2'], 'Vincitrice nei regolamentari e ai rigori');
$unfinished=$semifinals; $unfinished[1]['giocata']=0;
expect_aio(aio_finalists($unfinished) === [], 'Finale attende entrambe le semifinali');
foreach ([['decisa_rigori'=>0],['rigori_casa'=>null],['rigori_ospite'=>3],['fase_leg'=>'RITORNO']] as $changes) {
    $invalid=$semifinals; $invalid[1]=array_merge($invalid[1],$changes);
    expect_aio(aio_finalists($invalid) === [], 'Nessuna vincitrice inventata');
}
expect_aio(aio_finalists([$semifinals[0]]) === [], 'Richieste due semifinali');
expect_aio(aio_finalists([$semifinals[0],$semifinals[0]]) === [], 'Semifinali duplicate non valide');
echo "OK: finaliste, rigori, semifinali incomplete e risultati senza vincitrice.\n";

if (in_array('--db', $argv ?? [], true)) {
    // Tabelle temporanee della sola connessione di test: nessun dato reale modificato.
    require __DIR__ . '/../includi/db.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->query('CREATE TEMPORARY TABLE squadre (id INT, nome VARCHAR(100), girone VARCHAR(32), torneo VARCHAR(100)) ENGINE=InnoDB');
    $conn->query("CREATE TEMPORARY TABLE partite (id INT AUTO_INCREMENT PRIMARY KEY, torneo VARCHAR(100), fase VARCHAR(32), fase_round VARCHAR(32), fase_leg VARCHAR(32), squadra_casa VARCHAR(100), squadra_ospite VARCHAR(100), gol_casa INT, gol_ospite INT, decisa_rigori INT DEFAULT 0, rigori_casa INT, rigori_ospite INT, data_partita DATE, ora_partita TIME, campo VARCHAR(100), giornata INT, giocata INT, created_at DATETIME) ENGINE=InnoDB");
    $slug='AllInOneNightTest';
    foreach ($teams as $team) aio_semifinal_query($conn,'INSERT INTO squadre VALUES (?,?,?,?)','isss',[$team['id'],$team['nome'],$team['girone'],$slug])->close();
    foreach ($matches as $match) aio_semifinal_query($conn,"INSERT INTO partite (torneo,fase,squadra_casa,squadra_ospite,gol_casa,gol_ospite,giocata,campo,data_partita,ora_partita) VALUES (?,'REGULAR',?,?,?,?,?,?,?,?,?)",'sssiiisss',[$slug,$match['squadra_casa'],$match['squadra_ospite'],$match['gol_casa'],$match['gol_ospite'],$match['giocata'],$match['campo'],$match['data_partita'],$match['ora_partita']])->close();
    $conn->query('UPDATE partite SET giocata=0 WHERE id=12');
    expect_aio(all_in_one_after_result($conn,11) === '', 'DB: attende ultima gara');
    $conn->query('UPDATE partite SET giocata=1 WHERE id=12');
    $result=all_in_one_after_result($conn,12);
    expect_aio(strpos($result,'Semifinali create') === 0, 'DB: creazione: ' . $result);
    $semis=$conn->query("SELECT * FROM partite WHERE fase='GOLD' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    expect_aio(count($semis)===2 && $semis[0]['squadra_casa']==='A1' && $semis[0]['squadra_ospite']==='B2' && $semis[1]['squadra_casa']==='B1' && $semis[1]['squadra_ospite']==='A2','DB: due incroci corretti');
    expect_aio($semis[0]['fase_round']==='SEMIFINALE' && (int)$semis[0]['giornata']===2 && (int)$semis[0]['giocata']===0,'DB: fase e stato');
    expect_aio(all_in_one_after_result($conn,12)==='' && (int)$conn->query("SELECT COUNT(*) AS n FROM partite WHERE fase='GOLD'")->fetch_assoc()['n']===2,'DB: idempotenza');
    $firstId=(int)$semis[0]['id']; $secondId=(int)$semis[1]['id'];
    $conn->query("UPDATE partite SET giocata=1, gol_casa=3, gol_ospite=1 WHERE id=$firstId");
    expect_aio(all_in_one_after_result($conn,$firstId)==='', 'DB: finale attende seconda semifinale');
    $conn->query("UPDATE partite SET giocata=1, gol_casa=2, gol_ospite=2 WHERE id=$secondId");
    expect_aio(strpos(all_in_one_after_result($conn,$secondId),'Finale non creata')===0, 'DB: pareggio richiede rigori');
    $conn->query("UPDATE partite SET decisa_rigori=1, rigori_casa=3, rigori_ospite=4 WHERE id=$secondId");
    expect_aio(strpos(all_in_one_after_result($conn,$secondId),'Finale creata')===0, 'DB: finale automatica');
    $final=$conn->query("SELECT * FROM partite WHERE fase_round='FINALE'")->fetch_assoc();
    expect_aio($final['squadra_casa']==='A1' && $final['squadra_ospite']==='A2' && (int)$final['giornata']===1 && (int)$final['giocata']===0 && $final['campo']==='Centro', 'DB: finale corretta');
    expect_aio(all_in_one_after_result($conn,$secondId)==='' && (int)$conn->query("SELECT COUNT(*) AS n FROM partite WHERE fase_round='FINALE'")->fetch_assoc()['n']===1, 'DB: finale non duplicata');
    expect_aio(all_in_one_after_result($conn,(int)$final['id'])==='', 'DB: nessuna fase dopo la finale');
    $conn->close();
    echo "OK: integrazione database con tabelle temporanee, creazione e idempotenza.\n";
}
