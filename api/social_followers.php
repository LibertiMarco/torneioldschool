<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/env_loader.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
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
    $timeout = isset($options['timeout']) ? (int)$options['timeout'] : 10;
    $headers = $options['headers'] ?? [];
    $method = strtoupper($options['method'] ?? 'GET');
    $body = $options['body'] ?? null;

    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
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
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
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
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
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
];

$cacheFile = __DIR__ . '/../cache/social_followers.json';
$cacheTtl = 15 * 60; // 15 minuti
$force = isset($_GET['force']) && $_GET['force'] === '1';

if (!$force && file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < $cacheTtl) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            if (isset($cached['counts']) && is_array($cached['counts'])) {
                $cached['counts']['instagram'] = apply_fallback(
                    $cached['counts']['instagram'] ?? social_result(null, 'Cache mancante'),
                    $config['FALLBACK_INSTAGRAM']
                );
                $cached['counts']['facebook'] = apply_fallback(
                    $cached['counts']['facebook'] ?? social_result(null, 'Cache mancante'),
                    $config['FALLBACK_FACEBOOK']
                );
                $cached['counts']['tiktok'] = apply_fallback(
                    $cached['counts']['tiktok'] ?? social_result(null, 'Cache mancante'),
                    $config['FALLBACK_TIKTOK']
                );
            }
            json_response([
                'cached' => true,
                'updated_at' => $cached['updated_at'] ?? filemtime($cacheFile),
                'counts' => $cached['counts'] ?? [],
            ]);
        }
    }
}

$payload = [
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
        'youtube' => fetch_youtube($config),
        'tiktok' => apply_fallback(
            fetch_tiktok($config),
            $config['FALLBACK_TIKTOK']
        ),
    ],
];

$cacheDir = dirname($cacheFile);
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
@file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_SLASHES));

json_response($payload);
