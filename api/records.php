<?php
require_once __DIR__.'/../includi/db.php';
require_once __DIR__.'/../includi/records.php';
require_once __DIR__.'/../includi/records_automatic.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if(($_GET['scope']??'')==='records') {
        $data=records_automatic_fetch($conn);
    } else {
        [$matches,$rows,$verified]=records_source($conn);
        $player=max(0,(int)($_GET['player_id']??0));
        $compute=$player > 0 ? 'records_player_goalkeeper_compute' : 'records_compute';
        $data=$compute($matches,$rows,$verified,(string)($_GET['torneo']??''),(string)($_GET['formato']??''),$player);
    }
    $data['groups']=(object)$data['groups'];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Record API: '.$e->getMessage());
    http_response_code(503);
    echo json_encode(['error'=>['code'=>'records_unavailable','message'=>'Impossibile caricare le statistiche. Riprova tra poco.']]);
}
