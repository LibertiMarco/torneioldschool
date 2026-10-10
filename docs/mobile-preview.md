# Anteprima dell'app con dati reali

L'anteprima principale è `https://torneioldschool.it/app-preview/`, accessibile da PC e Safari su iPhone. Richiede il login del sito come admin o sysadmin. Usa il database esistente tramite PHP: non contiene credenziali DB, dati dimostrativi o una copia del database.

La palette deriva da `style.css`: blu `#15293e`, rosso `#d80000`, sfondo `#f4f4f4`. Il logo è quello del sito. Le schermate sono condivise con l'app nativa; la build web usa `TOS_WEB_PREVIEW=true`. La sessione browser usa il cookie HTTP-only esistente, senza salvare token mobile nel browser. Tutte le scritture richiedono CSRF e ruolo admin attuale; le modifiche confermate alle abilitazioni vengono salvate anche sul sito. Non sono state eseguite scritture di prova sui dati reali.

Le API della preview risiedono in `app-preview/api.php` e usano gli stessi parametri DB del sito. Non dipendono dall'attivazione delle tabelle di sessione native. La build Android/iOS mantiene invece PKCE e le API `api/mobile/v1`, da attivare con `admin_mobile.php` prima del login sul dispositivo.

La schermata iniziale è la Home: marcatori e presenze totali (prime 5 posizioni, classifica completa paginata con ricerca), notizie della sezione, albo d’oro e riferimenti di contatto. Esport usa il ranking EA FC; il server conserva il limite di 5 ospiti / 50 autenticati. Conteggi, pari merito e ordinamenti vengono dalle stesse API del sito. Notizie e albo accettano una sezione esplicita per le sole letture; senza parametro mantengono il comportamento basato sul dominio. Le scritture del blog continuano a seguire il dominio.

Tornei conserva Calcio/Esport e presenta i tre gruppi del sito: In corso (fine crescente, inizio decrescente a pari fine), Programmati (inizio crescente), Terminati (fine decrescente). Ricerca senza distinzione di maiuscole/accenti, filtro categoria e archivio a gruppi di 12. Con filtri attivi si cercano tutti i tornei; le API non tagliano più l’archivio a 200 righe.

Percorso da provare:

0. In Home alternare Marcatori/Presenze, aprire la classifica completa, una notizia e l’albo completo; cambiare sezione e verificare il ranking EA FC. Aprire Tornei per i gruppi e i filtri.

1. Aprire un torneo reale e passare fra Classifica, Marcatori, Calendario, Rose e Regole (Marcatori solo per Calcio).
2. Toccare una squadra, poi un giocatore, per vedere rosa e statistiche del torneo.
3. Toccare una partita per vedere risultato e referto giocatori.
4. Tornare alla home e aprire Account: mostra lo stesso account con cui si è entrati nel sito.
5. In Gestione aprire un account per consultare le abilitazioni. Salvare soltanto una modifica che si intende applicare anche sul sito; annullando la conferma non viene salvato nulla.

Per aggiornare la preview compilare dalla cartella `mobile`:

```powershell
..\.mobile-tools\flutter\bin\flutter.bat build web --release --dart-define=TOS_WEB_PREVIEW=true --base-href=/app-preview/build/ --no-web-resources-cdn --no-wasm-dry-run
```

Copiare l'output verificato in `app-preview/build/` (senza file `.map` e log), poi pubblicare con il normale deploy Git. `app-preview/build/.htaccess` impedisce l'apertura diretta di `index.html`; l'ingresso è il PHP protetto. Gli asset compilati non contengono segreti. La configurazione CanvasKit e i font sono locali per rispettare la CSP del sito.

Il vecchio demo rimane opzionale solo per test automatici/offline, con `mobile/preview.cmd -Demo`. In quella modalità compare il banner giallo e non si fanno chiamate al sito. Non è la versione da usare per provare il database reale. `mobile/preview.cmd` senza opzioni indica la preview reale.

L'anteprima non certifica il comportamento iOS di login, portachiavi, file e notifiche. Non è ancora la versione completa: mancano gli altri moduli elencati in `app-mobile.md`. Per provarla come app nativa sul proprio iPhone servirà una build macOS e poi installazione personale (ad esempio AltStore); TestFlight e pubblicazione sono rimandati.

Per arrestare il server usare `Stop-Process -Id PID`, sostituendo PID con quello mostrato all'avvio e verificando che sia il processo PHP dell'anteprima.

Le pagine torneo usano schede bianche, intestazioni blu, loghi e colori delle qualificazioni. Le statistiche scorrono orizzontalmente mantenendo visibili posizione e squadra. Gironi, soglie Gold/Silver/Bronzo, spareggi e regole vengono dalla configurazione e dai file pubblici del torneo tramite mobile_layout. A pari punti lo scontro diretto si applica solo a due squadre, poi differenza reti e gol fatti, come sul sito. Il calendario parte dalla giornata da giocare e permette di selezionare fase/giornata; le coppe sono raggruppate per turno. Le regole HTML e Markdown sono presentate in schede per argomento, conservando titoli, grassetti ed elenchi. Il dettaglio partita separa risultato, informazioni del campo e referto per squadra, con statistiche e voto in evidenza.

La pagina squadra mostra scudetto originale, riepilogo punti/giocate/gol e schede giocatore con statistiche separate. In assenza di foto, incluso il segnaposto unknown.jpg, usa le iniziali. Il test delle regole comprende il regolamento Markdown reale di Mc League e verifica titoli, grassetti ed elenchi.

Safari: app-preview/.htaccess estende solo img-src della CSP ereditata con blob:, necessario alla decodifica delle immagini statiche nel motore Flutter quando ImageDecoder non e disponibile. La direttiva non modifica script-src, il login admin o le API. Verificata con una risposta Apache reale; dopo il deploy ricaricare la pagina per applicare la nuova policy.

La pagina squadra dispone di Partite e Rosa: dalla classifica parte dagli incontri disputati, dalle Rose parte dai giocatori. Lo storico include casa/trasferta e tutte le fasi del torneo, esclude gare da giocare e altre squadre, ordina dalle piu recenti e apre il dettaglio/referto. Esito vittoria/pareggio/sconfitta calcolato dal punto di vista della squadra, anche con rigori. La rosa usa schede sportive a due colonne quando lo spazio lo consente, foto o iniziali e statistiche reali; sugli schermi stretti o con caratteri ingranditi passa a una colonna.
