# Preferenze notifiche

Nel profilo, sezione Notifiche push sul telefono, ogni account puo scegliere
se ricevere risultati delle partite e risposte ai propri commenti. Il consenso
del dispositivo resta separato. Le notifiche gia presenti nella casella del sito
non vengono cancellate. I destinatari continuano a dipendere dalle proprie
partite e dai seguiti esistenti; non si aggiungono nuove iscrizioni.

GET/POST autenticati: `api/mobile/v1/notification_preferences.php`.
La preview usa sessione e CSRF tramite `app-preview/api.php`.
Campi booleani `results`, `comments`; default true per conservare il comportamento
esistente. Scritture parziali atomiche, lock per account. Archivio privato
`TOS_RUNTIME_DIR/notification-preferences` (fallback runtime condiviso del sito),
fuori dal repository; nessuna migrazione del database. Il processo PHP deve poter
scrivere nel runtime. Non spostare o cancellare questi file durante i deploy.

Il filtro centrale agisce su push web e FCM per i tag risultati/commenti esistenti.
Un errore di lettura sopprime gli avvisi della categoria per il solo account
interessato. Altri tipi di notifiche mantengono il comportamento precedente.

Promemoria e cambi di calendario non sono inclusi in questo intervento e non
vengono mostrati come disponibili: serve un worker periodico con deduplicazione
e verifica dell'attivazione sul server.

Il codice Flutter e comune ad Android/iOS e alla preview. La pubblicazione della
preview non distribuisce nuovi binari negli store.
