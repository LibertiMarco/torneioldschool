<?php
require_once __DIR__ . '/includi/admin_guard.php';

// Keep the package outside public download paths; authorize every request.
$apk = __DIR__ . '/cache/mobile-builds/tornei-old-school.apk';
if (!is_file($apk) || !is_readable($apk)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'La build Android non è ancora disponibile. Riprova dopo il caricamento.';
    exit;
}

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="tornei-old-school.apk"');
header('Content-Length: ' . filesize($apk));
header('X-Content-Type-Options: nosniff');
session_write_close();
readfile($apk);
