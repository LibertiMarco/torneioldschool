# Screenshot iPad per App Store

In Codemagic avvia manualmente il workflow `ipad-screenshots` sul branch `master`.
Non richiede firma, non carica build su TestFlight e non pubblica l'app.

Il workflow avvia l'app reale su un simulatore iPad da 13 pollici e salva due
acquisizioni della Home in `build/app-store-screenshots`, tra gli artifact.
Scarica i PNG e controlla che dati e immagini siano caricati prima di usarli.
Le immagini devono mostrare la Home, senza errori di rete o schermate di attesa.

Carica una delle immagini in Distribuzioni → versione → Anteprime dell'app e
screenshot → iPad 13 pollici. Non usare la sezione App per iMessage.

`TOS_SCREENSHOTS=true` disattiva soltanto l'avvio automatico di pubblicità e
richieste push per l'acquisizione. Non usa dati dimostrativi e non modifica la
build firmata del normale workflow TestFlight.
