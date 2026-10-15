# Download Android per il test

`admin_mobile.php` contiene il link a `app-download.php`, che richiede la sessione
admin e restituisce `cache/mobile-builds/tornei-old-school.apk`. La directory dei
pacchetti è bloccata all'accesso HTTP diretto. Il download è disponibile agli
amministratori del sito, non ai visitatori.

L'endpoint verifica la directory ZIP finale dell'APK prima del download: un
caricamento troncato restituisce HTTP 503. Durante l'invio disabilita i buffer
PHP e la compressione, libera la sessione e rimuove il limite di esecuzione PHP.
Questo evita di accumulare l'intero pacchetto nella memoria della richiesta.
Verifica locale: `C:\xampp\php\php.exe tests/android_package_test.php`.

Per aggiornare il pacchetto, dalla directory `mobile`:

```powershell
& ..\.mobile-tools\flutter\bin\flutter.bat build apk --release --no-pub --dart-define=TOS_BASE_URL=https://torneioldschool.it
Copy-Item build/app/outputs/flutter-apk/app-release.apk ../cache/mobile-builds/tornei-old-school.apk
```

Distribuire il pacchetto insieme all'endpoint e al pannello. La firma attuale è
quella di sviluppo: questo APK serve per il test personale. L'app usa il backend
reale e richiede l'accesso mobile attivato. Le scritture modificano i dati reali.

Per iPhone non usare questo APK: seguire `iphone-testflight.md`. Non esiste ancora
una build iOS firmata disponibile per il download.

La build 2 richiede l'accesso nell'app per aprire l'elenco e i dettagli dei tornei
e le classifiche complete. La Home conserva le prime cinque posizioni pubbliche.
I link ai tornei nell'albo d'oro rispettano lo stesso controllo. Logout e perdita
della sessione rimuovono le schermate riservate dalla navigazione.

La build 3 distingue l'accesso richiesto dai guasti del servizio: le risposte
HTTP 401, anche HTML o provenienti dalle API precedenti, mostrano l'invito ad
accedere. Per gli ospiti anche HTTP 403 propone il login; per gli utenti già
autenticati indica la mancanza dei permessi. Dopo il login il pulsante ricarica
il contenuto richiesto. Il controllo copre pannelli dati, dettagli, storico e premi.

L'icona Android e il catalogo icone iOS usano il logo originale
`img/logo_old_school_1200.png`, su fondo bianco. Android include icone adattive.
Per rigenerare tutte le dimensioni: `mobile/generate-app-icons.ps1`.
