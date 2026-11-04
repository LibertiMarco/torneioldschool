# Revisione iOS: App Tracking Transparency

La build 0.1.1 (16) richiede ATT tramite il framework Apple dopo il flusso
Google UMP e prima di inizializzare Google Mobile Ads o caricare annunci.
La richiesta viene eseguita soltanto quando l'app è attiva. Se UMP ha già
richiesto ATT, viene letto il risultato senza mostrare un secondo avviso.
Su iOS, rifiuto, restrizione o stato non determinato impediscono gli annunci;
il resto dell'app rimane accessibile. Al ritorno in primo piano il consenso
viene ricontrollato. Android mantiene il consenso UMP.

## Prima dell'invio

- Compilare e archiviare la build 16 su macOS con Xcode. La compilazione Swift
  e la presentazione effettiva dell'avviso non sono verificabili su Windows.
- In AdMob > Privacy e messaggi, verificare che il messaggio GDPR sia pubblicato
  per l'app iOS corretta. Il messaggio esplicativo IDFA di UMP è facoltativo:
  l'app richiede ATT direttamente anche quando quel messaggio non è configurato.
- Verificare le dichiarazioni di App Store Connect per ogni tipo di dato:
  raccolta, collegamento all'identità, finalità e uso per tracking devono
  corrispondere ai servizi effettivamente usati. Non dichiarare automaticamente
  che email e tutti gli altri dati sono usati per tracking.
- Su un dispositivo iOS/iPadOS con stato ATT non determinato e richieste di
  tracking abilitate, provare: consenso, rifiuto, riavvio e revoca nelle
  Impostazioni. Verificare banner e annunci nativi, anche dopo il ritorno
  dalle Impostazioni. Con rifiuto non devono apparire annunci; login e
  navigazione devono continuare a funzionare. Provare anche avvio offline.
- Controllare che altri flussi WebView/SDK non eseguano tracking indipendente
  dal sistema annunci; questa integrazione autorizza esclusivamente gli annunci.

## Note per App Review (da usare dopo la verifica su dispositivo)

We have added App Tracking Transparency authorization in build 16. On a fresh
installation with tracking requests enabled, launch the app and complete the
Google privacy consent flow, if displayed. The native iOS ATT prompt is then
requested before initializing the advertising SDK or loading any ads. No login
is required to reach this flow. If tracking permission is denied or restricted,
ads are disabled and the app remains usable. Authorization is checked again
when the app returns to the foreground. If permission has already been decided,
iOS does not display the prompt again.

Riferimenti: https://developer.apple.com/documentation/apptrackingtransparency
e https://developers.google.com/admob/flutter/privacy/idfa
