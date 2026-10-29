# Bundle Google Play del 29 ottobre 2026

## Aggiornamento: build 15 con eccezione account

Il file da caricare aggiornato è
`mobile/build/app/outputs/bundle/release/tornei-old-school-0.1.1-build-15.aab`.
Include l'eccezione di visualizzazione per `oldschooltornei@gmail.com`:
emblemi a iniziali indipendentemente dal flag globale, dopo l'accesso.
Versione 0.1.1, versionCode 15; stessa configurazione produzione e firma della
build 14. Compilazione completata, firma JAR verificata e certificato confrontato
con il certificato upload esistente: corrispondente. Manifest generato verificato.

SHA256: `EC7B1BFDFC15E0B0F190E8218B07197642F0CA146D1F4FFA8F12375A52762D35`.
Nessun caricamento su Play Console effettuato. La build 14 seguente resta
documentata come precedente e non include l'eccezione account.

## Build 14 precedente

Generato `mobile/build/app/outputs/bundle/release/tornei-old-school-0.1.1-build-14.aab`.

- Versione: 0.1.1; versionCode: 14.
- Package: `it.torneioldschool.tornei_old_school`.
- Backend: `https://torneioldschool.it`.
- Firma upload attivata tramite `tosStoreRelease=true`.
- Monetizzazione: `TOS_ADS_MONETIZATION=true`, come previsto per le release pubbliche.
- Build Android release completata; `jarsigner -verify`: `jar verified`.
- Certificato confrontato con `upload_certificate.pem`: corrispondente.
- SHA256 del bundle: `63883FEE68043E7377A3617FCB1CDBBC993DA3B02903B648132588E7D5DEF4E5`.

La suite Flutter iniziale ha superato 275 test e rilevato tre test da aggiornare
per lo scorrimento del gestionale e il conteggio degli strumenti. Corrette le
aspettative dei test, senza modificare il codice dell'app. I due file coinvolti
sono stati rieseguiti: tutti i nove test superati.

Nessun caricamento su Play Console, pubblicazione o modifica della policy di
aggiornamento obbligatorio. Numero di build da confrontare con quelli già
utilizzati in Play Console prima del caricamento. Le verifiche locali non
includono l'installazione su un dispositivo Android fisico.
