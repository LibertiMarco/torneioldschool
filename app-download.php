<?php
require_once __DIR__ . '/includi/admin_guard.php';
require_once __DIR__ . '/includi/android_package.php';

// Keep the package outside public download paths; authorize every request.
$apk = __DIR__ . '/cache/mobile-builds/tornei-old-school.apk';
if (!is_file($apk) || !is_readable($apk) || !android_package_complete($apk)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'La build Android non è ancora disponibile. Riprova dopo il caricamento.';
    exit;
}

// PHP/FastCGI output buffers must not retain the whole APK in memory.
session_write_close();
@set_time_limit(0);
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    if (!ob_end_clean()) { break; }
}
header('X-Accel-Buffering: no');
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="tornei-old-school.apk"');
header('Content-Length: ' . filesize($apk));
header('X-Content-Type-Options: nosniff');
readfile($apk);
exit;
