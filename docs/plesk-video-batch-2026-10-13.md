# Correzione runtime e batch video — 13 ottobre 2026

Modifiche locali sul branch master. Nessun deploy, modifica alla pianificazione Plesk o accesso al database di produzione.

## File modificati

- `includi/env_loader.php`: runtime nella subscription e controllo preventivo open_basedir, inclusi traversal e antenati symlink.
- `includi/instagram_token_store.php`: esclude le vecchie ubicazioni non consentite prima di verificarne i file; nessun token spostato o eliminato.
- `includi/match_video_schedule.php`: stessa risoluzione runtime del sito, finestra precisa ai provider e selezione dei soli servizi con link mancanti.
- `includi/match_video_sync_job.php`: ricerca YouTube delimitata, verifica dei dettagli, limiti, risposte incomplete e retry.
- `includi/match_video_sync.php`: parametri temporali e implementazione YouTube condivisa con il job; risposta HTTP limitata a 2 MiB.
- `api/script/sync_match_video_daily.php`: budget temporale CLI e attesa tra retry transitori.
- `tests/match_video_sync_test.php`, `tests/match_video_sync_job_test.php`, `tests/match_video_schedule_test.php`: aggiornamento fixture e percorsi attesi.
- `tests/match_video_daily_limits_test.php`, `tests/runtime_plesk_test.php`: nuove verifiche isolate.
- Questo documento.

`security.php` e `api_cache.php` usano già `tos_runtime_path`: log PHP, sessioni, rate limiting, cache e relativi file temporanei ereditano la correzione senza cambiare la loro logica.

## Problemi e comportamento corretto

Il default del sito risaliva fino a `/data/vhosts`, esterno alla subscription. Il cron usava invece `/data/vhosts/torneioldschool.it/private/torneioldschool-runtime`. Entrambi ora risolvono `/data/vhosts/torneioldschool.it/torneioldschool-runtime`, assumendo il progetto in `httpdocs`. `TOS_RUNTIME_DIR` mantiene la precedenza; un override esterno a open_basedir produce un errore esplicito prima delle operazioni filesystem, senza cambiare silenziosamente ubicazione di sessioni o token. Nella configurazione locale l'override non è presente; le variabili del server Plesk non sono accessibili da questo workspace.

Il cron calcolava già una finestra di 86400 secondi per le partite, ma passava ai provider solo le date, includendo intere giornate. Ora Instagram e YouTube ricevono lo stesso intervallo `[inizio, fine)`, con fine alle 13 Europe/Rome per `--scheduled`. Nei cambi DST l'intervallo resta di 24 ore effettive: l'ora locale iniziale può essere 12 o 14. Le esecuzioni manuali conservano la finestra mobile terminante all'avvio.

YouTube risolve ID UC, handle o username con `channels.list`; usa `search.list` con `channelId`, `type=video`, `publishedAfter`, `publishedBefore`, 50 risultati/pagina e ordine relevance. La documentazione avverte che altri ordini possono dare risultati incompleti con filtri temporali. La ricerca estende il limite iniziale di un secondo per includere un video pubblicato esattamente all'inizio; `videos.list` ricontrolla rigorosamente `[inizio, fine)` sul vero `snippet.publishedAt`, privacy e ID canale. Nessuna lettura della playlist storica e nessuna richiesta per singolo video.

La compatibilità dei formati di canale è verificata con fixture; l'identità del canale reale e l'accesso con la chiave server devono essere verificati dall'operatore. Non sono state usate credenziali reali per chiamare API.

Instagram mantiene caption/Reel e abbinamenti esistenti. Legge pagine da 50, include solo timestamp della finestra e interrompe quando incontra contenuti precedenti. Il comportamento dipende dall'ordinamento cronologico del feed, come nell'implementazione precedente. Le pagine sono comunque limitate e cursori mancanti/ciclici causano un errore. Una fonte interrotta non contribuisce contenuti parziali; l'altra può completare il proprio lavoro, ma il run risulta failed.

## Risorse, quota e stime

Con N caricamenti storici e R video recenti, la vecchia scansione incrementale richiedeva circa `1 + 2*ceil(N/50)` chiamate YouTube. La nuova richiede `1 + ceil(R/50) + ceil(R/50)` con risultati non vuoti, senza retry; per zero risultati bastano canale e ricerca. Il test con 53 risultati API, di cui 51 nella finestra esatta, misura 5 chiamate.

Esempio stimato: 5000 video storici e 80 recenti passano da 201 a 5 chiamate, circa -97,5%. Il job non mantiene più migliaia di ID storici; la memoria dei provider dipende dal volume recente. Non sono misurazioni di CPU/RAM o durata sul server.

Le [specifiche search.list](https://developers.google.com/youtube/v3/docs/search/list) e il [calcolatore ufficiale quota](https://developers.google.com/youtube/v3/determine_quota_cost), consultati il 13 ottobre 2026, indicano ora un bucket Search Queries di 100 chiamate/giorno, costo 1 per chiamata; gli altri endpoint hanno generalmente costo 1 per list e bucket predefinito 10000 unità/giorno. Questo differisce dal precedente modello di 100 unità per search.list: verificare le quote effettive del progetto Google Cloud. Con R=80 servono 2 chiamate nel bucket Search e 3 unità negli altri endpoint; nel precedente modello sarebbero state 203 unità. PlaylistItems costa meno nel precedente modello, ma non offre la stessa delimitazione temporale affidabile della pubblicazione: interrompere sul tempo di inserimento può perdere video pubblicati successivamente. Non viene usato come fallback storico.

Limiti giornalieri: massimo 10 pagine per fonte e 80 passi del job, compresi tentativi falliti e inizializzazione. Il limite non viene azzerato riprendendo uno stato. YouTube rifiuta di dichiarare completa una ricerca con almeno 500 ID, perché la ricerca per canale può avere quel tetto di risultati. Sono possibili fino a 500 Reel; l'adattamento della dimensione pagina può ridurre la copertura. Il raggiungimento di un limite genera failed, mai un successo parziale.

Timeout API 15 secondi, connessione 5 secondi; retry transitori massimo 3 con attese 2/4/8 secondi nel batch. Errori quota permanenti non vengono ritentati dentro la fonte. Massimo 3 tentativi giornalieri già previsto dalla pianificazione. CLI si ferma dopo circa 120 secondi e HTTP dopo circa 5, controllati tra passi: l'ultima richiesta o transazione può superare il budget. CLI esce 2 se running, 1 se failed, 0 se terminato/idle. Ripetere `--scheduled` riprende lo stato salvato sotto lock. Nessun processo resta in ciclo infinito.

Le connessioni DB vengono aperte solo per caricare le partite e per i blocchi di scrittura, poi chiuse prima delle richieste social. Restano le transazioni, `SELECT FOR UPDATE`, aggiornamenti condizionati ai link vuoti e blocchi da 50 scritture: i link esistenti sono preservati anche dopo un'interruzione tra commit e salvataggio dello stato. `flock` esclusivo non bloccante protegge il cron; stato aggiornato con rename e permessi 0600. Un vecchio stato in fase scan senza finestra esatta viene rifiutato e richiede il tentativo successivo; nessun dato è migrato automaticamente.

## Test

28/28 script `tests/*test.php` passati con PHP 8.1.9. SQLite e mbstring sono stati caricati solo sulla riga di comando, senza modificare php.ini. Prima esecuzione: 7 test del resto del sito si erano fermati per estensioni mancanti; riesecuzione completa riuscita. Sintassi verificata sui PHP modificati e `git diff --check` senza errori.

Copertura: intervallo esatto e estremi, DST marzo/ottobre, 51 video recenti su due pagine, video privati, zero video/partite, salto piattaforma già collegata, preservazione link nel piano, paginazione e cursori ciclici, errori parziali, budget richieste, retry e ripresa serializzata, 2400 Reel su 30 giorni nel flusso manuale, due processi concorrenti sul lock e successivo rilascio, subscription Plesk simulata con open_basedir attivo e override vietati senza warning.

I test di integrazione MySQL opzionali (`--db`/`--database`) non sono stati eseguiti. Le garanzie delle transazioni reali e la contesa con scritture manuali vanno confermate in staging. Nessun database di produzione è stato modificato, nessuna chiamata social reale è stata effettuata.

## Operazioni Plesk/VHosting e rischi residui

1. Verificare directory effettiva `httpdocs`, open_basedir del PHP web e CLI, `TOS_RUNTIME_DIR` nell'ambiente del cron/FPM e nell'env.local del server. Non disabilitare open_basedir. Creare la directory runtime privata della subscription, proprietà dell'utente della subscription e permessi 0700, fuori dal document root. Verificare i permessi delle sottodirectory e degli eventuali runtime già esistenti.
2. Prima dell'attivazione inventariare entrambi i vecchi runtime e l'eventuale fallback temporaneo Instagram: sessioni, token, stato cron, log e cache. Fare backup privato con permessi preservati. Scegliere un runtime consentito comune usando TOS_RUNTIME_DIR se già contiene i dati corretti. Se serve migrazione, fermare le scritture e i cron, copiare senza cancellare gli originali, verificare ownership/integrità e duplicati, poi cambiare configurazione. Le sessioni potrebbero richiedere nuovo login se la directory cambia senza migrazione. Non sovrascrivere token/stato cron più recente con un backup vecchio.
3. Verificare la pianificazione Plesk esistente, utente, binario PHP e fuso orario. Preferire una sola attività CLI giornaliera alle 13 Europe/Rome: `php /data/vhosts/torneioldschool.it/httpdocs/api/script/sync_match_video_daily.php --scheduled`, usando il binario PHP della subscription. Non è stata cambiata alcuna pianificazione. Se lo scheduler usa UTC, configurare correttamente il fuso o gestire il cambio DST; non fissare un offset UTC per tutto l'anno. Se il run esce 2, riprenderlo con lo stesso comando e verificarne la conclusione; non introdurre automaticamente un cron HTTP frequente.
4. Dopo il deploy autorizzato separatamente, verificare log privato, sessioni web, cache, token Instagram, canale ufficiale configurato, quota Google Cloud, un dry-run controllato e il report state.json. `--dry-run` non scrive link/stato giornaliero, ma può rinnovare il token Instagram; non è un test senza chiamate esterne.
5. Monitorare durata, picco RAM, limiti di pagina, uscite running/failed e quota. Search può avere ritardo di indicizzazione: un video assente dall'indice durante la finestra può restare senza link, dato che il batch successivo usa una nuova finestra. Non è garantita la completezza assoluta dell'indice YouTube; serve un recupero manuale per eventuali omissioni. Una singola esecuzione CLI sospesa non si riprende da sola: va richiamata dall'operatore con --scheduled.

### Incidente Plesk

Sono disponibili soltanto i sintomi e l'intervallo citati nella richiesta, non i log integrali né la configurazione server. I warning e la scansione storica possono aumentare I/O e consumo FPM; la coincidenza con le 13 non dimostra causalità. La pagina predefinita Plesk può indicare instradamento verso il virtual host di default: il solo codice PHP non consente di confermarlo.

Chiedere al provider, per il 13 ottobre 2026 almeno 12:50–13:25 Europe/Rome (10:50–11:25 UTC), di correlare:

- Access/error log nginx e Apache, incluso vhost di default, status e upstream, Host/SNI e indirizzi IPv4/IPv6 raggiunti dai client; verificare cosa è stato effettivamente servito durante 12:58:45–13:16:06.
- Configurazione e storico reload/restart dei virtual host Plesk, mapping dominio/subscription/document root, reverse proxy, certificati/SNI, cambi DNS e processi di rigenerazione configurazione; eventuale sospensione della subscription.
- Log PHP-FPM del pool: max_children, coda richieste, slowlog, timeout, terminazioni, restart e saturazione worker. Controllare separatamente open_basedir ed error_log effettivi web/CLI.
- Metriche CPU/RAM/I/O, spazio e inode, OOM killer, limiti CloudLinux/LVE se presenti (EP/NPROC/PMEM/IO), log kernel e interventi automatici del provider.
- Avvio/fine/esito del task delle 13, eventuali processi duplicati e retry HTTP, consumo del processo CLI/FPM e log MySQL di connessioni lente/lock. Il vuoto del log PHP da solo non dimostra che Apache/FPM fosse fermo.

Conservare evidenze e timezone dei log prima di eventuali modifiche server; confrontare il prossimo batch con le metriche dopo la correzione.
