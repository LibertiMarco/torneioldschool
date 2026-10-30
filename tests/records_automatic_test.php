<?php
require_once __DIR__.'/records_test.php';
require_once __DIR__.'/../includi/records_automatic.php';
$tournaments=[['nome'=>'T1','filetorneo'=>'T1.php','categoria'=>'Calcio a 5','sezione'=>'calcio'],['nome'=>'T2','filetorneo'=>'T2.php','categoria'=>'Calcio a 6','sezione'=>'calcio']];
$auto=static fn($ms,$rs,$vs=[])=>records_automatic_compute($ms,$rs,$vs,$tournaments);
$data=$auto([$p,$q,$six],[1=>$r]);$group=$data['groups']['Generali'];
check($group['records']['total']['value']===20 && count($group['records']['total']['holders'])===1,'Unico massimo generale tra calcio a 5 e a 6');
check($group['records']['player']['value']===3 && $group['records']['scorers']['value']===2,'Tabellino completo riscontrato automaticamente');
check(count($data['groups'])===1,'Una sola classifica generale');
$globalTie=$six;$globalTie['id']='4';$globalTie['torneo']='T1';
check(count($auto([$p,$six,$globalTie],[1=>$r])['groups']['Generali']['records']['total']['holders'])===2,'Pari merito tra formati diversi');
$data=$auto([$corrected],[1=>$r],[1=>$v]);
check($data['groups']['Generali']['records']['total']['value']===5,'Correzione risultato si aggiorna senza nuova attestazione');
check($data['groups']['Generali']['records']['player']['value']===null,'Correzione: vecchie marcature incoerenti escluse');
$fixed=$r;$fixed[0]['goal']='4';
check($auto([$corrected],[1=>$fixed])['groups']['Generali']['records']['player']['value']===4,'Correzione tabellino si aggiorna automaticamente');
check($auto([$p],[1=>$own])['groups']['Generali']['records']['scorers']['value']===1,'Autogol esclusi dai marcatori diversi');
$ambiguous=$r;$ambiguous[0]['squadra']=null;$ambiguous[0]['squadra_torneo']=null;
check($auto([$p],[1=>$ambiguous])['groups']['Generali']['records']['player']['value']===null,'Squadra ambigua non attribuita');
foreach(['annullata','tavolino'] as $outcome){$bad=$v;$bad['esito']=$outcome;check(!$auto([$p],[1=>$r],[1=>$bad])['groups'],'Esito speciale escluso: '.$outcome);}
$excluded=$p;$excluded['record_esclusa']='1';check(!$auto([$excluded],[1=>$r])['groups'],'Stato annullata o tavolino escluso');
$penalties=$draw;$penalties['torneo']='T1';$penalties['rigori_casa']=9;$penalties['rigori_ospite']=8;
check($auto([$penalties],[])['groups']['Generali']['records']['draw']['value']===6,'I rigori finali non sono gol della partita');
check(!$auto([$np],[])['groups'],'Partite non concluse escluse');
$unknown=$p;$unknown['torneo']='Sconosciuto';check($auto([$unknown],[])['groups']['Generali']['records']['total']['value']===4,'Formato ignoto incluso nei record generali');
check(!$auto([],[])['groups'],'Eliminazione riflessa automaticamente');
$esport=$p;$esport['torneo']='EAFC';
check(!records_automatic_compute([$esport],[1=>$r],[],[['nome'=>'EAFC','filetorneo'=>'EAFC.php','sezione'=>'esport']])['groups'],'Esport escluso dai record calcistici generali');
echo "Record generali: formati e tornei unificati, pari merito, correzioni e stati superati.\n";
