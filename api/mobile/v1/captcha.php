<?php
require_once __DIR__ . '/../../../includi/env_loader.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$key = getenv('RECAPTCHA_SITE_KEY') ?: '';
?><!doctype html><html lang="it"><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verifica accesso</title></head>
<body style="font:16px system-ui;margin:16px;background:#f4f4f4;color:#15293e">
<p>Completa la verifica di sicurezza per continuare.</p>
<?php if ($key === ''): ?><p>Verifica non configurata sul server.</p><?php else: ?>
<div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-callback="completed" data-expired-callback="expired"></div>
<script>function completed(token){if(window.TosCaptcha)TosCaptcha.postMessage(token)}function expired(){if(window.TosCaptcha)TosCaptcha.postMessage('')}</script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?></body></html>
