<?php
require_once __DIR__ . '/includi/seo.php';
$baseUrl = seo_base_url();
$legalSeo = [
  'title' => 'Note legali - Tornei Old School',
  'description' => 'Termini legali e condizioni d uso del sito Tornei Old School.',
  'url' => $baseUrl . '/note_legali.php',
  'canonical' => $baseUrl . '/note_legali.php',
];
$legalBreadcrumbs = seo_breadcrumb_schema([
  ['name' => 'Home', 'url' => $baseUrl . '/'],
  ['name' => 'Note legali', 'url' => $baseUrl . '/note_legali.php'],
]);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($legalSeo); ?>
  <?php render_jsonld($legalBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    .legal-page { padding: 40px 20px; max-width: 960px; margin: 0 auto; }
    .legal-hero h1 { margin-bottom: 8px; }
    .legal-block { margin-top: 28px; background: #ffffff; border: 1px solid #e5e8ed; border-radius: 12px; padding: 20px 24px; box-shadow: 0 10px 30px rgba(21,41,62,0.06); }
    .legal-block h2 { margin-top: 0; color: #15293e; }
    .legal-block ul { padding-left: 18px; }
    .legal-meta { color: #4b5563; }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="content legal-page">
    <section class="legal-hero">
      <p class="legal-meta">Ultimo aggiornamento: <?php echo date('d/m/Y'); ?></p>
      <h1>Note legali e Termini d'uso</h1>
      <p>Condizioni di utilizzo del sito, informazioni sul titolare e disclaimer sui contenuti.</p>
    </section>

    <section class="legal-block">
      <h2>Chi siamo e contatti</h2>
      <p>Titolare/gestore: Tornei Old School (gestione amatoriale).</p>
      <p>Contatti: <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a></p>
    </section>

    <section class="legal-block">
      <h2>Termini di utilizzo</h2>
      <ul>
        <li>La registrazione richiede dati corretti e aggiornati; l'account e' personale e non cedibile.</li>
        <li>E' vietato l'uso del sito per attivita' illecite, diffamatorie o per compromettere la sicurezza altrui.</li>
        <li>L'accesso puo' essere limitato o sospeso in caso di violazioni di questi termini o abuso del servizio.</li>
      </ul>
    </section>

    <section class="legal-block">
      <h2>Contenuti e responsabilita'</h2>
      <ul>
        <li>Articoli, notizie e materiali pubblicati sono forniti a scopo informativo; possono contenere errori o imprecisioni non intenzionali.</li>
        <li>Immagini e loghi restano dei rispettivi proprietari; e' vietato riutilizzarli senza autorizzazione.</li>
        <li>Link esterni possono rimandare a siti di terzi: non rispondiamo dei relativi contenuti o policy.</li>
      </ul>
    </section>

    <section class="legal-block">
      <h2>Classifiche, risultati e statistiche</h2>
      <p>I risultati, le classifiche e le statistiche mostrati sul sito sono indicativi e basati sui dati disponibili al momento della pubblicazione. Potrebbero verificarsi ritardi o errori di inserimento; in caso di discrepanze fanno fede i referti ufficiali o le comunicazioni degli organizzatori.</p>
    </section>

    <section class="legal-block">
      <h2>Proprieta' intellettuale</h2>
      <p>I contenuti originali del sito (testi, grafiche, layout) sono protetti dalle leggi sul diritto d'autore. E' vietata la copia o la riproduzione senza consenso scritto.</p>
    </section>

    <section class="legal-block">
      <h2>Limitazione di responsabilita'</h2>
      <p>Il sito e i servizi sono forniti "cosi' come sono". Non garantiamo disponibilita' continua, assenza di errori o idoneita' per scopi specifici. Nei limiti di legge, il gestore non risponde di danni indiretti o conseguenti all'uso del sito.</p>
    </section>

    <section class="legal-block">
      <h2>Segnalazioni</h2>
      <p>Per segnalare violazioni, problemi sui dati o richiedere chiarimenti, scrivi a <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a>.</p>
    </section>
  </main>

  <div id="footer-container"></div>

  <script src="/includi/app.min.js?v=20251201"></script>
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
