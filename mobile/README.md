# Tornei Old School — Android e iOS

Base Flutter dell'app collegata al backend PHP del sito.

Sono presenti tornei, classifiche, partite, account e consultazione utenti per admin. La parità completa con il sito è ancora in sviluppo.

Vedi [DEVELOPMENT.md](DEVELOPMENT.md) per comandi e stato della verifica, [piano completo](../docs/app-mobile.md) per le funzionalità e [rilascio backend](../docs/mobile-production.md) per collegare il sito reale.

## Dashboard staff nell’app

Tutti i 19 strumenti della dashboard usano campi, selettori con ricerca, pulsanti,
card, elenchi paginati e anteprime Flutter. Gli account mantengono il servizio
nativo dedicato. Articoli e sezioni hanno un editor con grassetto, titoli e liste;
le grafiche conservano caricamento foto, ritaglio tramite cursori, template ed
esportazione PNG. I grafici vedono soltanto i sei strumenti delle grafiche.
Il Fantacalcio resta una sezione in preparazione: il sito non espone ancora
operazioni per gestire leghe e formazioni.

Per riutilizzare le regole dei gestionali PHP, la nuova interfaccia riceve un
modello strutturato da un controller interno non visibile. Sul web questo usa
un iframe fuori dallo schermo; su Android/iOS una WebView esclusa dall’interfaccia
e dall’accessibilità. Non si mostrano pagine del sito nella schermata dello
strumento. Form, identificativi, CSRF, controlli e salvataggi rimangono nei
controller originali; questa soluzione richiede la connessione al sito e non è
un gestionale offline. Le sezioni Calcio/Esport sono selezionabili nell’app.

`app-preview/staff-engine.php` accetta soltanto gli strumenti autorizzati. La
preview usa la sessione browser e ricontrolla il ruolo nel database. Android/iOS
usano un bearer verificato per avviare la sessione interna, senza credenziali
nell’URL; richiedono l’API mobile già attivata. Il bridge verifica origine,
finestra, canale casuale e versione del documento; i campi CSRF e nascosti non
vengono esposti. Conferme e messaggi sono mostrati nell’app. La demo usa solo
fixture locali e non salva sul sito.

Verifiche: `flutter test test/native_staff_tool_test.dart test/staff_dashboard_test.dart`,
`php tests/staff_engine_test.php` e `NODE_PATH=/path/to/node_modules node tests/staff_engine_test.js`
(jsdom 26). I test simulano i controller senza modificare dati di produzione.
`tests/staff_web_smoke.js` prova la build web compilata in Chromium con un server
HTTPS locale e un controller fixture, verificando modifica dei campi e salvataggio
con CSRF. Richiede OpenSSL e `playwright-core` (percorso Chromium configurabile con
`TOS_TEST_CHROMIUM`). Android/iOS e il salvataggio nel rullino vanno verificati
anche su dispositivi fisici.

## Getting Started

Usare Flutter 3.47.7. La configurazione `TOS_BASE_URL` consente di selezionare il backend; il valore predefinito è `https://torneioldschool.it`.

A few resources to get you started if this is your first Flutter project:

- [Learn Flutter](https://docs.flutter.dev/get-started/learn-flutter)
- [Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Flutter learning resources](https://docs.flutter.dev/reference/learning-resources)

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.
