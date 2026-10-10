<?php
declare(strict_types=1);
require_once __DIR__ . '/../includi/match_video_sync_job.php';
function job_expect(bool $value,string $message): void {if (!$value) throw new RuntimeException($message);}
$oldChannel = getenv('YOUTUBE_CHANNEL_ID');
putenv('YOUTUBE_CHANNEL_ID=@testchannel');
$title = 'BRASILERAO | GIORNATA 1 | CEARA 5 - 3 MIRASSOL';
$calls = 0;
$youtube = function($service,$params) use (&$calls,$title) {
    $calls++;
    if ($service === 'channels') return ['items'=>[['contentDetails'=>['relatedPlaylists'=>['uploads'=>'uploads']]]]];
    if ($service === 'playlistItems') return ['items'=>[['contentDetails'=>['videoId'=>empty($params['pageToken']) ? 'abcdefghijk' : 'lmnopqrstuv']]]]+(empty($params['pageToken']) ? ['nextPageToken'=>'next'] : []);
    $id = $params['id'];
    return ['items'=>[['id'=>$id,'snippet'=>['title'=>$title,'description'=>'','thumbnails'=>['default'=>['url'=>'https://i.ytimg.com/vi/'.$id.'/default.jpg']],'publishedAt'=>$id === 'abcdefghijk' ? '2026-10-08T22:30:00Z' : '2026-10-09T12:00:00Z'],'status'=>['privacyStatus'=>$id === 'abcdefghijk' ? 'public' : 'private']]]];
};
$instagramInit = fn()=>['user'=>'user','token'=>'test-token','version'=>'v26.0'];
$instagram = function($resource,$params,$state) use (&$calls,$title) {
    $calls++;
    if (str_ends_with($resource,'/media')) return ['data'=>[
        ['id'=>'reel1','caption'=>$title.' #torneioldschool','media_type'=>'VIDEO','media_product_type'=>'REELS','permalink'=>'https://www.instagram.com/p/abc/','thumbnail_url'=>'https://scontent.cdninstagram.com/cover.jpg','timestamp'=>'2026-10-09T10:00:00Z'],
        ['id'=>'photo','media_type'=>'IMAGE','permalink'=>'https://www.instagram.com/p/photo/','timestamp'=>'2026-10-09T10:00:00Z'],
        ['id'=>'old','media_type'=>'VIDEO','media_product_type'=>'REELS','permalink'=>'https://www.instagram.com/reel/old/','timestamp'=>'2026-09-01T10:00:00Z']
    ],'paging'=>['next'=>'ignored-url','cursors'=>['after'=>'not-needed']]];
    return ['caption'=>$title.' #torneioldschool'];
};
$job = video_sync_job_create('both','2026-10-09','2026-10-09');
$steps = 0;
while ($job['index'] < count($job['sources'])) {
    $before = $calls;
    video_sync_job_step($job,$youtube,$instagram,$instagramInit);
    job_expect($calls-$before <= 1,'More than one external API request in a step');
    // Each subsequent step runs from state persisted by an earlier HTTP request.
    $job = unserialize(serialize($job));
    job_expect(++$steps < 20,'Job did not terminate');
}
job_expect(count($job['media']) === 2 && !$job['errors'],'Media or error isolation failed');
job_expect($job['media'][0]['thumbnail'] === 'https://scontent.cdninstagram.com/cover.jpg' && $job['media'][1]['thumbnail'] === 'https://i.ytimg.com/vi/abcdefghijk/default.jpg','Cover metadata was not retained');
job_expect($job['media'][0]['platform'] === 'instagram' && $job['media'][1]['date'] === '2026-10-09','Provider order, Reel filter or timezone failed');
job_expect($calls === 6 && $steps === 7,'Unexpected number of paginated requests');
job_expect(!str_contains(video_sync_job_progress($job),'test-token'),'Progress exposed credentials');

// Fail after already reading one YouTube video: nothing from that provider may survive.
$job = video_sync_job_create('youtube','2026-10-09','2026-10-09');
$failure = function($service,$params) use ($youtube) {
    if ($service === 'playlistItems' && !empty($params['pageToken'])) throw new RuntimeException('YouTube unavailable');
    return $youtube($service,$params);
};
for ($i=0;$i<4;$i++) video_sync_job_step($job,$failure);
job_expect(!$job['media'] && count($job['errors']) === 1 && $job['index'] === 1,'Partial provider output was accepted');

// A failed Instagram source still allows a complete YouTube scan.
$job = video_sync_job_create('both','2026-10-09','2026-10-09');
while ($job['index'] < 2) video_sync_job_step($job,$youtube,$instagram,fn()=>throw new RuntimeException('Instagram not connected'));
job_expect(count($job['media']) === 1 && count($job['errors']) === 1,'Failure prevented the other source');

// Reduced Instagram page size requires a new step, never a blocking retry loop.
$job = video_sync_job_create('instagram','2026-10-09','2026-10-09');
video_sync_job_step($job,null,$instagram,$instagramInit);
video_sync_job_step($job,null,fn()=>throw new RuntimeException('Please reduce the amount of data'));
job_expect($job['state']['limit'] === 25 && !$job['errors'] && $job['index'] === 0,'Adaptive page retry failed');
video_sync_job_step($job,null,$instagram,$instagramInit);
video_sync_job_step($job,null,$instagram,$instagramInit);
job_expect(count($job['media']) === 1,'Retry did not preserve the caption');

$job = video_sync_job_create('youtube','2026-10-09','2026-10-09');
$loop = fn($service,$params)=>$service === 'channels' ? ['items'=>[['contentDetails'=>['relatedPlaylists'=>['uploads'=>'uploads']]]]] : ['items'=>[],'nextPageToken'=>'repeat'];
for ($i=0;$i<3;$i++) video_sync_job_step($job,$loop);
job_expect($job['index'] === 1 && count($job['errors']) === 1 && !$job['media'],'Cursor cycle was accepted');
$job = video_sync_job_create('youtube','2026-10-09','2026-10-09');$job['expires'] = time()-1;
try {video_sync_job_step($job,$youtube);throw new LogicException('Expired job was accepted');} catch (RuntimeException $expected) {}
putenv($oldChannel === false ? 'YOUTUBE_CHANNEL_ID' : 'YOUTUBE_CHANNEL_ID='.$oldChannel);
// Production volume: 80 Reel/day over 30 days; no per-Reel caption requests.
$bulkCalls = 0;
$bulk = function($resource,$params,$state) use (&$bulkCalls) {
    job_expect(str_ends_with($resource,'/media'),'A caption was fetched separately');
    job_expect(str_contains($params['fields'],'caption'),'Captions missing from the page request');
    $bulkCalls++;
    $offset = (int)($params['after'] ?? 0);
    $limit = (int)$params['limit'];
    $data = [];
    for ($i=$offset;$i<min(2400,$offset+$limit);$i++) {
        $date = (new DateTimeImmutable('2026-10-09T12:00:00+02:00'))->modify('-'.intdiv($i,80).' days');
        $data[] = ['id'=>'bulk'.$i,'caption'=>'BRASILERAO | GIORNATA 1 | CEARA '.$i.' 5 - 3 MIRASSOL','media_type'=>'VIDEO','media_product_type'=>'REELS',
            'permalink'=>'https://www.instagram.com/reel/bulk'.$i.'/','timestamp'=>$date->format(DATE_ATOM)];
    }
    $response = ['data'=>$data];
    if ($offset+$limit < 2400) $response['paging'] = ['next'=>'ignored','cursors'=>['after'=>(string)($offset+$limit)]];
    return $response;
};
$job = video_sync_job_create('instagram','2026-09-10','2026-10-09');
while ($job['index'] < 1) {
    video_sync_job_step($job,null,$bulk,$instagramInit);
    $job = unserialize(serialize($job));
}
job_expect(count($job['media']) === 2400 && !$job['errors'] && $bulkCalls === 48,'High-volume scan lost Reel or used too many requests');
$matches = [];
for ($i=0;$i<2400;$i++) $matches[] = ['id'=>$i+1,'torneo'=>'Brasilerao','torneo_nome'=>'Brasilerao','giornata'=>1,'fase_round'=>null,
    'squadra_casa'=>'Ceara '.$i,'squadra_ospite'=>'Mirassol','gol_casa'=>5,'gol_ospite'=>3,'giocata'=>1,'data_partita'=>$job['media'][$i]['date'],'link_instagram'=>null,'link_youtube'=>null];
$plan = video_sync_plan($job['media'],$matches);
job_expect(count($plan) === 2400 && count(array_filter($plan,fn($row)=>$row['automatic'])) === 2400,'High-volume match indexing failed');
$job = video_sync_job_create('instagram','2026-09-10','2026-10-09');
video_sync_job_step($job,null,$bulk,$instagramInit);
video_sync_job_step($job,null,$bulk,$instagramInit);
$retained = count($job['state']['media']);
video_sync_job_step($job,null,fn()=>throw new RuntimeException('Operation timed out'));
job_expect($job['index'] === 0 && count($job['state']['media']) === $retained && $job['state']['retries'] === 1,'Transient failure lost scan state');
video_sync_job_step($job,null,$bulk,$instagramInit);
job_expect(count($job['state']['media']) === $retained+50 && !isset($job['state']['retries']),'Retry did not resume at the next page');
echo "PASS: 2400 Reel / 30 days / 48 bulk API requests and 2400 exact matches\n";
echo "PASS: resumed jobs, one API call per step, captions, timezones, privacy, failed-source isolation, adaptive paging and expiry\n";
