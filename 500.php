<?php
http_response_code(500);
require_once __DIR__ . '/includi/seo.php';

$baseUrl = seo_base_url();
$seo = [
  'title' => 'Errore interno - Tornei Old School',
  'description' => 'Si e\' verificato un errore momentaneo. Riprova tra poco o torna alla home.',
  'image' => $baseUrl . '/img/logo_old_school_1200.png',
  'url' => $baseUrl . ($_SERVER['REQUEST_URI'] ?? '/500'),
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
    .error-hero { min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 60px 20px; background: linear-gradient(135deg, #0f1f2c 0%, #15293e 50%, #0f1f2c 100%); color: #fff; text-align: center; }
    .error-card { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); border-radius: 14px; padding: 28px 22px; max-width: 520px; width: 100%; box-shadow: 0 12px 32px rgba(0,0,0,0.25); }
    .error-card h1 { font-size: 32px; margin-bottom: 10px; }
    .error-card p { margin: 10px 0 22px; color: #d9e6ff; line-height: 1.5; }
    .error-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .error-actions a { text-decoration: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; }
    .error-actions .primary { background: #118bff; color: #fff; }
    .error-actions .ghost { border: 1px solid rgba(255,255,255,0.4); color: #fff; }
  </style>
</head>
<body>
  <section class="error-hero">
    <div class="error-card">
      <h1>Errore temporaneo</h1>
      <p>Qualcosa Ã¨ andato storto, ma stiamo giÃ  controllando. Riprova tra qualche istante o torna alla home.</p>
      <div class="error-actions">
        <a class="primary" href="/" aria-label="Torna alla home">Home</a>
        <a class="ghost" href="/contatti.php" aria-label="Vai alla pagina contatti">Contattaci</a>
      </div>
    </div>
  </section>
</body>
</html>
