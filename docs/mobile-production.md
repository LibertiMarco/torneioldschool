# Collegamento dell'app al sito reale

Ambiente scelto dall'utente: `https://torneioldschool.it`.

La build Android di prova usa questo indirizzo. Il 10 ottobre 2026 il sito risponde HTTP 200, mentre l'endpoint nuovo `/api/mobile/v1/tournaments.php?section=calcio` risponde HTTP 404: i file mobile non sono ancora pubblicati.

## Contenuto del pacchetto backend

- `api/mobile/v1/`: nuovi endpoint JSON e autorizzazione mobile.
- `includi/mobile_auth.php`: servizio condiviso per le sessioni mobile.
- `includi/mobile_config.php` e `admin_mobile.php`: attivazione e disattivazione dal pannello admin.
- `migrations/mobile_auth.sql`: creazione aggiuntiva di tre tabelle.
- `migrations/.htaccess`: impedisce accesso HTTP alla cartella delle migrazioni.

Il pacchetto non contiene SDK, build Flutter, credenziali, `env.local.php`, backup o modifiche alle pagine esistenti. L'endpoint admin disponibile è soltanto la consultazione paginata degli utenti: non ci sono ancora operazioni admin di modifica.

## Preparazione sul server

Con il deploy automatico da `master`, dopo il push aprire `https://torneioldschool.it/admin_mobile.php` con un account admin e scegliere **Prepara e attiva accesso mobile**. La pagina crea soltanto le tre tabelle mobile, verifica le colonne necessarie e salva l'attivazione nella directory runtime già utilizzata dal sito. Non sono richieste nuove credenziali in chat. Se `MOBILE_API_ENABLED` è già impostata sul server, la variabile ha precedenza e l'attivazione deve essere gestita tramite quella configurazione.

In alternativa è possibile effettuare i passaggi manuali seguenti.

1. Verificare PHP 8.1 o superiore, estensione PDO MySQL, HTTPS e disponibilità dei file già presenti `includi/env_loader.php`, `includi/security.php`, `includi/user_features.php`.
2. Effettuare il normale backup del database prima del rilascio.
3. Caricare il contenuto del pacchetto nella stessa radice del sito PHP, conservando i percorsi. Non caricare la cartella `.mobile-tools` o il progetto `mobile` sul server web.
4. Eseguire `migrations/mobile_auth.sql` nel database già usato dal sito, attraverso phpMyAdmin o il processo di migrazione dell'hosting. Contiene soltanto `CREATE TABLE IF NOT EXISTS`; non cancellare tabelle. Non eseguire `database_schema.sql`.
5. Verificare che `utenti` abbia `email_verificata`, `feature_flags`, `avatar` e che `tornei` abbia `sezione`. Le nuove API non aggiungono automaticamente queste colonne esistenti.
6. Impostare `MOBILE_API_ENABLED` a `1` nella configurazione PHP già caricata da `includi/env_loader.php` (ad esempio una nuova voce nell'array restituito da `includi/env.local.php`). Conservare le altre voci e non sostituire il file con quello del PC.
7. Verificare che l'hosting inoltri l'header `Authorization: Bearer ...` a PHP. La soluzione dipende da Apache/FastCGI/Nginx: non introdurre regole CORS permissive.

Non impostare `MOBILE_ALLOW_LOCAL_HTTP` in produzione. L'app richiede HTTPS nelle build release. L'APK disponibile è una build debug per prove; non è un pacchetto firmato per Google Play.

## Controlli dopo il caricamento

1. `GET https://torneioldschool.it/api/mobile/v1/status.php`: deve restituire HTTP 200 con `ready: true`.
2. `GET https://torneioldschool.it/api/mobile/v1/tournaments.php?section=calcio`: deve restituire l'elenco JSON dei tornei reali. La sezione `esport` è una selezione esplicita.
3. `GET https://torneioldschool.it/api/mobile/v1/me.php` senza credenziali: deve restituire HTTP 401.
4. Installare l'APK su un telefono Android e provare tornei, classifiche, partite e login con un account esistente.
5. Provare la consultazione utenti con un admin. Un account normale o grafico deve ricevere HTTP 403 dall'endpoint admin anche inviando manualmente una richiesta autenticata.
6. Verificare logout, scadenza dell'access token e cambio ruolo senza effettuare modifiche ai dati sportivi per la sola prova.

Codici attesi prima dell'attivazione: 404 se i file non sono caricati; 503 con `unavailable` se `MOBILE_API_ENABLED` non è `1`; 503 con `server_error` se schema/connessione non sono disponibili.

## Disattivazione

Per disattivare l'accesso mobile usare **Disattiva accesso mobile** nella pagina admin. La variabile server `MOBILE_API_ENABLED=0` forza comunque la disattivazione e prevale sull'impostazione del pannello. Se si rimuove la variabile viene nuovamente letta l'impostazione runtime salvata dal pannello. Non è necessario eliminare tabelle o account per disattivare il client mobile.

## Stato della verifica

Test PHP su fixture SQLite e test Flutter superati. Compilazione Android debug verificata. La pipeline iOS è predisposta ma non eseguita. Il rilascio backend sul sito reale, il login completo da dispositivo e la compilazione iOS devono ancora essere verificati. L'app completa richiede gli altri moduli elencati in `app-mobile.md`.
