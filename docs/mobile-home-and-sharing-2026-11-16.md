# Invito giocatore e condivisione risultati

Il Profilo mantiene statistiche complete, storico, squadre e collegamento
del giocatore. La Home conserva il riepilogo della prossima partita e i
preferiti. Quando il servizio personale restituisce un giocatore assente,
una scheda chiudibile invita al collegamento. Una seconda verifica sul
servizio richieste distingue richieste pendenti e collegamenti gia esistenti.
La scheda non invia richieste e non viene mostrata in caso di errore della
verifica. Il pulsante apre la scheda Profilo esistente.

Le partite giocate e la scheda Ultima partita in Home mostrano Condividi
risultato. Il pulsante prepara l'immagine e apre direttamente la condivisione
di sistema, senza navigare a una pagina intermedia. Un indicatore segnala
la preparazione e viene rimosso anche in caso di errore.
Viene generata una sola immagine PNG di 1080x1920 pixel, usando
gli stemmi consentiti dalle impostazioni esistenti. Le due meta affiancate
contengono tutte le presenze, casa a sinistra e ospiti a destra, senza tagli
o conteggi sostitutivi. Il tabellino si ridimensiona per restare nel formato
storia. Sono inclusi risultato, torneo, data/giornata, rigori, gol e voto.
I badge POR e C identificano portiere e capitano (anche contemporaneamente),
usando ruolo in squadra e dati del referto gia esposti dall'API.
I voti assenti/zero sono indicati con un trattino; gli autogol restano separati.
Le righe senza squadra riconoscibile sono mostrate in Altri presenti.
L'API eventi espone ora anche presenza, per escludere gli assenti espliciti.
Gli eventi legacy privi del campo rimangono compatibili. L'anteprima scorre
con l'immagine intera e l'esportazione mantiene tutte le righe in un solo file.
Il menu di sistema riceve immagine e link alla pagina esistente della partita;
la destinazione puo gestire il testo separatamente. Non e stata modificata
la richiesta di accesso della pagina web, ne configurati universal/app links.
Nessun contenuto viene inviato automaticamente a un destinatario.

Nessuna modifica a notifiche, inserimento risultati, formazioni o sponsor.

Verifica: 41 test mirati superati, analisi statica senza problemi. Esportazioni
quadrata/verticale ispezionate, dimensioni PNG e passaggio al servizio di
condivisione testati; testo ingrandito e larghezza 320px verificati. Una fixture
del Profilo e stata allineata alla richiesta Home gia esistente `view=upcoming`.
Restano necessarie nuove build e prove di condivisione su Android/iPhone/iPad.

## Lettura degli screenshot AdMob forniti dall'utente

Fonte: due screenshot Panoramica app, filtro Ultimi 7 giorni rispetto ai
7 giorni precedenti; date assolute non visibili. Primo Android; secondo
attribuito a iOS dalle unita BANNER TOS IOS e iOS_Home_Nativo. Valori K
arrotondati; nessun export per unita o versione fornito.

| Valore | Android | iOS |
|---|---:|---:|
| Ricavi stimati del riquadro 7 giorni | EUR 0.13 | EUR 0.69 |
| Richieste visualizzate | 3.18K | 5.19K |
| Impressioni visualizzate | 929 | 2.17K |
| Match rate | 62.69% | 84.98% |
| eCPM | EUR 0.14 | EUR 0.32 |
| Ricavi banner | EUR 0.11 | EUR 0.65 |
| Ricavi nativo | EUR 0.02 | EUR 0.04 |

Stima show rate: impressioni / (richieste * match rate).
Android: 929 / (3180 * 0.6269) = 46.6005%, circa 47%.
iOS: 2170 / (5190 * 0.8498) = 49.2012%, circa 49%.
Sono ricostruzioni approssimative, non valori esatti esportati da AdMob.
Non sono stati usati i ricavi mensili del riquadro superiore come ricavi
settimanali. Definizioni: https://support.google.com/admob/table/9462111

Interpretazione: le richieste soddisfatte superano le impressioni in entrambe
le app. I dati non identificano la causa: scorrimento, uscita dalla pagina,
posizioni poco raggiunte, reload o problemi di rendering restano ipotesi.
Non e possibile attribuire miglioramenti alle modifiche locali non distribuite.
I dati mancanti utili sono il report per unita con match/show rate, impressioni,
eCPM e ricavi, e le impostazioni di refresh dei banner. Nessuna impostazione
AdMob e stata modificata. Nessun incremento dei ricavi e stato promesso.
