# Modifiche app Android e iOS — 20 ottobre 2026

- Notifiche: richiesta di autorizzazione al primo avvio, anche senza login,
  dopo il consenso pubblicitario per evitare dialoghi sovrapposti. La scelta
  viene memorizzata; un rifiuto non genera nuove richieste automatiche.
  Il token viene associato soltanto a un account autenticato e con backend
  notifiche configurato. La disattivazione manuale rimane memorizzata.
- Profilo: le prossime partite erano già implementate nel commit `e0cadab`
  del 19 ottobre, con endpoint autenticato e test. Non è stato verificato lo
  stato della distribuzione di quella versione su Play Console/App Store Connect.
- Esport: selettore nascosto sia nelle schermate pubbliche sia negli strumenti
  staff. Il default rimane Calcio; dati e API Esport vengono conservati.
- Aggiornamenti obbligatori: requisito confermato per Android e iOS. Il gate
  esistente impedisce di continuare con una build precedente alla versione
  pubblicata configurata sul server. A ogni rilascio seguire
  `docs/aggiornamento-obbligatorio.md`, aggiornando separatamente la policy
  di ciascuna piattaforma soltanto quando il download è disponibile. Il link
  iOS attuale porta al sito e va sostituito con quello App Store/TestFlight
  prima di imporre l'aggiornamento. Aggiunti test su entrambe le piattaforme
  per controllo alla ripresa e impossibilità di aggirare il blocco con Indietro.
- Pubblicità: banner fra classifica e notizie nella Home; annuncio nativo ogni
  sei notizie nell'elenco completo. Rimane l'annuncio nativo già presente nella
  Home. Nessun interstitial aggiunto. Gli spazi senza annunci disponibili
  rimangono nascosti; valgono gli attuali controlli UMP.
- iOS: unità reali disponibili con build release e
  `--dart-define=TOS_ADS_MONETIZATION=true`, come per Android. Il file xcconfig
  release usa l'App ID iOS del progetto per UMP; debug usa quello sample Google.
  Senza il flag le unità rimangono di test anche nelle release.
- Gestione: il bridge scambiava il dialogo `#tos-consent-banner` del sito per
  una finestra dello strumento. Ora esclude quel contenitore dalle schermate
  native, mantenendo le vere finestre di modifica/conferma. Non viene registrata
  alcuna accettazione automatica dei cookie e il sito mantiene il proprio banner.

Le modifiche sono locali: richiedono deploy del bridge PHP/JS e nuove build
Android/iOS. Nessun caricamento sugli store eseguito. Per generare ricavi,
le build destinate al pubblico devono includere il flag monetizzazione indicato
sopra (Android richiede anche la configurazione release già prevista dal progetto).
I test locali non sostituiscono la verifica di notifiche APNs/FCM, UMP e annunci
su dispositivi fisici.

Verifiche: `flutter analyze --no-pub` senza problemi; 85 test mirati su push,
prossime partite, gestione, Home e annunci superati. Un secondo gruppo di
43 test su Profilo, Home, tema, push e annunci è passato dopo l'aggiornamento
delle aspettative sulla rimozione di Esport. Passano anche
`tests/staff_engine_test.php` e `tests/staff_engine_test.js`, quest'ultimo con
un banner cookie visibile nella fixture e conferme/modali staff ancora funzionanti.

## Primo intervento sulla Home personalizzata

La Home mostra ora «La tua prossima partita» prima delle classifiche agli utenti
con un giocatore associato. Il riquadro include squadre, torneo, data, ora e campo
se disponibile, con apertura del dettaglio partita. Usa l'endpoint autenticato
già impiegato dal Profilo e il suo ordinamento, senza ID giocatore dal client.
Gestisce calendario vuoto, data da definire, caricamento e riprova; si ricarica
con il refresh della Home e scompare al logout, anche con richieste in corso.
Gli ospiti non effettuano richieste personali; per account non associati il
riquadro non viene mostrato. Classifiche e notizie restano sotto il riquadro.

Verifiche: 29 test superati su Home, nuovo riquadro, Profilo e tema, inclusi
schermi a 320 px con testo ingrandito in modalità chiara/scura; analisi Flutter
senza problemi. Anteprima locale con fixture controllata visivamente in
`mobile/build/home-review/next-match.png` (non versionata). Modifica locale,
non distribuita sugli store. Preferiti e altri interventi restano fuori da
questo passaggio.

La scheda della prossima partita include ora scudetti e nomi delle due squadre
affiancati con VS, torneo in testata, data/campo con icone e pulsante a larghezza
piena. Con caratteri ingranditi le squadre passano in verticale. Il backend
personale restituisce `logo_casa` e `logo_ospite`, cercati per nome e torneo;
le sottoquery non moltiplicano le partite in presenza di squadre omonime.
Le immagini mantengono le proporzioni; un simbolo neutro gestisce immagini
assenti o non raggiungibili. Verificati 14 test Home/calendario, analisi Flutter
e test PHP del servizio personale. La preview web viene ricompilata insieme
all'aggiornamento del backend condiviso da Android e iOS.

Anche «Le tue prossime partite» nel Profilo mostra ora gli scudetti: due righe
con logo e nome della squadra, al posto dell'icona calendario. Torneo, data e
apertura del dettaglio restano disponibili sull'intera scheda. Usa i campi logo
già aggiunti al backend personale. Verificati 12 test Profilo/calendario e analisi
Flutter senza problemi; preview web ricompilata per la pubblicazione.

Il calendario personale ora mostra inizialmente tre incontri e ne aggiunge tre
con «Mostra altre partite». Dopo il refresh riparte dai primi tre. Le date sono
confrontate con il giorno corrente del dispositivo: prima oggi e le date future,
poi gli incontri senza data, infine quelli con date passate ancora da disputare.
L'ordine resta cronologico all'interno dei gruppi e non modifica i dati ricevuti.
La Home usa lo stesso ordinamento per scegliere la prossima partita. Test dedicati
verificano il cambio giorno e il caricamento progressivo di sette incontri.

La scheda «La tua prossima partita» in Home ora propone «Vai al torneo» e apre
direttamente la scheda Calendario della competizione, invece del referto ancora
vuoto. Il servizio personale include ID e file canonico del torneo per gestire
anche gli incontri storici che usano il nome anziché lo slug. Gli altri ingressi
al torneo mantengono Classifica come scheda iniziale predefinita.
