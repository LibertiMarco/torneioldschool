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
foreach (['Napoli-Sampdoria 2-4','2-4'] as $text) {
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
$plan = video_sync_plan([array_replace($youtube,['title'=>'Napoli-Juve Stabia 2-3'])],[$game]);date_expect(reset($plan)['automatic'],'Different title/description scores blocked the same team pair');
$plan = video_sync_plan([array_replace($youtube,['title'=>'Napoli-Sampdoria 2-3'])],[$game]);date_expect(!reset($plan)['candidates'],'Conflicting title/description teams linked');
$month = array_replace($media,['date'=>'2026-03-01']);
$plan = video_sync_plan([$month],[array_replace($game,['data_partita'=>'2026-02-28'])]);date_expect(reset($plan)['automatic'],'Previous day across month boundary failed');
$alias = array_replace($game,['squadra_casa'=>'Barcellona','squadra_ospite'=>'Arsenal','gol_casa'=>7,'gol_ospite'=>5]);
$plan = video_sync_plan([array_replace($media,['title'=>'BARCELONA-ARSENAL 7-5'])],[$alias]);date_expect(reset($plan)['automatic'],'Confirmed alias failed in date matcher');
$betis = array_replace($game,['squadra_casa'=>'Rayo Vallecano','squadra_ospite'=>'Betis','gol_casa'=>9,'gol_ospite'=>2]);
$betisVideo = array_replace($media,['date'=>'2026-10-09','title'=>"LA LIGA CALCIO A 8 🇪🇸\nRAYO VALLECANO - BETIS SIVIGLIA 9-2"]);
$plan = video_sync_plan([$betisVideo],[$betis]);date_expect(reset($plan)['automatic'],'Betis Siviglia did not match the registered Betis team');
$plan = video_sync_plan([$betisVideo],[array_replace($betis,['gol_ospite'=>3])]);date_expect(reset($plan)['automatic'],'Different Betis score blocked team/date matching');
$plan = video_sync_plan([$betisVideo],[array_replace($betis,['data_partita'=>'2026-10-07'])]);date_expect(!reset($plan)['candidates'],'Betis alias bypassed the two-day window');
date_expect(video_sync_team_key('Betis Siviglia FC') === video_sync_team_key('Betis'),'Betis alias with a club suffix failed');
date_expect(video_sync_team_key('Betis Juniors') !== video_sync_team_key('Betis Siviglia'),'Betis alias merged a distinct team');

// Public production records checked on 2026-10-10: IDs 1938 and 1942, EredivisieC.
$excelsior = array_replace($game,['id'=>1938,'torneo'=>'EredivisieC','torneo_nome'=>'Eredivisie (Fascia C)',
    'squadra_casa'=>'Excelsior','squadra_ospite'=>'Ajax','gol_casa'=>3,'gol_ospite'=>5,'data_partita'=>'2026-10-05']);
$excelsiorVideo = array_replace($media,['date'=>'2026-10-06','title'=>"EREDIVISE C\nEXCELSIOR ROTTERDAM-AJAX 3-5"]);
$plan = video_sync_plan([$excelsiorVideo],[$excelsior]);date_expect(reset($plan)['automatic'],'Excelsior Rotterdam alias missed the previous-day game');
date_expect(video_sync_result_match(video_sync_parse_result($excelsiorVideo['title']),$excelsior,'2026-10-06'),'Save revalidation rejected the Excelsior alias');
date_expect(video_sync_team_key('Excelsior Rotterdam FC') === video_sync_team_key('Excelsior'),'Excelsior alias with club suffix failed');
date_expect(video_sync_team_key('Excelsior Maassluis') !== video_sync_team_key('Excelsior Rotterdam'),'Excelsior alias merged a different club');
$plan = video_sync_plan([$excelsiorVideo],[$excelsior,array_replace($excelsior,['id'=>9999])]);
date_expect(count(reset($plan)['candidates']) === 2 && !reset($plan)['automatic'],'Excelsior alias bypassed ambiguity checks');
$feyenoord = array_replace($excelsior,['id'=>1942,'squadra_casa'=>'Feyenoord','squadra_ospite'=>'Telstar','gol_casa'=>9,'gol_ospite'=>1,'data_partita'=>'2026-10-06']);
$feyenoordVideo = array_replace($media,['date'=>'2026-10-06','title'=>'EREDIVISE | Feyenoord-Telstar 8-1']);
$plan = video_sync_plan([$feyenoordVideo],[$feyenoord]);date_expect(reset($plan)['automatic'],'Caption 8-1 did not link to the registered 9-1');
date_expect(video_sync_result_match(video_sync_parse_result($feyenoordVideo['title']),$feyenoord,'2026-10-06'),'Save validation still required equal scores');
$reversedVideo = array_replace($feyenoordVideo,['title'=>'Telstar-Feyenoord 4-7']);
$plan = video_sync_plan([$reversedVideo],[$feyenoord]);date_expect(reset($plan)['automatic'],'Reversed teams with incorrect scores missed');
$duplicateGame = array_replace($feyenoord,['id'=>9998,'gol_casa'=>8,'data_partita'=>'2026-10-05']);
$plan = video_sync_plan([$feyenoordVideo],[$feyenoord,$duplicateGame]);
date_expect(count(reset($plan)['candidates']) === 2 && !reset($plan)['automatic'],'Exact score was used to guess between two compatible games');
$plan = video_sync_plan([$feyenoordVideo],[array_replace($feyenoord,['link_instagram'=>'https://www.instagram.com/reel/existing/'])]);
date_expect(!reset($plan)['automatic'],'Incorrect-score caption overwrote an existing link');
$plan = video_sync_plan([$feyenoordVideo],[array_replace($feyenoord,['data_partita'=>'2026-10-04'])]);
date_expect(!reset($plan)['candidates'],'Ignoring score expanded the allowed date window');
$sameTeamsLines = video_sync_parse_result("Feyenoord-Telstar 8-1\nFeyenoord-Telstar 9-1");
date_expect($sameTeamsLines !== null,'Different scores for one team pair blocked caption parsing');
date_expect(video_sync_parse_result("Feyenoord-Telstar 8-1\nExcelsior-Ajax 3-5") === null,'Multiple team pairs were arbitrarily parsed');
$plan = video_sync_plan([array_replace($feyenoordVideo,['title'=>'EREDIVISE | Feyenoord-Telstar 9-1'])],[$feyenoord]);
date_expect(reset($plan)['automatic'],'Exact Feyenoord result was not linked');
echo "PASS: today/yesterday team matching, ignored scores, save validation, reversed teams, aliases, preserved links and ambiguity\n";
