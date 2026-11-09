<?php
require_once __DIR__ . '/mobile_auth.php';

function account_users_list(PDO $db, array $query): array {
    if (!is_string($query['q'] ?? '') || !is_scalar($query['page'] ?? '1')) throw new MobileAuthError('invalid_request','Ricerca non valida.',400);
    $page = filter_var($query['page'] ?? '1', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>100000]]);
    $search = trim((string)($query['q'] ?? ''));
    if ($page === false || mb_strlen($search) > 120) throw new MobileAuthError('invalid_request','Ricerca non valida.',400);
    $filter = '%' . $search . '%';
    $stmt = $db->prepare('SELECT id,email,nome,cognome,ruolo,avatar,feature_flags FROM utenti WHERE nome LIKE ? OR cognome LIKE ? OR email LIKE ? OR CONCAT(nome,\' \',cognome) LIKE ? ORDER BY cognome,nome,id LIMIT 51 OFFSET ?');
    foreach ([1,2,3,4] as $index) $stmt->bindValue($index,$filter);
    $stmt->bindValue(5,($page-1)*50,PDO::PARAM_INT); $stmt->execute();
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['users'=>array_map([MobileAuth::class,'publicUser'],array_slice($rows,0,50)),'page'=>$page,'has_more'=>count($rows)>50];
}

function account_role_save(PDO $db, int $actorId, array $input): array {
    if (array_diff(array_keys($input),['id','role','expected_role','_csrf']) || !is_int($input['id'] ?? null) || !in_array($input['role'] ?? '',['user','grafico','admin','sysadmin'],true) || !is_string($input['expected_role'] ?? null)) {
        throw new MobileAuthError('invalid_request','Profilo non valido.',400);
    }
    $db->beginTransaction();
    try {
        $lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
        $admins=$db->query("SELECT id,ruolo FROM utenti WHERE ruolo IN ('admin','sysadmin') ORDER BY id".$lock)->fetchAll(PDO::FETCH_ASSOC);
        $actor=null; foreach($admins as $admin) if((int)$admin['id']===$actorId) $actor=$admin;
        if(!$actor) throw new MobileAuthError('forbidden','Operazione riservata agli amministratori.',403);
        $stmt=$db->prepare('SELECT id,nome,cognome,email,ruolo FROM utenti WHERE id=?'.$lock);$stmt->execute([$input['id']]);$target=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$target) throw new MobileAuthError('not_found','Account non trovato.',404);
        if($target['ruolo']!==$input['expected_role']) throw new MobileAuthError('conflict','Il profilo è cambiato. Ricarica prima di salvare.',409);
        if(in_array($target['ruolo'],['admin','sysadmin'],true) && !in_array($input['role'],['admin','sysadmin'],true) && count($admins)<=1) throw new MobileAuthError('conflict','Deve rimanere almeno un amministratore.',409);
        $stmt=$db->prepare('UPDATE utenti SET ruolo=? WHERE id=?');$stmt->execute([$input['role'],$input['id']]);
        $db->commit();$target['ruolo']=$input['role'];return ['user'=>$target,'message'=>'Profilo aggiornato.'];
    } catch(Throwable $error) {if($db->inTransaction())$db->rollBack();throw $error;}
}
