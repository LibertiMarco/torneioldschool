<?php
require_once __DIR__.'/records_test.php';
require_once __DIR__.'/../includi/records_automatic.php';
$tournaments=[['nome'=>'T1','filetorneo'=>'T1.php','categoria'=>'Calcio a 5','sezione'=>'calcio'],['nome'=>'T2','filetorneo'=>'T2.php','categoria'=>'Calcio a 6','sezione'=>'calcio']];
$auto=static fn($ms,$rs,$vs=[],$t='',$f='')=>records_automatic_compute($ms,$rs,$vs,$tournaments,$t,$f);
$data=$auto([$p,$q,$six],[1=>$r]);$group=$data['groups']['Calcio a 5'];
check($group['records']['total']['value']===4 && count($group['records']['total']['holders'])===2,'Storico senza attestazioni: pari merito calcolati');
check($group['records']['player']['value']===3 && $group['records']['scorers']['value']===2,'Tabellino completo riscontrato automaticamente');
check($data['groups']['Calcio a 6']['records']['total']['value']===20,'Categorie reali separano i formati');
check(count($auto([$p,$six],[1=>$r],[],'T1')['groups'])===1,'Filtro torneo automatico');
check(count($auto([$p,$six],[1=>$r],[],'','Calcio a 6')['groups'])===1,'Filtro formato automatico');
$data=$auto([$corrected],[1=>$r],[1=>$v]);
check($data['groups']['Calcio a 5']['records']['total']['value']===5,'Correzione risultato si aggiorna senza nuova attestazione');
check($data['groups']['Calcio a 5']['records']['player']['value']===null,'Correzione: vecchie marcature incoerenti escluse');
$fixed=$r;$fixed[0]['goal']='4';
check($auto([$corrected],[1=>$fixed])['groups']['Calcio a 5']['records']['player']['value']===4,'Correzione tabellino si aggiorna automaticamente');
check($auto([$p],[1=>$own])['groups']['Calcio a 5']['records']['scorers']['value']===1,'Autogol esclusi dai marcatori diversi');
$ambiguous=$r;$ambiguous[0]['squadra']=null;$ambiguous[0]['squadra_torneo']=null;
check($auto([$p],[1=>$ambiguous])['groups']['Calcio a 5']['records']['player']['value']===null,'Squadra ambigua non attribuita');
foreach(['annullata','tavolino'] as $outcome){$bad=$v;$bad['esito']=$outcome;check(!$auto([$p],[1=>$r],[1=>$bad])['groups'],'Esito speciale escluso: '.$outcome);}
$excluded=$p;$excluded['record_esclusa']='1';check(!$auto([$excluded],[1=>$r])['groups'],'Stato annullata o tavolino escluso');
$penalties=$draw;$penalties['torneo']='T1';$penalties['rigori_casa']=9;$penalties['rigori_ospite']=8;
check($auto([$penalties],[])['groups']['Calcio a 5']['records']['draw']['value']===6,'I rigori finali non sono gol della partita');
check(!$auto([$np],[])['groups'],'Partite non concluse escluse');
$unknown=$p;$unknown['torneo']='Sconosciuto';check(!$auto([$unknown],[])['groups'],'Formato ignoto non mescolato');
check(!$auto([],[])['groups'],'Eliminazione riflessa automaticamente');
echo "Record automatici: storico, correzioni, filtri, completi/incompleti e stati superati.\n";
