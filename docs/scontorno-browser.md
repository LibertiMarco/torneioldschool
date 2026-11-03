# Rimozione sfondo nel browser

Full Time e MVP usano il solo pulsante **Rimuovi sfondo**. L'elaborazione applica
automaticamente la maschera, senza editor manuale, upload della foto o API a pagamento.
Full Time conserva tutte le persone rilevate; MVP conserva i soggetti principali.
I colori della foto originale vengono conservati. Le luci appartenenti allo sfondo
vengono eliminate con lo sfondo; i riflessi sovrapposti ai soggetti non vengono ricostruiti.

## Caricamento su VHosting/cPanel

Caricare mantenendo i percorsi:

- `api/grafiche_post_partita.php`
- `api/grafiche_foto_touch.js` (spostamento e ridimensionamento della foto nell'anteprima)
- `api/graphics_remove_background.php` (disattiva la vecchia chiamata OpenAI)
- `api/grafiche_scontorno.js`
- `api/grafiche_scontorno_worker.js`
- `api/grafiche_scontorno_core.js`
- `api/models/rtdetr-r18-int8-ec641af.onnx`
- `api/models/mobilesam-encoder-0d3b403.onnx`
- `api/models/sam-decoder-b64d034.onnx`
- `api/models/modnet-fa2fa546.onnx`
- i tre file di licenza `api/models/*-LICENSE.txt` e `TERZE_PARTI.md`.

La policy in `.htaccess` deve consentire worker sullo stesso dominio e runtime
WASM da `cdn.jsdelivr.net` (la configurazione del repository lo consente gia).
Non occorrono Python, SSH o chiavi API. Il vecchio endpoint restituisce HTTP 410
senza chiamare servizi esterni. Le altre funzioni del sito restano indipendenti.

Dopo il caricamento ricaricare la pagina senza cache. Caricare una foto, premere
**Rimuovi sfondo**, aspettare le fasi mostrate sotto il pulsante e verificare il PNG
scaricato. Provare sia Full Time sia MVP e **Ripristina originale**.

Per installare lo ZIP da cPanel: aprire **Gestione file**, entrare nella cartella
del sito che contiene `index.php` e la cartella `api` (spesso `public_html`),
caricare lo ZIP aggiornato ed estrarlo li. Non estrarlo dentro `api`, altrimenti
si creerebbe `api/api`. Se cPanel chiede se sovrascrivere i file, confermare.
Il pacchetto non contiene credenziali o `env.local.php`.
La modifica touch rimane disponibile nell'anteprima; i cursori separati di zoom
e posizione e il caricamento manuale dei loghi restano rimossi.

Il primo utilizzo scarica circa 92 MB di modelli oltre al runtime. Una connessione
internet e necessaria al primo caricamento. Tempi e memoria dipendono dal dispositivo;
non chiudere la scheda durante il lavoro. Errori e avanzamento appaiono sotto il pulsante.
Un browser che viene terminato dal sistema per memoria insufficiente non puo mostrare
un errore JavaScript. In tal caso provare da un dispositivo con piu memoria.

## Verifica locale

`node tests/graphics_cutout_flow_test.js`

`node tests/graphics_cutout_model_browser_test.js`

Il secondo comando richiede Chrome e accesso internet per il runtime. Esegue i modelli
su una foto singola e una foto di gruppo e salva anteprime PNG nella cartella temporanea.
