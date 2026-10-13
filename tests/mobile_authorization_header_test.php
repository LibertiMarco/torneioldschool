<?php
// Header extraction fixtures only: no credentials or database connections.
if (!function_exists('getallheaders')) {
    function getallheaders(): array { return $GLOBALS['fixtureHeaders'] ?? []; }
}
require_once __DIR__ . '/../includi/mobile_authorization_header.php';
function header_expect(string $expected): void {
    if (mobile_authorization_header() !== $expected) throw new RuntimeException('Header extraction failed');
}
unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
header_expect('');
$token = 'Bearer ' . str_repeat('A', 43);
$_SERVER['HTTP_AUTHORIZATION'] = $token;
header_expect($token);
$_SERVER['HTTP_AUTHORIZATION'] = '';
$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $token;
header_expect($token);
unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
$GLOBALS['fixtureHeaders'] = ['aUtHoRiZaTiOn' => $token];
header_expect($token);
$GLOBALS['fixtureHeaders'] = ['X-Authorization' => $token];
header_expect('');
echo "Mobile authorization header tests passed.\n";
