# Revisione Tornei Old School — ottobre 2026

## Ambito e stato

Revisione eseguita sul repository `LibertiMarco/torneioldschool`, base
`252c57b086e1f08488dd3000bffa0045fefda720`, ramo `codex/app-modernization`.
Il sorgente dell’app è `mobile/`; la preview protetta del sito carica la build
Flutter Web da `app-preview/build/`. Le schermate staff Flutter usano il bridge
`app-preview/staff-engine.js` per riutilizzare i controller PHP, CSRF e permessi.
Non sono state eseguite scritture nel database di produzione, cancellazioni o
pubblicazioni Instagram. Non sono stati cambiati segreti o account.

| Output richiesto | Stato e risultato |
| --- | --- |
| 1. File modificati/creati | **Completato e testato**: inventario in fondo, modifiche isolate sul ramo dedicato. |
| 2. Migliorie grafiche | **Parzialmente completato**: campi condivisi chiari/scuri, card torneo compatte, filtro categoria in bottom sheet, metriche squadra, primi tre marcatori, risultato e informazioni partita compatti, scudetti nei referti, segmenti e paginazione. Verifiche automatiche su layout e nomi lunghi; revisione visiva su dispositivi fisici ancora necessaria. |
| 3. Funzionalità ripristinate | **Parzialmente completato**: referti vincolati a `giocata`, 0-0 validi, ordine stabile portiere/capitano/marcatori/cartellini/altri, navigazione campi, cronologia premi, richieste su giocatori già associati, posizioni ufficiali, commenti e selezione arbitri. Copertura con fixture descritta sotto; operazioni reali non dichiarate verificate. |
| 4. API | **Implementato ma non verificato** sul server: login, recupero password, CAPTCHA, adapter commenti e anagrafica arbitri; ampliamenti compatibili a endpoint pubblici e preview. Servizi di identità/arbitri/associazioni verificati in isolamento. |
| 5. Database | **Completato e testato**: nessuna migrazione nuova e nessuno schema distruttivo. Riutilizzati `utenti`, tabelle `mobile_*`, `blog_commenti`, `password_reset_tokens`, `staff`, `partite` e `player_link_requests`. Le tabelle delle precedenti migrazioni devono già esistere nel server scelto. |
| 6. Autenticazione nativa | **Implementato ma non verificato** end-to-end: email/password Flutter, CAPTCHA nel solo pannello di sicurezza, recupero password API, storage sicuro, restore, refresh rotante e logout esistenti. Test su credenziali/sessioni con fixture. Registrazione tramite modulo del sito; preview conserva l’identità cookie e i vincoli staff esistenti. |
| 7. Instagram | **Parzialmente completato**: riutilizzato il flusso Meta server, aggiunta conferma, conversione PNG→JPEG server e verifica ID della pubblicazione. Solo feed immagini supportato dal flusso esistente; storie automatiche non implementate. Condivisione manuale PNG tramite funzione esistente. Nessuna pubblicazione reale effettuata. |
| 8. Importazione giocatori | **Parzialmente completato**: galleria nativa Android/iOS e selezione file, UTI iOS compatibili, limite OCR di 20 MB comunicato prima del trasferimento e riuso del vero `File` nel controller Tesseract esistente. Anteprima/correzione/duplicati/import restano nei controller PHP originali. Test bridge su trasferimento file; OCR con foto reali e import MySQL richiedono account staff e dispositivo. |
| 9. Banner pubblicitari | **Parzialmente completato**: riutilizzato `AdaptiveAdBanner`, già presente negli strumenti calendario, statistiche, giocatori e grafiche; test su consenso, revoca e dispose Android/iOS. Annunci mobili di test preservati. **Bloccato da configurazioni mancanti** per annunci web: non risultano unità web e configurazione CMP pubblicitaria; nessuna unità AdMob mobile usata nel browser. |
| 10. Preview web | **Parzialmente completato**: stessi sorgenti e bridge aggiornati; build compilata e verifiche riportate sotto. Il sito live non viene aggiornato dal ramo di lavoro: occorre distribuire il risultato dopo verifica staging. Cache busting PHP esistente preservato. |
| 11. Test | **Completato e testato** in locale; risultati e limiti nell’apposita sezione sotto. I test con fixture non equivalgono a prove su account e servizi reali. |
| 12. Blocchi residui | **Bloccato da credenziali, API o configurazioni mancanti**: test autenticati sul server, recapcha reale, email SMTP, import OCR completo, account Meta/permessi, unità web/CMP, SDK Android e ambiente macOS/iOS per build e prove su hardware. Nessun successo simulato. |

## Comportamenti e contratti

### Accesso e sessioni

`POST api/mobile/v1/login.php` accetta JSON `client_id=tos-mobile`, `email`,
`password`, `captcha`. Usa gli hash password del sito, la verifica reCAPTCHA
condivisa e il rate limit `login_attempt` esistente (5 tentativi/15 minuti per IP).
Richiede API mobile abilitate e HTTPS. Restituisce gli stessi token opachi del
flusso PKCE: accesso di 15 minuti, refresh rotante con scadenza assoluta di 30
giorni, hash persistiti, ruoli riletti e revoca al cambio password. Un controllo
del fingerprint evita l’emissione con una password cambiata durante il login.
Le password non vengono memorizzate sul dispositivo.

`GET api/mobile/v1/captcha.php` mostra soltanto la challenge con la chiave pubblica
già configurata. Nessuna password passa nella WebView o nell’URL. Il normale
login mobile non apre il browser di sistema. `POST password_reset.php` riutilizza
tabella e invio email del sito e richiede CAPTCHA/rate limit. Il link ricevuto
apre il reset esistente. La registrazione rimane disponibile tramite il sito.

La preview mantiene sessione browser e CSRF, senza importare cookie nel mobile o
salvare bearer token nel browser. La restrizione della preview ad admin/grafici
non è stata rimossa.

### Commenti

`GET api/mobile/v1/comments.php?id=POST&page=N` legge anche senza login.
`POST ...?operation=create` usa `{post_id, commento, parent_id?}`;
`POST ...?operation=delete` usa `{id}`. Le scritture richiedono bearer valido;
il bridge preview usa cookie verificato e CSRF. L’identità bearer viene usata
solo nella richiesta corrente, senza trasformarla in sessione browser.

L’adapter riutilizza `api/blog.php`, `blog_commenti` e notifiche esistenti. Il
parametro opzionale `page` restituisce `{data,pagination:{page,has_more}}`, 20
commenti per pagina, più recenti prima; le chiamate storiche senza `page`
mantengono il precedente albero di commenti/risposte. Limite 1.200 caratteri e
5 inserimenti al minuto. Cancellazione soltanto autore/admin, verificata sul
server. L’app presenta conferma di eliminazione e conserva il testo se l’invio
fallisce. La moderazione disponibile è la cancellazione amministrativa.

### Associazioni, premi e classifiche

La ricerca include foto, squadra e il solo indicatore `already_linked`; non
restituisce identità o email dell’account precedente. Anche una rivendicazione
in conflitto resta pending. Lo staff vede il conflitto e può rifiutare oppure
risolvere esplicitamente il collegamento con le funzioni già esistenti prima
dell’approvazione. La normale approvazione non sovrascrive mai l’assegnazione.
Lock e vincolo esclusivo precedenti sono preservati.

Il palmarès usa data evento/finale/fine competizione e anno come fallback,
mai data inserimento o ID; ex aequo stabili e date sconosciute in fondo.
`includi/player_rankings.php` conserva query, esclusione SerieB e posizioni
ex aequo del servizio ufficiale. Classifica e profilo chiamano la stessa
funzione. Zero gol/presenze significa `null` quando il giocatore è escluso dalla
classifica ufficiale, visualizzato come “Non in classifica”. La classifica
ufficiale è all-time e non espone un filtro stagione: l’app lo indica.
Il grafico per squadra esclude roster con zero presenze e conserva zero gol
quando ci sono presenze, lasciando intatto lo storico delle appartenenze.

### Partite e campi

`giocata` è la fonte dello stato, indipendentemente dal punteggio. Non vengono
richiesti o resi i referti di partite future; anche l’endpoint eventi pubblico
risponde con lista vuota per queste gare. I referti 0-0 vengono caricati quando
la gara è disputata. Il dettaglio aggiunge scudetti reali, senza inventare
posizioni o indirizzi. Maps usa il valore campo reale come già sul sito;
coordinate/indirizzo sono preferiti se presenti nella risposta. Campi vuoti,
online o da definire non avviano la navigazione.

### Staff, arbitri ed editor

`api/referees.php` usa sessione admin e CSRF per creare, legge l’anagrafica
`staff` categoria arbitro e gli arbitri già nelle partite; non crea una tabella
parallela. I sei nomi iniziali sono quelli richiesti. Normalizzazione Unicode,
spazi e maiuscole e lock MySQL impediscono duplicazioni tra richieste del nuovo
servizio. Il selettore PHP è condiviso tramite bridge con la picklist Flutter
ricercabile e il dialogo di aggiunta. Non sono state cambiate autorizzazioni.

La scelta immagini usa il photo picker di sistema oppure i file. Le fotografie dalla galleria sono preparate dal plugin a qualità 95 e larghezza massima 4.096 pixel, mentre i file mantengono il contenuto originale; i limiti di upload rimangono applicati. Il testo privacy iOS è aggiornato e non viene richiesto accesso alla fotocamera.

Le trasformazioni delle foto Flutter arrivano a `PhotoTouchEditor.apply` e alla
stessa funzione di ridisegno usata dal sito; l’esportazione usa il canvas
originale a piena risoluzione. Per Presentazione e per l’editor dei template Full Time/MVP, gli eventi touch passano ai
gestori pointer già esistenti: trascinamento e pinch di testi, loghi e livelli
aggiornano le posizioni che il salvataggio originale conserva. La modalità modifica è esplicita e le gesture
sono limitate all’anteprima. Reset ed esportazione sono disponibili; rotazione
e salvataggio persistente di nuovi tipi di composizione non vengono inventati.
Le funzioni di salvataggio template/draft già esistenti sono mantenute.

Instagram usa soltanto il servizio PHP esistente e token lato server. Il post è
dichiarato pubblicato solo se Meta restituisce un ID. Il server richiede GD per
convertire in JPEG; in assenza restituisce errore e indica la condivisione
manuale. Annunci e controlli Flutter non fanno parte del canvas esportato.

## Verifica e distribuzione

Non eseguire `database_schema.sql` per questo aggiornamento. Nessuna nuova
migrazione richiesta; in staging verificare le tabelle già previste dal sito e
dalle precedenti migrazioni mobile. Conservare le configurazioni server
reCAPTCHA, SMTP, DB e Meta: nessun segreto va nei `dart-define`.

Per ricompilare e aggiornare i file della preview nel repository:

```bash
FLUTTER_BIN=/percorso/flutter/bin/flutter bash scripts/build_mobile_preview.sh
```

Il comando usa Flutter 3.47.7, modalità reale `TOS_WEB_PREVIEW`, base href della
preview, risorse locali e conserva i guard Apache/PHP. Non esegue deploy remoto.

### Risultati dei test

- Flutter 3.47.7 / Dart 3.13.5: **216 test superati**, nessuna anomalia di analisi statica (`flutter analyze --no-pub`).
- **45 test degli strumenti staff** ripetuti dopo l’integrazione galleria/file: superati.
- **37 suite PHP** su fixture SQLite e ambienti isolati: superate. Le fixture storiche che estendono mysqli emettono avvisi di deprecazione con PHP 8.4; nessuna suite fallita.
- Sintassi PHP: **28 file modificati/nuovi verificati**. `git diff --check` senza errori; normalizzate esclusivamente le terminazioni di riga delle licenze generate.
- JavaScript: bridge staff e referti, conferme e CSRF, trasferimento file, trasformazioni delle foto, template/draft, selettore arbitri, gestione delle immagini e cache preview: verifiche superate.
- Chromium, **app Flutter Web compilata**: dashboard e salvataggio su controller fixture con CSRF; modifica risultato, passaggio al referto, presenze, gol e finalizzazione su fixture: superati.
- Chromium con gli **script reali Full Time/MVP** e foto sintetiche isolate: upload e pixel della foto nel canvas, PNG trasparenti, proporzioni, zoom 10/50/100/250%, alpha, template, ripristino ed esportazione 1.080 × 1.350: superati.
- Layout Flutter: chiaro/scuro, 320/390/430 px e login anche 768/1.024 px con testo ingrandito; placeholder, nomi lunghi, liste e stati vuoti coperti dalle suite.
- Banner: test UMP, errori, revoca e rilascio risorse su entrambe le piattaforme superati; rendering pubblicitario reale su hardware ancora da verificare.
- Confronto in lettura con il sito: il bundle live ha hash `518b10a8783c9ad42e0f70c3c73424ec6c8cf35bbfab77360641af6adcf7b370`; è diverso dalla build del ramo. La preview pubblicata **non è stata aggiornata** da questo lavoro.
- Build Web release reale (`TOS_WEB_PREVIEW=true`): riuscita. I file generati sono copiati in `app-preview/build/`, con hash di `main.dart.js` identico all’output di build. Bootstrap con cache busting preservato. Nessun deploy del sito live.

Non sono disponibili SDK Android, Xcode/macOS, dispositivi fisici o credenziali staff/Meta nell’ambiente. Non sono quindi dichiarati verificati build APK/IPA, login reCAPTCHA reale, consegna email, import OCR completo con commit MySQL, pubblicazione Instagram o rendering annunci reali. Il salvataggio persistente di ulteriori tipi di composizione, oltre ai template/draft già presenti, non è stato esteso.


Hash SHA-256 della build verificata: `c016b338c656de7ccb009680fe3a6f183c857dbc866cfa0ba03f6d37dcf8efbb`.

## Inventario dei file


### Sorgenti, configurazione e documentazione

| File | Modifica |
| --- | --- |
| [api/blog.php](../api/blog.php) | Modificato |
| [api/classifica_giocatori.php](../api/classifica_giocatori.php) | Modificato |
| [api/gestione_account_giocatore.php](../api/gestione_account_giocatore.php) | Modificato |
| [api/gestione_partite.php](../api/gestione_partite.php) | Modificato |
| [api/get_eventi_partita.php](../api/get_eventi_partita.php) | Modificato |
| [api/get_partita.php](../api/get_partita.php) | Modificato |
| [api/grafiche_basi.js](../api/grafiche_basi.js) | Modificato |
| [api/grafiche_foto_touch.js](../api/grafiche_foto_touch.js) | Modificato |
| [api/grafiche_post_partita.php](../api/grafiche_post_partita.php) | Modificato |
| [api/instagram_publish.php](../api/instagram_publish.php) | Modificato |
| [api/mobile/v1/captcha.php](../api/mobile/v1/captcha.php) | Creato |
| [api/mobile/v1/comments.php](../api/mobile/v1/comments.php) | Creato |
| [api/mobile/v1/login.php](../api/mobile/v1/login.php) | Creato |
| [api/mobile/v1/password_reset.php](../api/mobile/v1/password_reset.php) | Creato |
| [api/referee-picker.js](../api/referee-picker.js) | Creato |
| [api/referees.php](../api/referees.php) | Creato |
| [app-preview/api.php](../app-preview/api.php) | Modificato |
| [app-preview/staff-engine.js](../app-preview/staff-engine.js) | Modificato |
| [docs/app-revision-2026-10.md](../docs/app-revision-2026-10.md) | Creato |
| [forgot_password.php](../forgot_password.php) | Modificato |
| [includi/mobile_auth.php](../includi/mobile_auth.php) | Modificato |
| [includi/mobile_player_stats.php](../includi/mobile_player_stats.php) | Modificato |
| [includi/password_recovery.php](../includi/password_recovery.php) | Creato |
| [includi/player_awards.php](../includi/player_awards.php) | Modificato |
| [includi/player_link_requests.php](../includi/player_link_requests.php) | Modificato |
| [includi/player_rankings.php](../includi/player_rankings.php) | Creato |
| [includi/recaptcha.php](../includi/recaptcha.php) | Creato |
| [includi/referees.php](../includi/referees.php) | Creato |
| [login.php](../login.php) | Modificato |
| [mobile/DEVELOPMENT.md](../mobile/DEVELOPMENT.md) | Modificato |
| [mobile/README.md](../mobile/README.md) | Modificato |
| [mobile/ios/Runner/Info.plist](../mobile/ios/Runner/Info.plist) | Modificato |
| [mobile/lib/api.dart](../mobile/lib/api.dart) | Modificato |
| [mobile/lib/auth.dart](../mobile/lib/auth.dart) | Modificato |
| [mobile/lib/browser_auth.dart](../mobile/lib/browser_auth.dart) | Modificato |
| [mobile/lib/comments_section.dart](../mobile/lib/comments_section.dart) | Creato |
| [mobile/lib/details.dart](../mobile/lib/details.dart) | Modificato |
| [mobile/lib/home_dashboard.dart](../mobile/lib/home_dashboard.dart) | Modificato |
| [mobile/lib/login_page.dart](../mobile/lib/login_page.dart) | Creato |
| [mobile/lib/main.dart](../mobile/lib/main.dart) | Modificato |
| [mobile/lib/match_report_logic.dart](../mobile/lib/match_report_logic.dart) | Creato |
| [mobile/lib/native_staff_tool.dart](../mobile/lib/native_staff_tool.dart) | Modificato |
| [mobile/lib/personal_player_design.dart](../mobile/lib/personal_player_design.dart) | Modificato |
| [mobile/lib/player_history.dart](../mobile/lib/player_history.dart) | Modificato |
| [mobile/lib/player_link_request_page.dart](../mobile/lib/player_link_request_page.dart) | Modificato |
| [mobile/lib/ranking_mode_selector.dart](../mobile/lib/ranking_mode_selector.dart) | Modificato |
| [mobile/lib/sport_controls.dart](../mobile/lib/sport_controls.dart) | Creato |
| [mobile/lib/staff_photo_canvas.dart](../mobile/lib/staff_photo_canvas.dart) | Creato |
| [mobile/lib/team_page.dart](../mobile/lib/team_page.dart) | Modificato |
| [mobile/lib/theme.dart](../mobile/lib/theme.dart) | Modificato |
| [mobile/lib/tournament_list.dart](../mobile/lib/tournament_list.dart) | Modificato |
| [mobile/lib/tournament_page.dart](../mobile/lib/tournament_page.dart) | Modificato |
| [mobile/lib/venue_navigation.dart](../mobile/lib/venue_navigation.dart) | Creato |
| [mobile/pubspec.lock](../mobile/pubspec.lock) | Modificato |
| [mobile/pubspec.yaml](../mobile/pubspec.yaml) | Modificato |
| [scripts/build_mobile_preview.sh](../scripts/build_mobile_preview.sh) | Creato |

### Test

| File | Modifica |
| --- | --- |
| [mobile/test/app_revision_test.dart](../mobile/test/app_revision_test.dart) | Creato |
| [mobile/test/comments_section_test.dart](../mobile/test/comments_section_test.dart) | Creato |
| [mobile/test/home_test.dart](../mobile/test/home_test.dart) | Modificato |
| [mobile/test/match_report_test.dart](../mobile/test/match_report_test.dart) | Modificato |
| [mobile/test/player_history_test.dart](../mobile/test/player_history_test.dart) | Modificato |
| [mobile/test/preview_test.dart](../mobile/test/preview_test.dart) | Modificato |
| [mobile/test/tournament_list_test.dart](../mobile/test/tournament_list_test.dart) | Modificato |
| [tests/app_revision_test.php](../tests/app_revision_test.php) | Creato |
| [tests/graphics_photo_touch_test.js](../tests/graphics_photo_touch_test.js) | Modificato |
| [tests/graphics_templates_test.js](../tests/graphics_templates_test.js) | Modificato |
| [tests/mobile_auth_test.php](../tests/mobile_auth_test.php) | Modificato |
| [tests/player_link_requests_test.php](../tests/player_link_requests_test.php) | Modificato |
| [tests/referee_picker_test.js](../tests/referee_picker_test.js) | Creato |
| [tests/squadra_gironi_test.php](../tests/squadra_gironi_test.php) | Modificato |
| [tests/staff_engine_test.js](../tests/staff_engine_test.js) | Modificato |

### Build Flutter Web

| File | Modifica |
| --- | --- |
| [app-preview/build/.last_build_id](../app-preview/build/.last_build_id) | Modificato |
| [app-preview/build/assets/AssetManifest.bin](../app-preview/build/assets/AssetManifest.bin) | Modificato |
| [app-preview/build/assets/AssetManifest.bin.json](../app-preview/build/assets/AssetManifest.bin.json) | Modificato |
| [app-preview/build/assets/FontManifest.json](../app-preview/build/assets/FontManifest.json) | Modificato |
| [app-preview/build/assets/NOTICES](../app-preview/build/assets/NOTICES) | Modificato |
| [app-preview/build/assets/fonts/MaterialIcons-Regular.otf](../app-preview/build/assets/fonts/MaterialIcons-Regular.otf) | Modificato |
| [app-preview/build/assets/packages/cupertino_icons/assets/CupertinoIcons.ttf](../app-preview/build/assets/packages/cupertino_icons/assets/CupertinoIcons.ttf) | Creato |
| [app-preview/build/flutter_bootstrap.js](../app-preview/build/flutter_bootstrap.js) | Modificato |
| [app-preview/build/main.dart.js](../app-preview/build/main.dart.js) | Modificato |
