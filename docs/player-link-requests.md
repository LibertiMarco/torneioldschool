# Richieste di collegamento giocatore

Il Profilo dell'app propone la ricerca dei giocatori liberi agli account senza
associazione. L'utente seleziona il proprio giocatore, conferma la scelta e invia
la richiesta; l'associazione non cambia finché un admin non la accetta.

Gli admin e sysadmin trovano le richieste in Gestione → Account giocatore e nel
pannello web `api/gestione_account_giocatore.php`. Possono accettare oppure
rifiutare indicando un motivo facoltativo. L'utente vede l'esito nel Profilo;
dopo l'approvazione, Verifica associazione apre le statistiche personali.

La migrazione additiva `migrations/player_link_requests.sql` crea la tabella delle
richieste. Il servizio la inizializza al primo utilizzo se manca, usando i
permessi CREATE del database. Se l'account SQL non dispone di tali permessi,
applicare la migrazione durante il deploy. Non modifica i collegamenti esistenti.

Le richieste conservano ID distinti e storico, una sola richiesta in attesa per
account, admin revisore, data e motivo. Approvazione e associazione sono atomiche:
si ricontrollano ruolo admin, account e giocatore prima di salvare. Le richieste
concorrenti per il giocatore assegnato vengono rifiutate con un motivo esplicito.
Le ricerche non espongono email e le scritture browser richiedono il CSRF della
sessione. L'API nativa usa l'account autenticato, senza accettare ID di altri utenti.

Verifiche:

- `php tests/player_link_requests_test.php` (database SQLite in memoria).
- `NODE_PATH=.mobile-tools/rules-tests/node_modules node tests/player_link_admin_bridge_test.js`.
- `flutter test test/player_link_request_test.dart test/personal_player_test.dart test/browser_auth_test.dart`.

Non sono state inviate richieste o cambiati collegamenti sul database reale durante i test.
