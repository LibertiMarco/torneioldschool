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
  <?php
  require_once __DIR__ . '/includi/seo.php';
  $baseUrl = seo_base_url();
  $cookieSeo = [
    'title' => 'Cookie Policy - Tornei Old School',
    'description' => 'Dettagli su uso dei cookie e strumenti di tracciamento sul sito Tornei Old School.',
    'url' => $baseUrl . '/cookie.php',
    'canonical' => $baseUrl . '/cookie.php',
  ];
  $cookieBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Cookie Policy', 'url' => $baseUrl . '/cookie.php'],
  ]);
  $lastUpdate = date('d/m/Y', @filemtime(__FILE__) ?: time());
  ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($cookieSeo); ?>
  <?php render_jsonld($cookieBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    .policy-page { padding: 40px 20px; max-width: 960px; margin: 0 auto; }
    .policy-hero h1 { margin-bottom: 8px; }
    .policy-block { margin-top: 28px; background: #ffffff; border: 1px solid #e5e8ed; border-radius: 12px; padding: 20px 24px; box-shadow: 0 10px 30px rgba(21,41,62,0.06); }
    .policy-block h2 { margin-top: 0; color: #15293e; }
    .policy-block ul { padding-left: 18px; }
    .policy-meta { color: #4b5563; }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="content policy-page">
    <section class="policy-hero">
      <p class="policy-meta">Ultimo aggiornamento: <?php echo $lastUpdate; ?></p>
      <h1>Cookie Policy</h1>
      <p>Spieghiamo quali cookie e strumenti simili usiamo e come gestire le preferenze.</p>
    </section>

    <section class="policy-block">
      <h2>Cookie tecnici</h2>
      <ul>
        <li>Cookie di sessione (es. PHPSESSID) per login e funzionalità di base.</li>
        <li>Cookie/localStorage per ricordare la tua scelta nel banner di consenso.</li>
        <li>Altri cookie tecnici legati a preferenze del sito. Non usiamo cookie di profilazione.</li>
        <li>reCAPTCHA di Google per proteggere i form (login, registrazione, contatti): Google può impostare cookie e tracciare l'IP per rilevare abusi. Vedi la <a href="https://policies.google.com/privacy" target="_blank" rel="noreferrer">Privacy Policy di Google</a> e i <a href="https://policies.google.com/terms" target="_blank" rel="noreferrer">Termini di servizio</a>.</li>
      </ul>
    </section>

    <section class="policy-block">
      <h2>Analisi e tracciamento delle operazioni</h2>
      <p>Registriamo eventi di utilizzo del sito (es. pagine viste, click su tab o filtri) solo se acconsenti tramite il banner. Non salviamo il contenuto dei form; vengono raccolti solo metadati (pagina, tipo di azione, data/ora, user agent, IP troncato) per migliorare il sito.</p>
    </section>

    <section class="policy-block">
      <h2>Come gestire o revocare il consenso</h2>
      <ul>
        <li>Puoi accettare o rifiutare dal banner al primo accesso.</li>
        <li>Puoi modificare la scelta in qualsiasi momento dal link "Gestisci preferenze" presente nel footer.</li>
        <li>Puoi anche cancellare i cookie dalle impostazioni del browser; questo farà riapparire il banner.</li>
      </ul>
    </section>

    <section class="policy-block">
      <h2>Contatti</h2>
      <p>Per domande o richieste relative a cookie e tracciamento, scrivi a <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a>.</p>
    </section>
  </main>

  <div id="footer-container"></div>

  <script src="/includi/header-interactions.js?v=20251208"></script>
  <script src="/includi/app.min.js?v=20251204"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      fetch('/includi/header.php')
        .then(r => r.text())
        .then(html => {
          const headerSlot = document.getElementById('header-container');
          if (headerSlot) {
            headerSlot.innerHTML = html;
            if (typeof initHeaderInteractions === 'function') {
              initHeaderInteractions();
            }
          }
        });

      fetch('/includi/footer.html')
        .then(r => r.text())
        .then(html => {
          const footerSlot = document.getElementById('footer-container');
          if (footerSlot) {
            footerSlot.innerHTML = html;
          }
        });
    });
  </script>
</body>
</html>
