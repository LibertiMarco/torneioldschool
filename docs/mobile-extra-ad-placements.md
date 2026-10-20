# Spazi pubblicitari aggiuntivi - 20 ottobre 2026

Aggiunti due posizionamenti del banner adattivo gia esistente nella pagina torneo:

- Calendario: dopo tutte le partite visibili, solo se sono almeno tre.
- Rose: dopo tutte le squadre, solo se sono almeno quattro.

Un solo nuovo banner per scheda, senza inserirlo fra filtri e risultati o dentro
una scheda partita. Nessun interstitial o nuovo annuncio nelle operazioni staff.
Le regole esistenti del componente rimangono applicate: consenso UMP, rimozione
quando la rotta e coperta, nessuno spazio vuoto se non caricato. Nessuna nuova
unita pubblicitaria o modifica degli ID e dell'abilitazione monetizzazione.
Gli annunci non sono mostrati nella preview web/demo: servono nuove build native
per controllarne il caricamento e la resa effettiva su Android/iOS.

Verifiche: suite `ads_test.dart` e `tournament_page_test.dart`, build web release.
Da provare sui dispositivi: calendario con meno/almeno tre incontri e rose con
meno/almeno quattro squadre; consenso negato, caricamento fallito, apertura di una
partita e ritorno; orientamento e leggibilita. Non interagire con annunci reali
per eseguire i test.
