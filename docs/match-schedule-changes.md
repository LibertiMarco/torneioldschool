# Avvisi sui cambi di calendario

Il worker `api/script/send_match_reminders.php --scheduled` controlla anche data,
ora e campo di tutte le partite non giocate. Rimane il cron Plesk esistente
`*/5 * * * *`, PHP 8.2: non aggiungere altre attivita.

La prima osservazione memorizza soltanto il calendario; nessuna notifica iniziale.
Ogni variazione successiva genera un singolo avviso con i nuovi valori dei campi
modificati. Una variazione multipla viene accorpata. I valori rimossi sono indicati
come "da definire". Sono escluse le partite giocate e quelle con data/orario gia
trascorsi. Il confronto e fra controlli: cambi annullati entro lo stesso intervallo
non vengono notificati. Non sono annunci di creazione, eliminazione o cambio squadre.

Destinatari: gli stessi giocatori e follower dei promemoria, senza duplicati.
Preferenza account `schedule_changes`, booleana e disattivata per default.
Il filtro comune viene applicato a push web e FCM. Nessun invio di prova automatico.
Nessuna nuova notifica nella casella del sito.

La UI mostra "Cambi di data, orario o campo" solo quando l'heartbeat del worker
conferma di aver completato anche il controllo calendario (`schedule_changes_ready`).
La normale verifica di due esecuzioni recenti e della scadenza di undici minuti
resta attiva. Un vecchio heartbeat dei soli promemoria non abilita la scelta.

Runtime: `match-reminders/schedule-changes/<partita>.json`, protetto dal lock
condiviso e dalla directory privata. Snapshot atomici con revisione crescente.
Non cancellare o sovrascrivere il runtime nei deploy. Le modifiche sono registrate
prima dell'invio: interruzioni o errori di rete possono perdere avvisi, ma il
riavvio non ripete un invio dall'esito incerto. Il riepilogo del cron aggiunge
`schedule_changes.changed` e `subscriptions_attempted` (tentativi, non consegne).
Un limite operativo fra le partite evita un controllo indefinito; i singoli invii
restano soggetti ai timeout del trasporto esistente.

`--dry-run` confronta il calendario esistente senza scrivere snapshot/heartbeat e
senza inviare push. Al primo dry-run non esiste ancora una baseline, quindi zero
cambi e normale. Le preferenze precedenti ricevono il nuovo default senza migrazioni.

Verifiche: test PHP per baseline, no-op, cambi multipli, rimozioni, partite concluse,
passate, dry-run, ritorno A-B-A e interruzione durante l'invio; test preferenze e
widget Flutter per disponibilita, salvataggio separato e fallimenti.

Verifica reale ancora necessaria: attendere un cron riuscito, attivare l'opzione
su un account di prova consenziente e verificare una variazione autorizzata di una
partita pertinente. Non modificare partite reali solo per provare la funzione.
La preview non distribuisce nuove build Android/iOS negli store.
