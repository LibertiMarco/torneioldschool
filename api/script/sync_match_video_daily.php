<?php
declare(strict_types=1);
require_once __DIR__.'/../../includi/env_loader.php';
require_once __DIR__.'/../../includi/match_video_schedule.php';

$cli = PHP_SAPI === 'cli';
if ($cli && in_array('--help',$argv,true)) {
    echo "Uso: php api/script/sync_match_video_daily.php [--scheduled | --dry-run]\n"
        ."--scheduled: avvio giornaliero alle 13 Europe/Rome; riprende lavori interrotti.\n"
        ."--dry-run: verifica le ultime 24 ore senza salvare link o stato giornaliero.\n"
        ."Senza opzioni: sincronizza subito le ultime 24 ore.\n";
    exit;
}
if (!$cli) {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    $expected = (string)getenv('MATCH_VIDEO_CRON_TOKEN');
    $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $provided = str_starts_with($authorization,'Bearer ') ? substr($authorization,7) : (string)($_GET['token'] ?? '');
    if (strlen($expected) < 32 || !hash_equals($expected,$provided)) {http_response_code(403);echo '{"error":"Accesso non autorizzato."}';exit;}
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {http_response_code(405);exit;}
    @set_time_limit(45);
}
$dryRun = $cli && in_array('--dry-run',$argv,true);
$scheduled = !$cli || in_array('--scheduled',$argv,true);
$now = new DateTimeImmutable('now',new DateTimeZone('Europe/Rome'));
$lock = null;$run = null;
$reply = static function(array $data,int $code = 0) use ($cli): void {
    if (!$cli && $code) http_response_code(500);
    echo json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
};
$persist = static function(string $path,array $state): void {
    $json = json_encode($state,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($path.'.tmp',$json,LOCK_EX) === false) throw new RuntimeException('Impossibile salvare lo stato del cron.');
    @chmod($path.'.tmp',0600);
    if (!rename($path.'.tmp',$path)) throw new RuntimeException('Impossibile aggiornare lo stato del cron.');
};
try {
    $path = tos_runtime_path('match-video-daily/state.json');$dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Directory privata del cron non disponibile.');
    $lock = fopen($dir.'/run.lock','c');
    if ($lock === false) throw new RuntimeException('Lock del cron non disponibile.');
    if (!flock($lock,LOCK_EX | LOCK_NB)) {$reply(['status'=>'busy','message'=>'Sincronizzazione già in corso.']);exit;}
    $state = null;
    if (is_file($path)) {
        $state = json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['version'] ?? 0) !== 1) throw new RuntimeException('Stato cron non valido.');
    }
    if ($dryRun) $run = video_schedule_create($now);
    elseif ($scheduled) $run = video_schedule_due($state,$now);
    else {
        if (($state['status'] ?? '') === 'running') throw new RuntimeException('Ricerca pianificata in corso: usa --scheduled per continuarla.');
        $run = video_schedule_create($now);$run['scheduled'] = false;
    }
    if ($run === null) {$reply(['status'=>'idle','message'=>'Nessuna esecuzione dovuta; prossimo avvio alle 13:00 Europe/Rome.']);exit;}
    $started = microtime(true);
    do {
        video_schedule_step($run,$dryRun);
        if (!$dryRun) $persist($path,$run);
        // CLI completes the run; URL cron resumes on later invocations with bounded requests.
    } while ($run['status'] === 'running' && ($cli || microtime(true)-$started < 5));
    $reply(['status'=>$run['status'],'window'=>$run['window'],'phase'=>$run['phase'],'dry_run'=>$dryRun,
        'saved'=>$run['saved'],'preserved'=>$run['preserved'],'ambiguous'=>$run['ambiguous'],'errors'=>$run['errors']]);
    if ($run['status'] === 'failed') exit(1);
} catch (Throwable $e) {
    if (is_array($run) && !$dryRun) {
        $run['status'] = 'failed';$run['errors'][] = $e->getMessage();$run['retry_after'] = time()+300;
        unset($run['job'],$run['matches'],$run['writes']);
        try {$persist($path,$run);} catch (Throwable $ignored) {}
    }
    $reply(['status'=>'failed','error'=>$e->getMessage()],1);exit(1);
} finally {
    if (is_resource($lock)) {flock($lock,LOCK_UN);fclose($lock);}
}
