<?php
// Solo istanza MySQL di test isolata; non usa env.local.php o database del sito.
require_once __DIR__.'/../includi/records.php';
require_once __DIR__.'/../includi/records_automatic.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$port=(int)getenv('TOS_RECORDS_TEST_PORT');
if($port!==3319) throw new RuntimeException('Avviare MySQL isolato sulla porta 3319 e impostare TOS_RECORDS_TEST_PORT.');
$db=new mysqli('127.0.0.1','root','','',$port);
$name='tos_records_'.bin2hex(random_bytes(6));
$db->query("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4");
$db->select_db($name);
function db_check(bool $value,string $message): void {if(!$value) throw new RuntimeException($message);}
try {
    foreach(['INT','INT UNSIGNED'] as $type) {
        $db->query("CREATE TABLE partite(id {$type} PRIMARY KEY,torneo VARCHAR(80),squadra_casa VARCHAR(80),squadra_ospite VARCHAR(80),gol_casa INT,gol_ospite INT,data_partita DATE,ora_partita TIME,giocata INT) ENGINE=InnoDB");
        $db->query('CREATE TABLE tornei(id INT PRIMARY KEY,nome VARCHAR(80),filetorneo VARCHAR(80),sezione VARCHAR(20),categoria VARCHAR(80))');
        $db->query('CREATE TABLE giocatori(id INT PRIMARY KEY,nome VARCHAR(80),cognome VARCHAR(80))');
        $db->query('CREATE TABLE squadre(id INT PRIMARY KEY,nome VARCHAR(80),torneo VARCHAR(80),logo VARCHAR(255))');
        $db->query('CREATE TABLE partita_giocatore(id INT PRIMARY KEY,partita_id INT,giocatore_id INT,squadra_id INT,presenza INT,goal INT,autogol INT)');
        $db->query('CREATE TABLE squadre_giocatori(giocatore_id INT,squadra_id INT)');
        $automatic=records_automatic_fetch($db);
        db_check($automatic['groups']===[],'API automatica funziona senza tabella attestazioni');
        db_check($db->query("SHOW TABLES LIKE 'match_record_verification'")->num_rows===0,'Calcolo automatico senza modifiche DB');
        records_ensure_schema($db); records_ensure_schema($db);
        [$matches,$rows,$verified]=records_source($db);
        db_check(!$matches && !$verified,'Schema vuoto disponibile senza migrazione manuale');
        $db->query("INSERT INTO tornei VALUES(1,'Coppa','coppa','calcio','Calcio a 5')");
        $db->query("INSERT INTO partite VALUES(1,'coppa','A','B',2,0,'2026-10-01','20:00:00',1)");
        $db->query("INSERT INTO squadre VALUES(1,'A','coppa','/img/scudetti/a.png'),(2,'B','coppa',NULL)");
        $db->query("INSERT INTO giocatori VALUES(1,'Portiere','Casa'),(2,'Attaccante','Casa'),(3,'Portiere','Ospite')");
        $db->query('INSERT INTO partita_giocatore VALUES(1,1,1,1,1,0,0),(2,1,2,1,1,2,0),(3,1,3,2,1,0,0)');
        $db->query('INSERT INTO squadre_giocatori VALUES(1,1),(2,1),(3,2)');
        $automatic=records_automatic_fetch($db);
        db_check($automatic['groups']['Generali']['records']['player']['value']===2,'Record storico calcolato senza nuova attestazione');
        db_check($automatic['groups']['Generali']['records']['player']['holders'][0]['match']['logo_casa']==='/img/scudetti/a.png','Scudetto squadra del torneo nel payload');
        db_check($automatic['groups']['Generali']['records']['player']['holders'][0]['match']['logo_ospite']===null,'Logo mancante esplicito per fallback');
        [$matches,$rows]=records_source($db);$p=$matches[0];
        $keepers=[['player_id'=>1,'goals'=>0,'full'=>true],['player_id'=>3,'goals'=>2,'full'=>true]];
        records_validate_keepers($p,$rows[1],$keepers);
        db_check(records_scoring_complete($p,$rows[1]),'Marcature coerenti');
        $rh=records_hash($p);$sh=records_hash($p,$rows[1]);$json=json_encode($keepers);
        $stmt=$db->prepare("INSERT INTO match_record_verification(partita_id,formato,esito,risultato_hash,marcature_hash,portieri_hash,portieri_json,verified_by) VALUES(1,'Calcio a 5','confermato',?,?,?,?,1)");
        $stmt->bind_param('ssss',$rh,$sh,$sh,$json);$stmt->execute();
        $data=records_compute(...[...records_source($db),'','',1]);
        $k=$data['groups']['Calcio a 5']['goalkeeper'];
        db_check($k['appearances']===1 && $k['clean_sheets']===1 && $k['average']==='0.00','Statistiche effettive da MySQL');
        $db->query('UPDATE partite SET gol_casa=3 WHERE id=1');
        $data=records_compute(...records_source($db));
        db_check($data['groups']['Calcio a 5']['records']['total']['value']===null,'Correzione invalida verifica');
        $db->query('DELETE FROM partite WHERE id=1');
        db_check($db->query('SELECT * FROM match_record_verification')->num_rows===0,'Eliminazione in cascata');
        // Schema storico: mancano squadra_id e autogol. Non fallire e non dedurre dati.
        $db->query('ALTER TABLE partita_giocatore DROP COLUMN squadra_id, DROP COLUMN autogol');
        [$matches,$rows]=records_source($db);
        db_check($rows[1][0]['squadra_id']===null && $rows[1][0]['autogol']===null,'Partita eliminata: nessuna attribuzione inventata');
        $db->query("INSERT INTO partite VALUES(1,'coppa','A','B',2,0,'2026-10-01','20:00:00',1)");
        [$matches,$rows]=records_source($db);
        db_check((int)$rows[1][0]['squadra_id']===1,'Squadra storica risolta da appartenenza univoca');
        $db->query('INSERT INTO squadre_giocatori VALUES(1,2)');
        [$matches,$rows]=records_source($db);
        db_check($rows[1][0]['squadra_id']===null,'Doppia appartenenza storica resta ambigua');
        foreach(['match_record_verification','partita_giocatore','partite','tornei','giocatori','squadre','squadre_giocatori'] as $table) $db->query('DROP TABLE '.$table);
    }
    echo "Database Record: preparazione automatica signed/unsigned, lettura, statistiche, correzione, cascade e schema storico superati.\n";
} finally { $db->query("DROP DATABASE `{$name}`"); $db->close(); }
