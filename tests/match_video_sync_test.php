<?php
declare(strict_types=1);
require_once __DIR__ . '/../includi/match_video_sync.php';
function video_expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
function video_rejects(callable $callback): void {try {$callback();} catch (RuntimeException $e) {return;}throw new RuntimeException('Invalid response was accepted.');}
$caption = 'BRASILERAO | GIORNATA 1 | CEARA 5 - 3 MIRASSOL #highlightstorneioldschool #calcioa6 #torneioldschool';
$title = 'BRASILERAO | GIORNATA 1 | CEARA 5 - 3 MIRASSOL';
$identity = video_sync_parse($caption);
video_expect($identity === video_sync_parse($title),'Hashtags changed the identity');
video_expect($identity['day'] === 1 && $identity['home_score'] === 5 && $identity['away_score'] === 3,'Caption extraction failed');
$match = ['id'=>42,'torneo'=>'Brasilerao','torneo_nome'=>'Brasilerao','giornata'=>1,'fase_round'=>null,'squadra_casa'=>'Ceará SC','squadra_ospite'=>'Mirassol FC','gol_casa'=>5,'gol_ospite'=>3,'giocata'=>1,'data_partita'=>'2026-09-20','link_instagram'=>null,'link_youtube'=>null];
$formats = [
    ["CHAMPIONS LEAGUE 2\nBARCELONA-ARSENAL 7-5",'Champions League 2','Barcellona','Arsenal',7,5],
    ['🏆CHAMPIONS LEAGUE | Napoli-Sporting Lisbona 5-7','Champions League','Napoli','Sporting Lisbona',5,7],
    ["LA LIGA CALCIO A 8 🇪🇸\nRAYO VALLECANO - BETIS SIVIGLIA 9-2...",'La Liga','Rayo Vallecano','Betis Siviglia',9,2],
];
foreach ($formats as [$text,$tournament,$home,$away,$homeScore,$awayScore]) {
    $parsed = video_sync_parse($text);
    video_expect($parsed !== null && $parsed['day'] === null && $parsed['round'] === null,'New caption format or omitted day failed');
    $fixture = array_replace($match,['torneo'=>$tournament,'torneo_nome'=>$tournament,'squadra_casa'=>$home,'squadra_ospite'=>$away,'gol_casa'=>$homeScore,'gol_ospite'=>$awayScore]);
    video_expect(video_sync_match($parsed,$fixture),'New caption did not match its game');
    $mediaFixture = ['platform'=>'instagram','id'=>'format','url'=>'https://www.instagram.com/reel/format/','title'=>$text,'description'=>'','date'=>'2026-10-09'];
    $newPlan = video_sync_plan([$mediaFixture],[$fixture]);
    video_expect(reset($newPlan)['automatic'],'Omitted-round index missed a unique game');
    $otherDay = array_replace($fixture,['id'=>999,'giornata'=>2]);
    $newPlan = video_sync_plan([$mediaFixture],[$fixture,$otherDay]);
    video_expect(count(reset($newPlan)['candidates']) === 2 && !reset($newPlan)['automatic'],'Missing day guessed between matching games');
    if ($tournament === 'Champions League 2') video_expect(!video_sync_match($parsed,array_replace($fixture,['torneo'=>'Champions League','torneo_nome'=>'Champions League'])),'Edition 2 silently became day 2');
    if ($tournament === 'La Liga') video_expect(!video_sync_match($parsed,array_replace($fixture,['torneo_nome'=>'La Liga Calcio a 6'])),'Conflicting Calcio a 6/8 tournaments merged');
}
video_expect(video_sync_parse('CHAMPIONS LEAGUE | Napoli-Sporting Lisbona') === null,'A match without scores was accepted');
foreach ([['Napoli','Sporting Lisbona',5,7],['Galatasaray','Real Madrid',0,13]] as [$home,$away,$homeGoals,$awayGoals]) {
    $game = array_replace($match,['torneo'=>'championsleague2','torneo_nome'=>'Champions League 2','data_partita'=>'2026-10-09',
        'squadra_casa'=>$home,'squadra_ospite'=>$away,'gol_casa'=>$homeGoals,'gol_ospite'=>$awayGoals]);
    $text = '🏆CHAMPIONS LEAGUE | '.$home.'-'.$away.' '.$homeGoals.'-'.$awayGoals;
    $item = ['platform'=>'instagram','id'=>'edition','url'=>'https://www.instagram.com/reel/edition/','title'=>$text,'description'=>'','date'=>'2026-10-10'];
    $proposal = video_sync_plan([$item],[$game]);$row = reset($proposal);
    video_expect(count($row['candidates']) === 1 && !$row['automatic'] && $row['missing_edition'],'Omitted edition was not proposed for manual verification');
    $otherEdition = array_replace($game,['id'=>999,'torneo'=>'championsleague3','torneo_nome'=>'Champions League 3']);
    $proposal = video_sync_plan([$item],[$game,$otherEdition]);$row = reset($proposal);
    video_expect(count($row['candidates']) === 2 && !$row['automatic'],'Missing edition chose arbitrarily between tournaments');
    $explicit = array_replace($item,['title'=>'CHAMPIONS LEAGUE 2 | '.$home.'-'.$away.' '.$homeGoals.'-'.$awayGoals]);
    $proposal = video_sync_plan([$explicit],[$game,$otherEdition]);$row = reset($proposal);
    video_expect(count($row['candidates']) === 1 && $row['automatic'],'Explicit tournament edition was ignored');
    $proposal = video_sync_plan([$explicit],[$otherEdition]);video_expect(!reset($proposal)['candidates'],'Edition 2 matched edition 3');
    $proposal = video_sync_plan([$item],[array_replace($game,['gol_casa'=>$homeGoals+1])]);video_expect(!reset($proposal)['candidates'],'Edition fallback bypassed scores');
}
video_expect(video_sync_tournament_base('Champions League 2026') === 'championsleague2026','A year was stripped as an edition');
// Reel captions omit A/B; the game determines the division, never the first tournament returned.
$cups = [
    array_replace($match,['id'=>101,'torneo'=>'CoppaItaliaA','torneo_nome'=>'COPPA ITALIA A','data_partita'=>'2026-10-08','squadra_casa'=>'Napoli','squadra_ospite'=>'Juve Stabia','gol_casa'=>2,'gol_ospite'=>4]),
    array_replace($match,['id'=>102,'torneo'=>'CoppaItaliaB','torneo_nome'=>'COPPA ITALIA B','data_partita'=>'2026-10-08','squadra_casa'=>'Modena','squadra_ospite'=>'Sampdoria','gol_casa'=>6,'gol_ospite'=>4]),
];
foreach ([['Napoli','Juve Stabia',2,4,101],['Modena','Sampdoria',6,4,102]] as [$home,$away,$homeGoals,$awayGoals,$expectedId]) {
    $text = '🇮🇹🏆COPPA ITALIA | '.$home.'-'.$away.' '.$homeGoals.'-'.$awayGoals;
    $item = ['platform'=>'instagram','id'=>'cup','url'=>'https://www.instagram.com/reel/cup/','title'=>$text,'description'=>'','date'=>'2026-10-09'];
    $proposal = video_sync_plan([$item],$cups);$row = reset($proposal);
    video_expect(count($row['candidates']) === 1 && $row['candidates'][0]['id'] === $expectedId && $row['automatic'],'The match did not select the correct cup division');
    $sameGame = array_replace($row['candidates'][0],['id'=>103,'torneo'=>'CoppaItaliaC','torneo_nome'=>'COPPA ITALIA C']);
    $proposal = video_sync_plan([$item],array_merge($cups,[$sameGame]));$row = reset($proposal);
    video_expect(count($row['candidates']) === 2 && !$row['automatic'],'An ambiguous cup division was selected automatically');
    $future = array_replace($sameGame,['data_partita'=>'2026-10-10']);
    $proposal = video_sync_plan([$item],[$future]);video_expect(!reset($proposal)['candidates'],'A game after the Reel publication was selected');
}
$explicitCup = ['platform'=>'instagram','id'=>'cup-a','url'=>'https://www.instagram.com/reel/cupA/','title'=>'COPPA ITALIA A | Modena-Sampdoria 6-4','description'=>'','date'=>'2026-10-09'];
$proposal = video_sync_plan([$explicitCup],$cups);video_expect(!reset($proposal)['candidates'],'Explicit Cup A matched Cup B');
video_expect(video_sync_tournament_base('Coppa Italia') === 'coppaitalia','Italia was incorrectly shortened as a division');
video_expect(video_sync_team_key('FC BARCELONA') === video_sync_team_key('Barcellona FC'),'Confirmed team alias failed');
video_expect(video_sync_team_key('Barcelona Juniors') !== video_sync_team_key('Barcellona'),'Alias merged a different team');
$barcelonaMatch = array_replace($match,['torneo'=>'Champions League 2','torneo_nome'=>'Champions League 2','squadra_casa'=>'FC Barcelona','squadra_ospite'=>'Arsenal','gol_casa'=>7,'gol_ospite'=>5]);
video_expect(video_sync_match(video_sync_parse("CHAMPIONS LEAGUE 2\nBARCELLONA-ARSENAL 7-5"),$barcelonaMatch),'Reverse alias failed');
video_expect(!video_sync_match(video_sync_parse("CHAMPIONS LEAGUE 2\nBARCELLONA-ARSENAL 7-4"),$barcelonaMatch),'Alias bypassed result validation');
video_expect(video_sync_parse('BRASILERAO | GIORNATA 1 | CEARA 5:3 MIRASSOL') !== null,'Existing colon score separator regressed');
video_expect(video_sync_thumbnail_url('https://i.ytimg.com/vi/abcdefghijk/default.jpg') !== '','YouTube thumbnail rejected');
video_expect(video_sync_thumbnail_url('https://scontent.cdninstagram.com/cover.jpg') !== '','Instagram thumbnail rejected');
video_expect(video_sync_thumbnail_url('javascript:alert(1)') === '' && video_sync_thumbnail_url('https://ytimg.com.attacker.example/cover.jpg') === '','Unsafe thumbnail accepted');
video_expect(video_sync_match($identity,$match,'2026-10-09'),'Late publication or accented team was rejected');
video_expect(!video_sync_match($identity,$match,'2026-09-19'),'Future match linked');
foreach (['giornata'=>2,'gol_casa'=>4,'gol_ospite'=>4,'giocata'=>0,'squadra_casa'=>'Ceara Juniors','torneo'=>'Bundesliga','torneo_nome'=>'Bundesliga'] as $field=>$value) {
    $changed = array_replace($match,[$field=>$value]);
    if ($field === 'torneo' || $field === 'torneo_nome') $changed = array_replace($match,['torneo'=>'Bundesliga','torneo_nome'=>'Bundesliga']);
    video_expect(!video_sync_match($identity,$changed,'2026-10-09'),'Wrong '.$field.' was accepted');
}
video_expect(video_sync_match(video_sync_parse('brasilerao | giornata 1 | MIRASSOL 3 – 5 CEARÁ'),$match),'Reversed team order or en dash failed');
video_expect(video_sync_parse('BRASILERAO | GIORNATA 0 | CEARA 5 - 3 MIRASSOL') === null,'Invalid day was accepted');
video_expect(video_sync_parse('BRASILERAO | GIORNATA 1 | CEARA - MIRASSOL') === null,'Missing result accepted');
$knockout = array_replace($match,['giornata'=>null,'fase_round'=>'FINALE']);
video_expect(video_sync_match(video_sync_parse('BRASILERAO | FINALE | CEARA 5 - 3 MIRASSOL'),$knockout),'Knockout round');
video_expect(!video_sync_match($identity,array_replace($match,['fase_round'=>'FINALE'])),'Regular day matched a knockout');
$ig = ['platform'=>'instagram','id'=>'ig-1','url'=>'https://www.instagram.com/reel/ABC123/','title'=>$caption,'description'=>'','date'=>'2026-10-09'];
$yt = ['platform'=>'youtube','id'=>'abcdefghijk','url'=>'https://www.youtube.com/watch?v=abcdefghijk','title'=>$title,'description'=>'','date'=>'2026-10-08'];
$plan = video_sync_plan([$ig,$yt],[$match]);
video_expect(count($plan) === 2 && count(array_filter($plan,fn($row)=>$row['automatic'])) === 2,'Both platform links should be ready');
$repeat = video_sync_plan([$ig,$ig],[$match]);video_expect(count($repeat) === 1,'Duplicate media was not deduplicated');
$duplicate = array_replace($match,['id'=>43,'data_partita'=>'2025-09-20']);
$plan = video_sync_plan([$ig],[$match,$duplicate]);video_expect(!reset($plan)['automatic'] && count(reset($plan)['candidates']) === 2,'Different editions must remain ambiguous');
$another = array_replace($ig,['id'=>'ig-2','url'=>'https://www.instagram.com/reel/DEF456/']);
$plan = video_sync_plan([$ig,$another],[$match]);video_expect(count(array_filter($plan,fn($row)=>$row['automatic'])) === 0,'Two reels were assigned to the same match');
$linked = array_replace($match,['link_instagram'=>'https://www.instagram.com/reel/ORIGINAL/']);
$plan = video_sync_plan([$ig],[$linked]);video_expect(!reset($plan)['automatic'] && str_contains(reset($plan)['status'],'conservato'),'Existing link would be overwritten');
$plan = video_sync_plan([array_replace($yt,['title'=>'Video highlights','description'=>$caption])],[$match]);video_expect(reset($plan)['automatic'],'YouTube description fallback');
$plan = video_sync_plan([array_replace($yt,['description'=>'brasilerao | giornata 1 | Ceará 5 – 3 Mirassol'])],[$match]);video_expect(reset($plan)['automatic'],'Case/accent differences created a false conflict');
$plan = video_sync_plan([array_replace($yt,['description'=>'BRASILERAO | GIORNATA 1 | CEARA 2 - 3 MIRASSOL'])],[$match]);video_expect(!reset($plan)['automatic'] && !reset($plan)['candidates'],'Conflicting metadata was ignored');
$plan = video_sync_plan([array_replace($yt,['title'=>"BRASILERAO\nCEARA-MIRASSOL 5-3",'description'=>$caption])],[$match]);video_expect(reset($plan)['automatic'] && reset($plan)['identity']['day'] === 1,'Compatible title/description did not retain the known day');
video_expect(!video_sync_valid_url('youtube','https://attacker.example/watch?v=abcdefghijk'),'Invalid video host');
video_expect(!video_sync_valid_url('instagram','https://www.instagram.com.attacker.example/reel/abc/'),'Invalid Instagram host');
video_expect(video_sync_valid_url('instagram','https://www.instagram.com/p/abc/'),'Official /p/ permalink for a verified Reel');
video_expect(video_sync_youtube_channel('https://www.youtube.com/@torneioldschool') === ['forHandle'=>'@torneioldschool'],'Channel handle');
video_expect(video_sync_youtube_channel('UC'.str_repeat('a',22)) === ['id'=>'UC'.str_repeat('a',22)],'Channel ID');

$calls = [];
$request = function($service,$params) use (&$calls,$title) {
    $calls[] = [$service,$params];
    if ($service === 'channels') return ['items'=>[['contentDetails'=>['relatedPlaylists'=>['uploads'=>'UPLOADS']]]]];
    if ($service === 'playlistItems' && empty($params['pageToken'])) return ['items'=>[['contentDetails'=>['videoId'=>'abcdefghijk']]],'nextPageToken'=>'next'];
    if ($service === 'playlistItems') return ['items'=>[['contentDetails'=>['videoId'=>'lmnopqrstuv']],['contentDetails'=>['videoId'=>'wxyz0123456']]]];
    return ['items'=>[
        ['id'=>'abcdefghijk','snippet'=>['title'=>$title,'description'=>'','publishedAt'=>'2026-10-08T22:30:00Z'],'status'=>['privacyStatus'=>'public']],
        ['id'=>'lmnopqrstuv','snippet'=>['title'=>$title,'publishedAt'=>'2026-10-09T12:00:00Z'],'status'=>['privacyStatus'=>'private']],
        ['id'=>'wxyz0123456','snippet'=>['title'=>$title,'publishedAt'=>'2026-10-10T01:00:00Z'],'status'=>['privacyStatus'=>'public']],
    ]];
};
$from = new DateTimeImmutable('2026-10-09',new DateTimeZone('Europe/Rome'));
$media = video_sync_youtube('test-key','@torneioldschool',$from,$from,$request);
video_expect(count($media) === 1 && $media[0]['date'] === '2026-10-09','Rome timezone, dates or public-video filter');
video_expect(count($calls) === 4 && $calls[2][1]['pageToken'] === 'next','API pagination or video batching');
video_rejects(fn()=>video_sync_youtube('','@torneioldschool',$from,$from,$request));
$loop = fn($service,$params)=>$service === 'channels' ? ['items'=>[['contentDetails'=>['relatedPlaylists'=>['uploads'=>'uploads']]]]] : ['items'=>[],'nextPageToken'=>'repeat'];
video_rejects(fn()=>video_sync_youtube('test-key','@torneioldschool',$from,$from,$loop));
// Including the existing sync script as a provider must not execute it or contact the DB/API.
define('TOS_INSTAGRAM_SYNC_LIBRARY',true);
ob_start();require __DIR__ . '/../api/script/sync_instagram_match_links.php';$output = ob_get_clean();
video_expect($output === '' && !isset($conn),'Instagram library include ran the sync');
video_expect(sync_instagram_is_reel(['media_type'=>'VIDEO','media_product_type'=>'REELS','permalink'=>'https://www.instagram.com/p/abc/']),'Reel classification');
video_rejects(fn()=>sync_instagram_fail('test error'));
echo "PASS: supplied captions, delayed publishing, rounds/results, accents, ambiguity, preserved links, YouTube pagination/timezones/privacy and Instagram provider isolation\n";
