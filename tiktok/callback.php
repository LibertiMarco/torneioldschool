<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/env_loader.php';

header('Content-Type: application/json; charset=utf-8');

function tiktoauth_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$state = isset($_GET['state']) ? (string)$_GET['state'] : null;

if ($code === '') {
    tiktoauth_json(['ok' => false, 'error' => 'Parametro code mancante'], 400);
}

$clientKey = trim((string)getenv('TIKTOK_CLIENT_KEY'));
$clientSecret = trim((string)getenv('TIKTOK_CLIENT_SECRET'));
$redirectUri = trim((string)getenv('TIKTOK_REDIRECT_URI'));

if ($clientKey === '' || $clientSecret === '' || $redirectUri === '') {
    tiktoauth_json([
        'ok' => false,
        'error' => 'Config mancante: serve TIKTOK_CLIENT_KEY, TIKTOK_CLIENT_SECRET e TIKTOK_REDIRECT_URI',
    ], 400);
}

$postData = http_build_query([
    'client_key' => $clientKey,
    'client_secret' => $clientSecret,
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirectUri,
]);

$tokenEndpoints = [
    'https://open.tiktokapis.com/v2/oauth/token',
    'https://open.tiktokapis.com/v2/oauth/token/', // fallback se il primo risponde 404 Unsupported path
];

$last = null;
foreach ($tokenEndpoints as $endpoint) {
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
        $last = ['ok' => false, 'error' => $curlError ?: 'Richiesta fallita', 'status' => $status];
        continue;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $last = ['ok' => false, 'error' => 'Risposta non valida', 'status' => $status, 'raw' => $raw];
        // se 404 unsupported path, prova il prossimo endpoint
        if ($status === 404) {
            continue;
        }
        break;
    }

    tiktoauth_json([
        'ok' => $status >= 200 && $status < 300 && !isset($decoded['error']),
        'status' => $status,
        'state' => $state,
        'data' => $decoded,
        'endpoint' => $endpoint,
        'note' => 'Copia access_token in includi/env.local.php come TIKTOK_ACCESS_TOKEN e non committarlo.',
    ]);
}

// Se siamo qui, nessun endpoint ha dato una risposta JSON valida
if ($last === null) {
    $last = ['ok' => false, 'error' => 'Nessuna risposta dal token endpoint', 'status' => 0];
}
tiktoauth_json($last, 502);
