# Promemoria partita

Preferenza account `reminder`: `off` (default), `1h`, `2h`, `1d`.
Il giorno prima significa la stessa ora locale del giorno precedente, Europe/Rome.
Finestra di invio: cinque minuti dall'orario previsto; nessun recupero tardivo.
Destinatari: giocatori associati alle squadre e follower torneo/squadre, unione distinta.
Invia solo alle sottoscrizioni web/native esistenti tramite il servizio push comune.
Non crea notifiche nella casella del sito.

## Attivazione Plesk

Lasciare invariato il cron dei video. Aggiungere una nuova attività "Esegui script PHP",
stesso interprete e utente del cron video:

- Script: `httpdocs/api/script/send_match_reminders.php`
- Argomenti: `--scheduled`
- Pianificazione cron: `* * * * *` (ogni minuto).

Prima eseguire con `--dry-run`: legge DB e preferenze senza inviare push, creare
stato o modificare dati. Poi impostare `--scheduled` e verificare almeno due
esecuzioni automatiche riuscite e distanziate di almeno 30 secondi.
L'API espone `reminders_available` solo con due esecuzioni riuscite recenti.
Dopo tre minuti senza heartbeat la scelta scompare; le preferenze restano salvate.
Questa verifica prova l'esecuzione del worker, non la consegna su un dispositivo:
verificare successivamente un promemoria scelto da un account di prova consenziente.

Runtime privato condiviso `TOS_RUNTIME_DIR/match-reminders`: lock globale non bloccante,
heartbeat e registro per partita/account. Non cancellarlo durante deploy.
Un solo tentativo per partita/account anche se cambiano orario o preferenza.
Il registro viene salvato prima dell'invio: crash o errore di rete possono perdere
un avviso, ma non generano ritentativi duplicati. I registri vanno conservati per
garantire questa proprietà; non contengono messaggi o credenziali.
Il worker termina con errore su DB/stato non disponibili; timeout operativo 45 secondi
fra invii, mentre la durata delle richieste push dipende dai timeout del trasporto.
Non agganciare il worker al cron giornaliero video. Nessun endpoint web eseguibile.

Verifiche locali: `php tests/match_reminders_test.php`, test preferenze PHP e Flutter.
Durante i test non inviare notifiche reali. Le modifiche Android/iOS richiedono nuove build store.
