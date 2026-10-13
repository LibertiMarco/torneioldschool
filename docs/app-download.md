# Download Android per il test

`admin_mobile.php` contiene il link a `app-download.php`, che richiede la sessione
admin e restituisce `cache/mobile-builds/tornei-old-school.apk`. La directory dei
pacchetti è bloccata all'accesso HTTP diretto. Il download è disponibile agli
amministratori del sito, non ai visitatori.

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
