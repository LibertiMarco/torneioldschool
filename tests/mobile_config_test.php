<?php
$fixtureRoot = __DIR__ . '/../.mobile-tools/config-test-' . bin2hex(random_bytes(5));
putenv('TOS_RUNTIME_DIR=' . $fixtureRoot);
require_once __DIR__ . '/../includi/mobile_config.php';
function expect_mobile_flag(bool $value, string $message): void {
    if (!$value) { throw new RuntimeException($message); }
}
try {
    putenv('MOBILE_API_ENABLED');
    expect_mobile_flag(!mobile_api_enabled(), 'New installation is disabled');
    mobile_api_set_enabled(true);
    expect_mobile_flag(mobile_api_enabled(), 'Admin activation persisted');
    putenv('MOBILE_API_ENABLED=0');
    expect_mobile_flag(!mobile_api_enabled(), 'Environment kill switch takes precedence');
    $rejected = false;
    try { mobile_api_set_enabled(true); } catch (RuntimeException $error) { $rejected = true; }
    expect_mobile_flag($rejected, 'Panel cannot bypass environment policy');
    putenv('MOBILE_API_ENABLED=invalid');
    expect_mobile_flag(!mobile_api_enabled(), 'Invalid explicit setting fails closed');
    putenv('MOBILE_API_ENABLED');
    mobile_api_set_enabled(false);
    expect_mobile_flag(!mobile_api_enabled(), 'Admin deactivation persisted');
    echo "Mobile configuration: all checks passed.\n";
} finally {
    $flag = $fixtureRoot . '/mobile/api-enabled';
    if (is_file($flag)) { unlink($flag); }
    if (is_dir($fixtureRoot . '/mobile')) { rmdir($fixtureRoot . '/mobile'); }
    if (is_dir($fixtureRoot)) { rmdir($fixtureRoot); }
}
