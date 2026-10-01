<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/env_loader.php';

$appId = trim((string)getenv('INSTAGRAM_APP_ID'));
$redirectUri = trim((string)getenv('INSTAGRAM_REDIRECT_URI'));
if ($appId === '' || $redirectUri === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Config Instagram Login mancante: definisci INSTAGRAM_APP_ID e INSTAGRAM_REDIRECT_URI sul server e registra lo stesso redirect nella configurazione Instagram Login di Meta.\n");
}

$redirectParts = parse_url($redirectUri);
if (!is_array($redirectParts) || strtolower((string)($redirectParts['scheme'] ?? '')) !== 'https' || empty($redirectParts['host'])) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('INSTAGRAM_REDIRECT_URI deve essere un indirizzo HTTPS pubblico.');
}

$state = bin2hex(random_bytes(24));
$_SESSION['instagram_publish_oauth_state'] = $state;
$authUrl = 'https://www.instagram.com/oauth/authorize?' . http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'instagram_business_basic,instagram_business_content_publish',
    'state' => $state,
]);
header('Location: ' . $authUrl, true, 302);
exit;
