# Sincronizzazione dei link video

Aprire **Dashboard amministratore → Video delle partite → Sincronizza link**, oppure `/api/sincronizza_video_partite.php`. La pagina usa il controllo di accesso amministratore e CSRF del sito.

1. Scegliere le date di **pubblicazione** dei contenuti e Instagram, YouTube o entrambe le piattaforme.
2. Avviare la ricerca: non salva link nelle partite. Per Instagram può rinnovare il token con il sistema già esistente.
3. Controllare i risultati, scegliere la gara per i casi ambigui e selezionare un solo contenuto per gara/piattaforma.
4. Salvare i link selezionati. Una transazione ricontrolla le partite e conserva i link già presenti, anche se aggiunti da un altro operatore dopo la ricerca.

## Formato riconosciuto

```text
BRASILERAO | GIORNATA 1 | CEARA 5 - 3 MIRASSOL #highlightstorneioldschool #calcioa6 #torneioldschool
```

Su YouTube il titolo può essere lo stesso senza hashtag. Se il titolo non contiene il formato, viene cercato nella descrizione. Sono riconosciuti accenti, maiuscole/minuscole, sigle societarie comuni, trattini dei risultati e l'ordine invertito delle squadre con il punteggio corrispondente. Sono supportati anche i turni eliminatori (`FINALE`, `SEMIFINALE`, ecc.). Un titolo e una descrizione che riportano gare diverse restano da correggere.

L'abbinamento richiede torneo, giornata/turno, entrambe le squadre e risultato di una gara terminata. La gara può essere stata giocata prima della pubblicazione. Se più edizioni del torneo contengono la stessa combinazione, il sistema non sceglie arbitrariamente: propone le gare compatibili. Contenuti generici come “Grande punizione” non vengono collegati senza informazioni sufficienti.

## Configurazione

- Instagram: riutilizza il collegamento in `/instagram/login.php`, la memoria privata dei token e le impostazioni `INSTAGRAM_ACCESS_TOKEN`, `INSTAGRAM_USER_ID`, `INSTAGRAM_GRAPH_API_VERSION` già previste. Il profilo deve essere accessibile attraverso l'integrazione professionale configurata nell'app Meta. Recupera esclusivamente i media identificati come Reel.
- YouTube: riutilizza `YOUTUBE_API_KEY` e `YOUTUBE_CHANNEL_ID` già presenti per le statistiche social. Il canale può essere indicato come ID `UC…`, `@handle`, URL `/channel/…`, URL `/@…` o username `/user/…`. Sono letti i video pubblici usando la playlist dei caricamenti e le [API ufficiali Google](https://developers.google.com/youtube/v3/guides/implementation/videos).
- Le API vengono chiamate dal server; chiavi e token non sono inviati al browser. Gli errori rimuovono le credenziali dalle risposte.
- Nessuna modifica allo schema: vengono aggiornati solamente `partite.link_instagram` e `partite.link_youtube`. Le anteprime durano 30 minuti nella sessione amministratore.
- Il limite di lettura è 100 pagine per piattaforma. Se la paginazione non è completa, la piattaforma segnala un errore e non prepara link; non vengono considerati sicuri abbinamenti ricavati da un elenco parziale.

Lo script precedente `/api/script/sync_instagram_match_links.php` continua a funzionare da CLI e da web. Per il formato strutturato abbina anche gare precedenti al giorno selezionato; per le vecchie didascalie libere mantiene il confronto sulle gare del medesimo giorno. Esempio di prova senza aggiornare link:

```text
php api/script/sync_instagram_match_links.php --date=2026-10-09 --dry-run
```

Non è stato configurato un nuovo cron. L'esecuzione dalla dashboard avvia la lettura quando lo staff preme “Cerca video e Reel”.

## Verifiche

`php tests/match_video_sync_test.php` verifica il testo fornito, pubblicazioni ritardate, accenti, giornata, risultato, turni, doppioni, ambiguità, conservazione dei link e lettura YouTube con risposte simulate, inclusi paginazione, privacy e fuso Europe/Rome. Non modifica il database e non pubblica contenuti.

Durante l'implementazione la configurazione YouTube locale ha risposto con il canale TORNEI OLD SCHOOL. In questo ambiente non era presente un collegamento Instagram utilizzabile e la connessione al database era rifiutata; lettura Instagram e salvataggio sul database reale rimangono da verificare sull'ambiente configurato.
