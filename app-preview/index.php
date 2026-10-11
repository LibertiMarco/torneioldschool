<?php
require_once __DIR__ . '/../includi/admin_guard.php';
$html = __DIR__ . '/build/index.html';
if (!is_file($html)) {
    http_response_code(503);
    echo 'Anteprima in preparazione.';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
// Change both loader and app URLs whenever either compiled file changes.
$mainHash = hash_file('sha256', __DIR__ . '/build/main.dart.js');
$loaderHash = hash_file('sha256', __DIR__ . '/build/flutter_bootstrap.js');
$version = substr(hash('sha256', $mainHash . $loaderHash), 0, 20);
echo str_replace('src="flutter_bootstrap.js"', 'src="flutter_bootstrap.js?v=' . $version . '"', file_get_contents($html));
