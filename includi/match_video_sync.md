# Sincronizzazione dei link video

Aprire **Dashboard amministratore → Video delle partite → Sincronizza link**.

1. Scegliere le date di pubblicazione dei contenuti e Instagram, YouTube o entrambe.
2. Premere **Cerca video e Reel**: vengono letti i contenuti, cercate le gare e associati automaticamente i link delle corrispondenze uniche senza un link già presente.
3. Controllare il riepilogo. Nei casi dubbi scegliere la gara o un solo video per gara/piattaforma e premere **Salva i link selezionati**.

## Regola di abbinamento

Le squadre e il punteggio identificano la partita nel giorno di pubblicazione del video oppure nel giorno precedente, in Europe/Rome. Il nome del torneo e la giornata nella descrizione non sono vincolanti: il torneo corretto viene ricavato dalla partita nel database.

La query del database limita le gare all'intervallo di pubblicazione selezionato, includendo il giorno precedente alla data iniziale. Un indice in memoria confronta ogni contenuto solo con le gare dei suoi due giorni: non vengono eseguite migliaia di query, una per ciascun Reel.

- La partita deve essere conclusa (`giocata=1`) e avere entrambi i punteggi.
- È ammesso l'ordine invertito delle squadre con il risultato invertito.
- Accenti, maiuscole/minuscole, sigle societarie comuni e l'equivalenza confermata Barcelona/Barcellona vengono normalizzati.
- Il link esistente della stessa piattaforma viene sempre conservato. Instagram e YouTube hanno campi distinti.
- Più gare compatibili nei due giorni, oppure più video della stessa piattaforma per la stessa gara, richiedono una scelta manuale.
- Una pubblicazione oltre il giorno successivo alla partita non viene collegata con questa regola.

## Descrizioni

Sono riconosciuti risultati come:

```text
BRASILERAO | GIORNATA 1 | CEARA 5 - 3 MIRASSOL #highlightstorneioldschool

CHAMPIONS LEAGUE 2
BARCELONA-ARSENAL 7-5

🏆CHAMPIONS LEAGUE | Napoli-Sporting Lisbona 5-7

🇮🇹🏆COPPA ITALIA | Modena-Sampdoria 6-4

LA LIGA CALCIO A 8 🇪🇸
RAYO VALLECANO - BETIS SIVIGLIA 9-2...

Napoli-Juve Stabia 2-4
```

Il torneo e la giornata possono mancare o essere scritti diversamente. Sono comunque necessari nomi delle squadre e risultato leggibili; titoli generici come “Grande punizione” non bastano. Per YouTube il risultato viene cercato anche nella descrizione. Se titolo e descrizione riportano risultati diversi, il video non viene associato.

Le copertine disponibili dalle API vengono mostrate nei risultati per la verifica visiva, con caricamento lazy. Non vengono lette tramite OCR e non richiedono una chiamata API separata per ogni Reel.

## Volume e salvataggio

La dashboard effettua al massimo una chiamata social per richiesta HTTP, con avanzamento visibile e timeout API di 15 secondi. Instagram richiede fino a 50 media con didascalia per pagina; se Meta richiede meno dati, il blocco viene ridotto. Gli errori temporanei vengono ritentati fino a tre volte preservando lo stato.

La lettura consente fino a 1000 pagine per piattaforma. Un elenco incompleto non produce abbinamenti dalla piattaforma coinvolta. Il salvataggio automatico procede in blocchi da 50, ricontrollando le partite in una transazione e preservando i link inseriti da altri operatori. I risultati e i salvataggi manuali sono divisi in pagine da 100 per non superare i limiti PHP dei campi POST.

Le anteprime e lo stato della ricerca sono conservati nella sessione amministratore. Non è stato configurato un nuovo cron. Lo script esistente `api/script/sync_instagram_match_links.php` usa la stessa finestra di due giorni; `--dry-run` cerca senza salvare.

## Configurazione e distribuzione

- Instagram: collegamento tramite `/instagram/login.php`, memoria privata dei token e impostazioni `INSTAGRAM_ACCESS_TOKEN`, `INSTAGRAM_USER_ID`, `INSTAGRAM_GRAPH_API_VERSION`.
- YouTube: `YOUTUBE_API_KEY` e `YOUTUBE_CHANNEL_ID`. Sono letti solo i video pubblici.
- Chiavi e token rimangono sul server; la pagina richiede accesso amministratore e CSRF.
- Nessuna modifica allo schema: solo `partite.link_instagram` e `partite.link_youtube`.

Caricare insieme questi file per aggiornare la regola e il salvataggio automatico:

```text
includi/match_video_sync.php
includi/match_video_sync_job.php
api/sincronizza_video_partite.php
api/script/sync_instagram_match_links.php
```

La pagina richiede inoltre il JavaScript già introdotto `api/sincronizza_video_partite.js` (versione 3).

## Verifiche

- `php tests/match_video_date_test.php`: giorno del video e precedente, cambio mese, descrizioni prive di torneo, squadre invertite, punteggi, alias, link già presenti e ambiguità.
- `php tests/match_video_sync_job_test.php`: 2400 Reel, 48 chiamate simulate da 50 contenuti, 2400 abbinamenti, stato serializzato, retry, copertine e isolamento degli errori.
- `php tests/match_video_sync_test.php`: parser, provider e verifiche del vecchio matcher mantenuto separatamente per regressione.
- `node tests/match_video_sync_frontend_test.js` e `node tests/match_video_sync_browser_test.js`: flusso del browser, CSRF, endpoint e recupero dagli errori.

In locale il collegamento Instagram e il database di produzione non sono accessibili. I test verificano la logica con fixture; il salvataggio sul database reale rimane da verificare sull'ambiente configurato.
