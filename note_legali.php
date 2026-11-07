<?php
require_once __DIR__ . '/includi/seo.php';
$baseUrl = seo_base_url();
$legalSeo = [
  'title' => 'Note legali - Tornei Old School',
  'description' => 'Termini di servizio del sito e dell\'app Tornei Old School per Android e iOS.',
  'url' => $baseUrl . '/note_legali.php',
  'canonical' => $baseUrl . '/note_legali.php',
];
$legalBreadcrumbs = seo_breadcrumb_schema([
  ['name' => 'Home', 'url' => $baseUrl . '/'],
  ['name' => 'Note legali', 'url' => $baseUrl . '/note_legali.php'],
]);
$lastUpdate = date('d/m/Y', @filemtime(__FILE__) ?: time());
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
  <?php include __DIR__ . '/includi/header.php'; ?>

  <main class="content legal-page">
    <section class="legal-hero">
      <p class="legal-meta">Ultimo aggiornamento: <?php echo $lastUpdate; ?></p>
      <h1>Note legali e Termini d'uso</h1>
      <p>Termini di servizio e condizioni di utilizzo del sito torneioldschool.it e dell'app Tornei Old School per Android e iOS.</p>
    </section>

    <section class="legal-block">
      <h2>Chi siamo e contatti</h2>
      <p>Titolare/gestore: Tornei Old School (gestione amatoriale).</p>
      <p>Area operativa: Napoli e provincia.</p>
      <p>Contatti: <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a> · Sponsorizzazioni: <a href="mailto:sponsor@torneioldschool.it">sponsor@torneioldschool.it</a></p>
    </section>

    <section class="legal-block">
      <h2>Ambito del servizio</h2>
      <p>Questi termini si applicano al sito e all'app Tornei Old School, comprese le funzioni di consultazione di tornei di calcio ed eSport, calendari, risultati, classifiche, squadre, giocatori e notizie, e alle funzioni riservate agli utenti e allo staff. Le funzioni disponibili possono variare in base alla versione, alla piattaforma e alle autorizzazioni dell'account.</p>
      <p>L'utilizzo dell'app non comporta automaticamente l'iscrizione a un torneo. La partecipazione agli eventi resta soggetta ai rispettivi regolamenti e alle condizioni comunicate dagli organizzatori.</p>
    </section>

    <section class="legal-block">
      <h2>Account e corretto utilizzo</h2>
      <ul>
        <li>La registrazione richiede dati corretti e aggiornati; l'account e' personale e non cedibile.</li>
        <li>Sito e app utilizzano lo stesso account. L'utente deve custodire le proprie credenziali e segnalare eventuali accessi non autorizzati.</li>
        <li>E' vietato l'uso del sito e dell'app per attivita' illecite, diffamatorie, molestie, accessi non autorizzati o per compromettere la sicurezza altrui.</li>
        <li>Le richieste di associazione a un giocatore devono riguardare la propria identita' e sono soggette alla verifica dello staff. Non e' consentito impersonare altri utenti o giocatori.</li>
        <li>Gli strumenti di gestione sono riservati agli account autorizzati. Chi li utilizza deve operare soltanto sui dati e sulle funzioni per cui ha ricevuto l'autorizzazione.</li>
        <li>L'accesso puo' essere limitato o sospeso in caso di violazioni di questi termini o abuso del servizio. Per chiarimenti o contestazioni e' possibile contattare il gestore all'indirizzo indicato in questa pagina.</li>
      </ul>
      <p>La cancellazione dell'account puo' essere richiesta dalla sezione Account dell'app o dalla <a href="/account_delete.php">pagina di cancellazione sul sito</a>. Per il trattamento e la conservazione dei dati si rimanda all'<a href="/privacy.php">informativa privacy</a>. Disinstallare l'app non cancella l'account.</p>
    </section>

    <section class="legal-block">
      <h2>Contenuti e responsabilita'</h2>
      <ul>
        <li>Articoli, notizie e materiali pubblicati sono forniti a scopo informativo; possono contenere errori o imprecisioni non intenzionali.</li>
        <li>Immagini e loghi restano dei rispettivi proprietari; e' vietato riutilizzarli senza autorizzazione.</li>
        <li>L'utente deve avere il diritto di pubblicare le foto, i commenti e gli altri materiali che invia. Tali contenuti non devono violare diritti di terzi o contenere materiale illecito o offensivo. Il gestore puo' rimuovere i contenuti in violazione e riceve segnalazioni all'indirizzo indicato in questa pagina.</li>
        <li>Link esterni possono rimandare a siti di terzi: non rispondiamo dei relativi contenuti o policy.</li>
      </ul>
    </section>

    <section class="legal-block">
      <h2>Classifiche, risultati e statistiche</h2>
      <p>I risultati, le classifiche e le statistiche mostrati sul sito e nell'app sono indicativi e basati sui dati disponibili al momento della pubblicazione. Potrebbero verificarsi ritardi o errori di inserimento; in caso di discrepanze fanno fede i referti ufficiali o le comunicazioni degli organizzatori.</p>
    </section>

    <section class="legal-block">
      <h2>App, aggiornamenti e notifiche</h2>
      <p>Le funzioni online richiedono una connessione Internet, con eventuali costi previsti dal proprio operatore. Per ragioni di sicurezza o compatibilita' puo' essere necessario installare una versione aggiornata dell'app per continuare a utilizzare il servizio.</p>
      <p>Le notifiche push dipendono dalle preferenze dell'utente, dai permessi del dispositivo, dalla connessione e dai servizi di recapito. Possono essere disattivate nell'app o nelle impostazioni del sistema. La consegna tempestiva non e' garantita: per gli appuntamenti dei tornei occorre consultare anche il calendario e le comunicazioni degli organizzatori.</p>
    </section>

    <section class="legal-block">
      <h2>Pubblicita' e privacy</h2>
      <p>L'app puo' mostrare banner e annunci nativi tramite Google AdMob. Le scelte relative alla pubblicita' sono gestite mediante le opzioni privacy annunci dell'app e, su iOS, le autorizzazioni di sistema applicabili. Queste scelte sono distinte dai consensi per newsletter e comunicazioni promozionali dell'account.</p>
      <p>Questi termini non sostituiscono il consenso al trattamento dei dati quando richiesto. Per informazioni sui dati trattati, sui servizi di terzi e su come modificare o revocare le proprie scelte, consulta l'<a href="/privacy.php#app-mobile">informativa privacy per l'app</a> e la <a href="/cookie.php">Cookie Policy del sito</a>.</p>
    </section>

    <section class="legal-block">
      <h2>Proprieta' intellettuale</h2>
      <p>I contenuti originali del sito e dell'app (testi, grafiche, layout e software) sono protetti dalle leggi sul diritto d'autore. Il download dell'app consente di utilizzarla per le finalita' del servizio e non trasferisce diritti di proprieta' sui contenuti o sul software. Restano salvi gli utilizzi consentiti dalla legge; negli altri casi la copia o la riproduzione richiede il consenso del titolare dei diritti.</p>
    </section>

    <section class="legal-block">
      <h2>Limitazione di responsabilita'</h2>
      <p>Il sito, l'app e i servizi possono subire interruzioni per manutenzione, aggiornamenti o problemi tecnici. Non e' garantita la disponibilita' continua o l'assenza di errori. Nei soli limiti consentiti dalla legge, il gestore non risponde di danni indiretti o conseguenti all'uso del servizio.</p>
      <p>Questi termini non escludono o limitano le responsabilita' che non possono essere escluse per legge, comprese quelle per dolo o colpa grave, e non pregiudicano i diritti inderogabili degli utenti e dei consumatori, incluse le garanzie applicabili ai servizi digitali.</p>
    </section>

    <section class="legal-block">
      <h2>Aggiornamento dei termini</h2>
      <p>Questa pagina riporta la data dell'ultimo aggiornamento. Eventuali modifiche sostanziali saranno comunicate attraverso il sito, l'app o i recapiti dell'account, secondo quanto richiesto dalla legge. Le modifiche non pregiudicano i diritti gia' maturati.</p>
    </section>

    <section class="legal-block">
      <h2>Segnalazioni</h2>
      <p>Per segnalare violazioni, problemi sui dati o richiedere chiarimenti, scrivi a <a href="mailto:info@torneioldschool.it">info@torneioldschool.it</a>.</p>
    </section>
  </main>

  <?php include __DIR__ . '/includi/footer.html'; ?>
  <script src="/includi/app.min.js?v=20251220"></script>
</body>
</html>


