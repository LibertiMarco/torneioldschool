<?php
require_once __DIR__ . '/../includi/android_package.php';

$apk = __DIR__ . '/../cache/mobile-builds/tornei-old-school.apk';
if (!android_package_complete($apk)) {
    throw new RuntimeException('The compiled APK must have a complete ZIP directory.');
}
$partial = tempnam(sys_get_temp_dir(), 'tos-apk-');
try {
    $source = fopen($apk, 'rb');
    $target = fopen($partial, 'wb');
    stream_copy_to_stream($source, $target, 28 * 1024 * 1024);
    fclose($source);
    fclose($target);
    if (android_package_complete($partial)) {
        throw new RuntimeException('An APK truncated at 28 MB must be rejected.');
    }
    file_put_contents($partial, '<html>Login required</html>');
    if (android_package_complete($partial)) {
        throw new RuntimeException('An HTML response must not be accepted as an APK.');
    }
} finally {
    unlink($partial);
}
echo "Android package checks passed.\n";
