<?php
declare(strict_types=1);

const VIDEO_SYNC_REEL_MIN_SECONDS = 30.0;
const VIDEO_SYNC_MP4_CHUNK = 65536;

function video_sync_reel_allowed(array $media): bool
{
    if (($media['platform'] ?? '') !== 'instagram') return true;
    $duration = $media['duration_seconds'] ?? null;
    return is_numeric($duration) && is_finite((float)$duration) && (float)$duration >= VIDEO_SYNC_REEL_MIN_SECONDS;
}

/** Only signed media URLs supplied by Instagram; never follow redirects. */
function video_sync_duration_url(string $url): bool
{
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && $p['port'] !== 443)) return false;
    return preg_match('/(?:^|\.)(?:cdninstagram\.com|fbcdn\.net)$/iD', $p['host'] ?? '') === 1;
}

/** One bounded request: no full video downloads, even when the CDN ignores Range. */
function video_sync_duration_range(string $url, int $offset): string
{
    if (!video_sync_duration_url($url) || $offset < 0 || $offset > 2147483647) throw new RuntimeException('Metadati video non disponibili.');
    $body = ''; $contentRange = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>8,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_RANGE=>$offset.'-'.($offset+VIDEO_SYNC_MP4_CHUNK-1),
        CURLOPT_HEADERFUNCTION=>static function($ch, $line) use (&$contentRange) {
            if (stripos($line, 'Content-Range:') === 0) $contentRange = trim(substr($line,14));
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION=>static function($ch, $chunk) use (&$body) {
            $remaining = VIDEO_SYNC_MP4_CHUNK-strlen($body);
            $body .= substr($chunk,0,max(0,$remaining));
            return strlen($chunk) > $remaining ? 0 : strlen($chunk);
        }]);
    $ok = curl_exec($ch); $status = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $error = curl_errno($ch); curl_close($ch);
    if (!$ok && $error !== CURLE_WRITE_ERROR) throw new RuntimeException('Metadati video non disponibili.');
    if ($status === 206) {
        if (!preg_match('/^bytes (\d+)-(\d+)\/(\d+|\*)$/D',$contentRange,$m) || (int)$m[1] !== $offset) throw new RuntimeException('Intervallo video non valido.');
    } elseif ($status !== 200 || $offset !== 0) throw new RuntimeException('Metadati video non disponibili.');
    return $body;
}

function video_sync_mp4_u32(string $bytes, int $offset): int
{
    return unpack('N',substr($bytes,$offset,4))[1];
}

/** Walk box boundaries, skipping mdat by its size. Never scan video payload for fake atoms. */
function video_sync_mp4_duration_step(string $bytes, array &$probe): ?float
{
    $base = $probe['offset'] ?? 0; $pos = 0; $length = strlen($bytes);
    while ($pos+8 <= $length) {
        $size = video_sync_mp4_u32($bytes,$pos); $type = substr($bytes,$pos+4,4); $header = 8;
        if ($size === 1) {
            if ($pos+16 > $length) break;
            $size = video_sync_mp4_u32($bytes,$pos+8)*4294967296+video_sync_mp4_u32($bytes,$pos+12); $header = 16;
        }
        if ($size < $header || $size > 2147483647 || $base+$pos+$size > 2147483647) throw new RuntimeException('Struttura video non supportata.');
        if (isset($probe['moov_end']) && $base+$pos+$size > $probe['moov_end']) throw new RuntimeException('Metadati video non validi.');
        if ($type === 'moov') {
            $probe['moov_end'] = $base+$pos+$size; $pos += $header; continue;
        }
        if ($type === 'mvhd' && isset($probe['moov_end'])) {
            if ($pos+$header+4 > $length) break;
            $start = $pos+$header; $version = ord($bytes[$start]);
            if (!in_array($version,[0,1],true)) throw new RuntimeException('Versione video non supportata.');
            $required = $version === 1 ? 32 : 20;
            if ($size < $header+$required) throw new RuntimeException('Durata video non valida.');
            if ($start+$required > $length) break;
            $scale = video_sync_mp4_u32($bytes,$start+($version === 1 ? 20 : 12));
            $ticks = $version === 1 ? video_sync_mp4_u32($bytes,$start+24)*4294967296+video_sync_mp4_u32($bytes,$start+28) : video_sync_mp4_u32($bytes,$start+16);
            if (!$scale || ($version === 0 && $ticks === 4294967295) || ($version === 1 && $ticks >= 18446744073709551615.0)) throw new RuntimeException('Durata video non disponibile.');
            $seconds = $ticks/$scale;
            if (!is_finite($seconds) || $seconds <= 0 || $seconds > 86400) throw new RuntimeException('Durata video non valida.');
            return $seconds;
        }
        $pos += (int)$size;
        if (isset($probe['moov_end']) && $base+$pos >= $probe['moov_end']) throw new RuntimeException('Durata assente nei metadati video.');
    }
    $probe['offset'] = $base+$pos;
    if ($pos === 0 && $length < 32) throw new RuntimeException('Metadati video incompleti.');
    return null;
}

function video_sync_duration_cache_path(string $id): string
{
    $root = function_exists('tos_runtime_path') ? tos_runtime_path('reel-durations') : sys_get_temp_dir().'/tos-reel-durations-'.substr(hash('sha256',__DIR__),0,12);
    return $root.'/'.hash('sha256',$id).'.json';
}

/** Cache only verified durations; expired/failed URLs can be checked in the next search. */
function video_sync_duration_cached(string $id): ?float
{
    $path = video_sync_duration_cache_path($id);
    if (!is_file($path) || filesize($path) > 1024) return null;
    $data = json_decode((string)@file_get_contents($path),true);
    $value = $data['seconds'] ?? null;
    return ($data['checked'] ?? 0) > time()-7*86400 && is_numeric($value) && is_finite((float)$value) && $value > 0 && $value <= 86400 ? (float)$value : null;
}

function video_sync_duration_store(string $id, float $seconds): void
{
    $path = video_sync_duration_cache_path($id);
    if (!is_dir(dirname($path))) @mkdir(dirname($path),0700,true);
    if (@file_put_contents($path,json_encode(['seconds'=>$seconds,'checked'=>time()]),LOCK_EX) !== false) @chmod($path,0600);
}

/** Shared by the legacy CLI. The dashboard persists this probe between browser requests. */
function video_sync_reel_duration(array $media): ?float
{
    $cached = video_sync_duration_cached((string)$media['id']);
    if ($cached !== null) return $cached;
    $probe = ['offset'=>0];
    try {
        for ($i=0;$i<12;$i++) {
            $seconds = video_sync_mp4_duration_step(video_sync_duration_range((string)($media['media_url'] ?? ''),$probe['offset']),$probe);
            if ($seconds !== null) {video_sync_duration_store((string)$media['id'],$seconds);return $seconds;}
        }
    } catch (Throwable $e) {}
    return null;
}
