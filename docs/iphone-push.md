# Notifiche push iPhone

## Correzione registrazione del 20 ottobre 2026

Verificato il sorgente della versione installata: Firebase Messaging 16.0.0
inizializzava registrazione APNs e delegate notifiche soltanto nell'osservatore
`UIApplicationDidFinishLaunchingNotification`. Con UIScene il motore implicito
può registrare il plugin dopo questo evento, saltando l'intera inizializzazione.
La sola richiesta esplicita del token non copre ricezione, rinnovo e apertura.

Aggiornati e bloccati Firebase Core 4.14.0 e Messaging 16.7.0 (Apple SDK 12.19.0).
Questa versione include le correzioni ufficiali per registrazione tardiva UIScene
e inizializzazione Firebase da Dart. AppDelegate usa
`FLTFirebaseMessagingPlugin.configureNotificationCenterDelegate()` prima del
ritorno dal callback di avvio. Il plugin gestisce token e inoltro degli eventi;
il canale `tos/push_registration` permette nuovi tentativi e lettura degli errori
nativi. Attesa APNs fino a 30 secondi, con interruzione immediata su errore Apple.

Prima della pubblicazione TestFlight, Codemagic esegue
`mobile/tool/verify_ios_push.dart` sull'IPA esportata: verifica firma codesign,
entitlement `aps-environment=production` sia nell'app sia nel provisioning profile,
team/application identifier e configurazione Firebase inclusa. Un errore ferma
il workflow prima del caricamento. Il controllo richiede macOS ed è coperto da
test della validazione con configurazioni valide e non valide.

La modifica richiede una nuova build iOS; non aggiorna le app già installate.
Compilazione Swift, entitlement dell'IPA e attivazione su iPhone reale restano
da verificare su macOS/TestFlight. Se il problema persiste, controllare anche
il provisioning profile e la connessione ad APNs.

Fonti: [changelog ufficiale](https://pub.dev/packages/firebase_messaging/changelog)
e [inizializzazione UIScene](https://pub.dev/packages/firebase_messaging).

Verifica locale del 20 ottobre: suite Flutter completa, 231 test superati;
11 test specifici backend Firebase/validazione IPA rieseguiti dopo l'ultimo
adeguamento dell'attesa APNs; analisi Flutter senza problemi; test PHP push
e parsing YAML Codemagic superati. Nessuna build macOS o invio Apple eseguito.

Configurazione del 17 ottobre 2026. Il codice supporta Android e iOS; la chiave
APNs di produzione è collegata a Firebase. La consegna su iPhone resta da
verificare con una nuova build firmata e un dispositivo reale.

## Configurazione client completata

- App Apple registrata nel progetto Firebase `tornei-old-school-9e11b`:
  `1:1080279212423:ios:5c4114f569667f22cf7322`.
- Bundle ID: `it.torneioldschool.torneiOldSchool`.
- `GoogleService-Info.plist` incluso nelle risorse Runner. Contiene la
  configurazione pubblica del client, non una chiave privata APNs.
- Entitlement push, background fetch/remote notifications e delegate notifiche
  configurati nel progetto Xcode. Swizzling Firebase lasciato abilitato.
- Auto-init FCM disattivato finché l'utente non attiva le notifiche. Il client
  attende il token APNs prima di richiedere quello FCM.
- Sender PHP con payload APNs alert e suono standard. Preferenze torneo e
  associazione dispositivo/account usano il backend condiviso con Android.

## Stato delle console e passaggi di distribuzione

1. Apple Developer, Identifiers: Push Notifications già abilitato per il bundle
   ID, verificato il 17 ottobre 2026.
2. Chiave `Tornei Old School Firebase Push` creata con conferma dell'utente:
   Key ID `28H2JSJF46`, team `DJX3Z8L84Y`, ambiente Production, Topic Specific
   limitato a `it.torneioldschool.torneiOldSchool`.
3. Chiave caricata e verificata nella sezione APNs di produzione di Firebase.
   La copia privata `.p8` è nei Download dell'utente, fuori dal repository e
   dal sito pubblico. Custodirla: Apple consente un solo download. Nessuna
   chiave sandbox configurata; questa configurazione serve TestFlight/App Store.
4. Verificare che il provisioning profile App Store di Codemagic includa la
   capability push; rigenerarlo se manca. La firma di distribuzione deve produrre
   `aps-environment=production` nell'app esportata, anche se il progetto usa
   `development` per lo sviluppo locale.
5. Compilare e caricare una nuova build TestFlight, verificando il file Firebase
   incluso e gli entitlement dell'IPA firmata. La build 5 già caricata non basta.

## Verifica su dispositivo

Installare da TestFlight su un iPhone reale, accedere e seguire un torneo.
Attivare le notifiche e concedere il permesso iOS. Inviare il test dal profilo:
con app aperta deve comparire l'avviso interno; in background l'avviso iOS.
Verificare tocco dell'avviso, risultato confermato, disattivazione e logout.
La preview web non verifica la consegna APNs.

Validazione locale: analisi Flutter, test push/preferenze/preview e test PHP
del sender. Compilazione Xcode e consegna APNs restano da verificare su macOS
e dispositivo reale.

Riferimento: https://firebase.google.com/docs/cloud-messaging/flutter/get-started
