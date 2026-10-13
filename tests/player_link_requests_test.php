<?php
require_once __DIR__ . '/../includi/player_link_requests.php';
function link_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
function link_reject(callable $action, string $code): void {
    try { $action(); } catch (MobileAuthError $error) { link_expect($error->errorCode === $code, 'Unexpected error: ' . $error->errorCode); return; }
    throw new RuntimeException('Expected ' . $code);
}
$db = new PDO('sqlite::memory:');
$db->exec("CREATE TABLE utenti(id INTEGER PRIMARY KEY, ruolo TEXT, email_verificata INTEGER, nome TEXT, cognome TEXT, email TEXT);
    CREATE TABLE giocatori(id INTEGER PRIMARY KEY, utente_id INTEGER UNIQUE, nome TEXT, cognome TEXT, ruolo TEXT);
    CREATE TABLE squadre(id INTEGER PRIMARY KEY, nome TEXT);
    CREATE TABLE squadre_giocatori(squadra_id INTEGER, giocatore_id INTEGER, created_at TEXT);
    INSERT INTO squadre VALUES (1,'Old School');
    INSERT INTO squadre_giocatori VALUES (1,10,'2026-10-01');
    CREATE TABLE player_link_requests(id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, giocatore_id INTEGER,
        stato TEXT, pending_user_id INTEGER UNIQUE, created_at TEXT, reviewed_at TEXT, reviewed_by INTEGER, reason TEXT DEFAULT '');
    INSERT INTO utenti VALUES (1,'admin',1,'Admin','Demo','admin@test'), (2,'user',1,'Mario','Rossi','mario@test'),
        (3,'user',1,'Luca','Bianchi','luca@test'), (4,'grafico',1,'Grafico','Demo','grafico@test'),
        (5,'user',0,'Non','Verificato','no@test'), (6,'sysadmin',1,'Sys','Admin','sys@test'),
        (7,'user',1,'Altro','Utente','altro@test'), (8,'user',1,'Utente','Otto','otto@test'),
        (10,'user',1,'Gia','Associato','gia@test');
    INSERT INTO giocatori VALUES (10,NULL,'Mario','Rossi','Attaccante'), (11,NULL,'Luca','Bianchi','Portiere'),
        (12,10,'Gia','Associato','Difensore'), (13,NULL,'Altro','Giocatore','Centrocampista');");
$service = new PlayerLinkRequests($db);
link_reject(fn()=>$service->status(0), 'invalid_session');
link_reject(fn()=>$service->submit(5,10), 'invalid_session');
link_reject(fn()=>$service->submit(2,0), 'invalid_request');
link_reject(fn()=>$service->pending(4), 'forbidden');
link_expect(count($service->search(2,'Mario Rossi')) === 1, 'Search matches full name');
link_expect(count($service->search(2,'Rossi Mario')) === 1, 'Search matches surname first');
link_expect($service->search(2,'%_') === [], 'Wildcards are literal');
link_expect($service->search(2,'Gia') === [], 'Assigned players are excluded');
$first = $service->submit(2,10);
link_expect($first['request']['status'] === 'pending' && !$first['linked'], 'Submission never creates an association');
link_expect($db->query('SELECT utente_id FROM giocatori WHERE id=10')->fetchColumn() === null, 'Player remains free until approval');
link_reject(fn()=>$service->submit(2,11), 'conflict');
link_reject(fn()=>$service->submit(3,12), 'conflict');
link_reject(fn()=>$service->submit(3,999), 'conflict');
$competing = $service->submit(3,10);
link_expect(count($service->pending(1)) === 2, 'Admins see pending requests');
link_reject(fn()=>$service->review(3,(int)$first['request']['id'],true), 'forbidden');
$service->review(1,(int)$first['request']['id'],true);
link_expect((int)$db->query('SELECT utente_id FROM giocatori WHERE id=10')->fetchColumn() === 2, 'Approval links the requesting account');
link_expect($service->status(2)['linked'] && $service->status(2)['request']['status'] === 'accepted', 'User sees accepted status');
link_expect($service->status(3)['request']['status'] === 'rejected', 'Competing claims cannot remain approvable');
link_reject(fn()=>$service->review(1,(int)$competing['request']['id'],true), 'conflict');
link_reject(fn()=>$service->submit(2,11), 'conflict');
$second = $service->submit(3,11);
$service->review(6,(int)$second['request']['id'],false,'Nome non corrispondente');
link_expect($service->status(3)['request']['reason'] === 'Nome non corrispondente', 'Rejection reason reaches only the requester');
link_expect($db->query('SELECT utente_id FROM giocatori WHERE id=11')->fetchColumn() === null, 'Rejection never changes player ownership');
$third = $service->submit(3,11);
link_expect((int)$third['request']['id'] > (int)$second['request']['id'], 'Resubmission preserves history and uses a new request ID');
link_reject(fn()=>$service->review(6,(int)$second['request']['id'],true), 'conflict');
$db->exec('UPDATE giocatori SET utente_id=3 WHERE id=13');
link_reject(fn()=>$service->review(6,(int)$third['request']['id'],true), 'conflict');
link_expect($db->query('SELECT utente_id FROM giocatori WHERE id=11')->fetchColumn() === null, 'Stale approval rolls back completely');
$service->review(6,(int)$third['request']['id'],false);
$fourth = $service->submit(7,11);
$db->exec('UPDATE giocatori SET utente_id=8 WHERE id=11');
link_reject(fn()=>$service->review(6,(int)$fourth['request']['id'],true), 'conflict');
link_expect((int)$db->query('SELECT utente_id FROM giocatori WHERE id=11')->fetchColumn() === 8, 'Approval cannot steal another account association');
$db->exec("UPDATE utenti SET ruolo='user' WHERE id=1");
link_reject(fn()=>$service->review(1,(int)$fourth['request']['id'],false), 'forbidden');
link_reject(fn()=>player_link_requests_handle($db,7,[],['giocatore_id'=>13,'user_id'=>2]), 'invalid_request');
link_reject(fn()=>player_link_requests_handle($db,7,['q'=>[]],null), 'invalid_request');
link_expect(!isset($service->status(7)['request']['email']), 'User status never exposes another account email');
echo "Player link requests: authentication, roles, search, pending state, approval, rejection, history and conflicts passed.\n";
