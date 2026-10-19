# Aggiornamento obbligatorio dell’app

La build che introduce UpdateGate deve essere installata prima che il blocco possa funzionare. Le build precedenti prive del controllo non possono essere bloccate retroattivamente con questo meccanismo.

Per ogni pubblicazione Android/iOS:

1. Incrementare il numero dopo `+` in `mobile/pubspec.yaml` (o il build number della compilazione).
2. Pubblicare la nuova build e attendere che sia effettivamente disponibile agli utenti.
3. Aggiornare `includi/mobile_release.php`: impostare `build` al numero pubblicato e `url` al link di download della piattaforma. Distribuire questo file e `api/mobile/v1/app_version.php` sul server.

Il link Android preimpostato usa l’application ID del progetto: verificarne la disponibilità sul Play Store. Il link iOS va sostituito con il link reale App Store o TestFlight prima di richiedere un aggiornamento iPhone; attualmente porta al sito.

Il controllo legge il numero realmente installato tramite le API native, all’avvio, alla ripresa e ogni minuto. Le anteprime web/demo sono escluse. Quando la build è inferiore a quella pubblicata l’intera navigazione viene coperta dalla schermata di aggiornamento, senza pulsante per saltarla.

Al primo controllo, se il server non risponde, l’app resta sulla verifica con possibilità di riprovare. Dopo una verifica riuscita, un errore di rete mantiene l’ultimo esito della sessione: un blocco già rilevato resta attivo.
