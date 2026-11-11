# Caricamento della Home mobile

Calendario personale, preferiti e classifiche totali mostrano l'ultimo risultato
disponibile mentre richiedono dati aggiornati. Le richieste contemporanee per la
stessa chiave condividono una sola chiamata. Il primo caricamento senza cache
mantiene gli indicatori e i controlli di errore esistenti.

La cache dell'account usa lo stesso archivio sicuro della sessione, con chiavi
separate per server e identità. Conserva al massimo 40 risultati e ignora le
istantanee su disco più vecchie di 24 ore. Il logout svuota memoria e archivio;
risposte di richieste precedenti al logout non possono riscrivere la cache.
La demo usa solo memoria.

Si aggiorna all'apertura, al ritorno in primo piano, al ritorno da un'altra
schermata e ogni minuto quando la schermata è attiva. Il refresh manuale della
Home e le modifiche ai preferiti avviano una nuova verifica. Un errore di rete
mantiene visibili i dati salvati, con un tooltip che segnala il mancato aggiornamento.

Il calendario usa `player_stats.php?section=calcio&view=upcoming`: risolve sempre
il giocatore dalla sessione e restituisce giocatore e partite da disputare,
saltando calcoli di totali, classifiche, statistiche delle squadre e premi.
Le richieste senza `view` mantengono il contratto precedente.

Pubblicare prima `includi/mobile_player_stats.php`, quindi distribuire la nuova
build Android/iOS. Il backend aggiornato resta compatibile con l'app precedente.
