# Notifiche push iPhone

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
