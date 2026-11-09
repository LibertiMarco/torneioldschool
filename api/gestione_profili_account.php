<?php
require_once __DIR__.'/../includi/admin_guard.php';
require_once __DIR__.'/mobile/v1/_bootstrap.php';
require_once __DIR__.'/../includi/account_roles.php';
header('Content-Type: text/html; charset=utf-8');
$db=mobile_db();$message='';$error='';$query=trim((string)($_GET['q']??''));
$actorCheck=$db->prepare('SELECT ruolo FROM utenti WHERE id=?');$actorCheck->execute([(int)$_SESSION['user_id']]);
if(!in_array($actorCheck->fetchColumn(),['admin','sysadmin'],true)){http_response_code(403);exit('Accesso riservato agli amministratori.');}
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
    csrf_require('account_roles');
    try {$result=account_role_save($db,(int)$_SESSION['user_id'],['id'=>(int)($_POST['id']??0),'role'=>(string)($_POST['role']??''),'expected_role'=>(string)($_POST['expected_role']??'')]);$message=$result['message'];}
    catch(MobileAuthError $failure){$error=$failure->getMessage();}
    catch(Throwable $failure){error_log('Account role update failed: '.$failure->getMessage());$error='Salvataggio non riuscito. Riprova.';}
}
$result=account_users_list($db,['q'=>$query,'page'=>$_GET['page']??'1']);$users=$result['users'];
function role_h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gestione account</title><link rel="stylesheet" href="/style.css"><style>
.account-role-panel{max-width:760px;margin:100px auto 40px;padding:24px;background:#fff;border:1px solid #dce3ec;border-radius:20px;color:#15293e}.account-role-panel form{display:grid;gap:14px;margin-top:22px}.account-role-panel label{display:grid;gap:6px;font-weight:700}.account-role-panel input,.account-role-panel select,.account-role-panel button{padding:12px;border:1px solid #bac8d8;border-radius:12px;font:inherit;max-width:100%}.account-role-panel button{background:#15293e;color:white;cursor:pointer}.account-role-message{padding:14px;background:#edf6ef;border-radius:12px;margin:12px 0}.account-role-message.error{background:#fff0ef}
</style></head><body><?php include __DIR__.'/../includi/header.php'; ?><main class="account-role-panel"><a href="/admin_dashboard.php">Torna alla dashboard</a><h1>Gestione account</h1><p>Cerca un account e modifica il suo profilo.</p>
<?php if($message):?><p class="account-role-message" role="status"><?=role_h($message)?></p><?php endif;?><?php if($error):?><p class="account-role-message error" role="alert"><?=role_h($error)?></p><?php endif;?>
<form method="GET"><label>Cerca per nome o email<input type="search" name="q" value="<?=role_h($query)?>"></label><button>Cerca account</button></form>
<form method="POST"><?=csrf_field('account_roles')?><label>Account<select name="id" id="roleAccount" required><option value="">Seleziona account</option><?php foreach($users as $user):?><option value="<?=(int)$user['id']?>" data-role="<?=role_h($user['ruolo'])?>"><?=role_h(trim($user['nome'].' '.$user['cognome']).' - '.$user['email'])?></option><?php endforeach;?></select></label>
<input type="hidden" name="expected_role" id="expectedRole"><label>Profilo<select name="role" id="newRole" required><?php foreach(['user'=>'Utente','grafico'=>'Grafico','admin'=>'Amministratore','sysadmin'=>'Sysadmin'] as $key=>$label):?><option value="<?=$key?>"><?=$label?></option><?php endforeach;?></select></label><button>Salva profilo</button></form>
<?php if($result['has_more']):?><a href="?<?=role_h(http_build_query(['q'=>$query,'page'=>(int)$result['page']+1]))?>">Altri account</a><?php endif;?></main><script>document.getElementById('roleAccount').onchange=event=>{const role=event.target.selectedOptions[0].dataset.role||'';document.getElementById('expectedRole').value=role;document.getElementById('newRole').value=role;};</script></body></html>
