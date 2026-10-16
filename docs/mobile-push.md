# Notifiche Android — configurazione del 16 ottobre 2026

Progetto Firebase: `tornei-old-school-9e11b`, pacchetto
`it.torneioldschool.tornei_old_school`. Il file client `google-services.json`
è installato in `mobile/android/app/`. Firebase Core 4.0.0 e Messaging 16.0.0
usano i token di registrazione FCM, compatibili con il sender HTTP v1.
La configurazione iOS non è stata abilitata.

## Comportamento

L'account mostra Attiva notifiche, Disattiva notifiche e Invia notifica di test.
L'avvio non chiede il permesso e non registra dispositivi senza scelta esplicita.
L'attivazione richiede prima un backend configurato, poi il permesso Android.
Un token è associato all'account e alla sua sessione revocabile; il sender
esclude sessioni revocate, scadute, account non verificati e password cambiate.
Alla riapertura e al rinnovo token la registrazione viene aggiornata.
Disattivando si elimina la registrazione dal backend e si invalida il token FCM.
Il test invia solo al dispositivo corrente, massimo una volta al minuto.

I risultati, le risposte ai commenti e gli altri eventi già inviati attraverso
`tos_push_send_to_users` passano ora anche al sender Android. Il sender web
VAPID resta operativo e indipendente dalla presenza delle credenziali FCM.
L'app aperta mostra un avviso interno; in background il payload notification
è visualizzato da Android nel canale italiano Aggiornamenti Tornei Old School.
Toccando l'avviso si apre il link HTTPS del sito nel browser interno; eventuale
login web resta separato dalla sessione nativa. I link esterni sono rifiutati.
Su Android, un arresto forzato dalle impostazioni richiede di riaprire l'app.

## Passaggi ancora necessari sul server

1. In Firebase, Impostazioni progetto → Account di servizio, creare una chiave
   privata per l'invio FCM. Non inserirla nell'app, in Git o nella cartella
   pubblica del sito. `google-services.json` non sostituisce questa chiave.
2. Collocare la chiave in un percorso privato leggibile da PHP, fuori dalla
   directory pubblica. Il codice rifiuta chiavi collocate nel repository web.
   Configurare `FCM_SERVICE_ACCOUNT_FILE` con il percorso assoluto del file
   tramite variabile d'ambiente o l'esistente `includi/env.local.php`.
3. Verificare che Firebase Cloud Messaging API (HTTP v1) sia abilitata e che
   l'account di servizio abbia il permesso di inviare messaggi in questo progetto.
   PHP deve avere cURL e OpenSSL con accesso HTTPS a oauth2.googleapis.com e
   fcm.googleapis.com. Non disabilitare la verifica TLS.
4. Applicare `migrations/mobile_push.sql` dopo la migrazione mobile_auth già
   presente. Si aggiunge una tabella, senza modificare utenti o sessioni web.
5. Pubblicare insieme `api/mobile/v1/push.php`, `includi/mobile_push.php`,
   `includi/push_notifications.php` e `privacy.php`. Non sostituire env.local.php
   con un file di esempio e non includere le credenziali nel pacchetto pubblico.
6. Installare il nuovo AAB dalla traccia di test Play. Accedere, aprire Account,
   attivare e provare sia in primo piano sia in background. Verificare un
   risultato/risposta, disattivazione, logout e successivo accesso.

## Build e verifica

Da `mobile`:

```powershell
..\.mobile-tools\flutter\bin\flutter.bat --suppress-analytics build appbundle --no-pub --release --build-number=7 --android-project-arg=tosStoreRelease=true
```

Questa build carica il messaggio UMP dell'app reale e mantiene le unità annunci
di test; non impostare TOS_ADS_MONETIZATION per testare le notifiche.
Non è stato eseguito alcun deploy backend o caricamento su Play Console.
Il recapito reale richiede le credenziali server e la prova sul telefono.

Verifiche locali: `flutter analyze` senza problemi, suite completa di 199 test
superata, test PHP `mobile_push_test.php` e `mobile_auth_test.php` superati.
I test non inviano messaggi a dispositivi reali e non modificano il database del sito.

Riferimenti: [client Flutter](https://firebase.google.com/docs/cloud-messaging/flutter/get-started),
[ricezione messaggi](https://firebase.google.com/docs/cloud-messaging/flutter/receive-messages),
[invio HTTP v1](https://firebase.google.com/docs/cloud-messaging/send/v1-api).
