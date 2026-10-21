# Apertura notifiche dentro l'app - build 12

Le notifiche FCM continuano a contenere l'URL HTTPS del sito, per compatibilita
con le vecchie build. Il client lo traduce in destinazioni Flutter native:

- `/tornei/partita_eventi.php?id=...`: dettaglio partita e referto/statistiche.
  Si applica a risultati, promemoria e cambi di calendario.
- `/articolo.php?id=...`: articolo con commenti per le notifiche di risposta.
- Altri percorsi del sito: Profilo dentro l'app, senza aprire un browser.

Restano validi solo URL HTTPS dell'host esatto torneioldschool.it, senza credenziali
e senza porte diverse da 443. Gli ID devono essere interi positivi validi e unici
nella query. Le destinazioni non supportate non consentono navigazione esterna.

Lo stesso flusso gestisce l'azione Apri sulla notifica in primo piano, il tocco
con app in background e getInitialMessage dopo avvio da app chiusa. Se il
Navigator non e ancora presente (ad esempio durante UpdateGate), la destinazione
resta in attesa della prima rotta. I messageId gia gestiti non aprono duplicati.
I contenuti restano dietro AccessGate: dopo il login si prosegue nella partita
o nell'articolo richiesto. Il blocco di aggiornamento rimane sopra la navigazione.

Test: parsing e rifiuto URL/ID ostili, avvio prima del Navigator, referto della
partita richiesta, deduplicazione, ritorno alla Home, destinazione conservata
attraverso il login e apertura delle risposte ai commenti. Nessuna push reale
inviata nei test. La resa e la ricezione su iPhone/Android richiedono build 12 o
successiva; aggiornare soltanto il server non cambia il comportamento della 11.
La policy minima non viene alzata prima della distribuzione dei nuovi binari.

Verifica generale locale: 264 test Flutter superati.
