<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/security.php';
require_once __DIR__ . '/../includi/user_features.php';
require_once __DIR__ . '/../includi/env_loader.php';
require_once __DIR__ . '/../includi/instagram_token_store.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function instagram_publish_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function instagram_graph_request(string $url, array $fields = [], bool $post = false): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        return ['ok' => false, 'error' => 'Impossibile inizializzare la connessione a Meta.'];
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
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
        return ['ok' => false, 'status' => $status, 'error' => $curlError ?: 'Meta ha restituito una risposta non valida.'];
    }

    $apiError = $decoded['error']['message'] ?? null;
    return [
        'ok' => $status >= 200 && $status < 300 && $apiError === null,
        'status' => $status,
        'data' => $decoded,
        'error' => $apiError,
    ];
}

function instagram_refresh_access_token_state(array $state): array
{
    $now = time();
    $accessToken = trim((string)($state['access_token'] ?? ''));
    if ($accessToken === '') {
        return ['ok' => false, 'error' => 'Token Instagram non disponibile sul server.'];
    }

    $refreshAfter = (int)($state['refresh_after'] ?? 0);
    $expiresAt = (int)($state['expires_at'] ?? 0);
    $expiresSoon = $expiresAt > 0 && $expiresAt <= $now + (15 * 86400);
    if ($refreshAfter > $now && !$expiresSoon) {
        return ['ok' => true, 'state' => $state];
    }

    $refreshUrl = 'https://graph.instagram.com/refresh_access_token?' . http_build_query([
        'grant_type' => 'ig_refresh_token',
        'access_token' => $accessToken,
    ]);
    $refresh = instagram_graph_request($refreshUrl);
    $newToken = $refresh['ok'] ? trim((string)($refresh['data']['access_token'] ?? '')) : '';
    if ($newToken !== '') {
        $expiresIn = max(0, (int)($refresh['data']['expires_in'] ?? 5184000));
        $state['access_token'] = $newToken;
        $state['issued_at'] = $now;
        $state['expires_at'] = $now + $expiresIn;
        $state['refresh_after'] = $now + (45 * 86400);
        if (!tos_save_instagram_token_state($state)) {
            return ['ok' => false, 'error' => 'Instagram ha rinnovato il token, ma il server non riesce a salvarlo. Verifica i permessi della cartella runtime privata.'];
        }
        return ['ok' => true, 'state' => $state];
    }

    // Meta only permits a refresh once a long-lived token is at least 24 hours old.
    // For a manually configured token with unknown age, retry tomorrow and let this
    // publication continue with the current token.
    $state['refresh_after'] = $now + 86400;
    tos_save_instagram_token_state($state);
    if ($expiresAt > 0 && $expiresAt <= $now + 86400) {
        return ['ok' => false, 'error' => 'Il token Instagram sta per scadere e il rinnovo automatico non è riuscito. Ricollega Instagram dal generatore.'];
    }
    return ['ok' => true, 'state' => $state];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    instagram_publish_json(['ok' => false, 'error' => 'Metodo non consentito.'], 405);
}
if (!isset($_SESSION['user_id'])) {
    instagram_publish_json(['ok' => false, 'error' => 'Accedi con un account amministratore per pubblicare.'], 401);
}
if (!user_has_admin_access(trim((string)($_SESSION['ruolo'] ?? '')))) {
    instagram_publish_json(['ok' => false, 'error' => 'La pubblicazione Instagram è riservata agli amministratori.'], 403);
}
if (!csrf_is_valid((string)($_POST['_csrf'] ?? ''), 'instagram_publish')) {
    instagram_publish_json(['ok' => false, 'error' => 'Sessione scaduta. Ricarica il generatore e riprova.'], 400);
}

$storedTokenState = tos_load_instagram_token_state();
$accessToken = trim((string)($storedTokenState['access_token'] ?? getenv('INSTAGRAM_ACCESS_TOKEN')));
$instagramUserId = trim((string)($storedTokenState['user_id'] ?? getenv('INSTAGRAM_USER_ID')));
if ($accessToken === '' || $instagramUserId === '') {
    instagram_publish_json([
        'ok' => false,
        'error' => 'Collegamento Instagram non configurato: servono INSTAGRAM_ACCESS_TOKEN e INSTAGRAM_USER_ID con il permesso di pubblicazione.',
    ], 503);
}

if ($storedTokenState === null) {
    $storedTokenState = [
        'access_token' => $accessToken,
        'user_id' => $instagramUserId,
        'issued_at' => 0,
        'expires_at' => 0,
        'refresh_after' => 0,
    ];
}
$tokenRefresh = instagram_refresh_access_token_state($storedTokenState);
if (!$tokenRefresh['ok']) {
    instagram_publish_json(['ok' => false, 'error' => $tokenRefresh['error']], 503);
}
$accessToken = (string)$tokenRefresh['state']['access_token'];
$instagramUserId = trim((string)($tokenRefresh['state']['user_id'] ?? $instagramUserId));
if (!isset($_FILES['image']) || !is_array($_FILES['image']) || (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    instagram_publish_json(['ok' => false, 'error' => 'Impossibile ricevere la grafica. Riprova.'], 400);
}

$upload = $_FILES['image'];
$tmpPath = (string)($upload['tmp_name'] ?? '');
$uploadSize = (int)($upload['size'] ?? 0);
if ($tmpPath === '' || !is_uploaded_file($tmpPath) || $uploadSize <= 0 || $uploadSize > 15 * 1024 * 1024) {
    instagram_publish_json(['ok' => false, 'error' => 'La grafica deve essere un PNG valido e non superare 15 MB.'], 400);
}

$imageInfo = @getimagesize($tmpPath);
if (!is_array($imageInfo) || ($imageInfo['mime'] ?? '') !== 'image/png' || (int)$imageInfo[0] !== 1080 || (int)$imageInfo[1] !== 1350) {
    instagram_publish_json(['ok' => false, 'error' => 'Formato grafica non valido: è richiesto un PNG da 1080 × 1350 px.'], 400);
}

$caption = trim((string)($_POST['caption'] ?? ''));
if (function_exists('mb_strlen')) {
    $captionLength = mb_strlen($caption, 'UTF-8');
} else {
    $captionLength = strlen($caption);
}
if ($captionLength > 2200) {
    instagram_publish_json(['ok' => false, 'error' => 'La didascalia supera il limite di 2200 caratteri.'], 400);
}

$origin = tos_origin_url();
$originParts = parse_url($origin);
$host = strtolower((string)($originParts['host'] ?? ''));
if (($originParts['scheme'] ?? '') !== 'https' || $host === '' || filter_var($host, FILTER_VALIDATE_IP) || in_array($host, ['localhost'], true) || str_ends_with($host, '.local')) {
    instagram_publish_json(['ok' => false, 'error' => 'Il sito deve avere un indirizzo HTTPS pubblico perché Instagram possa scaricare l’immagine.'], 503);
}

$graphVersion = trim((string)(getenv('INSTAGRAM_GRAPH_API_VERSION') ?: 'v26.0'));
if (!preg_match('/^v\d+\.\d+$/', $graphVersion)) {
    instagram_publish_json(['ok' => false, 'error' => 'INSTAGRAM_GRAPH_API_VERSION non è configurata correttamente.'], 503);
}

$publicDir = dirname(__DIR__) . '/img/instagram-publish';
if (!is_dir($publicDir) && !@mkdir($publicDir, 0755, true) && !is_dir($publicDir)) {
    instagram_publish_json(['ok' => false, 'error' => 'Cartella temporanea di pubblicazione non disponibile sul server.'], 503);
}
foreach (glob($publicDir . '/*.png') ?: [] as $staleImage) {
    if (is_file($staleImage) && (filemtime($staleImage) ?: time()) < time() - 86400) {
        @unlink($staleImage);
    }
}
$fileName = bin2hex(random_bytes(20)) . '.png';
$filePath = $publicDir . '/' . $fileName;
if (!@move_uploaded_file($tmpPath, $filePath)) {
    instagram_publish_json(['ok' => false, 'error' => 'Il server non è riuscito a preparare la grafica.'], 500);
}

$containerId = null;
try {
    $basePath = tos_detect_base_path();
    $imageUrl = rtrim($origin, '/') . $basePath . '/img/instagram-publish/' . rawurlencode($fileName);
    $createUrl = 'https://graph.instagram.com/' . $graphVersion . '/' . rawurlencode($instagramUserId) . '/media';
    $container = instagram_graph_request($createUrl, [
        'image_url' => $imageUrl,
        'caption' => $caption,
        'access_token' => $accessToken,
    ], true);
    if (!$container['ok']) {
        throw new RuntimeException((string)($container['error'] ?? 'Instagram non ha accettato la grafica.'));
    }

    $containerId = trim((string)($container['data']['id'] ?? ''));
    if ($containerId === '') {
        throw new RuntimeException('Instagram non ha restituito il codice della pubblicazione.');
    }

    $statusUrl = 'https://graph.instagram.com/' . $graphVersion . '/' . rawurlencode($containerId) . '?' . http_build_query([
        'fields' => 'status_code',
        'access_token' => $accessToken,
    ]);
    $finished = false;
    for ($attempt = 0; $attempt < 12; $attempt++) {
        $status = instagram_graph_request($statusUrl);
        if (!$status['ok']) {
            throw new RuntimeException((string)($status['error'] ?? 'Impossibile verificare l’immagine su Instagram.'));
        }
        $statusCode = strtoupper((string)($status['data']['status_code'] ?? ''));
        if ($statusCode === 'FINISHED') {
            $finished = true;
            break;
        }
        if ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
            throw new RuntimeException('Instagram non è riuscito a elaborare la grafica.');
        }
        usleep(750000);
    }
    if (!$finished) {
        throw new RuntimeException('Instagram sta impiegando troppo tempo a elaborare la grafica. Riprova tra poco.');
    }

    $publishUrl = 'https://graph.instagram.com/' . $graphVersion . '/' . rawurlencode($instagramUserId) . '/media_publish';
    $published = instagram_graph_request($publishUrl, [
        'creation_id' => $containerId,
        'access_token' => $accessToken,
    ], true);
    if (!$published['ok']) {
        throw new RuntimeException((string)($published['error'] ?? 'Instagram non ha pubblicato la grafica.'));
    }

    @unlink($filePath);
    instagram_publish_json(['ok' => true, 'id' => (string)($published['data']['id'] ?? '')]);
} catch (Throwable $error) {
    if (is_file($filePath)) {
        @unlink($filePath);
    }
    error_log('instagram publish failed: ' . $error->getMessage());
    instagram_publish_json(['ok' => false, 'error' => $error->getMessage()], 502);
}
