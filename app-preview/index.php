<?php
require_once __DIR__ . '/../includi/admin_guard.php';
$html = __DIR__ . '/build/index.html';
if (!is_file($html)) {
    http_response_code(503);
    echo 'Anteprima in preparazione.';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
readfile($html);
