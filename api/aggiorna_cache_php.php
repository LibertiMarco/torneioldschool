<?php
require_once __DIR__ . '/../includi/admin_guard.php';

$opcacheAvailable = function_exists('opcache_reset');
$opcacheReset = $opcacheAvailable ? @opcache_reset() : false;

if (function_exists('clearstatcache')) {
    clearstatcache(true);
}
if (function_exists('realpath_cache_get')) {
    clearstatcache(true);
}

$status = $opcacheAvailable
    ? ($opcacheReset ? 'cache_aggiornata' : 'opcache_non_resettata')
    : 'opcache_non_disponibile';

header('Location: ' . login_with_base_path('/api/gestione_utenti.php') . '?php_cache=' . rawurlencode($status));
exit;
