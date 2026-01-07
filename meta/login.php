<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/env_loader.php';

$appId = trim((string)getenv('META_APP_ID'));
$redirectUri = trim((string)getenv('META_REDIRECT_URI'));
$scope = isset($_GET['scope']) && $_GET['scope'] !== ''
    ? $_GET['scope']
    : 'pages_show_list,pages_read_engagement,pages_read_user_content,instagram_basic';
$state = isset($_GET['state']) && $_GET['state'] !== '' ? $_GET['state'] : bin2hex(random_bytes(8));

if ($redirectUri === '') {
    // fallback di sicurezza per evitare errori di config
    $redirectUri = 'https://torneiodschool.it/meta/callback';
}

if ($appId === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Config mancante: definisci META_APP_ID e META_REDIRECT_URI in includi/env.local.php.\nRedirect: {$redirectUri}");
}

$authUrl = 'https://www.facebook.com/v20.0/dialog/oauth?' . http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'scope' => $scope,
    'response_type' => 'code',
    'state' => $state,
]);
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Meta</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, sans-serif; background:#0f172a; color:#e2e8f0; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:20px; }
        .card { background:#111827; border:1px solid #1f2937; border-radius:14px; padding:28px; max-width:520px; width:100%; box-shadow:0 20px 50px rgba(0,0,0,0.45); }
        h1 { margin:0 0 14px; font-size:24px; color:#f8fafc; }
        p { margin:0 0 12px; color:#cbd5e1; line-height:1.5; }
        code { background:#0b1220; border:1px solid #1f2937; padding:2px 6px; border-radius:6px; color:#e2e8f0; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:10px; padding:12px 18px; border-radius:10px; background:#1877f2; color:#fff; text-decoration:none; font-weight:700; border:none; cursor:pointer; transition:transform .12s ease, box-shadow .12s ease; box-shadow:0 10px 25px rgba(24,119,242,0.35); }
        .btn:hover { transform:translateY(-1px); box-shadow:0 16px 32px rgba(24,119,242,0.45); }
        .btn:active { transform:translateY(0); }
        .small { font-size:13px; color:#94a3b8; }
        .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; background:#0b1220; border:1px solid #1f2937; border-radius:999px; margin-right:6px; font-size:12px; color:#cbd5e1; }
        .stack { display:flex; flex-direction:column; gap:10px; }
    </style>
</head>
<body>
<div id="fb-root"></div>
<div class="card">
    <h1>Accedi con Meta</h1>
    <p>Verrai reindirizzato a Facebook per autorizzare le API pagina/IG. Assicurati che in Meta Developers sia presente il redirect <code><?php echo htmlspecialchars($redirectUri, ENT_QUOTES, 'UTF-8'); ?></code>.</p>
    <div class="stack">
        <a class="btn" href="<?php echo htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8'); ?>">Continua con Facebook</a>
        <p class="small">Scope usati: <?php echo htmlspecialchars($scope, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="small">Se non parte, copia e incolla questo URL:<br><code><?php echo htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8'); ?></code></p>
        <div class="small">
            <span class="pill">APP ID: <?php echo htmlspecialchars($appId, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="pill">State: <?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>
</div>

<script>
  window.fbAsyncInit = function() {
    FB.init({
      appId      : <?php echo json_encode($appId); ?>,
      cookie     : true,
      xfbml      : false,
      version    : 'v20.0'
    });
  };

  (function(d, s, id){
     var js, fjs = d.getElementsByTagName(s)[0];
     if (d.getElementById(id)) {return;}
     js = d.createElement(s); js.id = id;
     js.src = "https://connect.facebook.net/en_US/sdk.js";
     fjs.parentNode.insertBefore(js, fjs);
   }(document, 'script', 'facebook-jssdk'));
</script>
</body>
</html>
