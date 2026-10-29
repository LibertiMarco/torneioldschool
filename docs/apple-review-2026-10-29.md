# Revisione Apple: autenticazione e identita sportive

**Immagini e proposta grafica:** aprire [l'allegato visivo dei loghi](apple-review-loghi.html).
Contiene sei immagini effettive del progetto con i percorsi di origine, quattro
esempi di stemmi originali e una simulazione di classifica. Le immagini sono
incorporate nel file HTML, consultabile anche senza connessione al sito.

Stato: modifiche locali, nessun deploy e nessuna modifica ai dati del sito.
Non e stata prodotta o provata una IPA su iPhone. La conformita finale e la
risposta di Apple sui nomi restano da confermare.

## Accesso: inventario e interventi

| Percorso | Stato e intervento |
| --- | --- |
| `mobile/lib/main.dart` -> `TosAuth.login()` | Android/iOS di produzione presentano gia `NativeLoginPage`: email e password Flutter, captcha WebView, API `login.php`. Non e necessario sostituire questo login. |
| `login_page.dart` -> Crea un account | Prima `ExternalLinkButton` con `externalApplication`. Ora `AuthWebPage`, WebView visibile interna, senza browser esterno. Riusa `register.php`, CSRF, captcha, consensi, validazioni e invio email del sito. |
| `auth.dart` -> fallback PKCE | Conservato: `flutter_web_auth_2` usa ASWebAuthenticationSession su iOS (verificato nel sorgente Swift della versione installata). Stato, verificatore S256, callback `tosoldschool://auth/callback` e scambio token restano invariati. Android usa il comportamento della libreria. |
| Recupero password | Gia presente in Flutter: captcha e `api/mobile/v1/password_reset.php`; l'endpoint riusa token e email del sito. Nessuna rimozione dei controlli antiabuso. |
| Conferma email e completamento reset | Nuovo pulsante nel login per incollare il link email e aprirlo nella WebView interna. Accetta solo origine del backend e percorsi `verify_email.php`/`reset_password.php`, anche in sottocartella. Il server continua a verificare token e scadenze. |
| Ritorno al login | Pulsante esplicito nella WebView; i link a `login.php` chiudono la schermata. Nessuna autenticazione implicita tramite cookie: dopo conferma/reset si accede con il login nativo, che emette i normali token. |
| Eliminazione account | Prima browser esterno verso `account_delete.php`, con sessione web separata. Ora schermata Flutter, password attuale, conferma e API bearer `account_delete.php`. Cancellazione transazionale account/consensi/codici/sessioni/refresh; credenziali locali rimosse solo dopo risposta positiva. |
| Web/preview | `BrowserAuth` conserva navigazione e sessione web; eliminazione web esistente conservata. Le nuove schermate WebView sono raggiunte solo dal login nativo. |

`ExternalLinkButton` resta per collegamenti informativi; non e piu usato per
registrarsi. Privacy, mappe, store, social e collegamenti editoriali possono
ancora usare il browser: non sono percorsi di autenticazione. La dashboard
staff contiene anche apertura di collegamenti tramite il suo bridge.

Limite esplicito: il tap sul link nell'app Mail continua ad aprire il sito.
Non sono stati aggiunti Universal Links/Associated Domains o modifiche ai link
nelle email. L'utente puo completare l'azione sul sito oppure copiare il link
nel login dell'app. Non dichiarare ad Apple un ritorno automatico da Mail.
Un eventuale ritorno automatico richiede associazione dominio/app, file AASA,
gestione link a freddo/caldo e prove su dispositivi; non basta lo schema PKCE.

## Loghi: fonti e superfici da coprire

Esempi visivi dei file individuati (non prova di utilizzo nel database live):

| Squadra | Squadra | Competizione |
| --- | --- | --- |
| ![Atalanta](../img/scudetti/Atalanta.png) | ![Bologna](../img/scudetti/Bologna.png) | ![Serie A](../img/tornei/seriea.png) |
| `img/scudetti/Atalanta.png` | `img/scudetti/Bologna.png` | `img/tornei/seriea.png` |

Per immagini con dimensioni uniformi e anteprima degli stemmi proposti usare
[l'allegato HTML](apple-review-loghi.html).

L'inventario e del codice e dei file locali, non una lettura del database live.
Un percorso salvato nel database puo riferirsi a un file locale sul server oppure
a un URL remoto: non va confuso con un asset incluso nel bundle Flutter.

| Fonte | Utilizzo |
| --- | --- |
| Bundle Flutter: `mobile/assets/logo_old_school.png`, emoji, font; icone e launch assets iOS/Android | Il pubspec non include un catalogo di stemmi professionistici. Verificare comunque titolarita del marchio proprio e icone prima del rilascio. |
| File sito `img/scudetti/` | Presenti file denominati Atalanta, Bologna, Cagliari e altri club; il nome file suggerisce il soggetto, non dimostra un'autorizzazione. |
| File sito `img/tornei/` | Presenti immagini come `seriea.png`, `serieb3.png`, `ligaportugal.png`: revisione visiva necessaria. |
| DB `squadre.logo` | `get_squadra.php`, `get_squadre_torneo.php`, `leggiClassifica.php`, `get_partite.php`, `get_partita.php`, marcatori e altre classifiche alimentano le schermate Flutter. |
| DB `tornei.img` | `api/mobile/v1/tournaments.php`, API torneo e preview: elenco tornei, preferiti e schede. |
| DB albo/premi | Campi dei vincitori e delle competizioni in `api/albo_doro.php`, `player_awards.php` e relativo gestionale; immagini indipendenti dagli attuali stemmi squadra. |
| API personali e preferiti | `mobile_player_stats.php`, `mobile_follow.php`: `logo_casa`, `logo_ospite`, `squadra_logo`, `torneo_logo`, `team_logos`. |
| Articoli, grafiche, banner, tabelloni | Immagini caricate o link nei contenuti possono incorporare stemmi anche se le icone delle squadre vengono cambiate. Verificare contenuti effettivi e strumenti staff. |

Il rendering condiviso e `SiteImage` in `home_dashboard.dart`, che risolve i
percorsi sul sito e usa immagini di rete. E condiviso anche con foto/avatar:
non sostituire tutte le immagini indiscriminatamente. Superfici interessate:
home, albo, preferiti, elenco tornei, classifiche, rose, scheda squadra,
calendario, dettaglio/referto partita, prossima partita, profilo personale,
storico e premi giocatori, selettori delle notifiche e grafiche staff.

## Proposta da approvare prima di cambiare dati

1. Identita squadra originale: 1-3 iniziali, cerchio o rettangolo arrotondato,
   palette propria ricavata da un identificativo stabile. Esempio concettuale:
   `AB` bianco su cerchio petrolio; nessun disegno derivato da stemmi ufficiali,
   mascotte, stelle, corone o composizioni caratteristiche dei club.
2. Identita competizione originale: simbolo geometrico/pallone generico con
   titolo amatoriale, stessa grammatica visiva, nessun marchio di lega.
3. Contratto dati esplicito e versionato (`badge_kind`, iniziali, colori,
   versione) e widget dedicati TeamBadge/CompetitionBadge. Separarli da
   SiteImage per non alterare foto, sponsor e avatar. Identificativo stabile
   per mantenere lo stesso stemma tra tornei, premi e storico.
4. Generare anche SVG/PNG originali per sito, albo, esportazioni e grafiche.
   Mappatura censita e revisione degli upload futuri; nessun fallback agli
   stemmi ufficiali in caso di errore. Aggiornare cache e contenuti incorporati.
5. Prima inventario esportato in sola lettura e anteprima della mappatura;
   dopo approvazione, migrazione con backup, verifica e possibilita di ripristino.
   La visualizzazione sara identica per revisori e utenti, senza flag di review.

Non e stata implementata la sostituzione e non sono stati cambiati nomi o loghi.
I nomi dei club e delle competizioni non sono automaticamente approvati:
attendere la risposta Apple o definire denominazioni originali/autorizzazioni.
Una dichiarazione di non affiliazione non equivale a una licenza.

## App Store Connect: interventi manuali

Non ho accesso ai metadati caricati, quindi non posso identificare gli screenshot
contestati con certezza. Dopo la decisione sull'identita, rigenerare screenshot
iPhone/iPad e anteprime video di home, tornei, classifiche, partite, squadre,
albo e profilo che contengono marchi. Controllare anche icona, nome, sottotitolo,
descrizione, testo promozionale, keyword, localizzazioni, pagine prodotto
personalizzate e immagini promozionali. Verificare anche URL marketing/supporto.
Chiedere ad Apple quali elementi contesta e se accetta i nomi amatoriali con
stemmi originali; non presentare questa ipotesi come gia autorizzata.

Riferimenti: [linee guida Apple](https://developer.apple.com/app-store/review/guidelines/)
e [indicazioni su identita e metadati](https://developer.apple.com/help/app-review/guideline-reference/4-1-prevent-copycat-rejection/).

## Configurazione e preparazione della build

- Distribuire prima il nuovo endpoint e `includi/mobile_account_delete.php`.
  API mobile abilitate, HTTPS, header Authorization inoltrato a PHP, tabelle
  mobile gia migrate. Nessuna nuova migrazione richiesta da questa modifica.
- Verificare schema MySQL reale, chiavi esterne e tabelle associate all'utente:
  il servizio segue la cancellazione account/consensi del sito, senza inventare
  una cancellazione di statistiche sportive. File avatar e foto preesistenti e
  altre politiche di conservazione richiedono un audit separato. Se una FK
  impedisce il DELETE, l'intera transazione viene annullata.
- Verificare chiavi reCAPTCHA e dominio, invio email e scadenza/monouso dei link;
  provare anche captcha e upload avatar della registrazione nella WebView iOS.
- Su iPhone e Android reali: login riuscito/errato/annullato, registrazione,
  conferma email, recupero e nuovo accesso, ripristino sessione al riavvio,
  refresh token, logout, cancellazione con password errata/corretta, assenza
  dell'account e impossibilita di riusare vecchi token. Usare account di test
  sacrificabili. Provare rete assente, link scaduti e ritorno al login.
- Provare preview web per accertare la continuita del flusso cookie esistente.
- Versione preparata: `0.1.1+14`. Verificare che build 14 non sia gia stata
  usata in App Store Connect e incrementare ulteriormente se necessario.
- Su macOS con Xcode e firma configurata: `flutter pub get`, `flutter analyze`,
  test mirati e `flutter build ipa --release --build-name=0.1.1 --build-number=14
  --dart-define=TOS_BASE_URL=https://torneioldschool.it` (comando su una riga).
  Per Codemagic usare la pipeline firmata gia presente in `codemagic.yaml`,
  conservando export options, configurazioni push/AdMob e parametri esistenti.
- Installare con TestFlight, completare le prove sopra, caricare metadati e
  screenshot definitivi, selezionare la nuova build, fornire account reviewer
  verificato e note con i percorsi esatti per registrazione ed eliminazione.
  Riinviare dopo aver definito anche la questione dei marchi.

## Verifiche eseguite localmente

- `flutter analyze --no-pub`: nessun problema.
- 26 test Flutter riusciti nei file `auth_test.dart`, `auth_email_link_test.dart`,
  `account_page_test.dart`, `browser_auth_test.dart`, `app_revision_test.dart`
  e `delete_account_page_test.dart`. Coprono anche conservazione della sessione
  dopo cancellazione rifiutata, pulizia dopo successo, conferma esplicita,
  password obbligatoria e rifiuto di link email di altre origini/percorsi.
- `php tests/mobile_account_delete_test.php`: riuscito su SQLite in memoria;
  password errata, conferma assente, rollback e isolamento dagli altri utenti.
- `php tests/mobile_auth_test.php`: riuscito su fixture SQLite; flusso PKCE,
  credenziali, scadenze e revoche delle sessioni esistenti.
- Sintassi PHP dei tre nuovi file e `git diff --check`: superati.

Non eseguiti: build iOS/Android, prova fisica, registrazione reale, captcha reale,
consegna email, eliminazione su MySQL/server reale, verifica dei metadati
effettivamente caricati su App Store Connect. Nessun account reale eliminato.
