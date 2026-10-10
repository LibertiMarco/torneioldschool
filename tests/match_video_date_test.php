<?php
declare(strict_types=1);
require_once __DIR__.'/../includi/match_video_sync.php';
function date_expect(bool $ok,string $message): void {if (!$ok) throw new RuntimeException($message);}
$game = ['id'=>1,'torneo'=>'CoppaItaliaB','torneo_nome'=>'COPPA ITALIA B','giornata'=>4,'fase_round'=>null,
    'squadra_casa'=>'Napoli','squadra_ospite'=>'Juve Stabia','gol_casa'=>2,'gol_ospite'=>4,'giocata'=>1,'data_partita'=>'2026-10-09','link_instagram'=>null,'link_youtube'=>null];
$media = ['platform'=>'instagram','id'=>'date','url'=>'https://www.instagram.com/reel/date/','title'=>'🇮🇹🏆COPPA ITALIA | Napoli-Juve Stabia 2-4','description'=>'','date'=>'2026-10-10'];
foreach (['2026-10-10','2026-10-09'] as $day) {
    $plan = video_sync_plan([$media],[array_replace($game,['data_partita'=>$day])]);$row = reset($plan);
    date_expect(count($row['candidates']) === 1 && $row['automatic'],'Same-day or previous-day game missed');
}
foreach (['2026-10-08','2026-10-11','2025-10-09'] as $day) {
    $plan = video_sync_plan([$media],[array_replace($game,['data_partita'=>$day])]);date_expect(!reset($plan)['candidates'],'Game outside the two-day window accepted');
}
foreach (['Napoli-Juve Stabia 2-4',"Torneo scritto diversamente\nNapoli-Juve Stabia 2-4",'TORNEO ERRATO | GIORNATA 99 | Napoli 2 - 4 Juve Stabia'] as $text) {
    $plan = video_sync_plan([array_replace($media,['title'=>$text])],[$game]);date_expect(reset($plan)['automatic'],'Tournament/round label affected date matching');
}
$plan = video_sync_plan([array_replace($media,['title'=>'Juve Stabia-Napoli 4-2'])],[$game]);date_expect(reset($plan)['automatic'],'Reversed teams and scores failed');
foreach (['Napoli-Juve Stabia 2-3','Napoli-Sampdoria 2-4','2-4'] as $text) {
    $plan = video_sync_plan([array_replace($media,['title'=>$text])],[$game]);date_expect(!reset($plan)['candidates'],'A score or team mismatch was accepted');
}
$plan = video_sync_plan([$media],[array_replace($game,['giocata'=>0])]);date_expect(!reset($plan)['candidates'],'Unfinished game linked');
$plan = video_sync_plan([$media],[array_replace($game,['link_instagram'=>'https://www.instagram.com/reel/existing/'])]);date_expect(!reset($plan)['automatic'],'Existing link overwritten');
$same = array_replace($game,['id'=>2,'data_partita'=>'2026-10-10','torneo_nome'=>'COPPA ITALIA A']);
$plan = video_sync_plan([$media],[$game,$same]);date_expect(count(reset($plan)['candidates']) === 2 && !reset($plan)['automatic'],'Same game/result on both days guessed');
$other = array_replace($media,['id'=>'other','url'=>'https://www.instagram.com/reel/other/']);
$plan = video_sync_plan([$media,$other],[$game]);date_expect(count(array_filter($plan,fn($row)=>$row['automatic'])) === 0,'Multiple video links picked arbitrarily');
$youtube = array_replace($media,['platform'=>'youtube','id'=>'abcdefghijk','url'=>'https://www.youtube.com/watch?v=abcdefghijk','title'=>'Highlights','description'=>'Napoli-Juve Stabia 2-4']);
$plan = video_sync_plan([$media,$youtube],[$game]);date_expect(count(array_filter($plan,fn($row)=>$row['automatic'])) === 2,'Independent platform links or description fallback failed');
$plan = video_sync_plan([array_replace($youtube,['title'=>'Napoli-Juve Stabia 2-3'])],[$game]);date_expect(!reset($plan)['candidates'],'Conflicting title/description scores linked');
$month = array_replace($media,['date'=>'2026-03-01']);
$plan = video_sync_plan([$month],[array_replace($game,['data_partita'=>'2026-02-28'])]);date_expect(reset($plan)['automatic'],'Previous day across month boundary failed');
$alias = array_replace($game,['squadra_casa'=>'Barcellona','squadra_ospite'=>'Arsenal','gol_casa'=>7,'gol_ospite'=>5]);
$plan = video_sync_plan([array_replace($media,['title'=>'BARCELONA-ARSENAL 7-5'])],[$alias]);date_expect(reset($plan)['automatic'],'Confirmed alias failed in date matcher');
echo "PASS: today/yesterday query window, arbitrary tournament/round labels, bare result lines, reversed teams, month boundary, aliases, preserved links and ambiguity\n";
