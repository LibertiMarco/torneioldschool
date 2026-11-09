# Gestione, coppe e contenuti — novembre 2026

## Coppe

Scudetti sulle squadre, inclusi i segnaposto con qualificate note e le vincenti.
I riquadri usano oro, argento o bronzo, con contrasto adatto anche al tema scuro.
Il codice F1 resta un identificatore interno e non viene mostrato. I controlli
dei turni evidenziano la selezione anche quando si scorre manualmente.
Il vincitore mostra scudetto e nome; se non è ancora noto usa un segnaposto.

Le diffide ripartono per le coppe quando la regular season risulta conclusa
(nessuna gara regular in attesa e coppe già previste o copertura del girone
all'italiana completa), oppure quando è stata finalizzata una gara di coppa.
Le ammonizioni storiche non vengono cancellate. I referti non finalizzati non
entrano nel conteggio. Un calendario parziale di poche giornate non basta a
segnare conclusa la stagione.

## Gestioni

Nell'app e nella preview gli annunci precedono e seguono Crea/Modifica/Elimina
squadre, creazione giornata, account giocatore, articoli e gestione account.
Matchday ne ha uno iniziale e uno tra le grafiche.
La ricerca duplicata dello scudetto viene omessa solo dall'interfaccia nativa;
la scelta ricercabile si chiama Riutilizza squadra e mantiene il controller web.

La giornata usa un interruttore per i ritorni e una selezione ricercabile con
interruttori per le squadre, inizialmente tutte incluse. Le disponibilità sono
singole combinazioni data/ora; ogni scelta produce una regola separata, evitando
incroci involontari fra giorni e orari. Nessuna disponibilità selezionata mantiene
il significato precedente: squadra senza vincoli. Le preferenze non sono più
mostrate accanto ai nomi. Il controller HTML resta utilizzabile anche dalle
versioni precedenti dell'app: i nuovi selettori richiedono la capacità `ui=2`.

Gestione account cerca per nome o email e modifica soltanto `ruolo`:
user, grafico, admin, sysadmin. Restano invariati password, dati personali,
associazioni e abilitazioni. Sono verificati permessi attuali, modifiche
concorrenti e permanenza di almeno un amministratore.

Le voci Video partite, Accesso app, Totocalcio e Fantacalcio vengono omesse dai
menu pertinenti; servizi, batch e funzioni restano disponibili. Accesso app era
il pannello di configurazione: non viene disattivato l'accesso dell'app pubblicata.
L'albo d'oro conserva l'automatismo esistente, in attesa dell'intervento dedicato.

## Articoli

Grassetto, corsivo, sottolineato ed elenchi vengono convertiti e ripristinati
dagli editor, con lo stesso aspetto nella lettura di sito e app. Il salvataggio
asincrono conserva editor e oggetti File se upload o server falliscono, mostrando
la causa. Creazione/modifica e media sono in transazione: un file rifiutato non
lascia un articolo parzialmente pubblicato e non modifica la versione salvata.

## Notifiche di associazione

Accetta/Rifiuta usano lo stesso servizio di revisione con autenticazione admin,
controllo dello stato pending e protezione delle associazioni esistenti. Il tap
sul corpo apre la gestione; le azioni mostrano esito o errore. Il web worker
recupera un CSRF valido dalla sessione prima di eseguire una scelta esplicita.

Android ha una scheda di notifica con PendingIntent immutabili e nonce privato
per evitare intent di approvazione contraffatti. Apple registra una categoria
con azioni che richiedono lo sblocco. La richiesta è legata al destinatario e
viene verificata nuovamente dal server. Le nuove azioni richiedono una nuova
build dell'app; le versioni già installate mantengono le notifiche normali.
`mobile_push_devices.request_actions` viene aggiunta in modo compatibile e
abilitata solo dai client che dichiarano il supporto. Restano da provare le
notifiche su dispositivi fisici Android/iOS prima della distribuzione negli store;
la compilazione Android viene verificata localmente, Xcode non è disponibile qui.

## Matchday

Per ogni Gold/Silver/Bronzo presente nella settimana selezionata viene aggiunto
un PNG del tabellone, con scudetti, risultati e segnaposto. La pagina e il modulo
nativo condividono gli stessi canvas, pulsanti di download e condivisione.
Il generatore legge la stessa proiezione delle coppe senza creare partite.
