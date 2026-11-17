<?php
require_once __DIR__ . '/../../../includi/env_loader.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$key = getenv('RECAPTCHA_SITE_KEY') ?: '';
?><!doctype html><html lang="it"><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verifica accesso</title></head>
<body style="font:16px system-ui;margin:0;padding:8px 0;background:#fff;color:#15293e">
<?php if ($key === ''): ?><p>Verifica non configurata sul server.</p><?php else: ?>
<div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-size="compact" data-callback="completed" data-expired-callback="expired" data-error-callback="failed"></div>
<script>
function completed(token){if(window.TosCaptcha)TosCaptcha.postMessage(token)}
function expired(){if(window.TosCaptcha)TosCaptcha.postMessage('')}
function failed(){expired();if(window.TosCaptchaStatus)TosCaptchaStatus.postMessage('error')}
function reportHeight(){
  var height=166;
  document.querySelectorAll('iframe').forEach(function(frame){
    var rect=frame.getBoundingClientRect(),visible=true;
    for(var parent=frame;parent;parent=parent.parentElement){
      var style=getComputedStyle(parent);
      if(style.display==='none'||style.visibility==='hidden'||style.opacity==='0'){visible=false;break}
    }
    if(visible&&rect.height>0)height=Math.max(height,rect.bottom+window.scrollY+16);
  });
  if(window.TosCaptchaLayout)TosCaptchaLayout.postMessage(String(Math.ceil(height)));
}
new MutationObserver(reportHeight).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['style','height','class']});
window.addEventListener('resize',reportHeight);
window.addEventListener('load',reportHeight);
</script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?></body></html>
