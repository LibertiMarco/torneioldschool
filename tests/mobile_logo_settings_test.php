<?php
require_once __DIR__ . '/../includi/mobile_logo_settings.php';
$previous = getenv('TOS_RUNTIME_DIR');
$testRoot = sys_get_temp_dir() . '/tos-logo-settings-' . bin2hex(random_bytes(8));
putenv('TOS_RUNTIME_DIR=' . $testRoot);
function logo_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
try {
    logo_check(mobile_logo_settings() === ['original_logos' => false], 'Default must use emblems');
    logo_check(mobile_logo_settings(['original_logos' => true])['original_logos'], 'Enable failed');
    logo_check(mobile_logo_settings()['original_logos'], 'Value must persist across reads');
    foreach ([1, 'true', null, []] as $invalid) {
        try {
            mobile_logo_settings(['original_logos' => $invalid]);
            throw new RuntimeException('Invalid value accepted');
        } catch (MobileAuthError $error) {
            logo_check($error->status === 400, 'Invalid status');
        }
    }
    logo_check(mobile_logo_settings()['original_logos'], 'Invalid writes must preserve state');
    logo_check(!mobile_logo_settings(['original_logos' => false])['original_logos'], 'Disable failed');
    echo "Mobile logo settings: all checks passed (isolated temporary storage).\n";
} finally {
    if (is_file($testRoot . '/mobile/original-logos')) unlink($testRoot . '/mobile/original-logos');
    if (is_dir($testRoot . '/mobile')) rmdir($testRoot . '/mobile');
    if (is_dir($testRoot)) rmdir($testRoot);
    putenv($previous === false ? 'TOS_RUNTIME_DIR' : 'TOS_RUNTIME_DIR=' . $previous);
}
