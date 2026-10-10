<?php
declare(strict_types=1);
$testRoot = sys_get_temp_dir().'/tos-duration-test-'.bin2hex(random_bytes(6));
function tos_runtime_path(string $relative = ''): string {global $testRoot;return $testRoot.'/'.$relative;}
require_once __DIR__.'/../includi/match_video_sync_job.php';
function duration_expect(bool $value,string $message): void {if (!$value) throw new RuntimeException($message);}
function duration_box(string $type,string $payload): string {return pack('N',8+strlen($payload)).$type.$payload;}
function duration_movie(float $seconds,int $version = 0): string {
    $payload = $version === 0 ? pack('N5',0,0,0,1000,(int)round($seconds*1000)) : pack('N8',16777216,0,0,0,0,1000,0,(int)round($seconds*1000));
    return duration_box('moov',duration_box('mvhd',$payload));
}
foreach ([0,1] as $version) foreach ([29.999,30.0,90.25] as $duration) {
    $probe = ['offset'=>0];$actual = video_sync_mp4_duration_step(duration_box('ftyp','isom0000').duration_movie($duration,$version),$probe);
    duration_expect(abs($actual-$duration)<0.00001,'MP4 duration/version mismatch');
    duration_expect(video_sync_reel_allowed(['platform'=>'instagram','duration_seconds'=>$actual]) === ($duration>=30),'30-second threshold mismatch');
}
// A large mdat is skipped by its declared size; payload bytes are never mistaken for mvhd.
$probe = ['offset'=>0];$mdatSize = 100000008;
duration_expect(video_sync_mp4_duration_step(pack('N',$mdatSize).'mdat'.duration_movie(1),$probe) === null && $probe['offset'] === $mdatSize,'mdat was scanned/downloaded');
duration_expect(video_sync_mp4_duration_step(duration_movie(30),$probe) === 30.0,'moov at end of MP4 was not read');
// A child header crossing the range boundary resumes at that exact box.
$probe = ['offset'=>0];$movie = duration_movie(60);
duration_expect(video_sync_mp4_duration_step(substr($movie,0,15),$probe) === null && $probe['offset'] === 8,'Split box header lost');
duration_expect(video_sync_mp4_duration_step(substr($movie,8),$probe) === 60.0,'Split header resume failed');
$probe = ['offset'=>0];
duration_expect(video_sync_mp4_duration_step(pack('N',1).'moov'.pack('N2',0,44).substr($movie,8),$probe) === 60.0,'Extended box size failed');
foreach ([duration_box('moov',duration_box('mvhd',pack('N5',0,0,0,0,30000))),pack('N',3).'mdat',duration_box('moov',duration_box('free','data'))] as $invalid) {
    $probe = ['offset'=>0];$rejected = false;
    try {video_sync_mp4_duration_step($invalid,$probe);} catch (RuntimeException $e) {$rejected = true;}
    duration_expect($rejected,'Malformed/unknown duration accepted');
}
foreach (['https://scontent.cdninstagram.com/video.mp4?signature=abc','https://video.xx.fbcdn.net/video.mp4'] as $url) duration_expect(video_sync_duration_url($url),'Valid CDN refused');
foreach (['http://scontent.cdninstagram.com/video','https://cdninstagram.com.evil.test/video','https://localhost/video','https://u:p@video.fbcdn.net/video','https://video.fbcdn.net:8443/video'] as $url) duration_expect(!video_sync_duration_url($url),'Unsafe CDN URL accepted');
duration_expect(!video_sync_reel_allowed(['platform'=>'instagram']) && !video_sync_reel_allowed(['platform'=>'instagram','duration_seconds'=>INF]) && video_sync_reel_allowed(['platform'=>'youtube']),'Unknown duration or YouTube policy incorrect');

// Full resumed job: 29.999 is hidden; exactly 30 and longer survive; unreadable is counted.
$items = [];
foreach (['short','boundary','long','unknown','end'] as $id) $items[] = ['id'=>$id,'caption'=>'Napoli-Betis 5-7','media_type'=>'VIDEO','media_product_type'=>'REELS','media_url'=>'https://video.fbcdn.net/'.$id,
    'permalink'=>'https://www.instagram.com/reel/'.$id.'/','timestamp'=>'2026-10-09T12:00:00Z'];
$init = fn()=>['user'=>'user','token'=>'private-token','version'=>'v26.0'];
$apiCalls = 0;$rangeCalls = 0;
$instagram = function($resource,$params,$state) use ($items,&$apiCalls) {$apiCalls++;return ['data'=>$items];};
$range = function($url,$offset) use (&$rangeCalls) {
    $rangeCalls++;$id = basename($url);
    if ($id === 'unknown') throw new RuntimeException('CDN unavailable');
    if ($id === 'end' && $offset === 0) return pack('N',100000008).'mdat';
    return duration_movie($id === 'short' ? 29.999 : ($id === 'boundary' ? 30 : 90));
};
$job = video_sync_job_create('instagram','2026-10-09','2026-10-09');$steps = 0;
while ($job['index'] === 0) {
    $before = $apiCalls+$rangeCalls;
    video_sync_job_step($job,null,$instagram,$init,$range);
    duration_expect($apiCalls+$rangeCalls-$before <= VIDEO_SYNC_DURATION_CONCURRENCY,'Parallel batch exceeded concurrency limit');
    $job = unserialize(serialize($job));duration_expect(++$steps < 20,'Job failed to finish');
}
duration_expect(array_column($job['media'],'id') === ['boundary','long','end'] && ($job['duration_short'] ?? 0) === 1 && ($job['duration_unknown'] ?? 0) === 1,'Short/unknown Reel not excluded');
duration_expect(!str_contains(json_encode($job['media']),'fbcdn.net'),'Signed video URL leaked to results');
// Rerun: all verified durations use cache; only the unreadable Reel is retried.
$before = $rangeCalls;$job = video_sync_job_create('instagram','2026-10-09','2026-10-09');
while ($job['index'] === 0) video_sync_job_step($job,null,$instagram,$init,$range);
duration_expect($rangeCalls-$before === 1 && count($job['media']) === 3,'Cache not reused or failure cached permanently');
duration_expect(video_sync_duration_cached('short') === 29.999,'Short Reel duration not cached');
file_put_contents(video_sync_duration_cache_path('short'),json_encode(['seconds'=>29,'checked'=>time()-8*86400]));
duration_expect(video_sync_duration_cached('short') === null,'Expired cache used');
foreach (glob($testRoot.'/reel-durations/*.json') as $file) unlink($file);
rmdir($testRoot.'/reel-durations');rmdir($testRoot);
echo "PASS: MP4 v0/v1, range seeking, 29.999/30 threshold, short/unknown exclusion, bounded parallel batches and cache\n";
