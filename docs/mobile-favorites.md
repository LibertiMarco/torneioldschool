# Preferiti — step 2

La Home autenticata mostra «I tuoi preferiti» dopo la prossima partita e prima
delle classifiche. Mostra fino a tre tornei o squadre, con logo, nome e accesso
al relativo dettaglio; oltre tre voci compare «Mostra tutti». Gli ospiti non
effettuano richieste personali e non vedono il riquadro.

«Gestisci» apre l'elenco completo e la ricerca dei tornei. Espandendo un torneo
si può seguirlo interamente oppure scegliere le singole squadre; le modifiche
si applicano solo con «Salva» o «Rimuovi». La Home si ricarica al ritorno dalla
gestione e dai dettagli, oltre che con il normale trascinamento per aggiornare.

La pagina di gestione mostra solo i tornei con stato «in corso» o «programmato»,
sia nei preferiti elencati sia nella ricerca/selezione. I salvataggi relativi a
tornei terminati non vengono cancellati e restano accessibili dalla Home.

Il selettore delle squadre riceve ora anche `team_logos` dal servizio follow,
associati ai nomi e filtrati per torneo. Gli scudetti vengono quindi caricati
anche nella gestione preferiti, senza dover aprire prima il dettaglio torneo;
le immagini assenti o non raggiungibili mantengono il simbolo neutro.

I preferiti riutilizzano le scelte «Segui» già salvate in `seguiti`, incluse quelle
effettuate sul sito. Sono quindi associati all'account e condivisi fra Android,
iOS e preview. Non sono segnalibri separati dalle preferenze sui risultati:
aggiungere o rimuovere una scelta modifica anche i relativi aggiornamenti.
Le notifiche del telefono mantengono il consenso separato già esistente.
La pagina spiega questa relazione prima della selezione.

La lettura autenticata `GET api/mobile/v1/follow.php?list=1&section=calcio`
riutilizza il servizio `mobile_follow_handle`; nella preview il mapping esistente
punta ad `app-preview/api.php?action=follow`. I salvataggi mantengono i controlli
esistenti: bearer nativo, sessione staff/CSRF nella preview e verifica di torneo
e squadre sul server. Non sono necessarie nuove tabelle o migrazioni. L'elenco
filtra per account/sezione, ignora voci eliminate e deduplica i vecchi salvataggi
che comprendevano sia un torneo sia tutte le sue squadre. La sola lettura non
modifica le iscrizioni.

Verifiche: 36 test Flutter su preferiti, notifiche torneo, Home, Profilo,
autenticazione browser e demo; analisi Flutter senza problemi. Passano
`tests/mobile_follow_list_test.php`, `tests/mobile_follow_test.php` e
`tests/app_preview_test.php` con fixture SQLite. Screenshot locale controllato
in `mobile/build/home-review/favorites.png`; layout verificato anche a 320 px
con testo ingrandito nei due temi. I test non salvano dati di produzione.

La preview richiede la build web ricompilata insieme al servizio PHP aggiornato.
Android e iOS usano lo stesso codice Flutter ma richiedono nuove build native
per rendere disponibili questi cambiamenti nelle app installate.
