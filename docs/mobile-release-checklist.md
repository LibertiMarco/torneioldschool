# Preparazione rilascio — 15 ottobre 2026

## Risultato tecnico

- `flutter analyze`: nessun problema.
- `flutter test`: **181 test superati**, inclusa la suite prima bloccata da
  quattro aspettative obsolete su login, dashboard staff e formato punteggi.
  Sono stati modificati i test, non le funzionalità che verificano.
- `flutter build apk --release`: riuscita. L'APK personale conserva la firma
  di sviluppo precedente; **non è un pacchetto da caricare su Play Store**.
- `flutter build appbundle --release --build-number=3 --android-project-arg=tosStoreRelease=true`:
  riuscita. Bundle in `mobile/build/app/outputs/bundle/release/app-release.aab`.
  Firma verificata con jarsigner e certificato confrontato con la nuova chiave
  upload (CN=Tornei Old School). SHA256 del bundle:
  `3D00F080ADF53C4B7E37B05E698BE449803C8FA6D9B34B0A0A4D90411B4FD086`.
  Il certificato è autofirmato, come previsto per questa chiave Android.
  Il bundle non è stato caricato su Play Console né installato sul telefono.
- `php -l privacy.php`: riuscito. `app-ads.txt` contiene il publisher
  `pub-8390787841690316`, uguale all'account AdMob e all'ads.txt esistente.
- Smoke HTTP con server PHP temporaneo su loopback: app-ads.txt e privacy.php
  rispondono 200 e contengono i valori/sezione attesi. Il server è stato
  arrestato. Il runtime sessioni locale segnala directory non scrivibili:
  non è stata eseguita una prova di login PHP né modificata la configurazione
  del server per aggirarla. Dopo il deploy, verificati anche gli URL pubblici:
  entrambi HTTP 200, publisher corretto e sezione mobile presente nella privacy.
- Aggiunto il link all'informativa nelle viste Account ospite e autenticata.
- Nessun ID pubblicitario reale è stato attivato, nessuna app è stata
  pubblicata negli store. Creata una chiave upload Android privata su autorizzazione.

## AdMob

Nel browser autenticato è stato pubblicato il messaggio **Tornei Old School - Consenso
app Android e iOS**. Associa le due app, usa l'italiano come lingua predefinita
e include l'inglese. Sono disponibili Acconsenti, Nega il consenso e Gestisci
le opzioni; il rifiuto è abilitato per tutte le regioni del modulo. Targeting:
SEE, Regno Unito e Svizzera. URL privacy per entrambe:
`https://torneioldschool.it/privacy.php`.

Stato verificato nella console: **Pubblicato**. Non sono stati modificati partner pubblicitari,
interesse legittimo, IDFA/ATT, sincronizzazione del consenso o impostazioni
di fatturazione. Gli attuali ID Google di test hanno la propria configurazione
UMP: il messaggio delle app reali non modifica il comportamento dei sample ID.

Prima degli annunci reali rivedere la configurazione dei partner/finalità.
Pubblicare il messaggio non abilita da
solo gli annunci reali nell'app: gli opt-in del codice restano disabilitati.

Per app-ads.txt usare come sito sviluppatore/marketing negli store
`https://torneioldschool.it`. Il file deve risultare pubblico a
`https://torneioldschool.it/app-ads.txt`; solo dopo il deploy e il collegamento
delle schede store AdMob potrà verificarlo. Non dichiarare verificata la
proprietà sulla sola base della presenza del file locale.

## Android: firma privata creata

È pronta `mobile/android/key.properties.example`. La configurazione Gradle
usa la firma privata solo con la proprietà **tosStoreRelease=true**; in
questa modalità si arresta se mancano file o campi della firma. Senza opt-in
continua a produrre l'APK personale con la vecchia firma debug.

Su autorizzazione esplicita sono stati creati `.mobile-tools/signing/tornei-upload.jks`
e `mobile/android/key.properties`, esclusi da Git. Nella cartella signing sono
presenti una copia delle proprietà e il certificato pubblico `upload_certificate.pem`.
La cartella è protetta dall'accesso Apache tramite `.mobile-tools/.htaccess`.
Occorre ancora un backup privato esterno a questo PC: la copia locale non lo
sostituisce. Le password non vanno incollate in chat o nel repository.

Dopo la configurazione locale della firma, dalla directory `mobile`:

```powershell
..\.mobile-tools\flutter\bin\flutter.bat build appbundle --release --build-number=3 --android-project-arg=tosStoreRelease=true
```

Questo comando non abilita la monetizzazione e non carica nulla su Google
Play. Incrementare ulteriormente il numero se la versione 3 è già stata
utilizzata. Il bundle va verificato prima del caricamento. Il passaggio da
APK debug a firma di produzione non va effettuato disinstallando di nascosto
l'app sul telefono: prima concordare il percorso e la conservazione dati.
Usare l'argomento Flutter esplicito mostrato sopra: in questa sessione la
variabile ORG_GRADLE_PROJECT da sola non ha selezionato la firma upload.

## iOS

Podfile, configurazioni Pods, ID di test separati, SKAdNetwork e flusso UMP
sono predisposti. Tutti e tre gli ID reali iOS sono registrati ma non attivati.
Il workflow manuale `ios-verification` ora può superare i test Flutter ed è
configurato con CocoaPods; deve ancora essere eseguito realmente su macOS.
Non è stata compilata una IPA e non sono state verificate firma, linking
nativo, privacy manifest aggregato o UI su iPhone.

Per TestFlight occorrono account Apple Developer, certificato/profilo e
integrazione App Store Connect in Codemagic. Non inviare password o chiavi
private in chat; configurarle nel servizio. Non è stato avviato alcun workflow
remoto né accettato un abbonamento.

## Passaggi personali dell'utente

L'utente ha confermato di non avere account sviluppatore e di rivolgersi a
un pubblico **dai 13 anni in su**. Non equivale a certificare una classificazione
di età o che tutti gli utenti abbiano l'età del consenso digitale: verificare
i questionari store e l'eventuale gestione dei minori prima di annunci reali.

1. Iscriversi a Google Play Console e completare identità, accordi e quota
   indicata da Google. Per i nuovi account personali è previsto un test chiuso
   con almeno 12 tester aderenti continuativamente per 14 giorni, seguito
   dalla richiesta di accesso alla produzione.
2. Iscriversi all'Apple Developer Program e completare le verifiche/accordi
   Apple. Preparare l'app in App Store Connect e il servizio macOS per la build.
3. Confermare identità pubblica dello sviluppatore, contatto e pubblico;
   rivedere le dichiarazioni privacy delle schede store sulla build finale.
4. Conservare un backup privato esterno della cartella `.mobile-tools/signing`.
   Informativa, app-ads.txt e messaggio AdMob sono già pubblicati su autorizzazione.
5. Dopo i test nativi, caricare nei canali di prova. Pubblicazione pubblica e
   annunci reali richiedono passaggi successivi espliciti: non sono automatici.

## Link ufficiali

### Come organizzare i 12 tester

Il requisito riguarda i nuovi account Google Play personali creati dopo il
13 novembre 2023. Servono almeno 12 persone con account Google e dispositivo
Android che aderiscano al test chiuso tramite il link di Play Console e
rimangano aderenti per almeno 14 giorni consecutivi. Conviene coinvolgere
15–20 partecipanti reali per gestire eventuali rinunce. L'APK del sito e il
canale test interno non soddisfano questo requisito.

I tester devono provare le funzioni (accesso, tornei, classifiche, account,
preferenze privacy) e inviare feedback: non occorre tenere l'app aperta tutto
il giorno. Conservare un resoconto delle prove e dei problemi risolti. Il
conteggio non parte dalla creazione dell'account sviluppatore; la console
verifica l'adesione dei tester al test chiuso. Dopo i 14 giorni si richiede
l'accesso alla produzione, che Google valuta e può anche non concedere se
il test non è sufficiente. Non è una pubblicazione automatica.

### Riferimenti

- [Google Play Console e quota](https://support.google.com/googleplay/android-developer/answer/6112435?hl=it)
- [Requisiti test Google Play](https://support.google.com/googleplay/android-developer/answer/14151465?hl=it)
- [Apple Developer Program](https://developer.apple.com/programs/enroll/)
- [app-ads.txt](https://support.google.com/admob/answer/9363762?hl=it)
- [Messaggi europei AdMob](https://support.google.com/admob/answer/10113207?hl=it)

## Stato delle autorizzazioni

L'utente ha autorizzato esplicitamente la creazione della chiave Android,
la pubblicazione dell'informativa e app-ads.txt e del messaggio di consenso
AdMob. Questi passaggi sono stati completati. Restano disabilitati gli annunci
reali; non sono stati creati account sviluppatore, accettati accordi o
pubblicate app negli store.
