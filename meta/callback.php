<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/env_loader.php';

header('Content-Type: application/json; charset=utf-8');

function meta_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function fetch_json(string $url, array $options = []): array
{
    $method = strtoupper($options['method'] ?? 'GET');
    $timeout = isset($options['timeout']) ? (int)$options['timeout'] : 15;
    $headers = $options['headers'] ?? [];
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
        return ['ok' => false, 'status' => $status, 'error' => 'Risposta non valida', 'raw' => $raw];
    }

    $apiError = $decoded['error']['message'] ?? $decoded['error_msg'] ?? $decoded['message'] ?? null;
    return [
        'ok' => $status >= 200 && $status < 300 && $apiError === null,
        'status' => $status,
        'data' => $decoded,
        'error' => $apiError,
    ];
}

function exchange_short_lived(string $appId, string $appSecret, string $redirectUri, string $code): array
{
    $url = 'https://graph.facebook.com/v20.0/oauth/access_token?' . http_build_query([
        'client_id' => $appId,
        'redirect_uri' => $redirectUri,
        'client_secret' => $appSecret,
        'code' => $code,
    ]);

    return fetch_json($url);
}

function exchange_long_lived(string $appId, string $appSecret, string $shortToken): array
{
    $url = 'https://graph.facebook.com/v20.0/oauth/access_token?' . http_build_query([
        'grant_type' => 'fb_exchange_token',
        'client_id' => $appId,
        'client_secret' => $appSecret,
        'fb_exchange_token' => $shortToken,
    ]);

    return fetch_json($url);
}

function fetch_pages(string $userToken): array
{
    $url = 'https://graph.facebook.com/v20.0/me/accounts?' . http_build_query([
        'access_token' => $userToken,
        'fields' => 'id,name,category,access_token',
        'limit' => 50,
    ]);

    return fetch_json($url);
}

function fetch_page_details(string $pageId, string $pageToken): array
{
    $url = 'https://graph.facebook.com/v20.0/' . rawurlencode($pageId) . '?' . http_build_query([
        'fields' => 'id,name,fan_count,followers_count,instagram_business_account',
        'access_token' => $pageToken,
    ]);

    return fetch_json($url);
}

function pick_page(array $pages, ?string $preferredId): ?array
{
    if ($preferredId) {
        foreach ($pages as $page) {
            if (($page['id'] ?? '') === $preferredId) {
                return $page;
            }
        }
    }

    return $pages[0] ?? null;
}

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$state = isset($_GET['state']) ? (string)$_GET['state'] : null;
$pageHint = isset($_GET['page_id']) ? trim((string)$_GET['page_id']) : '';

if ($code === '') {
    meta_json(['ok' => false, 'error' => 'Parametro code mancante'], 400);
}

$appId = trim((string)getenv('META_APP_ID'));
$appSecret = trim((string)getenv('META_APP_SECRET'));
$redirectUri = trim((string)getenv('META_REDIRECT_URI'));

if ($appId === '' || $appSecret === '' || $redirectUri === '') {
    meta_json([
        'ok' => false,
        'error' => 'Config mancante: serve META_APP_ID, META_APP_SECRET e META_REDIRECT_URI in includi/env.local.php',
    ], 400);
}

$short = exchange_short_lived($appId, $appSecret, $redirectUri, $code);
if (!$short['ok']) {
    meta_json([
        'ok' => false,
        'error' => $short['error'] ?? 'Impossibile ottenere il token breve',
        'response' => $short,
    ], 502);
}

$shortToken = $short['data']['access_token'] ?? '';
if ($shortToken === '') {
    meta_json([
        'ok' => false,
        'error' => 'Risposta token breve senza access_token',
        'response' => $short,
    ], 502);
}

$long = exchange_long_lived($appId, $appSecret, $shortToken);
if (!$long['ok']) {
    meta_json([
        'ok' => false,
        'error' => $long['error'] ?? 'Impossibile ottenere il token long-lived',
        'response' => $long,
    ], 502);
}

$longToken = $long['data']['access_token'] ?? '';
if ($longToken === '') {
    meta_json([
        'ok' => false,
        'error' => 'Risposta token long-lived senza access_token',
        'response' => $long,
    ], 502);
}

$pagesRes = fetch_pages($longToken);
if (!$pagesRes['ok']) {
    meta_json([
        'ok' => false,
        'error' => $pagesRes['error'] ?? 'Impossibile recuperare le pagine',
        'response' => $pagesRes,
    ], 502);
}

$pagesList = $pagesRes['data']['data'] ?? [];
if (!is_array($pagesList) || count($pagesList) === 0) {
    meta_json([
        'ok' => false,
        'error' => 'Nessuna pagina disponibile con i permessi forniti',
        'response' => $pagesRes,
    ], 404);
}

$selectedPage = pick_page($pagesList, $pageHint !== '' ? $pageHint : null);
if ($selectedPage === null) {
    meta_json([
        'ok' => false,
        'error' => 'Pagina non trovata',
        'response' => $pagesRes,
    ], 404);
}

$pageToken = $selectedPage['access_token'] ?? '';
if ($pageToken === '') {
    meta_json([
        'ok' => false,
        'error' => 'La pagina selezionata non contiene access_token',
        'page' => $selectedPage,
    ], 502);
}

$pageDetails = fetch_page_details((string)$selectedPage['id'], $pageToken);

$igId = null;
$fanCount = null;
$followersCount = null;
if ($pageDetails['ok'] && isset($pageDetails['data'])) {
    $igId = $pageDetails['data']['instagram_business_account']['id'] ?? null;
    $fanCount = $pageDetails['data']['fan_count'] ?? null;
    $followersCount = $pageDetails['data']['followers_count'] ?? null;
}

$availablePages = [];
foreach ($pagesList as $p) {
    $availablePages[] = [
        'id' => $p['id'] ?? null,
        'name' => $p['name'] ?? null,
        'category' => $p['category'] ?? null,
    ];
}

meta_json([
    'ok' => true,
    'state' => $state,
    'app_id' => $appId,
    'page_hint' => $pageHint !== '' ? $pageHint : null,
    'user_token' => [
        'short_lived' => [
            'access_token' => $shortToken,
            'expires_in' => $short['data']['expires_in'] ?? null,
            'token_type' => $short['data']['token_type'] ?? null,
        ],
        'long_lived' => [
            'access_token' => $longToken,
            'expires_in' => $long['data']['expires_in'] ?? null,
            'token_type' => $long['data']['token_type'] ?? null,
        ],
    ],
    'page' => [
        'id' => $selectedPage['id'] ?? null,
        'name' => $selectedPage['name'] ?? null,
        'category' => $selectedPage['category'] ?? null,
        'access_token' => $pageToken,
        'fan_count' => $fanCount,
        'followers_count' => $followersCount,
        'instagram_business_account_id' => $igId,
    ],
    'available_pages' => $availablePages,
    'suggested_env' => [
        'META_PAGE_TOKEN' => $pageToken,
        'META_PAGE_ID' => $selectedPage['id'] ?? null,
        'META_IG_USER_ID' => $igId,
    ],
    'notes' => [
        'Non committare i token: copiarli in includi/env.local.php',
        'Permessi minimi consigliati: pages_show_list, pages_read_engagement, pages_read_user_content, instagram_basic',
    ],
    'page_details_raw' => $pageDetails,
]);
