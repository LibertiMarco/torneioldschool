<?php
declare(strict_types=1);
// Isolated subscription layout: no local configuration, credentials, or existing runtime data.
$subscription = __DIR__.'/runtime-fixture-'.bin2hex(random_bytes(8));
$include = $subscription.'/httpdocs/includi';mkdir($include,0700,true);
copy(__DIR__.'/../includi/env_loader.php',$include.'/env_loader.php');
$code = <<<'PHP'
require $argv[1].'/httpdocs/includi/env_loader.php';
putenv('TOS_RUNTIME_DIR');
if (str_replace('\\','/',tos_runtime_root()) !== str_replace('\\','/',$argv[1]).'/torneioldschool-runtime') exit(1);
tos_ensure_parent_dir(tos_runtime_path('logs/test.log'));
if (!is_dir(tos_runtime_path('logs'))) exit(2);
putenv('TOS_RUNTIME_DIR='.$argv[1].'/private/custom');
if (!str_ends_with(str_replace('\\','/',tos_runtime_root()),'/private/custom')) exit(3);
set_error_handler(function($n,$m) {throw new RuntimeException($m);});
putenv('TOS_RUNTIME_DIR='.dirname($argv[1]).'/forbidden');
try {tos_runtime_root();exit(4);} catch (RuntimeException $e) {
    if (strpos($e->getMessage(),'fuori dai percorsi') === false) exit(5);
}
putenv('TOS_RUNTIME_DIR='.$argv[1].'/../../forbidden');
try {tos_runtime_root();exit(6);} catch (RuntimeException $e) {}
exit(0);
PHP;
try {
    $child = proc_open([PHP_BINARY,'-d','open_basedir='.$subscription,'-r',$code,$subscription],[], $pipes);
    if (!is_resource($child) || proc_close($child) !== 0) throw new RuntimeException('Plesk boundary or runtime override failed');
} finally {
    unlink($include.'/env_loader.php');rmdir($include);rmdir(dirname($include));
    if (is_dir($subscription.'/torneioldschool-runtime/logs')) rmdir($subscription.'/torneioldschool-runtime/logs');
    if (is_dir($subscription.'/torneioldschool-runtime')) rmdir($subscription.'/torneioldschool-runtime');
    rmdir($subscription);
}
echo "PASS: isolated Plesk subscription, open_basedir, default runtime, overrides and traversal without warnings\n";
