<?php
declare(strict_types=1);

function video_sync_normalize(string $text): string
{
    // Windows iconv may transliterate á as 'a; normalize common accents first.
    foreach (['a'=>'ÀÁÂÃÄÅàáâãäå','e'=>'ÈÉÊËèéêë','i'=>'ÌÍÎÏìíîï','o'=>'ÒÓÔÕÖòóôõö','u'=>'ÙÚÛÜùúûü','c'=>'Çç','n'=>'Ññ','y'=>'ÝŸýÿ'] as $letter=>$chars) {
        $text = str_replace(preg_split('//u',$chars,-1,PREG_SPLIT_NO_EMPTY),$letter,$text);
    }
    $text = preg_replace('/[\x{0300}-\x{036f}]/u','',$text) ?? $text;
    $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : false;
    $text = strtolower($ascii !== false ? $ascii : $text);
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '') ?? '');
}

function video_sync_team_key(string $text): string
{
    $parts = explode(' ', video_sync_normalize($text));
    return implode(' ', array_filter($parts, fn($part) => !in_array($part, ['fc', 'sc', 'cf', 'afc', 'club', 'clube', 'football', 'futebol', 'esporte', 'sports'], true)));
}

function video_sync_tournament_key(string $name): string
{
    $name = preg_replace('/\.(php|html)$/i','',$name) ?? $name;
    $name = video_sync_normalize($name);
    // "Calcio a 8" is a sport label, not a matchday or an edition number.
    $name = preg_replace('/\s+calcio\s+a\s+\d{1,2}$/','',$name) ?? $name;
    return str_replace(' ','',$name);
}

function video_sync_parse_score(string $line): ?array
{
    $dash = '[-:\x{2013}\x{2014}]';
    $line = trim(preg_replace('/[.\x{2026}]+\s*$/u','',$line) ?? $line);
    if (preg_match('/^(.+?)\s+(\d{1,3})\s*'.$dash.'\s*(\d{1,3})\s+(.+?)$/u',$line,$score)) {
        return ['home'=>trim($score[1]),'away'=>trim($score[4]),'home_score'=>(int)$score[2],'away_score'=>(int)$score[3]];
    }
    if (preg_match('/^(.+?)\s*'.$dash.'\s*(.+?)\s+(\d{1,3})\s*'.$dash.'\s*(\d{1,3})$/u',$line,$score)) {
        return ['home'=>trim($score[1]),'away'=>trim($score[2]),'home_score'=>(int)$score[3],'away_score'=>(int)$score[4]];
    }
    return null;
}

/** Structured captions, two-column captions, and tournament/team-score lines. */
function video_sync_parse(string $text): ?array
{
    $header = null;
    foreach (preg_split('/\R/u', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $line) {
        $line = trim(preg_replace('/#[^\s|]+/u', '', $line) ?? '');
        $line = trim(preg_replace('/[\p{So}\x{FE0F}\x{200D}]/u','',$line) ?? $line);
        if ($line === '') continue;
        $parts = preg_split('/\s*\|\s*/u', $line);
        $day = null; $knockout = null;
        $score = null; $tournament = null;
        if (count($parts) === 3 && trim($parts[0]) !== '') {
            $round = video_sync_normalize($parts[1]);
            if (preg_match('/^(?:giornata|g|matchday|round)\s*(\d{1,3})$/', $round, $found)) {
                $day = (int)$found[1];
                if ($day < 1 || $day > 255) {$header = null;continue;}
            } else {
                $rounds = ['trentaduesimi'=>'TRENTADUESIMI', 'sedicesimi'=>'SEDICESIMI', 'ottavi'=>'OTTAVI', 'quarti'=>'QUARTI', 'semifinale'=>'SEMIFINALE', 'finale'=>'FINALE'];
                $knockout = $rounds[$round] ?? null;
                if ($knockout === null) {$header = null;continue;}
            }
            $tournament = trim($parts[0]); $score = video_sync_parse_score($parts[2]);
        } elseif (count($parts) === 2 && trim($parts[0]) !== '') {
            $tournament = trim($parts[0]); $score = video_sync_parse_score($parts[1]);
        } elseif (count($parts) === 1) {
            $score = video_sync_parse_score($line);
            if ($score === null) {$header = $line;continue;}
            $tournament = $header;
        }
        if ($score !== null && $tournament !== null && video_sync_tournament_key($tournament) !== '') {
            return ['tournament'=>$tournament,'day'=>$day,'round'=>$knockout]+$score;
        }
        $header = null;
    }
    return null;
}

function video_sync_tournament_matches(string $caption, array $match): bool
{
    $key = video_sync_tournament_key($caption);
    if (preg_match('/calcio a (\d{1,2})$/',video_sync_normalize($caption),$captionSport)) {
        foreach ([$match['torneo_nome'] ?? '',$match['torneo'] ?? ''] as $label) {
            if (preg_match('/calcio a (\d{1,2})$/',video_sync_normalize((string)$label),$matchSport) && $captionSport[1] !== $matchSport[1]) return false;
        }
    }
    foreach ([$match['torneo_nome'] ?? '', $match['torneo'] ?? ''] as $value) {
        if ($key !== '' && $key === video_sync_tournament_key((string)$value)) return true;
    }
    return false;
}

function video_sync_identity_key(array $identity): array
{
    $home = video_sync_team_key($identity['home']); $away = video_sync_team_key($identity['away']);
    $scores = [$identity['home_score'],$identity['away_score']];
    if (strcmp($home,$away) > 0) {[$home,$away] = [$away,$home];$scores = array_reverse($scores);}
    return [video_sync_tournament_key($identity['tournament']),$identity['day'],$identity['round'],$home,$away,$scores];
}

function video_sync_identities_compatible(array $first, array $second): bool
{
    $a = video_sync_identity_key($first); $b = video_sync_identity_key($second);
    if (($first['day'] !== null || $first['round'] !== null) && ($second['day'] !== null || $second['round'] !== null)
        && [$a[1],$a[2]] !== [$b[1],$b[2]]) return false;
    $a[1] = $a[2] = $b[1] = $b[2] = null;
    return $a === $b && video_sync_tournament_matches($first['tournament'],['torneo_nome'=>$second['tournament']]);
}

function video_sync_thumbnail_url(string $url): string
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) return '';
    return preg_match('/(?:^|\.)(?:ytimg\.com|cdninstagram\.com|fbcdn\.net|fbsbx\.com)$/i',$parts['host'] ?? '') ? $url : '';
}

function video_sync_match(array $identity, array $match, string $publicationDate = ''): bool
{
    if (!video_sync_tournament_matches($identity['tournament'], $match)) return false;
    if ($identity['day'] !== null && ((int)($match['giornata'] ?? 0) !== $identity['day'] || ($match['fase_round'] ?? '') !== '')) return false;
    if ($identity['round'] !== null && ($match['fase_round'] ?? '') !== $identity['round']) return false;
    if ($publicationDate !== '' && ($match['data_partita'] ?? '') > $publicationDate) return false;
    if ((int)($match['giocata'] ?? 0) !== 1 || !isset($match['gol_casa'], $match['gol_ospite'])) return false;
    $home = video_sync_team_key($identity['home']); $away = video_sync_team_key($identity['away']);
    if ($home === '' || $away === '') return false;
    $dbHome = video_sync_team_key($match['squadra_casa']); $dbAway = video_sync_team_key($match['squadra_ospite']);
    return ($home === $dbHome && $away === $dbAway && $identity['home_score'] === (int)$match['gol_casa'] && $identity['away_score'] === (int)$match['gol_ospite'])
        || ($home === $dbAway && $away === $dbHome && $identity['home_score'] === (int)$match['gol_ospite'] && $identity['away_score'] === (int)$match['gol_casa']);
}

function video_sync_valid_url(string $platform, string $url): bool
{
    if ($platform === 'instagram') return preg_match('~^https://(?:www\.)?instagram\.com/(?:reel|reels|p)/[A-Za-z0-9_-]+/?$~D', $url) === 1;
    if ($platform === 'youtube') return preg_match('~^https://www\.youtube\.com/watch\?v=[A-Za-z0-9_-]{11}$~D', $url) === 1;
    return false;
}

function video_sync_plan(array $media, array $matches): array
{
    $rows = []; $seen = []; $counts = [];
    $index = [];
    foreach ($matches as $match) {
        if ((int)($match['giocata'] ?? 0) !== 1 || !isset($match['gol_casa'],$match['gol_ospite'])) continue;
        $round = ($match['fase_round'] ?? '') !== '' ? $match['fase_round'] : null;
        $identity = ['tournament'=>'','day'=>$round === null ? (int)($match['giornata'] ?? 0) : null,'round'=>$round,
            'home'=>$match['squadra_casa'],'away'=>$match['squadra_ospite'],'home_score'=>(int)$match['gol_casa'],'away_score'=>(int)$match['gol_ospite']];
        $aliases = [];
        foreach ([$match['torneo_nome'] ?? '',$match['torneo'] ?? ''] as $alias) {
            $identity['tournament'] = preg_replace('/\.(php|html)$/i','',(string)$alias);
            // Index both the known round and captions that omit it, without scanning all games.
            foreach ([$identity,array_replace($identity,['day'=>null,'round'=>null])] as $variant) {
                $key = json_encode(video_sync_identity_key($variant));
                if (isset($aliases[$key])) continue;
                $aliases[$key] = true; $index[$key][] = $match;
            }
        }
    }
    foreach ($media as $item) {
        if (!video_sync_valid_url($item['platform'] ?? '', $item['url'] ?? '')) continue;
        $key = hash('sha256', $item['platform'] . ':' . $item['id']);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $title = video_sync_parse($item['title'] ?? ''); $description = video_sync_parse($item['description'] ?? '');
        // Conflicting title/description identities require correction, not an arbitrary choice.
        $identity = $title ?? $description;
        $conflict = $title !== null && $description !== null && !video_sync_identities_compatible($title,$description);
        if (!$conflict && $title !== null && $description !== null && $title['day'] === null && $title['round'] === null) $identity = $description;
        $candidates = [];
        if ($identity !== null && !$conflict) foreach ($index[json_encode(video_sync_identity_key($identity))] ?? [] as $match) {
            if (video_sync_match($identity, $match, $item['date'] ?? '')) {
                $candidates[] = $match;
                $target = $item['platform'] . ':' . $match['id'];
                $counts[$target] = ($counts[$target] ?? 0) + 1;
            }
        }
        $rows[$key] = ['media'=>$item, 'identity'=>$identity, 'candidates'=>$candidates,
            'status'=>$conflict ? 'Titolo e descrizione discordanti' : ($identity === null ? 'Formato non riconosciuto' : (count($candidates) === 0 ? 'Nessuna partita corrispondente' : 'Da verificare')),
            'automatic'=>false];
    }
    foreach ($rows as &$row) {
        if (count($row['candidates']) !== 1) {if (count($row['candidates']) > 1) $row['status'] = 'Più partite corrispondenti: scegli la gara'; continue;}
        $match = $row['candidates'][0]; $platform = $row['media']['platform'];
        $existing = trim((string)($match['link_' . $platform] ?? ''));
        if ($existing !== '') {$row['status'] = $existing === $row['media']['url'] ? 'Già collegato' : 'La partita ha già un link: conservato'; continue;}
        if ($counts[$platform . ':' . $match['id']] > 1) {$row['status'] = 'Più video per la stessa gara: scegli un solo contenuto'; continue;}
        $row['status'] = 'Corrispondenza trovata'; $row['automatic'] = true;
    }
    unset($row);
    return $rows;
}

function video_sync_load_matches(mysqli $conn): array
{
    // Resolve tournament aliases once, rather than a correlated subquery for every game.
    $tournaments = $conn->query('SELECT id,nome,filetorneo FROM tornei ORDER BY id ASC');
    if (!$tournaments) throw new RuntimeException('Impossibile leggere i tornei dal database.');
    $names = [];
    foreach ($tournaments->fetch_all(MYSQLI_ASSOC) as $tournament) {
        foreach ([$tournament['nome'],$tournament['filetorneo']] as $alias) {
            $names[(string)$alias] = $tournament['nome'];
            $names[preg_replace('/\.(php|html)$/i','',(string)$alias)] = $tournament['nome'];
        }
    }
    $result = $conn->query('SELECT id,torneo,giornata,fase_round,squadra_casa,squadra_ospite,gol_casa,gol_ospite,giocata,data_partita,link_instagram,link_youtube FROM partite WHERE giocata=1 ORDER BY data_partita DESC,id DESC');
    if (!$result) throw new RuntimeException('Impossibile leggere le partite dal database.');
    $matches = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($matches as &$match) $match['torneo_nome'] = $names[$match['torneo']] ?? $names[preg_replace('/\.(php|html)$/i','',$match['torneo'])] ?? $match['torneo'];
    unset($match);
    return $matches;
}

/** Request only fixed official API hosts; credentials never appear in error text. */
function video_sync_request(string $service, array $params, string $key): array
{
    $url = 'https://www.googleapis.com/youtube/v3/' . $service . '?' . http_build_query($params + ['key'=>$key], '', '&', PHP_QUERY_RFC3986);
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_FOLLOWLOCATION=>false]);
    $raw = curl_exec($curl); $status = (int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
    $json = is_string($raw) ? json_decode($raw,true) : null;
    if ($status < 200 || $status >= 300 || !is_array($json) || isset($json['error'])) {
        $message = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
        throw new RuntimeException('YouTube: ' . str_replace($key, '[chiave omessa]', $message ?: 'Connessione o risposta API non disponibile.'));
    }
    return $json;
}

function video_sync_youtube_channel(string $value): array
{
    $value = trim($value);
    if (preg_match('~^(?:https?://)?(?:www\.)?youtube\.com/(@[^/?]+|channel/[^/?]+|user/[^/?]+)~i', $value, $found)) $value = $found[1];
    if (str_starts_with($value,'@')) return ['forHandle'=>$value];
    if (str_starts_with($value,'user/')) return ['forUsername'=>substr($value,5)];
    if (str_starts_with($value,'channel/')) $value = substr($value,8);
    if (preg_match('/^UC[A-Za-z0-9_-]{22}$/D',$value)) return ['id'=>$value];
    throw new InvalidArgumentException('Configura YOUTUBE_CHANNEL_ID con l’ID UC… del canale o il suo @handle.');
}

function video_sync_youtube(string $key, string $channel, DateTimeImmutable $from, DateTimeImmutable $to, ?callable $request = null): array
{
    if ($key === '' || $channel === '') throw new RuntimeException('YouTube non configurato: imposta YOUTUBE_API_KEY e YOUTUBE_CHANNEL_ID sul server.');
    $request ??= fn($service,$params) => video_sync_request($service,$params,$key);
    $channels = $request('channels',['part'=>'contentDetails','fields'=>'items(contentDetails(relatedPlaylists(uploads)))'] + video_sync_youtube_channel($channel));
    $playlist = $channels['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
    if ($playlist === '') throw new RuntimeException('Canale YouTube o elenco dei caricamenti non trovato.');
    $ids = []; $pageToken = ''; $seen = [];
    // Traverse the upload list; do not assume the public video date is ordered by upload date.
    for ($page=0; $page<100; $page++) {
        $params = ['part'=>'contentDetails','playlistId'=>$playlist,'maxResults'=>50,'fields'=>'items(contentDetails(videoId)),nextPageToken'];
        if ($pageToken !== '') $params['pageToken'] = $pageToken;
        $list = $request('playlistItems',$params);
        foreach ($list['items'] ?? [] as $item) {
            $id = $item['contentDetails']['videoId'] ?? '';
            if (preg_match('/^[A-Za-z0-9_-]{11}$/D',$id)) $ids[$id] = true;
        }
        $pageToken = (string)($list['nextPageToken'] ?? '');
        if ($pageToken === '') break;
        if (isset($seen[$pageToken]) || $page === 99) throw new RuntimeException('Elenco YouTube incompleto: limite di paginazione raggiunto. Nessun link YouTube preparato.');
        $seen[$pageToken] = true;
    }
    $media = []; $end = $to->modify('+1 day');
    foreach (array_chunk(array_keys($ids),50) as $batch) {
        $videos = $request('videos',['part'=>'snippet,status','id'=>implode(',',$batch),'maxResults'=>50,'fields'=>'items(id,snippet(title,description,publishedAt),status(privacyStatus))']);
        foreach ($videos['items'] ?? [] as $video) {
            if (($video['status']['privacyStatus'] ?? '') !== 'public') continue;
            try {$published = new DateTimeImmutable($video['snippet']['publishedAt'] ?? '');} catch (Throwable $e) {continue;}
            if ($published < $from || $published >= $end || empty($video['snippet']['publishedAt'])) continue;
            $id = $video['id']; if (!isset($ids[$id])) continue;
            $media[] = ['platform'=>'youtube','id'=>$id,'url'=>'https://www.youtube.com/watch?v='.$id,
                'title'=>$video['snippet']['title'] ?? '', 'description'=>$video['snippet']['description'] ?? '',
                'date'=>$published->setTimezone(new DateTimeZone('Europe/Rome'))->format('Y-m-d')];
        }
    }
    return $media;
}

function video_sync_instagram(DateTimeImmutable $from, DateTimeImmutable $to): array
{
    if (!defined('TOS_INSTAGRAM_SYNC_LIBRARY')) define('TOS_INSTAGRAM_SYNC_LIBRARY', true);
    require_once __DIR__ . '/../api/script/sync_instagram_match_links.php';
    $state = tos_load_instagram_token_state();
    if ($state === null) $state = ['access_token'=>(string)getenv('INSTAGRAM_ACCESS_TOKEN'),'user_id'=>(string)getenv('INSTAGRAM_USER_ID')];
    if (empty($state['access_token']) || empty($state['user_id'])) throw new RuntimeException('Instagram non collegato. Collega il profilo da /instagram/login.php.');
    $refresh = sync_instagram_refresh_token($state);
    if (!$refresh['ok']) throw new RuntimeException($refresh['error']);
    $state = $refresh['state'];
    $version = (string)(getenv('INSTAGRAM_GRAPH_API_VERSION') ?: 'v26.0');
    if (!preg_match('/^v\d+\.\d+$/D',$version)) throw new RuntimeException('Versione API Instagram non valida.');
    $reels = sync_instagram_fetch_yesterdays_reels($state['user_id'],$state['access_token'],$version,$from,$to);
    return array_map(fn($reel)=>['platform'=>'instagram','id'=>$reel['id'],'url'=>$reel['permalink'],
        'title'=>$reel['caption'],'description'=>'','date'=>(new DateTimeImmutable($reel['timestamp']))->setTimezone(new DateTimeZone('Europe/Rome'))->format('Y-m-d')],$reels);
}
