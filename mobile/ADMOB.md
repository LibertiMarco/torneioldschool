# AdMob e consenso

## Consenso italiano nelle tracce di test Play (16 ottobre 2026)

Le build Android con `tosStoreRelease=true` usano l'App ID AdMob di Tornei
Old School per caricare il messaggio UMP pubblicato, anche nelle tracce di test.
Gli ID delle unità pubblicitarie restano quelli di test Google finché manca
`TOS_ADS_MONETIZATION=true`. Non serve abilitare `tosAdsMonetization` per
provare il consenso italiano. Le build debug e gli APK personali senza opt-in
continuano a usare l'App ID sample.

## Stato predefinito

`google_mobile_ads` 9.1.0 (stabile, risolta con Flutter 3.47.7 / Dart 3.13.5).
UMP è incluso nel pacchetto ufficiale: non serve un secondo plugin.
Nessuna monetizzazione reale è abilitata e nessuna pubblicazione sugli store è stata eseguita.

Stato aggiornato della preparazione: [checklist rilascio](../docs/mobile-release-checklist.md).
La suite completa passa ora con **181 test**; i quattro fallimenti citati
nei resoconti storici sotto sono stati risolti aggiornando esclusivamente
fixture e aspettative obsolete.

- Android App ID di test: `ca-app-pub-3940256099942544~3347511713`.
- Banner adattivo Android di test: `ca-app-pub-3940256099942544/9214589741`.
- Nativo Android di test: `ca-app-pub-3940256099942544/2247696110`.
- iOS App ID di test: `ca-app-pub-3940256099942544~1458002511`.
- iOS banner di test: `ca-app-pub-3940256099942544/2435281174`.
- iOS nativo di test: `ca-app-pub-3940256099942544/3986624511`.
- Android e iOS: aggiornamento UMP, raccolta/modifica consenso e richieste
  pubblicitarie solo quando `canRequestAds()` lo permette. iOS usa sempre
  gli ID di test, anche con opt-in monetizzazione Android, finché la
  configurazione di produzione iOS non sarà esplicitamente abilitata.
- Web, desktop e anteprima demo: nessuna inizializzazione o richiesta annunci.
- Anche una build release usa gli ID di test senza abilitazione esplicita.

Il manifest Android usa `${admobAppId}`, valorizzato da Gradle. L'App ID reale
fornito (`ca-app-pub-8390787841690316~9774422315`) è predisposto esclusivamente
nel ramo release con proprietà Gradle `tosAdsMonetization=true`.
Il banner reale (`ca-app-pub-8390787841690316/9634821513`) richiede una build
Dart release e `TOS_ADS_MONETIZATION=true` via dart-define. Per una futura
attivazione coerente servono entrambi gli opt-in; nessuno è impostato nel repo.
Non abilitare questi valori durante sviluppo, QA o test automatici.
Il nativo reale `ca-app-pub-8390787841690316/2123997150` è predisposto con
la stessa doppia condizione Dart (release e opt-in monetizzazione), ma non
viene richiesto nelle build attuali.

Identificativi reali iOS ricevuti (registrati, **non attivati**):

- App ID: `ca-app-pub-8390787841690316~9044628723`.
- Banner: `ca-app-pub-8390787841690316/6234457543`.
- Nativo: `ca-app-pub-8390787841690316/2726457370`.

Info.plist e le richieste iOS continuano a utilizzare i sample ID Google.
I tre ID sono raccolti nel record `iosProductionAdIds` di `lib/ads.dart` e
non vengono selezionati dal percorso runtime. Ricevere gli identificativi non
equivale ad abilitare annunci reali; l'eventuale attivazione dovrà avere un
opt-in esplicito e mantenere gli ID di test nelle build di sviluppo.

## Flusso e interfaccia

Dopo il primo frame di TosApp parte l'aggiornamento UMP della sessione, poi
`loadAndShowConsentFormIfRequired`. L'avvio dell'app e il ripristino login
continuano indipendentemente. Solo `canRequestAds()` può consentire
l'inizializzazione Mobile Ads e il caricamento del banner.
In caso di errore UMP si interroga comunque `canRequestAds()`, come previsto
da Google per un consenso valido della sessione precedente. Se restituisce
false, o il plugin genera un'eccezione, non si caricano annunci.

Quando UMP richiede un accesso alle opzioni privacy, compare nell'AppBar
principale il pulsante **Preferenze privacy annunci**. Il modulo UMP permette
di modificare o revocare le scelte. I banner vengono rimossi prima di aprire
il modulo e l'idoneità viene rivalutata alla chiusura; eventuali annunci
successivi seguono il nuovo stato UMP. La revoca della personalizzazione può
ancora consentire annunci limitati: si usa sempre la decisione di UMP.
`reset()` non è utilizzato come revoca in produzione.

Sono disponibili tre posizioni, ciascuna con un solo annuncio:

- Home: scheda nativa compatta dopo la prima notizia; sostituisce il banner
  precedentemente collocato dopo la classifica. Nessun nativo se le notizie
  sono vuote o non disponibili.
- Elenco tornei: in fondo ai risultati, solo se esistono tornei con i filtri correnti.
- Dettaglio torneo: nella scheda Classifica.

Tutti i banner scorrono con il contenuto, con SafeArea laterale, etichetta
Pubblicità, margini e colori del tema. Nessun annuncio è inserito nei flussi
di login/account o negli strumenti staff; nessun popup, interstitial, formato
espandibile o video a schermo intero è stato aggiunto. L'app non introduce
timer di refresh. Le route coperte da un'altra pagina rimuovono e rilasciano
il proprio banner. La
larghezza disponibile e l'orientamento determinano la misura adattiva;
si usa il formato inline per liste scorrevoli, con altezza massima richiesta
di 100 dp e misura effettiva letta dal SDK prima di mostrare l'annuncio.
ridimensionamento e uscita dalla schermata eliminano le vecchie istanze.
In assenza di annuncio non viene riservato uno spazio vuoto.

La Home usa il template nativo ufficiale `TemplateType.small`, con badge,
CTA e AdChoices gestiti dal SDK, colori chiari/scuri del tema ed etichetta
Pubblicità esterna. Dimensioni: larghezza tra 320 e 400 dp, altezza 160 dp
più etichetta e margini. Con meno di 320 dp effettivi lo slot resta nascosto
e non richiede annunci. Il nativo viene rilasciato in caso di revoca,
uscita dalla schermata, cambio tema o route coperta; un errore di caricamento
lascia lo slot vuoto senza bloccare il contenuto. Video richiesti muti.
Non sono stati aggiunti nuovi plugin o factory native personalizzate.
Riferimento: [template nativi Google](https://developers.google.com/admob/flutter/native/templates).

## Configurazione successiva e prove su dispositivo

Prima dell'uso reale occorre configurare e verificare i messaggi in
**Privacy & messaging** nell'account AdMob associato all'App ID. Gli ID di
esempio possono non avere un modulo UMP configurato: in questo caso il
banner può correttamente rimanere assente. Non aggirare il consenso per
forzare la visualizzazione.

Su dispositivi di test usare le impostazioni debug UMP e gli identificativi
dei dispositivi secondo la guida Google, verificando primo avvio in EEA,
accettazione, rifiuto, riapertura opzioni, revoca, errore rete, rotazione,
schermi stretti e uscita/rientro dalla classifica. Nessun identificativo di
dispositivo o forzatura geografica è incluso nelle build attuali.

Per iOS, quando disponibili, predisporre **App ID, banner ID e nativo ID iOS
separati** dietro un opt-in esplicito, lasciando i sample ID in tutte le build
di sviluppo. Non sostituire indiscriminatamente gli ID di test né riutilizzare
quelli Android. Verificare su Mac la risoluzione dei plugin e su iPhone i
moduli UMP, la revoca, la navigazione, SafeArea, font grandi e il tema scuro.
Prima di distribuire la build completare dichiarazioni privacy/App Store.
Nessuna richiesta ATT è stata aggiunta: gli annunci possono essere richiesti
senza IDFA; un eventuale flusso IDFA/ATT futuro richiede una configurazione
dedicata, distinta dal consenso UMP.

`ios/Podfile` deriva dal template della versione Flutter installata e permette
ai plugin che richiedono CocoaPods (incluso AdMob) di convivere con Swift
Package Manager già presente nel progetto. Include i target Runner/RunnerTests,
gli hook Flutter e un minimo iOS 15 per i pod, senza abbassare eventuali minimi
superiori. I file Debug/Release.xcconfig includono le configurazioni Pods.
Info.plist include i 50 SKAdNetworkIdentifier della guida Google del 15 ottobre
2026: [configurazione iOS](https://developers.google.com/admob/ios/quick-start).
L'elenco dovrà essere ricontrollato prima di una distribuzione reale.

## Verifiche del 15 ottobre 2026

- `flutter analyze`: superato, nessun problema.
- `flutter test`: 170 superati, 4 falliti già presenti anche nel codice HEAD
  precedente all'integrazione (riprodotti in `.mobile-tools/admob-baseline`).
  Sono i due test di `home_test.dart` e i test “Preview browses roster and
  referto without live network” e “Admin saves demo flags only after
  confirmation” di `preview_test.dart`; aspettative relative ad accesso
  ospite e vecchia navigazione demo. Non sono state modificate funzionalità
  o aspettative estranee alla pubblicità.
- Sei nuovi test verificano selezione sicura ID, piattaforma disabilitata,
  consenso negato, inizializzazione singola, revoca e comportamento offline
  con/senza consenso precedente.
- Verifica mirata finale: 16 test superati (`ads_test.dart`,
  `access_gate_test.dart`, `tournament_page_test.dart`).
- `flutter build apk --debug`: superato. Il manifest risultante contiene
  l'App ID Google di test e il ritardo dell'inizializzazione della misurazione.
- `flutter build web`: superato, incluso il dry run Wasm; gli annunci restano
  disabilitati via controllo piattaforma. Segnalazione non bloccante relativa
  al font CupertinoIcons già referenziato dalle dipendenze.
- `git diff --check`: superato. La build Android segnala avvisi non bloccanti
  sul formato SDK XML e su API/target Java di dipendenze native.
- Compilazione iOS non eseguibile su Windows (richiede macOS/Xcode).
  Moduli UMP, visualizzazione effettiva del banner e ciclo nativo su
  dispositivo richiedono ancora una verifica Android/iOS dedicata.

Riferimenti: [pacchetto ufficiale](https://pub.dev/packages/google_mobile_ads),
[setup](https://developers.google.com/admob/flutter/quick-start),
[banner adattivi e ID test](https://developers.google.com/admob/flutter/banner),
[consenso UMP](https://developers.google.com/admob/flutter/privacy).

## Correzione crash release su Android (15 ottobre 2026)

Il crash rilevato su OPPO CPH2529 / Android 15 avveniva prima di Flutter:
`Unable to get provider androidx.startup.InitializationProvider`, causato da
`Failed to create an instance of androidx.work.impl.WorkDatabase`.
La configurazione Room 2.2.5 transitiva conserva la classe del database, ma
non il costruttore senza argomenti usato tramite reflection: il precedente
`usage.txt` R8 riportava `WorkDatabase_Impl.public void <init>()` tra i membri
rimossi. Non era un errore di login o del consenso.

La release include ora `android/app/proguard-rules.pro`, con una regola
limitata a `androidx.work.impl.WorkDatabase_Impl` e al suo costruttore pubblico.
Le ottimizzazioni del resto dell'app rimangono attive.

Verifica: nuova build `flutter build apk --release` riuscita; costruttore
presente nel mapping R8 e non più nei membri rimossi; installazione `adb
install -r` sul telefono riuscita senza disinstallazione o cancellazione dati;
tre avvii a freddo consecutivi con processo persistente, senza crash per il
nuovo processo. UMP ha caricato correttamente il modulo (`load_complete: ok`).
Le scelte privacy restano all'utente; non sono stati attivati annunci reali.
L'APK corretto sostituisce il pacchetto del download admin.

## Estensione delle posizioni (15 ottobre 2026)

Verifiche successive: `flutter analyze` senza problemi; 31 test mirati
superati su pubblicità, Home, elenco tornei, dettaglio torneo e accesso.
I nuovi test controllano l'assenza di slot senza consenso e il rilascio del
banner quando una route viene coperta, con nuova istanza solo al ritorno.
Build release riuscita e installata come aggiornamento sullo stesso OPPO:
avvio a freddo riuscito, processo senza nuovi crash e verifica visiva del
banner **Annuncio di testo** di test dopo la classifica Home, prima delle
notizie. Nessun annuncio è stato toccato durante le verifiche.

## Formato nativo Home (15 ottobre 2026)

Predisposta l'unità reale Android `ca-app-pub-8390787841690316/2123997150`,
senza abilitarla. Il banner Home è stato sostituito da un solo nativo dopo
la prima notizia. Invariati gli slot banner dell'elenco tornei e della
classifica torneo. Invariati consenso UMP, opt-in monetizzazione e blocco
web/iOS.

Verifiche: analisi statica senza problemi, 23 test mirati superati (inclusi
ID nativo di test in debug e release senza opt-in, revoca, rilascio su route
coperta, ritorno e larghezza insufficiente); build APK release riuscita;
installazione come aggiornamento su OPPO CPH2529 e avvio a freddo riusciti.
Scheda di test verificata visivamente nel feed; validatore AdMob nativo:
“No implementation issues found”. Nessun nuovo crash del processo nei log
letti. L'APK verificato sostituisce il download admin. Nessun annuncio è
stato cliccato; nessun consenso è stato scelto automaticamente.

## Preparazione supporto iOS (15 ottobre 2026)

Abilitato il percorso iOS per SDK/UMP e scelti ID ufficiali iOS distinti per
entrambi i formati. La registrazione dell'app iOS in AdMob non è ancora stata
eseguita dall'utente: nessun ID reale iOS viene inventato o riutilizzato.
Le unità reali Android restano dietro i precedenti opt-in espliciti.

Verifiche: `flutter analyze` senza problemi; 27 test mirati superati, compresi
banner/nativo con variante piattaforma iOS, controllo degli ID effettivamente
richiesti, revoca, rilascio delle route, blocco web/desktop e protezione degli
ID iOS anche con monetizzazione richiesta. Info.plist parsato come XML, App ID
iOS di esempio verificato e 50 identificativi SKAdNetwork unici.

Non eseguibili su questo host Windows: `pod install`, controllo Ruby del
Podfile, compilazione/linking Xcode e prove su iPhone. Nessuna IPA firmata è
stata generata o distribuita; nessun workflow TestFlight è stato avviato.
Il workflow manuale `ios-verification` in `codemagic.yaml` è il prossimo
controllo nativo su macOS; non va confuso con i test Flutter su piattaforma
simulata. Le quattro aspettative obsolete della suite completa già documentate
devono essere risolte prima che quel workflow possa completare tutti gli step.
