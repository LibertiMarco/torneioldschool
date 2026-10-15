# AdMob e consenso

## Stato predefinito

`google_mobile_ads` 9.1.0 (stabile, risolta con Flutter 3.47.7 / Dart 3.13.5).
UMP è incluso nel pacchetto ufficiale: non serve un secondo plugin.
Nessuna monetizzazione reale è abilitata e nessuna pubblicazione è stata eseguita.

- Android App ID di test: `ca-app-pub-3940256099942544~3347511713`.
- Banner adattivo Android di test: `ca-app-pub-3940256099942544/9214589741`.
- iOS: solo App ID Google di esempio `ca-app-pub-3940256099942544~1458002511`;
  inizializzazione, UMP e richieste annunci disabilitati su questa piattaforma.
- Web, desktop e anteprima demo: nessuna inizializzazione o richiesta annunci.
- Anche una build release usa gli ID di test senza abilitazione esplicita.

Il manifest Android usa `${admobAppId}`, valorizzato da Gradle. L'App ID reale
fornito (`ca-app-pub-8390787841690316~9774422315`) è predisposto esclusivamente
nel ramo release con proprietà Gradle `tosAdsMonetization=true`.
Il banner reale (`ca-app-pub-8390787841690316/9634821513`) richiede una build
Dart release e `TOS_ADS_MONETIZATION=true` via dart-define. Per una futura
attivazione coerente servono entrambi gli opt-in; nessuno è impostato nel repo.
Non abilitare questi valori durante sviluppo, QA o test automatici.

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

Il banner compare solo nella scheda Classifica del dettaglio torneo,
all'interno del contenuto, con SafeArea laterale e colori del tema. La
larghezza disponibile e l'orientamento determinano la misura adattiva;
ridimensionamento e uscita dalla schermata eliminano le vecchie istanze.
In assenza di annuncio non viene riservato uno spazio vuoto.

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

Per iOS, quando disponibili, inserire **App ID e banner ID iOS separati**,
sostituire l'App ID di esempio in Info.plist ed estendere esplicitamente la
selezione piattaforma nel controller e nell'unità banner. Verificare su Mac
la risoluzione dei plugin, gli eventuali SKAdNetworkIdentifier richiesti dal
SDK e le dichiarazioni privacy/App Store prima di abilitare annunci iOS.
Non riutilizzare gli identificativi Android. Nessuna richiesta ATT è stata
aggiunta a questa configurazione iOS disabilitata.

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
