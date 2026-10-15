# Schede store e dichiarazioni — bozza da completare

## Testi proposti

Nome: **Tornei Old School**

Descrizione breve Google Play: **Tornei, classifiche e statistiche della community Old School.**

Sottotitolo App Store: **Tornei, risultati e classifiche**

Descrizione:

Segui la community Tornei Old School dal tuo telefono. Consulta le notizie,
scopri i tornei di calcio ed esport e ritrova risultati, calendari e classifiche
in un'unica app.

Accedi con il tuo account Tornei Old School per consultare i dettagli dei
tornei e seguire le statistiche dei giocatori. Se il tuo account è collegato
a un profilo giocatore, puoi ritrovare il tuo percorso e i tuoi risultati.

L'app supporta il tema chiaro e scuro. Alcune funzioni richiedono un account,
una connessione internet e i permessi previsti per il tuo ruolo.

Sito sviluppatore / URL marketing: https://torneioldschool.it

Assistenza: https://torneioldschool.it/contatti.php

Privacy: https://torneioldschool.it/privacy.php

Cancellazione account: https://torneioldschool.it/account_delete.php
(richiede autenticazione sul sito; verificare il percorso completo come
utente e la conformità ai requisiti della console prima dell'invio).

Categoria proposta: Sport. Pubblico dichiarato dall'utente: 13+; rispondere
ai questionari di età effettivi, senza sostituirli con questa indicazione.

## Materiale ancora necessario

Schermate della build finale per Android e iPhone/iPad nei formati richiesti
dalle console; evitare notifiche, dati privati e interfacce staff nelle
schermate rivolte al pubblico. Icone già nel progetto: verificare i requisiti
del file da caricare in ciascuna console. Nessuno screenshot Android va
presentato come screenshot iPhone. Nessun materiale è stato caricato.

## Dichiarazioni dati: evidenze per il titolare

Questa è una traccia di compilazione, non un modulo già inviato o una
certificazione: le risposte dipendono dalla build finale e dalle impostazioni.

| Dati/funzione | Evidenza nel progetto | Verifica prima dell'invio |
| --- | --- | --- |
| Nome, email, account | Login web e API account; nome/cognome nel profilo | Finalità funzionamento account, collegamento all'identità, conservazione del backend |
| Foto facoltative | Selezione file/avatar/foto giocatore in Account | Foto inviate al backend soltanto su scelta dell'utente; dichiarare il contenuto caricato |
| Token di sessione | flutter_secure_storage e refresh/logout | Identificativi di account e gestione sessione; non dichiarare assenza assoluta di identificativi |
| Statistiche sportive e profilo giocatore | API e pagine pubbliche/riservate | Associazione al giocatore, contenuti pubblici e informazioni dell'account |
| IP, identificativi, interazioni e diagnostica SDK | Google Mobile Ads e UMP | Consultare le dichiarazioni Google aggiornate anche usando annunci di test |
| Posizione | Nessuna richiesta di posizione precisa per gli annunci | L'IP può consentire una localizzazione approssimativa; non selezionare automaticamente “nessun dato di posizione” |
| IDFA iOS | Nessuna richiesta ATT implementata | Non abilitare tracking/IDFA senza una valutazione e un flusso dedicati; verificare il report privacy Xcode |
| Consensi facoltativi account | Newsletter, marketing, tracking su API account | Distinguere dalle scelte pubblicitarie UMP e verificare i servizi del sito aperti dall'app |
| Cifratura in transito | TosApi richiede HTTPS in release | Verificare anche le risorse del sito/WebView e i servizi terzi |

L'app integra pubblicità anche se la monetizzazione è disabilitata: rivedere
la risposta “contiene annunci” secondo la build effettivamente inviata. Non
dichiarare che gli SDK di test non trattano alcun dato. Non compilare alla
cieca le caselle raccolta/condivisione, dati collegati e finalità di tracking:
account sviluppatore e report nativo non sono ancora disponibili.

Riferimenti ufficiali:

- [Google Play: dichiarazioni SDK AdMob](https://developers.google.com/admob/android/privacy/play-data-disclosure)
- [App Store: dichiarazioni SDK AdMob](https://developers.google.com/admob/ios/privacy/data-disclosure)
- [Privacy manifest Apple](https://developer.apple.com/documentation/bundleresources/privacy_manifest_files)

Per la revisione store servirà un account di prova con privilegi minimi e
dati dimostrativi appropriati. Non usare l'account admin personale e non
salvare credenziali in questo documento. Non è stato creato un account reale
o modificato il database di produzione durante questa preparazione.
