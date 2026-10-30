<?php
require_once __DIR__.'/../includi/db.php';
require_once __DIR__.'/../includi/records.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    [$matches,$rows,$verified]=records_source($conn);
    $data=records_compute($matches,$rows,$verified,(string)($_GET['torneo']??''),(string)($_GET['formato']??''),max(0,(int)($_GET['player_id']??0)));
    $data['groups']=(object)$data['groups'];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Record API: '.$e->getMessage());
    http_response_code(503);
    echo json_encode(['error'=>['code'=>'records_unavailable','message'=>'Impossibile caricare le statistiche. Riprova tra poco.']]);
}
