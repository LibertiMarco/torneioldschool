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
