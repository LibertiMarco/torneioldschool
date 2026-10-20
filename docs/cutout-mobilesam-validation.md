# Validazione MobileSAM

Il percorso definitivo usa RT-DETR, MobileSAM TinyViT, decoder SAM a riquadri e MODNet, con input 1024 x 1024. Ogni fase usa un nuovo worker. Non ci sono fallback a ritaglio manuale o cancellazioni geometriche dei riquadri.

## Verifiche eseguite

- Test core, cache, flusso applica/annulla, ciclo di vita dei modelli ed esecuzione automatica iPhone/iPad: passati.
- Test con modelli reali in Chrome su Windows: soggetto singolo e foto di gruppo passati.
- Foto privata fornita dall'utente: test reale con cinque regioni di controllo per volto sinistro, capelli/fascia sinistri, volto destro, parte superiore della testa destra e sfondo. Passato; circa 15,4 secondi nella macchina di prova, non una misura su iPhone.
- Anteprima trasparente controllata su fondo bianco e scuro: entrambi i soggetti davanti integri. Il piccolo frammento della persona nascosta resta visibile e non viene dichiarato risolto.

Il test con user-agent iPhone verifica la scelta del percorso ma usa il motore Chrome: non dimostra la stabilita di Safari reale. Serve ancora verifica sul dispositivo.

## Ripetere la regressione con la foto originale

La foto privata non viene copiata nel repository. Con una shell PowerShell:

```powershell
$env:CUTOUT_TEST_IOS='1'
$env:CUTOUT_TEST_IMAGE='PERCORSO_ALLA_FOTO_ORIGINALE.jpg'
$env:CUTOUT_TEST_PROFILE='tests/fixtures/graphics_two_subjects_profile.json'
node tests/graphics_cutout_model_browser_test.js
```

Il profilo richiede la foto originale 960 x 1280. Le coordinate sono soltanto nel test, mai nell'algoritmo. Il test serve la foto sul localhost e scarica dalla CDN solo il runtime; non invia la foto a servizi esterni.

## Distribuzione

Includere il nuovo file completo `api/models/mobilesam-encoder-0d3b403.onnx` (28.157.093 byte), i file JS/PHP modificati e la regola cache aggiornata in .htaccess. Hash, provenienza e licenze sono in TERZE_PARTI.md. Lo SlimSAM storico non viene piu caricato. Non serve cambiare hosting ne installare Python sul server.
