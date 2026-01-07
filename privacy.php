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
  $privacySeo = [
    'title' => 'Privacy Policy - Tornei Old School',
    'description' => 'Informativa sulla privacy e sul trattamento dei dati personali per i tornei Old School.',
    'url' => $baseUrl . '/privacy.php',
    'canonical' => $baseUrl . '/privacy.php',
  ];
  $privacyBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Privacy Policy', 'url' => $baseUrl . '/privacy.php'],
  ]);
  $lastUpdate = date('d/m/Y', @filemtime(__FILE__) ?: time());
  ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($privacySeo); ?>
  <?php render_jsonld($privacyBreadcrumbs); ?>
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
      <h1>Privacy Policy</h1>
      <p>Questa informativa spiega come trattiamo i dati quando ti iscrivi ai tornei o navighi sul sito.</p>
      <p>Newsletter e comunicazioni promozionali sono facoltative: puoi attivarle o revocarle in qualsiasi momento dalla pagina account o dal link "Gestisci preferenze" nel footer.</p>
    </section>

    <section class="policy-block">
      <h2>Chi siamo e contatti</h2>
      <p>Titolare del trattamento: Tornei Old School (gestione amatoriale). Contatto: <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a>.</p>
    </section>

    <section class="policy-block">
      <h2>Dati che raccogliamo</h2>
      <ul>
        <li>Email per l'iscrizione ai tornei e per inviarti comunicazioni sugli eventi.</li>
        <li>Foto profilo (facoltativa) se scegli di caricarla.</li>
        <li>Dati tecnici di navigazione: log del server, IP troncato, user agent, data e ora, usati per sicurezza.</li>
        <li>Eventi di utilizzo del sito se acconsenti: pagine viste, click su tab/filtri, operazioni effettuate per migliorare l'esperienza.</li>
      </ul>
    </section>

    <section class="policy-block">
      <h2>Finalità e basi giuridiche</h2>
      <ul>
        <li>Iscrizione e gestione del torneo, comunicazioni correlate: necessità contrattuale.</li>
        <li>Uso della foto profilo: consenso facoltativo.</li>
        <li>Sicurezza, prevenzione abusi e manutenzione: legittimo interesse.</li>
        <li>Analisi dell'utilizzo e tracciamento delle operazioni sul sito: consenso tramite banner.</li>
        <li>Newsletter e comunicazioni promozionali sui tornei: consenso facoltativo e revocabile.</li>
      </ul>
    </section>

    <section class="policy-block">
      <h2>Conservazione</h2>
      <ul>
        <li>Email e dati di iscrizione: per la durata del torneo e fino a 6 mesi dopo la chiusura.</li>
        <li>Foto profilo: fino a revoca del consenso o cancellazione dell'account.</li>
        <li>Log tecnici: 30-180 giorni salvo obblighi di legge.</li>
        <li>Eventi di utilizzo (se hai acconsentito): dati aggregati per massimo 12 mesi.</li>
      </ul>
    </section>

    <section class="policy-block">
      <h2>Condivisione e trasferimenti</h2>
      <ul>
        <li>Fornitori tecnici e hosting strettamente necessari al funzionamento del sito.</li>
        <li>Nessuna vendita o cessione a terzi per finalità di marketing.</li>
        <li>Eventuali trasferimenti extra UE dipendono dai provider utilizzati; se presenti, verranno indicati con il relativo Paese.</li>
      </ul>
    </section>

    <section class="policy-block">
      <h2>I tuoi diritti</h2>
      <p>Puoi chiedere accesso, rettifica, cancellazione, limitazione, opposizione e portabilità dei dati. Puoi revocare in qualsiasi momento i consensi (foto e tracciamento) scrivendo a <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a>.</p>
    </section>

    <section class="policy-block">
      <h2>Obbligatorietà e preferenze</h2>
      <p>L'email è necessaria per l'iscrizione ai tornei. La foto è facoltativa. Il tracciamento delle operazioni è facoltativo e resta disattivato finché non esprimi il consenso; puoi modificarlo dal pulsante "Gestisci preferenze" o dal banner.</p>
    </section>

    <section class="policy-block">
      <h2>Cookie e strumenti simili</h2>
      <p>Usiamo cookie tecnici e il salvataggio della tua scelta di consenso. Per dettagli consulta la <a href="/cookie.php">Cookie Policy</a>.</p>
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
