<?php
require_once __DIR__.'/../includi/records.php';
function check($condition,$message) { if(!$condition) throw new RuntimeException($message); }
function matchFixture($id,$a=4,$b=0,$t='T1') { return ['id'=>(string)$id,'torneo'=>$t,'squadra_casa'=>'A','squadra_ospite'=>'B','gol_casa'=>(string)$a,'gol_ospite'=>(string)$b,'data_partita'=>'2026-10-01','ora_partita'=>'20:00:00','giocata'=>'1']; }
function rowFixture($id,$player,$team,$goals=0,$own=0) { return ['id'=>(string)$player,'partita_id'=>(string)$id,'giocatore_id'=>(string)$player,'squadra_id'=>$team==='A'?'1':'2','presenza'=>'1','goal'=>(string)$goals,'autogol'=>(string)$own,'nome'=>'Nome','cognome'=>(string)$player,'squadra'=>$team,'squadra_torneo'=>'T1']; }
function verification($p,$r,$f='Calcio a 5',$ks=[]) { return ['formato'=>$f,'esito'=>'confermato','risultato_hash'=>records_hash($p),'marcature_hash'=>records_hash($p,$r),'portieri_hash'=>$ks?records_hash($p,$r):null,'portieri_json'=>json_encode($ks)]; }
$p=matchFixture(1); $r=[rowFixture(1,1,'A',3),rowFixture(1,2,'A',1),rowFixture(1,3,'B')];
$ks=[['player_id'=>1,'goals'=>0,'full'=>false],['player_id'=>2,'goals'=>0,'full'=>false],['player_id'=>3,'goals'=>4,'full'=>true]];
$v=verification($p,$r,'Calcio a 5',$ks);
$q=matchFixture(2);$w=verification($q,[]);
$six=matchFixture(3,20,0,'T2');$sv=verification($six,[],'Calcio a 6');
$run=static fn($ms,$rs,$vs,$t='',$f='',$player=1)=>records_compute($ms,$rs,$vs,$t,$f,$player);
$d=$run([$p,$q,$six],[1=>$r],[1=>$v,2=>$w,3=>$sv]);$g=$d['groups']['Calcio a 5'];
check(count($g['records']['total']['holders'])===2,'Pari merito');
check($g['records']['player']['value']===3,'Gol individuali');
check($g['records']['scorers']['value']===2,'Marcatori diversi');
check($g['goalkeeper']['appearances']===1 && $g['goalkeeper']['average']==='0.00' && $g['goalkeeper']['clean_sheets']===0,'Alternanza: zero reale, nessuna porta inviolata');
check($d['groups']['Calcio a 6']['records']['total']['value']===20,'Formati separati');
check(count($run([$p,$six],[1=>$r],[1=>$v,3=>$sv],'T1')['groups'])===1,'Filtro torneo');
check(count($run([$p,$six],[1=>$r],[1=>$v,3=>$sv],'','Calcio a 6')['groups'])===1,'Filtro formato');
$corrected=$p;$corrected['gol_casa']='5';
check($run([$corrected],[1=>$r],[1=>$v])['groups']['Calcio a 5']['records']['total']['value']===null,'Risultato corretto invalida verifica');
$incomplete=$r;$incomplete[0]['goal']='2';$iv=verification($p,$incomplete);
check($run([$p],[1=>$incomplete],[1=>$iv])['groups']['Calcio a 5']['records']['player']['value']===null,'Tabellino incompleto');
$changed=$r;$changed[0]['goal']='4';
check($run([$p],[1=>$changed],[1=>$v])['groups']['Calcio a 5']['goalkeeper']['appearances']===0,'Correzione tabellino invalida portieri');
$zero=$run([$p],[1=>$r],[1=>$v],'','',99)['groups']['Calcio a 5']['goalkeeper'];
check($zero['appearances']===0 && $zero['average']===null && $zero['clean_sheet_percentage']===null,'Zero presenze');
foreach(['annullata','tavolino'] as $status) {$bad=$v;$bad['esito']=$status;check($run([$p],[1=>$r],[1=>$bad])['groups']['Calcio a 5']['eligible_matches']===0,'Esclusione '.$status);}
$bad=$ks;$bad[0]['goals']=4;
try {records_validate_keepers($p,$r,$bad); throw new RuntimeException('Attribuzione errata accettata');} catch(InvalidArgumentException $e) {}
$own=$r;$own[1]['goal']='0';$own[2]['autogol']='1';$ov=verification($p,$own);
check($run([$p],[1=>$own],[1=>$ov])['groups']['Calcio a 5']['records']['scorers']['value']===1,'Autogol esclusi dai marcatori');
$draw=matchFixture(4,3,3);$dv=verification($draw,[]);check($run([$draw],[],[4=>$dv])['groups']['Calcio a 5']['records']['draw']['value']===6,'Pareggio');
check($run([],[],[])['groups']===[],'Eliminazione e dati assenti');
check(records_format('calcio a 5')==='Calcio a 5' && records_format('C6')==='Calcio a 6','Formati canonici');
$full=[['player_id'=>1,'goals'=>0,'full'=>true],['player_id'=>3,'goals'=>4,'full'=>true]];
$fv=verification($p,$r,'Calcio a 5',$full);
$fg=$run([$p],[1=>$r],[1=>$fv])['groups']['Calcio a 5']['goalkeeper'];
check($fg['clean_sheets']===1 && $fg['clean_sheet_percentage']==='100.00','Partita intera a porta inviolata');
$np=$p;$np['giocata']='0';$nv=verification($np,$r);
check($run([$np],[1=>$r],[1=>$nv])['groups']['Calcio a 5']['eligible_matches']===0,'Partita non conclusa');
$renewed=verification($corrected,[]);
check($run([$corrected],[],[1=>$renewed])['groups']['Calcio a 5']['records']['total']['value']===5,'Risultato corretto e riconfermato');
echo "Record e portieri: tutti i controlli superati.\n";
