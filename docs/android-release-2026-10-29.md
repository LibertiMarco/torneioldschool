# Bundle Google Play del 29 ottobre 2026

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
