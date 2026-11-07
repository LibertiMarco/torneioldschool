# Google Play - 7 novembre 2026

File da caricare: `mobile/build/app/outputs/bundle/release/tornei-old-school-0.1.1-build-16.aab`.

- Versione 0.1.1, versionCode 16, package `it.torneioldschool.tornei_old_school`.
- Backend di produzione `https://torneioldschool.it`.
- Firma upload esistente con `tosStoreRelease=true`; monetizzazione con
  `TOS_ADS_MONETIZATION=true`, come nelle release precedenti.
- Compilazione appbundle release completata; `jarsigner -verify`: jar verified.
- SHA256 del certificato corrispondente a `upload_certificate.pem`:
  `28:31:9C:DE:81:7C:67:60:10:72:09:7F:4A:A9:A6:71:8D:79:DA:9F:D5:1C:58:47:AF:D4:E7:E8:1E:4B:45:F1`.
- Manifest generato: versionCode 16, versionName 0.1.1, target SDK 36.
- SHA256 del bundle: `FDAA267C8ED00A659B09E3ACA5627F08F5F01413F9868D7ABDFD865E8090D522`.
- `flutter analyze --no-pub`: nessun problema. `flutter test --no-pub`: 289 test superati.

Include la navigazione delle notifiche di associazione verso lo strumento
Account giocatore, banner nelle sezioni Account e Profilo e in tutte le voci
dei singoli tornei, verifica anti-robot prima dell'invio del login.

Nessun caricamento o pubblicazione su Play Console, nessuna modifica della
build minima. Verificare che il codice 16 non sia gia stato utilizzato nella
console prima del caricamento. Non effettuato un nuovo test su dispositivo fisico.

## iOS

Il progetto e predisposto anche per iOS. Il workflow `ios-testflight` in
`codemagic.yaml` compila una IPA firmata e la invia a TestFlight; non la invia
automaticamente alla revisione App Store (`submit_to_app_store: false`).
Non e stata eseguita una compilazione iOS in questa sessione Windows.
Per App Store occorrono account Apple Developer, firma e profili configurati,
build su macOS/Codemagic, test iPhone e invio alla revisione in App Store Connect.
Il bundle Android AAB non e utilizzabile per iOS. Lo stato degli account e delle
build gia caricate su App Store Connect non e stato verificato.
