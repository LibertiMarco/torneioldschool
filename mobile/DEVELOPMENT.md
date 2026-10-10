# Sviluppo mobile

La versione corrente è una base di sviluppo, non la versione completa richiesta per gli store.

Implementato: progetto Android/iOS, navigazione Calcio/Esport, elenco tornei, classifiche, partite con dettaglio e referto, squadre con rose e statistiche giocatori nel torneo, accesso tramite login web con PKCE S256, sessioni mobile revocabili, profilo e logout, consultazione utenti e modifica delle abilitazioni Totocalcio/Fantacalcio per admin. Palette e logo sono quelli del sito. L'anteprima browser principale è riservata agli admin e usa account/database reali: `../docs/mobile-preview.md`.

Non ancora implementato: le altre operazioni admin di scrittura, storico completo dei giocatori, blog/commenti, preferiti e notifiche, moduli fantasy, grafiche, importazioni, social e parità di tutte le varianti di torneo. La mappa completa è in `../docs/app-mobile.md`.

## Comandi Windows

L'SDK Flutter locale si trova in `../.mobile-tools/flutter`. L'SDK Android locale si trova in `../.mobile-tools/android-sdk`. Entrambi sono ignorati da Git e protetti dall'accesso HTTP con `.htaccess`. Il wrapper non modifica il PATH di Windows.

Dal terminale nella cartella `mobile`:

```powershell
.\flutter.ps1 doctor
.\flutter.ps1 analyze
.\flutter.ps1 test
.\flutter.ps1 run --dart-define=TOS_BASE_URL=https://INDIRIZZO-STAGING
.\flutter.ps1 build apk --debug --dart-define=TOS_BASE_URL=https://INDIRIZZO-STAGING
```

`TOS_BASE_URL` deve includere l'eventuale sottocartella del sito. In assenza del parametro l'app usa `https://torneioldschool.it`. I nuovi endpoint sono stati distribuiti tramite push su master; l'ultima verifica li trovava disattivati in attesa dell'attivazione dal pannello admin. Non inserire segreti nei parametri di build.

Per un emulatore Android collegato a XAMPP locale, l'indirizzo usuale è `http://10.0.2.2/torneioldschool`. Le build debug consentono HTTP; le release richiedono HTTPS. Un telefono fisico deve usare un indirizzo raggiungibile dal dispositivo oppure un tunnel locale configurato. Non è stato creato un emulatore o collegato un telefono.

## Backend

Le nuove API sono sotto `api/mobile/v1/`. Il guard mobile non modifica i guard o i cookie del sito.

La pagina `admin_mobile.php`, collegata dal pannello admin, permette di preparare le tre tabelle e attivare le API dopo il deploy automatico da master. In assenza di una variabile `MOBILE_API_ENABLED` esplicita viene usata l'impostazione nella directory runtime del sito; la variabile server, quando presente, ha precedenza.

Prima di attivarle nell'ambiente scelto:

1. Applicare soltanto `migrations/mobile_auth.sql` al database condiviso. È una migrazione aggiuntiva; non usare `database_schema.sql`, che contiene cancellazioni di tabelle.
2. Configurare `MOBILE_API_ENABLED=1` nell'ambiente PHP.
3. In locale soltanto, se necessario, configurare `MOBILE_ALLOW_LOCAL_HTTP=1`; il server consente HTTP solo per richieste provenienti da loopback.
4. Verificare le colonne account `email_verificata` e `feature_flags` e la colonna `tornei.sezione` nell'ambiente reale.
5. Verificare che Apache/PHP riceva l'header `Authorization`. Non abilitare CORS permissivo: l'app nativa non ne ha bisogno.

Per il database locale è disponibile `C:\xampp\php\php.exe api/script/mobile_migrate.php --apply` dalla radice del progetto. Senza `--apply` stampa soltanto l'anteprima. Il database locale è stato avviato per la prova ma rifiuta le credenziali configurate (1045); la migrazione non è stata applicata. L'utente ha scelto infine il sito reale. I file sono stati pubblicati su master con il commit `4d5862d`; l'attivazione delle API e la migrazione online restano da confermare dal pannello admin. I passaggi di rilascio sono in `../docs/mobile-production.md`.

Il login dell'app apre il login del sito nel browser del sistema. L'endpoint `authorize.php` emette un codice monouso con scadenza di 120 secondi; l'app lo scambia con il verificatore PKCE. Non viene introdotto un endpoint password che aggiri reCAPTCHA. La callback ammessa è soltanto `tosoldschool://auth/callback`.

Gli access token scadono dopo 15 minuti. Le sessioni durano al massimo 30 giorni. I refresh token ruotano ad ogni uso; riutilizzare un vecchio refresh revoca l'intera sessione. Si conservano soltanto hash dei token nel database. Cambio password, email non verificata ed eliminazione account rendono le credenziali inutilizzabili. Ruoli e abilitazioni sono letti nuovamente ad ogni richiesta autenticata.

Prima della produzione, pianificare la pulizia periodica dei codici scaduti e delle sessioni con `refresh_expires_at < UNIX_TIMESTAMP()`; eliminare le sessioni elimina anche i refresh associati. Conservare i refresh utilizzati fino alla scadenza della sessione per rilevare replay.

## Verifica

La modifica delle abilitazioni usa il guard admin del servizio mobile e le definizioni/normalizzazione già condivise dal sito. Un hash della versione letta e un aggiornamento condizionale rilevano i salvataggi concorrenti (HTTP 409); non vengono modificati ruoli o password. La schermata richiede conferma, disabilita il doppio invio e permette di ricaricare in caso di conflitto. La prova automatica `tests/mobile_account_features_test.php` usa SQLite in memoria: sono ancora necessarie la verifica MySQL e quella su dispositivo.

```powershell
C:\xampp\php\php.exe ..\tests\mobile_auth_test.php
```

I test PHP usano SQLite in memoria e non modificano il database del sito. Coprono PKCE, codice monouso, scadenze, promozioni/revoche dei ruoli, ruolo grafico, logout, replay dei refresh, cambio password ed eliminazione account. Non sostituiscono la verifica di concorrenza su MySQL o un login completo da dispositivo.

## iOS e distribuzione

`../codemagic.yaml` contiene una pipeline di verifica iOS senza firma e una pipeline manuale di firma e caricamento TestFlight. Nessuna è stata avviata: non esiste ancora una IPA installabile. L'utente ha un iPhone e soltanto un normale Apple ID. Per il percorso da Windows servono iscrizione Apple Developer e configurazione Codemagic, App Store Connect e certificati/profili. I passaggi sono in `../docs/iphone-testflight.md`.

La dipendenza `flutter_web_auth_2` è fissata a `6.0.0-alpha.8` per compatibilità con AGP 9 generato da Flutter 3.47.7. È una prerelease: prima della distribuzione definitiva verificare stabilità, comportamento su dispositivi e disponibilità della release stabile. Le icone e la firma Android attuali sono quelle di sviluppo.
