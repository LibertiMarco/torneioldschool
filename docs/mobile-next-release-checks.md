# Verifiche prima della prossima distribuzione - 20 ottobre 2026

Le modifiche Flutter recenti sono condivise fra Android e iOS; la preview non
aggiorna i binari installati. Questo elenco conserva le prove reali ancora aperte.

## Notifiche

- Verificare in Plesk il completamento del cron promemoria ogni cinque minuti,
  PHP 8.2, argomento --scheduled. Il cron video giornaliero resta separato.
- Nel Profilo scegliere e ricaricare Disattivato, 1 ora, 2 ore e giorno prima.
- Con account di prova consenzienti verificare una consegna per partita/account,
  anche se si seguono insieme squadra e torneo e si e collegati a un giocatore.
- Verificare ricezione con app chiusa e aperta su Android e iPhone; controllare
  destinazione al tocco e disattivazione. Nessun invio automatico durante i test locali.
- Verificare l'interruttore Cambi di data, orario o campo e una variazione
  autorizzata: un solo avviso con i nuovi valori, nessuna notifica iniziale.
  Non alterare partite reali solo per provare la funzione.

## Gestione e aggiornamento

- Nuova build: aprire piu voci della Gestione e verificare che non compaia
  Privacy e cookie. Sul codice corrente il filtro del bridge e testato;
  l'utente aveva osservato il problema sulla build precedente.
- Prima di alzare la build minima, distribuire davvero la nuova build e verificare
  il download con gli account tester Android/TestFlight. Il link iOS configurato
  e ancora quello del sito; manca un percorso di aggiornamento verificato.
- Verificare il blocco su avvio, ripresa e pagina interna, poi lo sblocco dopo
  aggiornamento. Non incrementare la policy per una prova pubblica.

## Annunci

- Nuovi banner a fine Calendario (almeno 3 incontri), Rose del torneo (almeno
  4 squadre), Rosa della singola squadra (almeno 4 giocatori).
- Con annunci di test verificare consenso, assenza di spazi vuoti quando non
  caricati, leggibilita su schermo stretto e rotazione, apertura del giocatore
  e ritorno. Nessun clic su annunci reali per collaudare.
- Nessun annuncio mobile visibile nella preview web; verificare nelle nuove build.

Riferimenti: match-reminders.md, match-schedule-changes.md,
aggiornamento-obbligatorio.md, mobile-extra-ad-placements.md.
