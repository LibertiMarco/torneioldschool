<?php
header('Content-Type: application/json; charset=utf-8');

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';


$torneo=$_GET['torneo']??''; $squadra=$_GET['squadra']??'';
if(!$torneo || !$squadra){ echo json_encode(['error'=>'Parametri mancanti']); exit; }

$sql="SELECT id, nome, cognome, ruolo, presenze, reti, gialli, rossi, media_voti, foto FROM giocatori WHERE torneo=? AND squadra=? ORDER BY cognome, nome";
$st=$conn->prepare($sql); $st->bind_param("ss",$torneo,$squadra); $st->execute(); $res=$st->get_result();
$out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
