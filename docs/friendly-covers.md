# Copertine amichevoli

In Grafiche → Copertine, il selettore «Tipo copertina» offre «Amichevole».
Questa modalità non richiede un torneo o una partita salvata. Si scelgono due
colori e il punteggio, poi si usa la foto dei capitani con gli stessi controlli
di spostamento e zoom delle altre copertine.

Sono disponibili nero, bianco, rosso, blu, azzurro, verde, giallo, arancione,
viola, rosa e grigio. I colori appaiono come pallini con bordo visibile,
senza nomi scritti né scudetti, su entrambe le esportazioni:
Reel 1080×1920 e YouTube 1280×720. La dicitura è «AMICHEVOLE».

I valori sono solo locali alla grafica. Non vengono create partite o salvati
risultati nel database. Tornando alla modalità torneo, i risultati delle
partite terminate restano bloccati. L'app staff legge i nuovi selettori dal
controller condiviso; non occorre modificare il bundle Flutter.

Verifica con `tests/friendly_covers_browser_test.js`: pagina di produzione
con dati simulati, pixel dei colori nei due canvas, assenza di nomi disegnati,
dimensioni PNG, cambio modalità e leggibilità mobile.
