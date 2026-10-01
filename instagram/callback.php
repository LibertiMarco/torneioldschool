<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/env_loader.php';
require_once __DIR__ . '/../includi/instagram_token_store.php';
require_once __DIR__ . '/oauth_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function instagram_oauth_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function instagram_oauth_request(string $url, array $fields = [], bool $post = false): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        return ['ok' => false, 'error' => 'Impossibile inizializzare la connessione a Instagram.'];
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($post) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
    }
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'error' => $curlError ?: 'Risposta Instagram non valida.'];
    }
    $apiError = $decoded['error_message'] ?? $decoded['error']['message'] ?? $decoded['message'] ?? null;
    return [
        'ok' => $status >= 200 && $status < 300 && $apiError === null,
        'status' => $status,
        'data' => $decoded,
        'error' => $apiError,
    ];
}

$state = (string)($_GET['state'] ?? '');
$stateIsValid = false;
$oauthContext = null;
$pendingStates = $_SESSION['instagram_publish_oauth_states'] ?? [];
if (is_array($pendingStates) && $state !== '') {
    foreach ($pendingStates as $expectedState => $pendingContext) {
        if (!is_string($expectedState)) {
            continue;
        }
        $createdAt = is_array($pendingContext) ? ($pendingContext['created_at'] ?? null) : $pendingContext;
        if (is_numeric($createdAt) && (int)$createdAt >= time() - 600 && hash_equals($expectedState, $state)) {
            $stateIsValid = true;
            $oauthContext = is_array($pendingContext) ? $pendingContext : null;
            unset($pendingStates[$expectedState]);
            break;
        }
    }
}
$_SESSION['instagram_publish_oauth_states'] = $pendingStates;

// Support a flow started just before this change was deployed.
$legacyState = (string)($_SESSION['instagram_publish_oauth_state'] ?? '');
if (!$stateIsValid && $state !== '' && $legacyState !== '' && hash_equals($legacyState, $state)) {
    $stateIsValid = true;
}
unset($_SESSION['instagram_publish_oauth_state']);
if ($state === '' || !$stateIsValid) {
    instagram_oauth_json(['ok' => false, 'error' => 'Verifica OAuth non valida. Riapri il collegamento dal generatore.'], 400);
}
if (!empty($_GET['error'])) {
    instagram_oauth_json(['ok' => false, 'error' => (string)($_GET['error_description'] ?? 'Autorizzazione Instagram annullata.')], 400);
}

$code = trim((string)($_GET['code'] ?? ''));
$appId = trim((string)getenv('INSTAGRAM_APP_ID'));
$appSecret = trim((string)getenv('INSTAGRAM_APP_SECRET'));
$redirectUri = trim((string)getenv('INSTAGRAM_REDIRECT_URI'));
// Use exactly the app ID and redirect URI sent in the authorization request.
if (is_array($oauthContext)) {
    $appId = trim((string)($oauthContext['app_id'] ?? $appId));
    $redirectUri = trim((string)($oauthContext['redirect_uri'] ?? $redirectUri));
}
if ($code === '' || $appId === '' || $appSecret === '' || $redirectUri === '') {
    instagram_oauth_json(['ok' => false, 'error' => 'Mancano il codice OAuth o le credenziali Instagram Login del server.'], 400);
}

$secretError = instagram_oauth_secret_error($appSecret);
if ($secretError !== null) {
    instagram_oauth_json(['ok' => false, 'error' => $secretError, 'stage' => 'configuration'], 500);
}

$short = instagram_oauth_request('https://api.instagram.com/oauth/access_token', [
    'client_id' => $appId,
    'client_secret' => $appSecret,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirectUri,
    'code' => $code,
], true);
if (!$short['ok']) {
    // Only public configuration and status metadata: never return the code,
    // state, app secret, tokens, request body, or the raw response here.
    $apiError = (string)($short['error'] ?? 'Instagram non ha rilasciato il token iniziale.');
    $apiError = str_replace([$appSecret, $code], '[omesso]', $apiError);
    instagram_oauth_json([
        'ok' => false,
        'error' => $apiError,
        'stage' => 'authorization_code_exchange',
        'diagnostics' => [
            'version' => 'instagram-oauth-3',
            'app_id' => $appId,
            'redirect_uri' => $redirectUri,
            'authorization_context_saved' => is_array($oauthContext),
            'configuration_matches_authorization' => (
                $appId === trim((string)getenv('INSTAGRAM_APP_ID'))
                && $redirectUri === trim((string)getenv('INSTAGRAM_REDIRECT_URI'))
            ),
            'authorization_age_seconds' => isset($oauthContext['created_at'])
                ? max(0, time() - (int)$oauthContext['created_at']) : null,
            'http_status' => $short['status'] ?? null,
        ],
    ], 502);
}

$shortToken = trim((string)($short['data']['access_token'] ?? ''));
$userId = trim((string)($short['data']['user_id'] ?? $short['data']['id'] ?? ''));
if ($shortToken === '') {
    instagram_oauth_json(['ok' => false, 'error' => 'Risposta Instagram senza access token.'], 502);
}

$longUrl = 'https://graph.instagram.com/access_token?' . http_build_query([
    'grant_type' => 'ig_exchange_token',
    'client_secret' => $appSecret,
    'access_token' => $shortToken,
]);
$long = instagram_oauth_request($longUrl);
if (!$long['ok']) {
    instagram_oauth_json(['ok' => false, 'error' => $long['error'] ?? 'Impossibile scambiare il token Instagram.'], 502);
}

$longToken = trim((string)($long['data']['access_token'] ?? ''));
if ($userId === '') {
    $graphVersion = trim((string)(getenv('INSTAGRAM_GRAPH_API_VERSION') ?: 'v26.0'));
    if (!preg_match('/^v\d+\.\d+$/', $graphVersion)) {
        $graphVersion = 'v26.0';
    }
    $profileUrl = 'https://graph.instagram.com/' . $graphVersion . '/me?' . http_build_query([
        'fields' => 'user_id,username',
        'access_token' => $longToken,
    ]);
    $profile = instagram_oauth_request($profileUrl);
    if ($profile['ok']) {
        $userId = trim((string)($profile['data']['user_id'] ?? $profile['data']['id'] ?? ''));
    }
}

if ($longToken === '' || $userId === '') {
    instagram_oauth_json(['ok' => false, 'error' => 'Instagram non ha restituito il token lungo o l’ID del profilo.'], 502);
}

$tokenExpiresIn = max(0, (int)($long['data']['expires_in'] ?? 5184000));
$tokenStored = tos_save_instagram_token_state([
    'access_token' => $longToken,
    'user_id' => $userId,
    'issued_at' => time(),
    'expires_at' => time() + $tokenExpiresIn,
    'refresh_after' => time() + tos_instagram_token_refresh_interval_seconds(),
]);

instagram_oauth_json([
    'ok' => true,
    'account' => [
        'username' => $short['data']['username'] ?? null,
        'user_id' => $userId,
    ],
    'access_token' => [
        'value' => $longToken,
        'type' => $long['data']['token_type'] ?? 'bearer',
        'expires_in' => $long['data']['expires_in'] ?? null,
    ],
    'suggested_env' => [
        'INSTAGRAM_ACCESS_TOKEN' => $longToken,
        'INSTAGRAM_USER_ID' => $userId,
    ],
    'token_storage' => [
        'saved_on_server' => $tokenStored,
        'automatic_refresh' => $tokenStored,
    ],
    'notes' => [
        $tokenStored
            ? 'Token salvato in un file server privato; verrà rinnovato automaticamente prima della scadenza.'
            : 'Salvataggio automatico non disponibile; configura il token sul server e rendi scrivibile la cartella runtime privata.',
        'Il token è segreto: configurarlo solo sul server, mai nel codice pubblico o in chat.',
        'Il token Instagram Login è a lunga durata ma ha una scadenza; ricollegare o aggiornare il token prima della scadenza.',
    ],
]);
