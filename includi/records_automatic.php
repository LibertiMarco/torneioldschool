<?php
require_once __DIR__.'/records.php';

function records_tournament_key(string $value): string {
    return strtolower(preg_replace('/\.php$/i','',basename(str_replace('\\','/',$value))));
}

/** Usa i risultati ufficiali già salvati, senza scrivere attestazioni storiche. */
function records_automatic_compute(array $matches,array $rows,array $verified,array $tournaments): array {
    $excludedTournaments=[];
    foreach($tournaments as $t) {
        if(($t['sezione']??'calcio')!=='esport') continue;
        foreach([$t['nome'],$t['filetorneo']] as $key) $excludedTournaments[records_tournament_key($key)]=true;
    }
    $automatic=[]; $complete=0; $played=0;
    foreach($matches as $p) {
        $id=$p['id'];$v=$verified[$id]??null;
        if((int)$p['giocata']!==1 || $p['gol_casa']===null || $p['gol_ospite']===null || (int)$p['gol_casa']<0 || (int)$p['gol_ospite']<0 || !empty($p['record_esclusa']) || ($v && in_array($v['esito'],['annullata','tavolino'],true))) continue;
        if(isset($excludedTournaments[records_tournament_key($p['torneo'])])) continue;
        $sheet=$rows[$id]??[]; $valid=records_scoring_complete($p,$sheet); $seen=[];
        foreach($sheet as $r) {
            if(isset($seen[$r['giocatore_id']]) || (int)$r['goal']<0 || (int)$r['autogol']<0 || ((int)$r['goal']>0 && (int)$r['presenza']!==1)) $valid=false;
            $seen[$r['giocatore_id']]=true;
        }
        $automatic[$id]=['formato'=>'Generali','esito'=>'confermato','risultato_hash'=>records_hash($p),'marcature_hash'=>$valid?records_hash($p,$sheet):null,'portieri_hash'=>null,'portieri_json'=>'[]'];
        $played++;if($valid)$complete++;
    }
    $data=records_compute($matches,$rows,$automatic);
    foreach($data['groups'] as &$group) {unset($group['goalkeeper'],$group['verified_keeper_matches']);} unset($group);
    $data['tournaments']=$data['formats']=[];
    $data['coverage']="Record generali di tutti i tornei e formati. Calcolati automaticamente da {$played} partite concluse con risultato registrato. Tabellini completi e riscontrati sul punteggio: {$complete}. Rigori finali esclusi.";
    $data['coverage'].=' Annullate e tavolino esclusi quando segnalati nei dati; eventuali vecchi esiti speciali non registrati non sono riconoscibili.';
    return $data;
}

function records_automatic_fetch(mysqli $db): array {
    [$matches,$rows,$verified]=records_source($db,false);
    $tournaments=$db->query('SELECT nome,filetorneo,sezione FROM tornei ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    return records_automatic_compute($matches,$rows,$verified,$tournaments);
}
