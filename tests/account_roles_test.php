<?php
require_once __DIR__.'/../includi/account_roles.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('CONCAT',static fn(...$values)=>implode('',$values));
$db->exec("CREATE TABLE utenti(id INTEGER PRIMARY KEY,nome TEXT,cognome TEXT,email TEXT,ruolo TEXT,avatar TEXT,feature_flags TEXT,password TEXT);
INSERT INTO utenti VALUES(1,'Sys','Uno','one@test','sysadmin','','{}','hash1'),(2,'Mario','Rossi','mario@test','user','','{\"totocalcio\":true}','hash2'),(3,'Admin','Tre','three@test','admin','','{}','hash3');");
function role_expect(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function role_reject(callable $callback,string $code):void{try{$callback();}catch(MobileAuthError $error){role_expect($error->errorCode===$code,$error->getMessage());return;}throw new RuntimeException('Missing rejection: '.$code);}
$before=$db->query('SELECT * FROM utenti WHERE id=2')->fetch(PDO::FETCH_ASSOC);
$saved=account_role_save($db,1,['id'=>2,'role'=>'grafico','expected_role'=>'user']);
role_expect($saved['user']['ruolo']==='grafico','Role updated');
$after=$db->query('SELECT * FROM utenti WHERE id=2')->fetch(PDO::FETCH_ASSOC);unset($before['ruolo'],$after['ruolo']);role_expect($before===$after,'Only role changes; identity, password and feature flags retained');
role_reject(fn()=>account_role_save($db,2,['id'=>3,'role'=>'sysadmin','expected_role'=>'admin']),'forbidden');
role_expect(account_role_save($db,3,['id'=>2,'role'=>'sysadmin','expected_role'=>'grafico'])['user']['ruolo']==='sysadmin','Existing admin permission manages every supported profile');
role_reject(fn()=>account_role_save($db,1,['id'=>2,'role'=>'user','expected_role'=>'user']),'conflict');
role_reject(fn()=>account_role_save($db,1,['id'=>2,'role'=>'admin','expected_role'=>'grafico','email'=>'evil@test']),'invalid_request');
$result=account_users_list($db,['q'=>'Mario Rossi']);role_expect(count($result['users'])===1 && (int)$result['users'][0]['id']===2,'Search selects intended account');
$db->exec("UPDATE utenti SET ruolo='user' WHERE id IN (2,3)");
role_reject(fn()=>account_role_save($db,1,['id'=>1,'role'=>'user','expected_role'=>'sysadmin']),'conflict');
echo "Account roles: permissions, last administrator, stale changes, search and role-only writes passed.\n";
