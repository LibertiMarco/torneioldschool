<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/env_loader.php';

function tiktoauth_abort(string $message, int $status = 500): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

$clientKey = trim((string)getenv('TIKTOK_CLIENT_KEY'));
$redirectUri = trim((string)getenv('TIKTOK_REDIRECT_URI'));
$scope = isset($_GET['scope']) && $_GET['scope'] !== '' ? $_GET['scope'] : 'user.info.basic';
$state = isset($_GET['state']) && $_GET['state'] !== '' ? $_GET['state'] : bin2hex(random_bytes(8));

if ($clientKey === '') {
    tiktoauth_abort('Config mancante: TIKTOK_CLIENT_KEY', 400);
}

if ($redirectUri === '') {
    tiktoauth_abort('Config mancante: TIKTOK_REDIRECT_URI', 400);
}

$authUrl = 'https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
    'client_key' => $clientKey,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $scope,
    'state' => $state,
]);

header('Location: ' . $authUrl);
exit;
