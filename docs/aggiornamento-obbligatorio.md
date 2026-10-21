# Aggiornamento obbligatorio dell’app

La build che introduce UpdateGate deve essere installata prima che il blocco possa funzionare. Le build precedenti prive del controllo non possono essere bloccate retroattivamente con questo meccanismo.

Per ogni pubblicazione Android/iOS:

1. Incrementare il numero dopo `+` in `mobile/pubspec.yaml` (o il build number della compilazione).
2. Pubblicare la nuova build e attendere che sia effettivamente disponibile agli utenti.
3. Aggiornare `includi/mobile_release.php`: impostare `build` al numero pubblicato e `url` al link di download della piattaforma. Distribuire questo file e `api/mobile/v1/app_version.php` sul server.

Il link Android preimpostato usa l’application ID del progetto: verificarne la disponibilità sul Play Store. Il link iOS va sostituito con il link reale App Store o TestFlight prima di richiedere un aggiornamento iPhone; attualmente porta al sito.

Il controllo legge il numero realmente installato tramite le API native, all’avvio, alla ripresa e ogni minuto. Le anteprime web/demo sono escluse. Quando la build è inferiore a quella pubblicata l’intera navigazione viene coperta dalla schermata di aggiornamento, senza pulsante per saltarla.

Al primo controllo, se il server non risponde, l’app resta sulla verifica con possibilità di riprovare. Dopo una verifica riuscita, un errore di rete mantiene l’ultimo esito della sessione: un blocco già rilevato resta attivo.

## Verifica del 20 ottobre 2026

- Policy pubblica letta da `api/mobile/v1/app_version.php`: build minima 8 per
  Android e iOS. Non aumentata: le modifiche correnti non sono ancora distribuite.
- iOS e attualmente distribuito tramite TestFlight, secondo conferma dell'utente.
  L'URL configurato porta ancora al sito: non e un percorso di aggiornamento
  verificato. Prima di richiedere una nuova build configurare e provare un link
  TestFlight valido per i destinatari (o App Store quando l'app sara pubblicata).
- Il link pubblico Android ha restituito HTTP 404 dal controllo esterno. Questo
  non dimostra che l'app non sia disponibile ai tester autorizzati. Verificare il
  link con l'account Google iscritto alla traccia prima di rendere obbligatorio
  l'aggiornamento; non usare la risposta pubblica per cambiare la distribuzione.
- Sei test widget superati: piattaforme Android/iOS, controllo alla ripresa,
  controllo periodico, tasto indietro, blocco persistente dopo errore di rete,
  primo avvio senza rete con recupero tramite Riprova, copertura di una rotta
  interna gia aperta tramite il builder reale della navigazione.
- Lettura nativa del build number presente per Android (versionCode) e iOS
  (CFBundleVersion). Questo controllo del sorgente e i test simulati non
  sostituiscono la prova con due build installabili sui dispositivi.

Da verificare sui telefoni prima dell'attivazione: installare una build precedente
che contenga UpdateGate; rendere disponibile la successiva ai tester; controllare
il link di download con quegli account; alzare la policy solo dopo disponibilita;
verificare avvio, ripresa, pagina interna aperta, ritorno dallo store e sblocco
sulla nuova build. Non alzare temporaneamente la policy pubblica per una prova:
bloccherebbe anche gli altri utenti con build precedenti.

## Regola richiesta: solo l'ultima build distribuita

Dal 21 ottobre 2026 la build minima coincide con l'ultima effettivamente
disponibile per ogni piattaforma. Prima dell'attivazione sono state verificate
le console: iOS 11 in TestFlight (Test in corso); Android 11 ancora in bozza,
ultima distribuita 10. Soglie attivate: iOS 11, Android 10.

Il collegamento iOS della policy usa `/app-update.php?platform=ios`, con istruzioni
per TestFlight e pulsante di apertura; non richiede un invito pubblico e non
inventa un link App Store dell'app non ancora pubblicata. L'apertura del pulsante
sul telefono resta da verificare; e sempre disponibile l'istruzione di aprire
TestFlight dalla Home. Non rimuovere un tester dalla build precedente prima di
aver reso disponibile la successiva anche a quel tester.

Per le prossime versioni la soglia deve avanzare solo dopo disponibilita reale,
indipendentemente dalla creazione del bundle, dal push o dal caricamento in bozza.
L'API pubblica non interroga ancora automaticamente le console store: quel
collegamento richiede un'integrazione di rilascio dedicata. Non equiparare
l'aggiornamento di pubspec.yaml a una versione pubblicata.

Il pulsante della vecchia app riceve ora un URL HTTPS con `open=1`: il server
risponde con redirect fisso `itms-beta://`, per aprire direttamente TestFlight
senza richiedere un nuovo binario solo per il collegamento. Nessun URL ricevuto
dall'utente viene usato come destinazione. La pagina senza open=1 resta disponibile
come guida e fallback manuale. Il redirect HTTP e verificabile sul server; l'apertura
nativa di TestFlight e la comparsa del blocco vanno provate su un iPhone con build
precedente. Il blocco nativo esistente e una schermata non aggirabile con Aggiorna
ora, non un avviso facoltativo che consente di continuare con la vecchia versione.
