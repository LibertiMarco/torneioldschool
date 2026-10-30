# Record e statistiche portieri

I record sono ora **generali**: tutti i tornei e formati calcistici vengono
confrontati in un'unica tabella, senza filtri e senza divisioni per categoria.
Anche i risultati senza categoria esplicita partecipano al confronto.
Esport, partite non concluse, annullate e tavolino restano esclusi.
Le statistiche dei portieri mantengono i propri criteri e filtri.

Record TOS è una **tabella sotto le classifiche totali di marcatori e presenze**,
nella home e nella classifica completa del sito e dell'app. Non è una pagina,
una voce di navigazione o un tab aggiuntivo. Il componente web è
`includi/records_table.php` / `records-table.js`; quello Flutter è `RecordsTable`.

Il servizio `api/records.php?scope=records` calcola automaticamente i sette record
con `includi/records_automatic.php`, riutilizzando il motore condiviso. Le partite
devono avere `giocata=1` e punteggi disponibili. Il confronto unisce tutti i tornei
e formati. Le marcature vengono riscontrate con il punteggio di entrambe le
squadre, inclusi gli autogol; tabellini incoerenti o con squadre ambigue non entrano
nei record individuali. Nessuna attestazione nuova, backfill o migrazione richiesta
per i record: il percorso è in sola lettura, anche senza la tabella verifiche.
Correzioni ed eliminazioni si riflettono alla lettura successiva. Annullate e
tavolino sono esclusi quando segnalati nei dati; vecchi esiti speciali non registrati
non sono riconoscibili. I rigori finali sono separati e non vengono sommati.

Test automatici: `tests/records_automatic_test.php`, `tests/records_database_test.php`
(MySQL isolato), `tests/records_table_test.js` (jsdom),
`mobile/test/records_table_test.dart` (320px, filtri e pari merito).
Le sezioni successive descrivono il flusso di attestazione, che rimane necessario
per le statistiche dei portieri: le attribuzioni individuali non si deducono dal
ruolo anagrafico o dalla convocazione.

## Database e rilascio

Il backend ora prepara automaticamente `match_record_verification` se manca,
come gli altri moduli del progetto. Adatta il tipo dell'identificativo al
database esistente, anche con `partite.id` signed. Non modifica le tabelle
storiche. L'utente MySQL deve poter creare la nuova tabella; in alternativa
è disponibile `migrations/records_goalkeepers.sql` per lo schema unsigned.
Non eseguire `database_schema.sql` su un database esistente:
è lo schema completo per nuove installazioni e contiene DROP.
La migrazione crea soltanto `match_record_verification`, con cancellazione
in cascata dalla partita. Non modifica risultati, presenze o marcature storiche.

Il database configurato non era raggiungibile. La preparazione automatica,
la persistenza, i calcoli e il cascade sono stati verificati con un'istanza
MariaDB isolata sulla porta 3319, usando sia identificativi signed sia unsigned.
Il test `tests/records_database_test.php` crea e rimuove un database temporaneo;
non usa la configurazione o i dati del sito. È verificato anche lo schema
storico senza `squadra_id`/`autogol`: i campi restano sconosciuti, senza deduzioni.

Distribuire i file PHP/JS e ricompilare Flutter per Android/iOS. Per aggiornare
la preview compilare il web con `TOS_WEB_PREVIEW=true` e base href
`/app-preview/build/`, come descritto in `scripts/build_mobile_preview.sh`.

## Flusso staff

1. Salvare normalmente distinta e risultato della partita conclusa.
2. Aprire «Verifica Record e presenze effettive in porta» nel tabellino.
3. Specificare il formato (Calcio a 5/6 o altro formato esplicito), uniforme
   nel torneo; non viene dedotto dalla categoria o dal nome della competizione.
4. Confermare il risultato **sul campo**, inclusi i supplementari ma senza
   i rigori finali, oppure indicare annullata/a tavolino.
5. Attestare separatamente la completezza delle marcature e dei portieri.
   I gol, inclusi gli autogol a favore dell'avversario, devono coincidere
   con il risultato. Per i portieri selezionare chi ha effettivamente giocato
   in porta, specificando i gol subiti (anche zero) e l'intera partita in porta.
   Occorre completare entrambe le squadre. Più portieri possono avere zero
   gol subiti, ma nessuno può dichiarare l'intera partita se c'è alternanza.
6. Salvare la verifica. I permessi restano admin/sysadmin del tabellino;
   POST protetti da token di sessione e controllo origine. Sono registrati
   verificatore e data dell'ultima attestazione.

Ogni lettura ricalcola dai dati correnti. Non esistono cache da sincronizzare
o formule Flutter. Le impronte del risultato e della distinta invalidano
automaticamente le attestazioni dopo correzioni, eliminazioni di righe o
cambi di stato. Dopo una correzione occorre salvare nuovamente la verifica:
fino a quel momento i dati corretti non producono record non verificati.
La modifica di una marcatura invalida anche i portieri per impedire di
riutilizzare attribuzioni subite non più coerenti. I nomi dei giocatori
fanno parte dell'impronta del tabellino: rinominarli richiede una nuova verifica.

## Limiti e significato degli zeri

Nel profilo personale le statistiche portieri sono sempre sotto «Le mie
statistiche», dopo il riepilogo generale, anche per chi non ha tabellini
verificabili. In questo caso tutte e cinque le voci mostrano «—» e la causa
dei dati mancanti. Un errore di connessione ha un pulsante «Riprova» e non
viene confuso con l'assenza di dati. Lo stesso comportamento vale sul sito.

Le vecchie partite senza attestazione non entrano nei record. Quelle con
risultato verificato possono contribuire ai record di squadra anche senza
marcature complete; i due record individuali/dei marcatori richiedono anche
la distinta verificata. I gol extra manuali non collegati a partite sono esclusi.
I tornei esport sono esclusi. Non si sommano mai formati differenti.

Le statistiche portieri comprendono soltanto presenze effettive in porta con
attribuzione completa e verificata. Presenze, gol, media e percentuale usano
lo stesso insieme di partite. Una convocazione o un ruolo anagrafico non genera
una presenza. Con zero presenze verificabili media e percentuale sono null,
visualizzate con «—». Lo zero nei gol subiti è invece ammesso soltanto come
valore esplicitamente registrato. La copertura delle partite complete è visibile;
non rappresenta il numero effettivo di partite giocate dal portiere in carriera.

## Verifica riproducibile

- `php tests/records_test.php`: parità, filtri, correzioni, eliminazione,
  incompleti, autogol, pareggi, alternanza, dati mancanti, zero presenze,
  annullate e tavolino.
- `php tests/staff_engine_test.php`: autorizzazioni del controller staff.
- `flutter analyze --no-pub`.
- `flutter test --no-pub test/records_page_test.dart test/native_staff_tool_test.dart test/staff_dashboard_test.dart`:
  consumo dei valori del servizio, filtri, stati vuoti e regressione staff.
- Lint PHP e sintassi JS sui file modificati.
- `tests/records_web_test.js` e `tests/staff_engine_test.js` con Node moderno
  e jsdom: espansione dei pari merito, filtri, link, valori delle statistiche,
  stati vuoti, presenza del nuovo modulo nel bridge e POST con CSRF preservato.

Verifiche eseguite: test PHP superati, analisi Flutter senza segnalazioni,
55 test Flutter selezionati superati, oltre alla verifica aggiuntiva
del layout a 320px con testo ingrandito (anch'essa superata);
test JS superati con il runtime Node del workspace.
Build web release completata e copiata in `app-preview/build`.
Nessuna distribuzione remota o build firmata Android/iOS eseguita.

Da verificare sul server di produzione dopo il rilascio: preparazione dello schema,
salvataggio e nuova attestazione dopo correzione, lettura degli stessi valori
da sito e dispositivo fisico, eliminazione partita e relativo cascade.

Correzione della scheda personale: superati 15 test Flutter su statistiche e
profilo, inclusi ordine sotto «Le mie statistiche», cinque voci sempre presenti,
recupero dopo errore, valori reali del servizio e layout a 320/390/430px.
