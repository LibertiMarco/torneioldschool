# Login e collegamento giocatore — 17 novembre 2026

Il login nativo mostra il comando «Non sono un robot» sotto email/password.
Toccandolo, il CAPTCHA si apre nello stesso modulo, senza una nuova pagina.
La superficie web riceve solo il token della verifica, non le credenziali.
Il token viene consumato all'invio e invalidato localmente dopo 110 secondi.
Errori e riprova restano nel modulo. Anche il recupero password usa questo flusso.

`api/mobile/v1/captcha.php` usa il widget compatto e comunica l'altezza della
sfida alla WebView per lasciare spazio alle eventuali immagini di verifica.
Pubblicare anche questo endpoint insieme alla nuova build nativa. La prova del
CAPTCHA Google reale, incluse le sfide con immagini, richiede Android/iPhone:
i test automatici simulano la WebView e i suoi messaggi.

Nel Profilo, «Sono io questo giocatore» è un riquadro selezionabile con nome,
bordo e sfondo di conferma. Il pulsante di invio è più grande; il testo ricorda
che serve l'approvazione dello staff. Selezionando un risultato si chiude la
tastiera. Permessi, conferma esplicita e richiesta agli admin sono conservati.

La preview browser usa la sessione del sito e permette di verificare il nuovo
Profilo; non riproduce il login nativo con CAPTCHA.
