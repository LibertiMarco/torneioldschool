<?php
// Incluso esclusivamente dal controller tabellini protetto da admin_guard.
require_once __DIR__.'/records.php';
require_once __DIR__.'/security.php';
$_SESSION['records_csrf'] = $_SESSION['records_csrf'] ?? bin2hex(random_bytes(32));
$recordsMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['records_save'])) {
    try {
        if (!tos_request_is_same_origin() || !hash_equals($_SESSION['records_csrf'], (string)($_POST['records_csrf'] ?? ''))) throw new RuntimeException('Richiesta non autorizzata.');
        $conn->begin_transaction();
        $lock=$conn->prepare('SELECT id FROM partite WHERE id=? FOR UPDATE'); $lock->bind_param('i',$partita_id); $lock->execute();
        [$ms,$rs,$vs]=records_source($conn); $match=null;
        foreach($ms as $m) if((int)$m['id']===$partita_id) $match=$m;
        if(!$match) throw new RuntimeException('Partita non trovata.');
        $format=records_format((string)($_POST['records_format']??''));
        if(!preg_match('/^[\p{L}\p{N} ._-]{1,80}$/u',$format)) throw new RuntimeException('Specificare un formato di gioco valido (es. Calcio a 5).');
        foreach($ms as $existing) {
            $old=$vs[$existing['id']]??null;
            if($old && (int)$existing['id']!==$partita_id && $existing['torneo']===$match['torneo'] && $old['formato']!==$format) throw new RuntimeException('Il torneo usa già il formato '.$old['formato'].'. Usare lo stesso formato per tutte le partite.');
        }
        $outcome=(string)($_POST['records_outcome']??'');
        if(!in_array($outcome,['confermato','annullata','tavolino'],true)) throw new RuntimeException('Esito non valido.');
        if($outcome==='confermato' && ((int)$match['giocata']!==1 || $match['gol_casa']===null || $match['gol_ospite']===null)) throw new RuntimeException('Concludere prima la partita con risultato.');
        $sheet=$rs[$partita_id]??[]; $hash=records_hash($match); $sheetHash=records_hash($match,$sheet);
        $scoring=null; $keeperHash=null; $keepers=[];
        if(isset($_POST['records_scoring'])) { if(!records_scoring_complete($match,$sheet)) throw new RuntimeException('Le marcature (autogol inclusi) non corrispondono al risultato.'); $scoring=$sheetHash; }
        if(isset($_POST['records_keepers'])) {
            foreach($sheet as $r) { $id=(int)$r['giocatore_id']; if(!isset($_POST['keeper'][$id])) continue;
                $goals=filter_var($_POST['keeper_goals'][$id]??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000]]);
                if($goals===false) throw new RuntimeException('Inserire i gol subiti, anche quando sono zero.');
                $keepers[]=['player_id'=>$id,'goals'=>$goals,'full'=>isset($_POST['keeper_full'][$id])];
            }
            records_validate_keepers($match,$sheet,$keepers); $keeperHash=$sheetHash;
        }
        $json=json_encode($keepers); $user=(int)$_SESSION['user_id'];
        $stmt=$conn->prepare('INSERT INTO match_record_verification (partita_id,formato,esito,risultato_hash,marcature_hash,portieri_hash,portieri_json,verified_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE formato=VALUES(formato),esito=VALUES(esito),risultato_hash=VALUES(risultato_hash),marcature_hash=VALUES(marcature_hash),portieri_hash=VALUES(portieri_hash),portieri_json=VALUES(portieri_json),verified_by=VALUES(verified_by),verified_at=CURRENT_TIMESTAMP');
        if(!$stmt) throw new RuntimeException('Migrazione Record non disponibile.');
        $stmt->bind_param('issssssi',$partita_id,$format,$outcome,$hash,$scoring,$keeperHash,$json,$user);
        if(!$stmt->execute()) throw new RuntimeException('Salvataggio non riuscito.');
        $conn->commit(); $recordsMessage='Verifica salvata.';
    } catch(Throwable $e) { $conn->rollback(); $recordsMessage=$e->getMessage(); }
}
function records_staff_form(mysqli $conn,int $id,string $message): void {
    $esc=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
    try { [$ms,$rs,$vs]=records_source($conn); } catch(Throwable $e) { echo '<p>Record: applicare migrations/records_goalkeepers.sql.</p>'; return; }
    $v=$vs[$id]??[]; $ks=[];foreach(json_decode($v['portieri_json']??'[]',true)?:[] as $k) $ks[$k['player_id']]=$k;
    ?>
    <details class="admin-card" id="recordsStaff" open><summary>Verifica Record e presenze effettive in porta</summary>
    <p><?= $esc($message) ?></p><p>Salvare prima il tabellino. Confermare il punteggio sul campo inclusi i supplementari, esclusi i rigori finali. Ogni correzione del risultato o del tabellino richiede una nuova verifica. Nessun dato storico viene dedotto.</p>
    <form method="post"><input type="hidden" name="records_csrf" value="<?= $esc($_SESSION['records_csrf']) ?>"><input type="hidden" name="records_save" value="1">
    <label>Formato di gioco <input name="records_format" list="records_formats" required maxlength="80" value="<?= $esc($v['formato']??'') ?>"></label><datalist id="records_formats"><option>Calcio a 5</option><option>Calcio a 6</option><option>Calcio a 7</option><option>Calcio a 11</option></datalist>
    <label>Esito <select name="records_outcome"><?php foreach(['confermato'=>'Risultato sul campo confermato','annullata'=>'Annullata','tavolino'=>'A tavolino'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($v['esito']??'')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <p><label><input type="checkbox" name="records_scoring"> Attesto che tutte le marcature sono complete e verificate</label></p>
    <p><label><input type="checkbox" name="records_keepers"> Attesto che i portieri e i gol subiti di entrambe le squadre sono completi e verificati</label></p>
    <div style="overflow-x:auto"><table><thead><tr><th>Ha giocato in porta</th><th>Giocatore / squadra</th><th>Gol subiti</th><th>Intera partita in porta</th></tr></thead><tbody>
    <?php foreach($rs[$id]??[] as $r): $pid=(int)$r['giocatore_id'];$k=$ks[$pid]??null; ?>
    <tr><td><input aria-label="Ha giocato in porta" type="checkbox" name="keeper[<?= $pid ?>]" <?= $k?'checked':'' ?>></td><td><?= $esc($r['nome'].' '.$r['cognome'].' / '.$r['squadra']) ?></td><td><input aria-label="Gol subiti" type="number" min="0" max="1000" name="keeper_goals[<?= $pid ?>]" value="<?= $esc($k['goals']??'') ?>"></td><td><input aria-label="Intera partita" type="checkbox" name="keeper_full[<?= $pid ?>]" <?= !empty($k['full'])?'checked':'' ?>></td></tr>
    <?php endforeach; ?></tbody></table></div><button type="submit">Salva verifica Record e portieri</button></form></details>
    <?php
}
