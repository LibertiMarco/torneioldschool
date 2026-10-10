<?php
declare(strict_types=1);
require_once __DIR__ . '/match_video_sync.php';

/** Private session state: one external HTTP call at most per browser request. */
function video_sync_job_create(string $platform, string $from, string $to): array
{
    if (!in_array($platform,['both','instagram','youtube'],true)) throw new InvalidArgumentException('Piattaforma non valida.');
    $sources = $platform === 'both' ? ['instagram','youtube'] : [$platform];
    return ['id'=>bin2hex(random_bytes(16)), 'expires'=>time()+1800, 'from'=>$from, 'to'=>$to,
        'sources'=>$sources, 'index'=>0, 'state'=>['phase'=>'init'], 'media'=>[], 'errors'=>[], 'steps'=>0];
}

function video_sync_job_instagram_library(): void
{
    if (!defined('TOS_INSTAGRAM_SYNC_LIBRARY')) define('TOS_INSTAGRAM_SYNC_LIBRARY',true);
    require_once __DIR__ . '/../api/script/sync_instagram_match_links.php';
}

function video_sync_job_instagram_init(): array
{
    video_sync_job_instagram_library();
    $state = tos_load_instagram_token_state() ?? ['access_token'=>(string)getenv('INSTAGRAM_ACCESS_TOKEN'),'user_id'=>(string)getenv('INSTAGRAM_USER_ID')];
    if (empty($state['access_token']) || empty($state['user_id'])) throw new RuntimeException('Instagram non collegato. Collega il profilo da /instagram/login.php.');
    $refresh = sync_instagram_refresh_token($state,15);
    if (!$refresh['ok']) throw new RuntimeException($refresh['error']);
    $version = (string)(getenv('INSTAGRAM_GRAPH_API_VERSION') ?: 'v26.0');
    if (!preg_match('/^v\d+\.\d+$/D',$version)) throw new RuntimeException('Versione API Instagram non valida.');
    return ['token'=>$refresh['state']['access_token'],'user'=>$refresh['state']['user_id'],'version'=>$version];
}

function video_sync_job_instagram_request(string $resource, array $params, array $state): array
{
    video_sync_job_instagram_library();
    $url = 'https://graph.instagram.com/'.rawurlencode($state['version']).'/'.implode('/',array_map('rawurlencode',explode('/',$resource)))
        .'?'.http_build_query($params+['access_token'=>$state['token']],'','&',PHP_QUERY_RFC3986);
    $response = sync_instagram_request($url,15);
    if (!$response['ok']) throw new RuntimeException('Instagram: '.str_replace($state['token'],'[token omesso]',(string)($response['error'] ?? 'Connessione API non disponibile.')));
    return $response['data'];
}

function video_sync_job_next(array &$job, bool $success): void
{
    if ($success) $job['media'] = array_merge($job['media'],$job['state']['media'] ?? []);
    $job['index']++;
    $job['state'] = ['phase'=>'init'];
}

function video_sync_job_step(array &$job, ?callable $youtube = null, ?callable $instagram = null, ?callable $instagramInit = null): void
{
    if ($job['expires'] < time()) throw new RuntimeException('La ricerca è scaduta. Avviane una nuova.');
    if ($job['index'] >= count($job['sources'])) return;
    $job['expires'] = time()+1800;
    $job['steps']++;
    $source = $job['sources'][$job['index']];
    $s =& $job['state'];
    $from = new DateTimeImmutable($job['from'],new DateTimeZone('Europe/Rome'));
    $end = (new DateTimeImmutable($job['to'],new DateTimeZone('Europe/Rome')))->modify('+1 day');
    try {
        if ($source === 'youtube') {
            if ($youtube === null && trim((string)getenv('YOUTUBE_API_KEY')) === '') throw new RuntimeException('YouTube non configurato: imposta YOUTUBE_API_KEY.');
            $youtube ??= fn($service,$params)=>video_sync_request($service,$params,trim((string)getenv('YOUTUBE_API_KEY')));
            if ($s['phase'] === 'init') {
                $channel = trim((string)getenv('YOUTUBE_CHANNEL_ID'));
                if ($channel === '') throw new RuntimeException('YouTube non configurato: imposta YOUTUBE_CHANNEL_ID.');
                $data = $youtube('channels',['part'=>'contentDetails','fields'=>'items(contentDetails(relatedPlaylists(uploads)))']+video_sync_youtube_channel($channel));
                unset($s['retries'],$s['retry_message']);
                $playlist = $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
                if ($playlist === '') throw new RuntimeException('Elenco dei caricamenti YouTube non trovato.');
                $s = ['phase'=>'page','playlist'=>$playlist,'cursor'=>'','seen'=>[],'ids'=>[],'pages'=>0,'media'=>[]];
            } elseif ($s['phase'] === 'page') {
                $params = ['part'=>'contentDetails','playlistId'=>$s['playlist'],'maxResults'=>50,'fields'=>'items(contentDetails(videoId)),nextPageToken'];
                if ($s['cursor'] !== '') $params['pageToken'] = $s['cursor'];
                $data = $youtube('playlistItems',$params);
                unset($s['retries'],$s['retry_message']);
                $s['batch'] = [];
                foreach ($data['items'] ?? [] as $item) {
                    $id = $item['contentDetails']['videoId'] ?? '';
                    if (!preg_match('/^[A-Za-z0-9_-]{11}$/D',$id) || isset($s['ids'][$id])) continue;
                    $s['ids'][$id] = true; $s['batch'][] = $id;
                }
                $cursor = (string)($data['nextPageToken'] ?? ''); $s['pages']++;
                if ($cursor !== '' && (isset($s['seen'][$cursor]) || $s['pages'] >= 1000)) throw new RuntimeException('Elenco YouTube incompleto: limite di paginazione raggiunto. Nessun link YouTube preparato.');
                $s['seen'][$cursor] = true; $s['cursor'] = $cursor;
                $s['phase'] = $s['batch'] ? 'videos' : 'page';
                if (!$s['batch'] && $cursor === '') video_sync_job_next($job,true);
            } elseif ($s['phase'] === 'videos') {
                $data = $youtube('videos',['part'=>'snippet,status','id'=>implode(',',$s['batch']),'maxResults'=>50,'fields'=>'items(id,snippet(title,description,publishedAt,thumbnails(default(url))),status(privacyStatus))']);
                unset($s['retries'],$s['retry_message']);
                foreach ($data['items'] ?? [] as $video) {
                    $timestamp = $video['snippet']['publishedAt'] ?? '';
                    if ($timestamp === '' || ($video['status']['privacyStatus'] ?? '') !== 'public' || !in_array($video['id'] ?? '',$s['batch'],true)) continue;
                    try {$date = new DateTimeImmutable($timestamp);} catch (Throwable $e) {continue;}
                    if ($date < $from || $date >= $end) continue;
                    $s['media'][] = ['platform'=>'youtube','id'=>$video['id'],'url'=>'https://www.youtube.com/watch?v='.$video['id'],
                        'title'=>$video['snippet']['title'] ?? '', 'description'=>$video['snippet']['description'] ?? '', 'thumbnail'=>video_sync_thumbnail_url($video['snippet']['thumbnails']['default']['url'] ?? ''), 'date'=>$date->setTimezone(new DateTimeZone('Europe/Rome'))->format('Y-m-d')];
                }
                $s['phase'] = 'page'; unset($s['batch']);
                if ($s['cursor'] === '') video_sync_job_next($job,true);
            }
        } else {
            $instagram ??= 'video_sync_job_instagram_request';
            if ($s['phase'] === 'init') {
                $credentials = ($instagramInit ?? 'video_sync_job_instagram_init')();
                $s = $credentials+['phase'=>'page','cursor'=>'','seen'=>[],'ids'=>[],'pages'=>0,'limit'=>50,'media'=>[]];
            } elseif ($s['phase'] === 'page') {
                $params = ['fields'=>'id,caption,media_type,media_product_type,permalink,thumbnail_url,timestamp','limit'=>$s['limit']];
                if ($s['cursor'] !== '') $params['after'] = $s['cursor'];
                try {$data = $instagram($s['user'].'/media',$params,$s);} catch (RuntimeException $e) {
                    if (str_contains(strtolower($e->getMessage()),'reduce the amount of data') && $s['limit'] > 1) {$s['limit'] = max(1,intdiv($s['limit'],2));return;}
                    throw $e;
                }
                unset($s['retries'],$s['retry_message']);
                $oldest = null;
                foreach ($data['data'] ?? [] as $item) {
                    if (empty($item['timestamp'])) continue;
                    try {$date = new DateTimeImmutable($item['timestamp']);} catch (Throwable $e) {continue;}
                    $oldest = $oldest === null || $date < $oldest ? $date : $oldest;
                    if ($date < $from || $date >= $end || empty($item['id']) || empty($item['permalink']) || isset($s['ids'][$item['id']])) continue;
                    // Same Reel classification as the CLI provider, without loading credentials in tests.
                    if (strtoupper($item['media_type'] ?? '') !== 'VIDEO') continue;
                    $product = strtoupper($item['media_product_type'] ?? '');
                    if ($product !== '' ? $product !== 'REELS' : !preg_match('~instagram\.com/(?:reel|reels)/~',$item['permalink'])) continue;
                    $s['ids'][$item['id']] = true;
                    $caption = trim((string)($item['caption'] ?? ''));
                    if ($caption !== '') $s['media'][] = ['platform'=>'instagram','id'=>$item['id'],'url'=>$item['permalink'],'title'=>$caption,'description'=>'',
                        'thumbnail'=>video_sync_thumbnail_url($item['thumbnail_url'] ?? ''),'date'=>$date->setTimezone(new DateTimeZone('Europe/Rome'))->format('Y-m-d')];
                }
                $cursor = !empty($data['paging']['next']) ? (string)($data['paging']['cursors']['after'] ?? '') : '';
                if ($oldest !== null && $oldest < $from) $cursor = '';
                $s['pages']++;
                if ($cursor !== '' && (isset($s['seen'][$cursor]) || $s['pages'] >= 1000)) throw new RuntimeException('Elenco Instagram incompleto: nessun link Instagram preparato.');
                $s['seen'][$cursor] = true; $s['cursor'] = $cursor;
                if ($cursor === '') video_sync_job_next($job,true);
            }
        }
    } catch (Throwable $e) {
        if (preg_match('/timed out|timeout|temporar|please retry|unexpected error|connessione (?:o risposta|api)/i',$e->getMessage()) && ($s['retries'] ?? 0) < 3) {
            $s['retries'] = ($s['retries'] ?? 0)+1;
            $s['retry_message'] = 'Connessione lenta: nuovo tentativo '.$s['retries'].' di 3';
            return;
        }
        $job['errors'][] = $e->getMessage();
        // A failed provider contributes no partially collected media.
        video_sync_job_next($job,false);
    }
}

function video_sync_job_progress(array $job): string
{
    $source = $job['sources'][$job['index']] ?? null;
    if ($source === null) return 'Lettura completata. Abbinamento alle partite…';
    if (!empty($job['state']['retry_message'])) return ucfirst($source).' · '.$job['state']['retry_message'];
    return ucfirst($source).' · '.($job['state']['pages'] ?? 0).' pagine lette · '
        .(count($job['media'])+count($job['state']['media'] ?? [])).' contenuti trovati';
}

function video_sync_open_database(): mysqli
{
    if (!getenv('DB_USER') || !getenv('DB_NAME')) throw new RuntimeException('Configurazione database mancante.');
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT,5);
    if (defined('MYSQLI_OPT_READ_TIMEOUT')) $conn->options(MYSQLI_OPT_READ_TIMEOUT,10);
    if (!@$conn->real_connect((string)(getenv('DB_HOST') ?: 'localhost'),(string)getenv('DB_USER'),(string)getenv('DB_PASSWORD'),(string)getenv('DB_NAME'))) {
        throw new RuntimeException('Connessione al database non disponibile. Riprova quando il database è raggiungibile.');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
