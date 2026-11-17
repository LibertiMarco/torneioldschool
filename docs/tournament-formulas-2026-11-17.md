# Formule Brasilerao e La Liga — 17 novembre 2026

- Brasilerao: 22 squadre; posizioni 1–16 in Gold, 17–20 in Silver,
  21–22 in Bronzo.
- La Liga (Calcio a 8): 16 squadre; spareggi 1–16, 2–15, 3–14, 4–13,
  5–12, 6–11, 7–10, 8–9. Le otto vincenti accedono ai quarti Gold;
  le otto perdenti ai quarti Silver.

Le formule sono centralizzate in `includi/tournament_fixed_formulas.php`.
Le letture API e le pagine web correggono i precedenti valori DB senza
scrivere dati durante la consultazione. Il salvataggio staff del torneo
conserva la formula corretta. Le regole della fase 2 vengono aggiornate,
preservando durata, premi e calendario nelle altre sezioni.

La pagina La Liga riutilizza la pagina con spareggi di Formula 1. Anche
la rigenerazione staff mantiene questo collegamento e la selezione della
fase SPAREGGIO è disponibile nella gestione partite per entrambi i tornei.

Nell'app tutte le posizioni La Liga sono marcate SPAREGGIO. Se mancano
incontri programmati vengono mostrati gli otto incroci provvisori. Le coppe
usano gli esiti degli spareggi, inclusi i rigori, invece delle qualificazioni
dirette per posizione. Gli esiti non risolti rimangono segnaposto.
Gli incontri già programmati hanno precedenza sulle proiezioni.

Nessuna partita viene creata e nessun risultato viene modificato da queste
proiezioni. Per applicare le formule online occorre pubblicare il backend
PHP insieme alla preview aggiornata; per l'app installata serve la nuova build.
