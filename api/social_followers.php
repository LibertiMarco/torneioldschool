<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/env_loader.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=21600');

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function social_request_is_same_origin(): bool
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return false;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $current = parse_url(($isHttps ? 'https' : 'http') . '://' . $host);
    if (!is_array($current)) {
        return false;
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $headerName) {
        $value = trim((string)($_SERVER[$headerName] ?? ''));
        if ($value === '') {
            continue;
        }

        $candidate = parse_url($value);
        if (!is_array($candidate)) {
            continue;
        }

        $currentScheme = strtolower((string)($current['scheme'] ?? ''));
        $currentHost = strtolower((string)($current['host'] ?? ''));
        $currentPort = isset($current['port']) ? (int)$current['port'] : ($currentScheme === 'https' ? 443 : 80);
        $candidateScheme = strtolower((string)($candidate['scheme'] ?? ''));
        $candidateHost = strtolower((string)($candidate['host'] ?? ''));
        $candidatePort = isset($candidate['port']) ? (int)$candidate['port'] : ($candidateScheme === 'https' ? 443 : 80);

        if ($candidateScheme === $currentScheme && $candidateHost === $currentHost && $candidatePort === $currentPort) {
            return true;
        }
    }

    return strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''))) === 'same-origin';
}

function ensure_parent_dir(string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function read_json_file(string $path): ?array
{
    if (!file_exists($path)) {
        return null;
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function social_result(?int $count, ?string $error = null): array
{
    return ['count' => $count, 'error' => $error];
}

function normalize_fallback(?string $value): ?int
{
    if ($value === null) {
        return null;
    }
    $raw = trim(strtolower($value));
    if ($raw === '') {
        return null;
    }
    // accetta formati tipo "2500", "2.5k", "2,5k", "1m"
    $normalized = str_replace(',', '.', $raw);
    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([km])?$/', $normalized, $m)) {
        $num = (float)$m[1];
        $suffix = $m[2] ?? '';
        if ($suffix === 'k') {
            $num *= 1000;
        } elseif ($suffix === 'm') {
            $num *= 1000000;
        }
        return (int)round($num);
    }
    if (is_numeric($raw)) {
        return (int)$raw;
    }
    return null;
}

function apply_fallback(array $result, ?string $fallback): array
{
    if ($result['count'] !== null) {
        return $result;
    }
    $parsed = normalize_fallback($fallback);
    if ($parsed !== null) {
        return ['count' => $parsed, 'error' => $result['error'] ?? null];
    }
    return $result;
}

function fetch_json(string $url, array $options = []): array
{
    $timeout = isset($options['timeout']) ? (int)$options['timeout'] : 4;
    $headers = $options['headers'] ?? [];
    $method = strtoupper($options['method'] ?? 'GET');
    $body = $options['body'] ?? null;

    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => max(2, min($timeout, 3)),
    ];

    if ($method === 'POST') {
        $curlOpts[CURLOPT_POST] = true;
        if ($body !== null) {
            if (is_array($body)) {
                $headers[] = 'Content-Type: application/json';
                $curlOpts[CURLOPT_POSTFIELDS] = json_encode($body);
            } else {
                $curlOpts[CURLOPT_POSTFIELDS] = $body;
            }
        }
    }

    if (!empty($headers)) {
        $curlOpts[CURLOPT_HTTPHEADER] = $headers;
    }

    curl_setopt_array($ch, $curlOpts);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => $status, 'error' => $curlError ?: 'Richiesta HTTP fallita'];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'error' => 'Risposta non valida'];
    }

    $apiError = $decoded['error']['message'] ?? $decoded['error_msg'] ?? $decoded['message'] ?? null;
    return [
        'ok' => $status >= 200 && $status < 300 && $apiError === null,
        'status' => $status,
        'data' => $decoded,
        'error' => $apiError,
    ];
}

function fetch_instagram(array $cfg): array
{
    $token = $cfg['META_PAGE_TOKEN'] ?? '';
    $igUserId = $cfg['META_IG_USER_ID'] ?? '';
    if ($token === '' || $igUserId === '') {
        return social_result(null, 'Config Instagram mancante');
    }

    $url = 'https://graph.facebook.com/v20.0/' . rawurlencode($igUserId) . '?' . http_build_query([
        'fields' => 'followers_count',
        'access_token' => $token,
    ]);

    $res = fetch_json($url);
    if (!$res['ok']) {
        return social_result(null, $res['error'] ?? 'Errore Instagram');
    }

    $count = $res['data']['followers_count'] ?? null;
    return social_result($count !== null ? (int)$count : null, $count === null ? 'followers_count non disponibile' : null);
}

function fetch_facebook(array $cfg): array
{
    $token = $cfg['META_PAGE_TOKEN'] ?? '';
    $pageId = $cfg['META_PAGE_ID'] ?? '';
    if ($token === '' || $pageId === '') {
        return social_result(null, 'Config Facebook mancante');
    }

    $url = 'https://graph.facebook.com/v20.0/' . rawurlencode($pageId) . '?' . http_build_query([
        'fields' => 'fan_count',
        'access_token' => $token,
    ]);

    $res = fetch_json($url);
    if (!$res['ok']) {
        return social_result(null, $res['error'] ?? 'Errore Facebook');
    }

    $count = $res['data']['fan_count'] ?? null;
    return social_result($count !== null ? (int)$count : null, $count === null ? 'fan_count non disponibile' : null);
}

function fetch_youtube(array $cfg): array
{
    $apiKey = $cfg['YOUTUBE_API_KEY'] ?? '';
    $channelId = $cfg['YOUTUBE_CHANNEL_ID'] ?? '';
    if ($apiKey === '' || $channelId === '') {
        return social_result(null, 'Config YouTube mancante');
    }

    $normalized = normalize_youtube_channel($channelId);
    $attempts = youtube_param_attempts($normalized);
    $lastError = null;

    foreach ($attempts as $params) {
        $query = array_merge(['part' => 'statistics', 'key' => $apiKey], $params);
        $url = 'https://www.googleapis.com/youtube/v3/channels?' . http_build_query($query);
        $res = fetch_json($url);
        if (!$res['ok']) {
            $lastError = $res['error'] ?? 'Errore YouTube';
            continue;
        }

        $items = $res['data']['items'] ?? [];
        $count = $items[0]['statistics']['subscriberCount'] ?? null;
        if ($count !== null) {
            return social_result((int)$count, null);
        }
        $lastError = $lastError ?? 'subscriberCount non disponibile';
    }

    return social_result(null, $lastError ?? 'Impossibile recuperare subscriberCount');
}

function normalize_youtube_channel(string $raw): array
{
    $value = trim($raw);
    if ($value === '') {
        return ['mode' => 'id', 'value' => ''];
    }

    // @handle
    if ($value[0] === '@') {
        return ['mode' => 'handle', 'value' => $value];
    }

    // URL con handle
    if (preg_match('#youtube\\.com/(?:@)([^/?&]+)#i', $value, $m)) {
        return ['mode' => 'handle', 'value' => '@' . $m[1]];
    }

    // URL con channel id
    if (preg_match('#youtube\\.com/channel/([^/?&]+)#i', $value, $m)) {
        return ['mode' => 'id', 'value' => $m[1]];
    }

    // URL con username/vanity
    if (preg_match('#youtube\\.com/(?:user|c)/([^/?&]+)#i', $value, $m)) {
        return ['mode' => 'username', 'value' => $m[1]];
    }

    // default: assume ID
    return ['mode' => 'id', 'value' => $value];
}

function youtube_param_attempts(array $normalized): array
{
    $attempts = [];
    $mode = $normalized['mode'];
    $value = $normalized['value'];

    $looksId = preg_match('/^UC[A-Za-z0-9_-]{20,}$/', $value) === 1;

    if ($mode === 'handle') {
        $attempts[] = ['forHandle' => $value];
        if ($looksId) {
            $attempts[] = ['id' => $value];
        }
        $attempts[] = ['forUsername' => ltrim($value, '@')];
    } elseif ($mode === 'username') {
        $attempts[] = ['forUsername' => $value];
        if ($looksId) {
            $attempts[] = ['id' => $value];
        }
    } else {
        $attempts[] = ['id' => $value];
        if ($value && !$looksId) {
            $attempts[] = ['forUsername' => $value];
        }
    }

    return $attempts;
}

function tiktok_token_cache_file(): string
{
    return __DIR__ . '/../cache/tiktok_token.json';
}

function tiktok_write_cache(array $data): void
{
    $cacheFile = tiktok_token_cache_file();
    ensure_parent_dir($cacheFile);
    @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_SLASHES));
}

function tiktok_resolve_token(array $cfg): array
{
    $accessToken = trim($cfg['TIKTOK_ACCESS_TOKEN'] ?? '');
    $refreshToken = trim($cfg['TIKTOK_REFRESH_TOKEN'] ?? '');
    $clientKey = trim($cfg['TIKTOK_CLIENT_KEY'] ?? '');
    $clientSecret = trim($cfg['TIKTOK_CLIENT_SECRET'] ?? '');

    $cacheFile = tiktok_token_cache_file();
    $now = time();
    $cached = null;
    if (file_exists($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
    }

    if (is_array($cached)) {
        $expiresAt = (int)($cached['expires_at'] ?? 0);
        if ($cached['access_token'] ?? '') {
            // se non scaduto, usa il cached
            if ($expiresAt > ($now + 60)) { // 1 minuto di margine
                return ['token' => (string)$cached['access_token'], 'error' => null];
            }
        }
    }

    // se abbiamo già un accessToken da env e nessun refresh disponibile, usiamo quello
    if ($accessToken !== '' && ($refreshToken === '' || $clientKey === '' || $clientSecret === '')) {
        return ['token' => $accessToken, 'error' => null];
    }

    // se possiamo fare refresh e/o il token è vuoto o scaduto
    if ($refreshToken !== '' && $clientKey !== '' && $clientSecret !== '') {
        $postData = http_build_query([
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $endpoints = [
            'https://open.tiktokapis.com/v2/oauth/token',
            'https://open.tiktokapis.com/v2/oauth/token/',
        ];

        foreach ($endpoints as $endpoint) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
            ]);
            $raw = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                $lastError = $curlError ?: 'Richiesta refresh fallita';
                continue;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                if ($status === 404) {
                    continue;
                }
                $lastError = 'Risposta refresh non valida';
                break;
            }

            $code = (int)($decoded['error']['code'] ?? 0);
            if ($status >= 200 && $status < 300 && ($code === 0 || !isset($decoded['error']))) {
                $newToken = $decoded['access_token'] ?? null;
                $expiresIn = (int)($decoded['expires_in'] ?? 0);
                $newRefresh = $decoded['refresh_token'] ?? $refreshToken;
                if ($newToken) {
                    $payload = [
                        'access_token' => $newToken,
                        'refresh_token' => $newRefresh,
                        'expires_at' => $now + max(0, $expiresIn - 60), // margine 60s
                        'updated_at' => $now,
                        'endpoint' => $endpoint,
                    ];
                    tiktok_write_cache($payload);
                    return ['token' => $newToken, 'error' => null];
                }
            }

            $apiError = $decoded['error']['message'] ?? $decoded['error'] ?? $decoded['message'] ?? null;
            $lastError = $apiError ?: 'Refresh token fallito';
        }

        return ['token' => $accessToken !== '' ? $accessToken : null, 'error' => $lastError ?? 'Refresh token fallito'];
    }

    return ['token' => $accessToken !== '' ? $accessToken : null, 'error' => $accessToken === '' ? 'Config TikTok mancante' : null];
}

function fetch_tiktok(array $cfg): array
{
    $tokenInfo = tiktok_resolve_token($cfg);
    $accessToken = $tokenInfo['token'] ?? '';
    $userId = $cfg['TIKTOK_USER_ID'] ?? '';
    if ($accessToken === '') {
        return social_result(null, $tokenInfo['error'] ?? 'Config TikTok mancante');
    }

    $query = ['fields' => 'follower_count'];
    if ($userId !== '') {
        $query['user_id'] = $userId;
    }

    $url = 'https://open.tiktokapis.com/v2/user/info/?' . http_build_query($query);
    $res = fetch_json($url, [
        'headers' => ['Authorization: Bearer ' . $accessToken],
    ]);

    // L'API TikTok restituisce error.code=0/message="success" anche quando ok,
    // quindi se code=0 consideriamo la risposta valida.
    if (!$res['ok']) {
        $apiErr = $res['data']['error'] ?? null;
        if (is_array($apiErr)) {
            $code = (int)($apiErr['code'] ?? 0);
            if ($code !== 0) {
                return social_result(null, $apiErr['message'] ?? $res['error'] ?? 'Errore TikTok');
            }
            // code 0 => successo, proseguiamo
        } else {
            return social_result(null, $res['error'] ?? 'Errore TikTok');
        }
    }

    $data = $res['data'];
    $candidates = [
        $data['data']['user']['follower_count'] ?? null,
        $data['user']['follower_count'] ?? null,
        $data['data']['follower_count'] ?? null,
        $data['follower_count'] ?? null,
    ];
    $count = null;
    foreach ($candidates as $candidate) {
        if ($candidate !== null) {
            $count = (int)$candidate;
            break;
        }
    }

    return social_result($count !== null ? $count : null, $count === null ? 'follower_count non disponibile' : null);
}

function finalize_cached_counts(array $counts, array $config): array
{
    return [
        'instagram' => apply_fallback(
            $counts['instagram'] ?? social_result(null, 'Cache mancante'),
            $config['FALLBACK_INSTAGRAM']
        ),
        'facebook' => apply_fallback(
            $counts['facebook'] ?? social_result(null, 'Cache mancante'),
            $config['FALLBACK_FACEBOOK']
        ),
        'tiktok' => apply_fallback(
            $counts['tiktok'] ?? social_result(null, 'Cache mancante'),
            $config['FALLBACK_TIKTOK']
        ),
        'youtube' => apply_fallback(
            $counts['youtube'] ?? social_result(null, 'Cache mancante'),
            $config['FALLBACK_YOUTUBE']
        ),
    ];
}

function serve_cached_payload(array $cached, array $config, bool $stale = false): void
{
    json_response([
        'cached' => true,
        'stale' => $stale,
        'updated_at' => $cached['updated_at'] ?? time(),
        'counts' => finalize_cached_counts($cached['counts'] ?? [], $config),
    ]);
}

function fallback_only_payload(array $config): array
{
    return [
        'instagram' => apply_fallback(social_result(null, 'Cache in aggiornamento'), $config['FALLBACK_INSTAGRAM']),
        'facebook' => apply_fallback(social_result(null, 'Cache in aggiornamento'), $config['FALLBACK_FACEBOOK']),
        'youtube' => apply_fallback(social_result(null, 'Cache in aggiornamento'), $config['FALLBACK_YOUTUBE']),
        'tiktok' => apply_fallback(social_result(null, 'Cache in aggiornamento'), $config['FALLBACK_TIKTOK']),
    ];
}

function build_payload(array $config): array
{
    return [
        'cached' => false,
        'updated_at' => time(),
        'counts' => [
            'instagram' => apply_fallback(
                fetch_instagram($config),
                $config['FALLBACK_INSTAGRAM']
            ),
            'facebook' => apply_fallback(
                fetch_facebook($config),
                $config['FALLBACK_FACEBOOK']
            ),
            'youtube' => apply_fallback(
                fetch_youtube($config),
                $config['FALLBACK_YOUTUBE']
            ),
            'tiktok' => apply_fallback(
                fetch_tiktok($config),
                $config['FALLBACK_TIKTOK']
            ),
        ],
    ];
}

function merge_with_cached_counts(array $freshPayload, ?array $cachedPayload): array
{
    if (!is_array($cachedPayload) || !isset($cachedPayload['counts']) || !is_array($cachedPayload['counts'])) {
        return $freshPayload;
    }

    foreach ($freshPayload['counts'] as $key => $result) {
        $cachedResult = $cachedPayload['counts'][$key] ?? null;
        if (($result['count'] ?? null) === null && is_array($cachedResult) && ($cachedResult['count'] ?? null) !== null) {
            $freshPayload['counts'][$key]['count'] = (int)$cachedResult['count'];
        }
    }

    return $freshPayload;
}

$config = [
    'META_PAGE_TOKEN' => getenv('META_PAGE_TOKEN') ?: '',
    'META_PAGE_ID' => getenv('META_PAGE_ID') ?: '',
    'META_IG_USER_ID' => getenv('META_IG_USER_ID') ?: '',
    'YOUTUBE_API_KEY' => getenv('YOUTUBE_API_KEY') ?: '',
    'YOUTUBE_CHANNEL_ID' => getenv('YOUTUBE_CHANNEL_ID') ?: '',
    'TIKTOK_ACCESS_TOKEN' => getenv('TIKTOK_ACCESS_TOKEN') ?: '',
    'TIKTOK_REFRESH_TOKEN' => getenv('TIKTOK_REFRESH_TOKEN') ?: '',
    'TIKTOK_CLIENT_KEY' => getenv('TIKTOK_CLIENT_KEY') ?: '',
    'TIKTOK_CLIENT_SECRET' => getenv('TIKTOK_CLIENT_SECRET') ?: '',
    'TIKTOK_USER_ID' => getenv('TIKTOK_USER_ID') ?: '',
    'FALLBACK_FACEBOOK' => getenv('FALLBACK_FACEBOOK') ?: '',
    'FALLBACK_INSTAGRAM' => getenv('FALLBACK_INSTAGRAM') ?: '',
    'FALLBACK_TIKTOK' => getenv('FALLBACK_TIKTOK') ?: '',
    'FALLBACK_YOUTUBE' => getenv('FALLBACK_YOUTUBE') ?: '',
];

$cacheFile = __DIR__ . '/../cache/social_followers.json';
$lockFile = __DIR__ . '/../cache/social_followers.lock';
$cacheTtl = 6 * 60 * 60; // 6 ore
$staleCacheTtl = 7 * 24 * 60 * 60; // 7 giorni
$force = isset($_GET['force']) && $_GET['force'] === '1';
if ($force && !social_request_is_same_origin()) {
    $force = false;
}
$cachedPayload = read_json_file($cacheFile);
$cacheAge = file_exists($cacheFile) ? max(0, time() - (int)filemtime($cacheFile)) : null;

if (!$force && is_array($cachedPayload) && $cacheAge !== null && $cacheAge < $cacheTtl) {
    serve_cached_payload($cachedPayload, $config);
}

ensure_parent_dir($lockFile);
$lockHandle = @fopen($lockFile, 'c+');
$hasLock = $lockHandle && @flock($lockHandle, LOCK_EX | LOCK_NB);

if (!$hasLock) {
    if (!$force && is_array($cachedPayload) && $cacheAge !== null && $cacheAge < $staleCacheTtl) {
        serve_cached_payload($cachedPayload, $config, true);
    }

    json_response([
        'cached' => false,
        'refreshing' => true,
        'updated_at' => time(),
        'counts' => fallback_only_payload($config),
    ]);
}

try {
    clearstatcache(true, $cacheFile);
    $cachedPayload = read_json_file($cacheFile);
    $cacheAge = file_exists($cacheFile) ? max(0, time() - (int)filemtime($cacheFile)) : null;

    if (!$force && is_array($cachedPayload) && $cacheAge !== null && $cacheAge < $cacheTtl) {
        serve_cached_payload($cachedPayload, $config);
    }

    $payload = merge_with_cached_counts(build_payload($config), $cachedPayload);
    ensure_parent_dir($cacheFile);
    @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_SLASHES));
    json_response($payload);
} finally {
    if ($hasLock && $lockHandle) {
        @flock($lockHandle, LOCK_UN);
    }
    if ($lockHandle) {
        @fclose($lockHandle);
    }
}
