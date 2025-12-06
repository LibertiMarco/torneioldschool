<?php
header('Content-Type: application/json; charset=utf-8');

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';


$torneo=$_GET['torneo']??''; $squadra=$_GET['squadra']??'';
if(!$torneo || !$squadra){ echo json_encode(['error'=>'Parametri mancanti']); exit; }

$sql = "
    SELECT 
        g.id, g.nome, g.cognome, g.ruolo,
        sg.ruolo AS ruolo_squadra,
        sg.is_captain,
        sg.presenze AS presenze,
        sg.reti AS reti,
        sg.assist AS assist,
        sg.gialli AS gialli,
        sg.rossi AS rossi,
        sg.media_voti AS media_voti,
        COALESCE(sg.foto, g.foto) AS foto,
        s.logo AS logo_squadra
    FROM squadre s
    JOIN squadre_giocatori sg ON sg.squadra_id = s.id
    JOIN giocatori g ON g.id = sg.giocatore_id
    WHERE s.torneo = ? AND s.nome = ?
    ORDER BY g.cognome, g.nome
";
$st=$conn->prepare($sql); $st->bind_param("ss",$torneo,$squadra); $st->execute(); $res=$st->get_result();
$out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
