# Record e statistiche portieri

Implementazione: PHP condiviso in `includi/records.php`, API di lettura
`api/records.php`, pagina `record.php`, schermate e schede Flutter
`mobile/lib/records_page.dart`. Il profilo personale web e i profili Flutter
mostrano le statistiche; il pulsante Record è nella navigazione del sito e
nella barra dell'app. Il bridge staff include anche il nuovo modulo.

## Database e rilascio

Applicare **una volta** `migrations/records_goalkeepers.sql` sul database
configurato. Non eseguire `database_schema.sql` su un database esistente:
è lo schema completo per nuove installazioni e contiene DROP.
La migrazione crea soltanto `match_record_verification`, con cancellazione
in cascata dalla partita. Non modifica risultati, presenze o marcature storiche.

Il database configurato non era raggiungibile durante questa implementazione
(connessione rifiutata). L'analisi si è basata sullo schema SQL versionato e
sui controller; migrazione e persistenza reale restano da verificare sul server.
Non è stato eseguito alcun backfill o accesso di scrittura al database.

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

Da verificare su ambiente con MySQL disponibile: import della migrazione,
salvataggio e nuova attestazione dopo correzione, lettura degli stessi valori
da sito e dispositivo fisico, eliminazione partita e relativo cascade.
