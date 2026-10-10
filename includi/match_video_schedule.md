# Sincronizzazione automatica dei video alle 13

Lo script `api/script/sync_match_video_daily.php` associa i link Instagram e YouTube alle gare concluse nelle 24 ore precedenti alle **13:00 Europe/Rome**. Usa `data_partita` e `ora_partita`; una gara priva di un orario valido viene esclusa. La finestra include l'istante iniziale ed esclude quello finale. Durante i cambi dell'ora legale rimane lunga esattamente 24 ore.

I contenuti vengono cercati nei giorni di pubblicazione che coprono la finestra. Si applicano le stesse regole della pagina amministrativa: nomi delle due squadre normalizzati, giorno del video o precedente, punteggio non vincolante, nessun filtro sulla durata. Più gare o più video compatibili richiedono la scelta manuale. I link già presenti vengono conservati. Nessuna richiesta social se tutte le gare hanno già entrambi i link.

## File da caricare

Oltre ai file della sincronizzazione già presenti e aggiornati, caricare:

```text
api/script/sync_match_video_daily.php
includi/match_video_schedule.php
includi/match_video_sync.php
```

Lo script richiede le credenziali social e database già configurate sul server. Il cron deve usare lo stesso utente PHP e la stessa directory runtime privata del sito (`TOS_RUNTIME_DIR`, se impostata). Il runtime contiene `match-video-daily/state.json` e `run.lock`: avanzamento, riepilogo e lock contro esecuzioni contemporanee. Solo un'esecuzione riuscita per giornata; errori consentono fino a tre tentativi, distanziati almeno cinque minuti.

## Opzione 1: cron con comando PHP

Nel pannello dell'hosting cercare “Cron”, “Attività pianificate” o “Scheduled tasks”. Se permette un comando, configurare una chiamata ogni cinque minuti:

```cron
*/5 * * * * /usr/bin/php /PERCORSO_ASSOLUTO_DEL_SITO/api/script/sync_match_video_daily.php --scheduled
```

Sostituire il binario PHP e il percorso con quelli indicati dall'hosting. Il cron richiama il controllo ogni cinque minuti, ma la ricerca giornaliera parte alle 13:00 italiane. I richiami successivi saltano la giornata già completata oppure riprendono un'esecuzione interrotta. Non dipende dal fuso orario configurato per il cron; PHP calcola l'orario di Roma. Un'esecuzione mancata viene recuperata al primo richiamo successivo alle 13.

Per controllare senza salvare:

```sh
php api/script/sync_match_video_daily.php --dry-run
```

Senza opzioni, lo script sincronizza immediatamente le ultime 24 ore rispetto all'ora attuale. `--help` mostra le opzioni.

## Opzione 2: cron che richiama un URL

Generare un token casuale di almeno 32 caratteri, ad esempio con:

```sh
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Aggiungere nell'array restituito da `includi/env.local.php` sul server:

```php
'MATCH_VIDEO_CRON_TOKEN' => 'TOKEN_CASUALE_GENERATO',
```

Configurare il servizio per chiamare **ogni minuto**:

```text
https://torneioldschool.it/api/script/sync_match_video_daily.php?token=TOKEN_CASUALE_GENERATO
```

Se il servizio permette intestazioni HTTP, preferire l'URL senza parametro `token` e l'intestazione `Authorization: Bearer TOKEN_CASUALE_GENERATO`. Il token protegge l'esecuzione senza una sessione amministratore.

Prima delle 13 e dopo il completamento, il richiamo non cerca video. Ogni chiamata web esegue un breve blocco e salva l'avanzamento; i richiami seguenti lo riprendono. **Un solo richiamo alle 13 potrebbe non bastare** per completare la ricerca. Ogni chiamata social ha timeout di 15 secondi; lo script avvia nuovi passi solo nei primi cinque secondi della chiamata web.

La risposta JSON riporta `running`, `done`, `failed`, `busy` oppure `idle`, con conteggi e intervallo. Non contiene credenziali. In caso di errore leggere il riepilogo nel runtime o l'output del cron.

## Attivazione

Il codice da solo non registra attività nel pannello dell'hosting: serve configurare una delle due opzioni sopra. Nessuna attività locale Windows sostituisce il cron del server di produzione.

Test locali: `php tests/match_video_schedule_test.php` e `node tests/match_video_schedule_http_test.js`, oltre ai test della sincronizzazione esistente. Le chiamate ai social e il salvataggio nel database di produzione richiedono la verifica sull'hosting configurato.
