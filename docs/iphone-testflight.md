# Provare l'app su iPhone da Windows

Il telefono dell'utente è un iPhone; ha un normale Apple ID e non è iscritto all'Apple Developer Program. La compilazione iOS non è stata eseguita e non esiste ancora una IPA installabile. Il progetto resta una base: la parità completa con il sito, comprese le operazioni admin, è ancora da sviluppare.

## Account necessario

TestFlight richiede l'Apple Developer Program. L'iscrizione va completata dal titolare su https://developer.apple.com/programs/enroll/; Apple indica 99 USD all'anno o il prezzo locale mostrato durante l'iscrizione. Non inviare password o chiavi in chat.

La prova gratuita ufficiale con un account personale usa Xcode su un Mac: i profili scadono dopo sette giorni e richiedono ricompilazione e reinstallazione. Non abilita TestFlight. Fonte: https://developer.apple.com/help/account/basics/about-your-developer-account.

## Preparazione una volta attivata l'iscrizione

1. Registrare l'identificatore `it.torneioldschool.torneiOldSchool` nel portale Apple e creare la relativa app iOS in App Store Connect. Se l'identificatore cambia, aggiornare sia Xcode sia `codemagic.yaml` prima della prima distribuzione.
2. Collegare il repository `LibertiMarco/torneioldschool` a Codemagic. La compilazione usa un Mac remoto; Windows resta il computer di sviluppo.
3. Configurare una chiave App Store Connect nell'integrazione Codemagic denominata `tos-app-store-connect`, con i permessi necessari a caricare le build. Conservare la chiave privata esclusivamente nel servizio, mai nel repository.
4. Aggiungere a Codemagic il certificato Apple Distribution con la sua chiave privata e il profilo App Store per questo identificatore. Seguire https://docs.codemagic.io/yaml-code-signing/signing-ios/.
5. Avviare manualmente `ios-verification` sul branch `master` per verificare la compilazione senza firma. Questo risultato non si installa sul telefono.
6. Avviare manualmente `ios-testflight`: esegue analisi e test, firma la build e la carica su App Store Connect. Il numero usa `BUILD_NUMBER + 1`, progressivo di Codemagic; se si caricano build da altri sistemi verificare che sia maggiore dei numeri già usati.
7. Dopo l'elaborazione Apple, completare le informazioni richieste, aggiungere il proprio account come tester interno e abilitare la build al test. Installare TestFlight sull'iPhone e accedere all'invito. L'eventuale test esterno richiede i passaggi di revisione Apple.

Non ci sono trigger automatici per questi workflow: un push su master continua a pubblicare il sito, ma non avvia né distribuisce una build iOS. Il workflow TestFlight non invia l'app alla pubblicazione sull'App Store.

Riferimento configurazione: https://docs.codemagic.io/yaml-quick-start/building-a-flutter-app/.

## Primo controllo sul dispositivo

Prima del login, un admin deve preparare e attivare l'accesso mobile su `https://torneioldschool.it/admin_mobile.php`. Controllare poi elenco tornei, classifiche e partite, login con ritorno dal browser, ripristino della sessione dopo chiusura, logout e permessi admin. Le prove di scrittura sui dati reali richiederanno casi concordati quando quelle funzioni saranno implementate.

Resta da verificare su macOS e sul telefono anche la dipendenza prerelease `flutter_web_auth_2` e la firma del portachiavi. Icone e materiale per lo store sono ancora da completare.
