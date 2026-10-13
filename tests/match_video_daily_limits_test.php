<?php
declare(strict_types=1);
require_once __DIR__.'/../includi/env_loader.php';
require_once __DIR__.'/../includi/match_video_schedule.php';
function daily_check(bool $ok,string $why): void {if (!$ok) throw new RuntimeException($why);}
$channel = 'UC'.str_repeat('a',22);
$zone = new DateTimeZone('Europe/Rome');
foreach (['2026-10-13','2026-03-29','2026-10-25'] as $day) {
    $run = video_schedule_create(new DateTimeImmutable($day.' 13:00:00',$zone));
    $job = $run['job'];$job['window'] = $run['window'];$job['sources'] = ['youtube'];$job['channel'] = $channel;
    $start = new DateTimeImmutable($run['window']['start']);$end = new DateTimeImmutable($run['window']['end']);
    daily_check($end->getTimestamp()-$start->getTimestamp() === 86400,'DST elapsed hours');
    $calls = [];
    $api = function($service,$params) use (&$calls,$channel,$start,$end) {
        $calls[] = $service;
        if ($service === 'channels') return ['items'=>[['id'=>$channel]]];
        if ($service === 'search') {
            daily_check($params['channelId'] === $channel && $params['type'] === 'video','Channel filter');
            daily_check(strtotime($params['publishedAfter']) === $start->getTimestamp()-1 && strtotime($params['publishedBefore']) === $end->getTimestamp(),'API window');
            $offset = empty($params['pageToken']) ? 0 : 50;$items = [];
            for ($i=$offset;$i<min(53,$offset+50);$i++) $items[] = ['id'=>['videoId'=>sprintf('v%010d',$i)]];
            return ['items'=>$items]+($offset === 0 ? ['nextPageToken'=>'page2'] : []);
        }
        daily_check($service === 'videos','Historical playlist requested');
        $items = [];
        foreach (explode(',',$params['id']) as $id) {
            $n = (int)substr($id,1);
            $date = $n === 0 ? $start->modify('-1 second') : ($n === 52 ? $end : $start);
            $items[] = ['id'=>$id,'snippet'=>['channelId'=>$channel,'publishedAt'=>$date->format(DATE_ATOM),'title'=>'test'],'status'=>['privacyStatus'=>'public']];
        }
        return ['items'=>$items];
    };
    while ($job['index'] < 1) {video_sync_job_step($job,$api);$job = unserialize(serialize($job));}
    daily_check(!$job['errors'] && count($job['media']) === 51 && count($calls) === 5,'Pagination or exact inclusive/exclusive boundaries');
}
$job = video_sync_job_create('youtube','2026-10-13','2026-10-13');$job['channel'] = $channel;
$empty = fn($service,$params)=>['items'=>$service === 'channels' ? [['id'=>$channel]] : []];
while ($job['index'] < 1) video_sync_job_step($job,$empty);
daily_check(!$job['errors'] && !$job['media'] && $job['steps'] === 2,'Zero videos');
$job = video_sync_job_create('youtube','2026-10-13','2026-10-13');$job['channel'] = $channel;
video_sync_job_step($job,$empty);video_sync_job_step($job,fn()=>[]);
daily_check(count($job['errors']) === 1 && !$job['media'],'Incomplete response reported success');
$job = video_sync_job_create('youtube','2026-10-13','2026-10-13');$job['channel'] = $channel;$job['max_steps'] = 1;
video_sync_job_step($job,$empty);video_sync_job_step($job,fn()=>throw new LogicException('Extra request'));
daily_check(count($job['errors']) === 1 && !$job['media'],'Request budget');
$game = ['giocata'=>1,'data_partita'=>'2026-10-13','ora_partita'=>'12:00:00','link_youtube'=>'saved','link_instagram'=>''];
$run = video_schedule_create(new DateTimeImmutable('2026-10-13 13:00:00',$zone));
video_schedule_step($run,true,null,fn()=>[$game]);
daily_check($run['job']['sources'] === ['instagram'] && $run['job']['window'] === $run['window'],'Already linked platform scanned');
$run = video_schedule_create(new DateTimeImmutable('2026-10-13 13:00:00',$zone));
video_schedule_step($run,true,null,fn()=>[]);video_schedule_step($run,true);
daily_check($run['status'] === 'done' && $run['games'] === 0,'Zero games');
// Two separate processes contend for the exact flock used by the daily runner.
$path = __DIR__.'/daily-lock-'.bin2hex(random_bytes(8));$lock = fopen($path,'c');
try {
    daily_check(flock($lock,LOCK_EX|LOCK_NB),'First lock');
    $code = '$h=fopen($argv[1],"c");exit(flock($h,LOCK_EX|LOCK_NB)?1:0);';
    $child = proc_open([PHP_BINARY,'-r',$code,$path],[], $pipes);
    daily_check(is_resource($child) && proc_close($child) === 0,'Concurrent process acquired lock');
    flock($lock,LOCK_UN);
    $child = proc_open([PHP_BINARY,'-r',$code,$path],[], $pipes);
    daily_check(is_resource($child) && proc_close($child) === 1,'Interrupted process lock not released');
} finally {fclose($lock);unlink($path);}
echo "PASS: 51 recent videos, five requests, boundaries/DST, empty work, partial errors, budgets, platform skipping and process lock\n";
