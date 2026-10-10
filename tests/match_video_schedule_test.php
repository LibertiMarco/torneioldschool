<?php
declare(strict_types=1);
require_once __DIR__.'/../includi/match_video_schedule.php';
function schedule_expect(bool $ok,string $message): void {if (!$ok) throw new RuntimeException($message);}
$zone = new DateTimeZone('Europe/Rome');$end = new DateTimeImmutable('2026-10-10 13:00:00',$zone);
schedule_expect(str_replace('\\','/',video_schedule_storage_file('/data/vhosts/torneioldschool.it/httpdocs')) === '/data/vhosts/torneioldschool.it/private/torneioldschool-runtime/match-video-daily/state.json','Cron storage escaped the subscription directory');
schedule_expect(video_schedule_storage_file('/data/vhosts/example/httpdocs','/custom/runtime/') === '/custom/runtime/match-video-daily/state.json','Configured runtime ignored');
$window = video_schedule_window($end);
$game = ['id'=>1,'torneo'=>'Test','torneo_nome'=>'Test','giornata'=>1,'fase_round'=>null,'giocata'=>1,
    'squadra_casa'=>'Feyenoord','squadra_ospite'=>'Telstar','gol_casa'=>9,'gol_ospite'=>1,'data_partita'=>'2026-10-09','ora_partita'=>'22:00:00','link_instagram'=>null,'link_youtube'=>null];
schedule_expect(video_schedule_game_in_window($game,$window),'Evening game missed');
schedule_expect(video_schedule_game_in_window(array_replace($game,['ora_partita'=>'13:00:00']),$window),'Start boundary excluded');
schedule_expect(!video_schedule_game_in_window(array_replace($game,['ora_partita'=>'12:59:59']),$window),'Old game included');
schedule_expect(!video_schedule_game_in_window(array_replace($game,['data_partita'=>'2026-10-10','ora_partita'=>'13:00:00']),$window),'End boundary included');
schedule_expect(!video_schedule_game_in_window(array_replace($game,['ora_partita'=>null]),$window),'Unknown game time guessed');
schedule_expect(!video_schedule_game_in_window(array_replace($game,['giocata'=>0]),$window),'Unfinished game included');
foreach (['2026-03-29 13:00:00','2026-10-25 13:00:00'] as $date) {
    $w = video_schedule_window(new DateTimeImmutable($date,$zone));
    schedule_expect(strtotime($w['end'])-strtotime($w['start']) === 86400,'DST window was not 24 elapsed hours');
}
schedule_expect(video_schedule_due(null,$end->modify('-1 minute')) === null,'Cron ran before 13');
$run = video_schedule_due(null,$end);schedule_expect($run['window'] === $window,'13 Rome anchor failed');
$done = ['day'=>'2026-10-10','status'=>'done','attempt'=>1];
schedule_expect(video_schedule_due($done,$end->modify('+1 hour')) === null,'Successful run repeated');
schedule_expect(video_schedule_due($done+['scheduled'=>false],$end) !== null,'Manual run blocked the daily 13 execution');
schedule_expect(video_schedule_due($done,$end->modify('+1 day')) !== null,'Next daily run blocked');
schedule_expect(video_schedule_due(['day'=>'2026-10-10','status'=>'failed','attempt'=>3],$end) === null,'Daily retry limit exceeded');
schedule_expect(video_schedule_due(['day'=>'2026-10-10','status'=>'running'], $end->modify('+1 hour'))['status'] === 'running','Interrupted run not resumed');
$loaded = 0;$saved = [];$calls = 0;
$load = function($w) use ($game,&$loaded) {$loaded++;return [$game,array_replace($game,['id'=>2,'ora_partita'=>'12:00:00'])];};
$scan = function(array &$job) use (&$calls) {
    $calls++;$job['index'] = count($job['sources']);$job['media'] = [
        ['platform'=>'instagram','id'=>'reel','url'=>'https://www.instagram.com/reel/test/','title'=>'Feyenoord-Telstar 8-1','description'=>'','date'=>'2026-10-10'],
        ['platform'=>'youtube','id'=>'abcdefghijk','url'=>'https://www.youtube.com/watch?v=abcdefghijk','title'=>'Telstar-Feyenoord 0-7','description'=>'','date'=>'2026-10-10']];
};
$save = function($writes) use (&$saved) {$saved = array_merge($saved,$writes);return ['saved'=>count($writes),'preserved'=>0];};
$steps = 0;
while ($run['status'] === 'running') {
    video_schedule_step($run,false,$scan,$load,$save);$run = unserialize(serialize($run));
    schedule_expect(++$steps < 10,'Schedule did not finish');
}
schedule_expect($loaded === 1 && $calls === 1 && $run['saved'] === 2 && $run['games'] === 1 && $run['status'] === 'done','Scheduled links or window failed');
schedule_expect(count($saved) === 2 && $saved[0]['id'] === 1 && !isset($run['job']),'Wrong game saved or credentials retained');
$run = video_schedule_create($end);$scanCalls = 0;
video_schedule_step($run,false,$scan,fn()=>[array_replace($game,['link_instagram'=>'existing','link_youtube'=>'existing'])],$save);
schedule_expect($run['phase'] === 'finish','Already linked games caused a social scan');
video_schedule_step($run);schedule_expect($run['status'] === 'done','Empty work did not finish');
$run = video_schedule_create($end);
while ($run['status'] === 'running') video_schedule_step($run,true,$scan,$load,fn()=>throw new RuntimeException('Dry run wrote links'));
schedule_expect($run['saved'] === 2,'Dry run did not report candidates');
echo "PASS: daily 13 Rome, exact 24h/DST, resumable run, two platforms, ignored scores, saved links, no redundant scans and dry run\n";
