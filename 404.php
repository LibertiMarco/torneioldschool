<?php
http_response_code(404);
require_once __DIR__ . '/includi/seo.php';

$baseUrl = seo_base_url();
$seo = [
  'title' => 'Pagina non trovata - Tornei Old School',
  'description' => 'La pagina che cerchi non esiste piÃ¹. Torna alla home o scopri i tornei attivi.',
  'image' => $baseUrl . '/img/logo_old_school_1200.png',
  'url' => $baseUrl . ($_SERVER['REQUEST_URI'] ?? '/404'),
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-VZ982XSRRN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-VZ982XSRRN');
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($seo); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    .error-hero { min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 60px 20px; background: radial-gradient(circle at 20% 20%, rgba(21, 41, 62, 0.18), transparent 32%), radial-gradient(circle at 80% 0%, rgba(17, 139, 255, 0.16), transparent 30%), #0f1f2c; color: #fff; text-align: center; }
    .error-card { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.2); border-radius: 14px; padding: 28px 22px; max-width: 520px; width: 100%; box-shadow: 0 12px 32px rgba(0,0,0,0.25); backdrop-filter: blur(4px); }
    .error-card h1 { font-size: 32px; margin-bottom: 10px; letter-spacing: 0.4px; }
    .error-card p { margin: 10px 0 24px; color: #d4e2ff; line-height: 1.5; }
    .error-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .error-actions a { text-decoration: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; }
    .error-actions .primary { background: #118bff; color: #fff; }
    .error-actions .ghost { border: 1px solid rgba(255,255,255,0.4); color: #fff; }
  </style>
</head>
<body>
  <section class="error-hero">
    <div class="error-card">
      <h1>Oops, pagina non trovata</h1>
      <p>Il link potrebbe essere cambiato o la pagina Ã¨ stata rimossa. Prova dalla home oppure vai ai tornei.</p>
      <div class="error-actions">
        <a class="primary" href="/">Torna alla home</a>
        <a class="ghost" href="/tornei.php">Vedi i tornei</a>
      </div>
    </div>
  </section>
</body>
</html>
