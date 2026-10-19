<?php
// Public release policy: update only after the release is available to users.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$policy = require __DIR__ . '/../../../includi/mobile_release.php';
echo json_encode($policy, JSON_UNESCAPED_SLASHES);
