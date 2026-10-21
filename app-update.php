<?php
// Fixed destination only: compatible with older apps accepting HTTPS policy URLs.
// A user tap on Aggiorna ora reaches this URL and opens the installed TestFlight app.
if (($_GET['open'] ?? '') === '1') {
    header('Cache-Control: no-store');
    header('Location: itms-beta://', true, 302);
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Aggiorna Tornei Old School</title>
<style>
body{margin:0;padding:24px;background:#101c2a;color:#f0f5fb;font:17px/1.6 system-ui,sans-serif}main{max-width:560px;margin:8vh auto;padding:28px;border:1px solid #34475c;border-radius:22px;background:#17283b}h1{line-height:1.2;font-size:30px}a{color:#8de2ee}.button{display:block;margin:24px 0;padding:14px 18px;text-align:center;border-radius:12px;background:#8de2ee;color:#102434;text-decoration:none;font-weight:700}li{margin:12px 0}small{display:block;color:#c3cfdd}@media(max-width:400px){body{padding:12px}main{padding:20px}}
</style>
</head>
<body><main>
<h1>Aggiorna Tornei Old School</h1>
<p>La versione iPhone &egrave; distribuita tramite TestFlight.</p>
<ol>
<li>Apri TestFlight sul tuo iPhone.</li>
<li>Cerca <strong>Tornei Old School</strong> e premi <strong>Aggiorna</strong>.</li>
<li>Torna nell&rsquo;app e premi <strong>Verifica di nuovo</strong>.</li>
</ol>
<a class="button" href="itms-beta://">Apri TestFlight</a>
<small>Se il pulsante non apre TestFlight, aprilo direttamente dalla schermata Home del telefono. Usa l&rsquo;account Apple con cui hai accettato l&rsquo;invito al test.</small>
<p><a href="https://apps.apple.com/app/testflight/id899247664">Installa TestFlight se non &egrave; presente</a></p>
</main></body></html>
