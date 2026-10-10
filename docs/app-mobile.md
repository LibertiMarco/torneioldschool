# App Tornei Old School per Android e iOS

Data: 10 ottobre 2026.

## Obiettivo concordato

Un'app per Android e iPhone con tutte le funzionalità del sito, comprese quelle amministrative. Account, dati, ruoli e abilitazioni devono essere condivisi con il sito. La prima versione da distribuire deve coprire l'intero perimetro; le fasi sotto descrivono l'ordine di sviluppo, non una riduzione del prodotto.

Stato: ambiente Windows preparato e base Flutter/API implementata. Sono presenti homepage Calcio/Esport con marcatori/presenze totali, ranking EA FC, notizie e albo d’oro, tornei suddivisi in corso/programmati/terminati con ricerca e filtro categoria, classifiche e partite con referto, squadre con rose e statistiche nel torneo, login con PKCE tramite browser di sistema, profilo, logout, consultazione utenti e modifica abilitazioni account per admin. La palette e il logo sono quelli del sito. L'anteprima browser `/app-preview/` è riservata agli admin e usa account/database reali (`docs/mobile-preview.md`); il demo locale rimane solo opzionale per test. L'app completa non è ancora pronta: le altre funzionalità della mappa e le altre operazioni amministrative restano da implementare. La pubblicazione sugli store è rimandata. I dettagli operativi sono in `mobile/DEVELOPMENT.md`.

## Architettura proposta

- Client Flutter con schermate dedicate al telefono e un progetto condiviso per Android e iOS.
- Backend PHP esistente e database esistente come fonte unica dei dati.
- API JSON versionate, proposte sotto `/api/mobile/v1/`, per letture e operazioni autenticate.
- Servizi PHP condivisi fra le pagine del sito e le API mobile: le regole di classifica, calendario, statistiche e permessi devono essere eseguite nello stesso codice.
- Configurazione separata per ambiente locale, staging e produzione; nessuna credenziale del database nell'app.
- Sezioni Calcio ed Esport esplicite nelle richieste, preservando i limiti applicati dal server.

Non tutte le risorse in `api/` sono API JSON. Per esempio `api/gestione_partite.php` combina gestione POST, query e interfaccia HTML e usa il guard admin con redirect al login. Il login attuale in `login.php` è un flusso web con cookie di sessione, CSRF e reCAPTCHA. Questi flussi richiedono un adattamento per un client mobile.

## Mappa iniziale delle funzionalità

| Area | Riferimenti nel sito | Lavoro mobile previsto |
| --- | --- | --- |
| Home e navigazione Calcio/Esport | `index.php`, `esport.php`, `includi/content_sections.php` | Navigazione e selezione sezione, feed e contenuti equivalenti |
| Tornei, fasi, giornate e calendario | `tornei.php`, `tornei-esport.php`, `tornei/`, `api/get_torneo.php`, `api/get_giornate.php` | Elenco tornei e dettaglio, tutte le varianti di torneo |
| Partite ed eventi | `api/get_partite.php`, `api/get_partita.php`, `api/get_eventi_partita.php`, `tornei/partita_eventi.php` | Calendario, risultati, eventi, collegamenti video |
| Squadre e rose | `api/get_squadre.php`, `api/get_squadra.php`, `api/get_rosa.php` | Dettagli e rose con immagini |
| Classifiche e statistiche | `api/leggiClassifica.php`, `api/classifica_marcatori.php`, `api/classifica_giocatori.php`, `api/esport_ranking.php`, `statistiche_giocatore.php` | Classifiche, marcatori, ranking e statistiche giocatore |
| Albo d'oro | `albo.php`, `api/albo_doro.php` | Consultazione equivalente |
| Blog e commenti | `blog.php`, `articolo.php`, `api/blog.php` | Articoli, commenti e relative azioni autorizzate |
| Account | `login.php`, `register.php`, `register_new.php`, `verify_email.php`, `forgot_password.php`, `reset_password.php`, `account.php`, `account_delete.php` | Login, registrazione, verifica email, recupero password, profilo, eliminazione account |
| Associazione account-giocatore | `api/search_giocatori_associazione.php`, `api/gestione_account_giocatore.php` | Richieste e approvazioni equivalenti, secondo i permessi del sito |
| Preferiti e notifiche | `api/follow.php`, `api/notifications.php`, `api/push_subscription.php`, `includi/push_notifications.php` | Preferiti, notifiche interne e integrazione push mobile |
| Totocalcio | `totocalcio.php`, `includi/totocalcio.php`, `api/gestione_totocalcio.php` | Pronostici, antepost, competizioni, scadenze e amministrazione |
| Fantacalcio | `fantacalcio.php`, `api/gestione_fantacalcio.php` | Inventario delle azioni, API e schermate equivalenti |
| Fanta Old School | `fantaoldschool.php`, `includi/fanta_old_school.php`, `admin_dashboard.php` | Registrazioni, referral, inviti e gestione dei record |
| Gestione tornei e squadre | `api/gestione_tornei.php`, `api/gestione_squadre.php` | Tutte le azioni amministrative e relativi controlli |
| Gestione giocatori e importazione | `api/gestione_giocatori.php`, `api/import_giocatori.php` | Gestione, importazione e selezione file dal dispositivo |
| Gestione partite e referti | `api/gestione_partite.php`, `api/partita_giocatore.php`, `api/statistiche_partita.php`, `api/diffidati.php` | Risultati, eventi, sanzioni, finalizzazione e ricalcoli condivisi |
| Generazione calendari | `api/crea_giornata_automatica.php`, `api/crea_giornata_automatica_api.php`, `api/crea_calendario_allinone.php` | Configurazione, anteprima, conflitti e salvataggio |
| Grafiche | `api/generatore_grafiche.php`, `api/grafiche_*.php`, `includi/graphics_templates.php`, `includi/presentation_templates.php` | Creazione, foto, anteprima, esportazione e condivisione; verificare dipendenze dai renderer browser |
| Video e social | `api/sincronizza_video_partite.php`, `instagram/`, `meta/`, `tiktok/`, `api/instagram_publish.php` | Connessioni autorizzate, sincronizzazione e pubblicazione quando consentita dal ruolo; verifica callback mobile |
| Utenti, staff e abilitazioni | `api/gestione_utenti.php`, `api/gestione_staff.php`, `api/gestione_funzioni_account.php`, `includi/user_features.php` | Gestione con permessi applicati lato server |
| Gestione blog e albo | `api/gestione_blog.php`, `api/gestione_blog_new.php`, `api/gestione_albo.php` | Funzioni editoriali e gestione albo |
| Informazioni, contatti e consensi | `chisiamo.php`, `contatti.php`, `privacy.php`, `cookie.php`, `note_legali.php`, `api/consensi.php` | Contenuti, contatti e consensi coerenti con il nuovo client |

L'elenco deriva dai file presenti e dai collegamenti del pannello admin. Non certifica ancora la completezza delle singole operazioni o il loro funzionamento in produzione.

## Autenticazione e autorizzazione

Proposta da implementare e testare:

1. Riutilizzare il login del sito nel browser del sistema, preservando verifica email, recupero password, limitazioni e reCAPTCHA. Scambiare un codice monouso protetto da PKCE S256 per le credenziali mobile: non introdurre un login password alternativo che aggiri le protezioni web.
2. Usare credenziali di sessione mobile revocabili, con token di accesso a breve durata e refresh token ruotato; conservare sul server hash dei token e scadenze, sul dispositivo lo storage protetto del sistema.
3. Leggere ruolo e abilitazioni dal server, anche nelle operazioni di scrittura. Nascondere un pulsante nell'app non autorizza né protegge l'operazione.
4. Conservare le distinzioni fra i ruoli presenti, incluso l'accesso limitato alle grafiche, senza uniformare tutti gli utenti amministrativi.
5. Preservare le abilitazioni individuali Totocalcio e Fantacalcio definite in `includi/user_features.php`.
6. Revocare l'accesso su logout, eliminazione account e secondo le regole definite per cambio password o revoca delle sessioni.
7. Mantenere CSRF e controlli di origine del sito. Le nuove API mobile richiedono un guard specifico; non disattivare globalmente le protezioni delle pagine web.

Le nuove tabelle sono definite in `migrations/mobile_auth.sql`. Il servizio `includi/mobile_auth.php` implementa codici monouso, token opachi conservati soltanto come hash, refresh ruotati e revoche. Il database locale è stato avviato per la prova ma rifiuta le credenziali configurate (1045); la migrazione non è stata applicata. L'utente ha scelto il sito reale, `https://torneioldschool.it`, con deploy automatico al push su master. Le API sono disattivate per impostazione iniziale: la pagina admin `admin_mobile.php` prepara le tabelle e permette l'attivazione. La variabile `MOBILE_API_ENABLED`, quando impostata sul server, prevale sul pannello. I passaggi di rilascio sono in `docs/mobile-production.md`.

## Regole condivise e scritture

Prima di portare una funzione admin, estrarre la relativa operazione dalla pagina HTML in un servizio condiviso. La pagina web conserva il proprio flusso; l'endpoint mobile riceve dati validati e restituisce JSON.

La finalizzazione delle partite deve preservare transazioni, ricalcoli, invalidazione cache e notifiche. Controllare salvataggi concorrenti da sito e app e prevedere protezione dai doppi invii per le azioni che producono effetti. Le importazioni e le generazioni devono mostrare anteprima ed errori coerenti con il sito.

Le API devono selezionare esplicitamente i campi restituiti: le classi CRUD esistenti usano anche `SELECT *`, inadatto a un contratto mobile quando può includere dati riservati.

## Notifiche e integrazioni del dispositivo

Le notifiche interne possono condividere destinatari e contenuti con il sito. Il trasporto push mobile richiede registrazione e revoca dei dispositivi e integrazione con i servizi Android/iOS; non coincide con la sottoscrizione Web Push esistente. Gli eventi del backend devono alimentare entrambi i canali rispettando preferenze e autorizzazioni.

Foto, importazioni, esportazioni e social richiedono test reali su entrambi i sistemi. Valutare per ogni generatore di grafiche se rendere sul server o portare il renderer; non assumere che gli script del browser funzionino direttamente in Flutter.

## Sequenza di sviluppo

1. Completare l'inventario delle azioni e dei ruoli per ciascuna riga della mappa; fissare i casi di verifica.
2. Preparare SDK, progetto Flutter, configurazione ambienti e contratti API.
3. Implementare autenticazione mobile e servizi condivisi, con test su autorizzazioni e revoche.
4. Portare navigazione, tornei, squadre, partite, statistiche e contenuti.
5. Portare account, commenti, preferiti, notifiche e tutte le funzioni fantasy.
6. Portare tutti i moduli admin, incluse grafiche, importazioni e integrazioni social.
7. Verificare parità con il sito, scritture incrociate, concorrenza, scadenze, errori di rete e funzionamento su dispositivi reali.
8. Preparare build firmate, materiale degli store, distribuzione di prova e pubblicazione.

## Prerequisiti e stato dell'ambiente

Flutter 3.47.7 e l'SDK Android sono stati installati nella cartella locale `.mobile-tools/`, esclusa da Git e protetta dall'accesso web. Le licenze Android sono state accettate su autorizzazione esplicita dell'utente. Il wrapper `mobile/flutter.ps1` permette di usare Flutter senza modificare il PATH di Windows.

L'utente ha confermato di disporre soltanto di Windows. Il percorso previsto è sviluppo Flutter e prove Android sul PC, con compilazione e firma iOS su un ambiente macOS remoto. Codemagic è una possibile soluzione da configurare dopo la scelta del servizio e l'accesso agli account di firma; non è stato attivato alcun servizio esterno. Le prove iOS devono comunque includere un iPhone reale o un ambiente di test iOS remoto: una build riuscita non dimostra la parità funzionale.

Per compilare e firmare iOS serve un ambiente macOS con Xcode, locale o remoto. Per distribuire negli store servono i relativi account, le identità di firma e la configurazione dei servizi scelti. L'accesso a questi ambienti e account non è stato verificato.

L'app usa `https://torneioldschool.it` come origine predefinita, con sezione Calcio/Esport esplicita. L'utente ha scelto questo ambiente reale. La base backend nativa è stata pubblicata tramite push su master; l'ultima verifica la trovava disattivata in attesa dell'attivazione admin. L'anteprima browser usa invece la sessione web esistente e lo stesso database, senza dipendere dalle tabelle di autenticazione mobile. Non sono state effettuate scritture di prova nei dati reali.

## Criterio di completamento

L'app è completa quando ogni funzionalità concordata ha schermate utilizzabili su Android e iOS, API con permessi verificati e una prova di equivalenza rispetto al sito. Non bastano una schermata di navigazione o collegamenti alle pagine web.

## Riferimenti tecnologici

- Flutter, integrazione multipiattaforma: https://docs.flutter.dev/platform-integration
- Configurazione iOS e Xcode: https://docs.flutter.dev/platform-integration/ios/setup
- Ambienti e piattaforme di sviluppo Flutter: https://docs.flutter.dev/install/custom
- Build Flutter iOS e distribuzione con Codemagic: https://labs.codemagic.io/your-first-flutter-app-to-appstore/
