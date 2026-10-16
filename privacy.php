<!DOCTYPE html>
<html lang="it">
<head>
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
  <?php include __DIR__ . '/includi/header.php'; ?>

  <main class="content policy-page">
    <section class="policy-hero">
      <p class="policy-meta">Ultimo aggiornamento: <?php echo $lastUpdate; ?></p>
      <h1>Privacy Policy</h1>
      <p>Questa informativa spiega come trattiamo i dati quando ti iscrivi ai tornei, navighi sul sito o utilizzi l'app Tornei Old School per Android e iOS.</p>
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
      <h2>Finalita e basi giuridiche</h2>
      <ul>
        <li>Iscrizione e gestione del torneo, comunicazioni correlate: necessita contrattuale.</li>
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
        <li>Nessuna vendita o cessione a terzi per finalita di marketing.</li>
        <li>Eventuali trasferimenti extra UE dipendono dai provider utilizzati; se presenti, verranno indicati con il relativo Paese.</li>
      </ul>
    </section>

    <section class="policy-block">
      <h2>Pubblicita e monetizzazione</h2>
      <p>Al momento il sito non serve annunci display di terze parti attivi. Se in futuro attiveremo un circuito pubblicitario come Google AdSense, aggiorneremo questa informativa e la Cookie Policy prima dell'attivazione, raccogliendo il consenso dove richiesto.</p>
    </section>

    <section class="policy-block" id="app-mobile">
      <h2>App Android e iOS: account, notifiche e pubblicit&agrave;</h2>
      <p>Su Android, attivando le notifiche dall'account, l'app utilizza Firebase Cloud Messaging (Google) per ricevere aggiornamenti sui risultati e sulle risposte ai commenti. Il server associa il token di registrazione del dispositivo all'account e alla sessione di accesso per recapitare gli avvisi. Puoi disattivare le notifiche nell'app o nelle impostazioni Android; dopo il logout il server non invia pi&ugrave; notifiche alla sessione revocata. Le notifiche di servizio sono separate dai consensi promozionali e pubblicitari. Questa integrazione non abilita Google Analytics.</p>
      <p>L'app utilizza lo stesso account del sito per accedere alle funzioni riservate. Conserva sul dispositivo i token necessari a mantenere la sessione attraverso l'archiviazione sicura del sistema. Il consenso alle comunicazioni promozionali dell'account &egrave; distinto dalle preferenze relative agli annunci.</p>
      <p>L'app integra Google Mobile Ads (AdMob) per banner e annunci nativi e Google User Messaging Platform (UMP) per le scelte sulla privacy. Nelle build di prova attuali sono utilizzati soltanto identificativi pubblicitari di test; la monetizzazione reale non &egrave; abilitata. Anche gli annunci di test utilizzano i servizi e gli SDK Google.</p>
      <p>In relazione al funzionamento e alle scelte applicabili, gli SDK pubblicitari possono trattare indirizzo IP (anche per stimare una posizione approssimativa), identificativi del dispositivo o pubblicitari disponibili, interazioni con gli annunci e dati diagnostici, per erogazione e misurazione della pubblicit&agrave;, sicurezza e prevenzione delle frodi. L'app non richiede l'accesso alla posizione precisa per mostrare annunci.</p>
      <p>All'avvio viene aggiornato lo stato UMP e viene mostrato il modulo di consenso quando richiesto. Gli annunci sono richiesti solo quando UMP lo consente. Quando previsto dal modulo, il pulsante <strong>Preferenze privacy annunci</strong> nella barra superiore dell'app permette di riaprire le opzioni e modificare o revocare le scelte. Il rifiuto della personalizzazione pu&ograve; consentire annunci limitati secondo le impostazioni e lo stato restituito da Google.</p>
      <p>La configurazione iOS attuale non richiede l'autorizzazione App Tracking Transparency e non autorizza l'accesso all'IDFA. Un eventuale cambiamento di questa configurazione richieder&agrave; i relativi avvisi e permessi. Le scelte dell'app sono separate dal consenso ai cookie del sito.</p>
      <p>Google pu&ograve; trattare dati attraverso la propria infrastruttura, anche fuori dallo Spazio economico europeo, secondo le condizioni e le garanzie descritte nella sua informativa. Per informazioni su trattamento e conservazione consulta la <a href="https://policies.google.com/privacy">Privacy Policy di Google</a> e <a href="https://policies.google.com/technologies/partner-sites">come Google utilizza i dati delle app che usano i suoi servizi</a>. Per richieste relative al tuo account Tornei Old School puoi scrivere a <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a>.</p>
    </section>

    <section class="policy-block">
      <h2>I tuoi diritti</h2>
      <p>Puoi chiedere accesso, rettifica, cancellazione, limitazione, opposizione e portabilita dei dati. Puoi revocare in qualsiasi momento i consensi (foto e tracciamento) scrivendo a <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a>.</p>
    </section>

    <section class="policy-block">
      <h2>Obbligatorieta e preferenze</h2>
      <p>L'email e necessaria per l'iscrizione ai tornei. La foto e facoltativa. Il tracciamento delle operazioni e facoltativo e resta disattivato finche non esprimi il consenso; puoi modificarlo dal pulsante "Gestisci preferenze" o dal banner.</p>
    </section>

    <section class="policy-block">
      <h2>Cookie e strumenti simili</h2>
      <p>Usiamo cookie tecnici e il salvataggio della tua scelta di consenso. Per dettagli consulta la <a href="/cookie.php">Cookie Policy</a>.</p>
    </section>
  </main>

  <?php include __DIR__ . '/includi/footer.html'; ?>
  <script src="/includi/app.min.js?v=20251220"></script>
</body>
</html>

