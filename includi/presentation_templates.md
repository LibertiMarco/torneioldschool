# Presentazione Squadra

La sezione `/api/grafiche_presentazione.php` è caricata dal Generatore Grafiche con lo stesso controllo di accesso delle altre sezioni. Legge tutti i tornei e le squadre dal database esistente, senza modifiche allo schema. `tornei.img`, `squadre.logo` e `/img/logo_old_school.png` sono gli unici asset dei loghi; se uno manca o non è caricabile, il download viene disabilitato con un messaggio. Per URL esterni il server immagini deve consentire CORS, come per il renderer MATCHDAY.

## Associazione al torneo

Ordine di precedenza:

1. Identificativo numerico in `presentation_template_assignments.json`.
2. Campo opzionale `presentation_template` nel JSON `tornei.config` esistente.
3. Associazione salvata come `<id>-presentation.json` nella cartella runtime `graphics-templates`, con fallback `cache/graphics-templates`.
4. Alla prima apertura, riconoscimento dello slug `filetorneo` o del nome e salvataggio dell'associazione per ID. I nomi di squadre non partecipano alla scelta.

Per associare una competizione nuova a un'identità disponibile, modificare ad esempio il manifest con l'ID reale del database:

```json
{"123": "conference", "124": "brasileirao"}
```

`123` e `124` sono esempi, non ID del sito. Il manifest iniziale è vuoto. Le chiavi valide sono quelle di `presentation_template_catalog()` e `PresentationRenderer.profiles`. Per creare una nuova identità, aggiungere un profilo al renderer e la sua chiave al catalogo PHP, quindi associarla all'ID reale nel manifest. Il generatore e il database non richiedono riscritture. Il fallback `editorial` usa geometrie e composizione deterministiche per ID e un accento estratto dal logo originale. Per una nuova competizione con un'identità editoriale specifica, assegnare un profilo dedicato.

Le associazioni automatiche richiedono la stessa cartella scrivibile dei template Full Time/MVP. Le foto e le loro posizioni rimangono nella memoria della pagina, separate per ID torneo/squadra; non sono inviate al server e vengono perse ricaricando la pagina. La foto parte intera e proporzionata. Trascinamento, cursori e pinch modificano solamente la foto all'interno della sua cornice. L'esportazione usa direttamente il Canvas dell'anteprima, con risoluzione nativa di 1080 × 1350.

## Verifiche

```text
php tests/presentation_templates_test.php
node tests/presentation_browser_test.js
```

Il test browser usa il Chrome locale (o `CHROME_PATH`) e una pipe privata CDP, dati di esempio dichiarati e asset originali locali. Non accede al database e non modifica dati utente. Verifica la CSP reale del sito, trascinamento mouse, pinch touch a 390 px, separazione delle foto per squadra, stabilità del template, identità distinte, asset mancanti ed uguaglianza pixel per pixel tra anteprima e PNG decodificato. Salva le anteprime di verifica in `cache/presentation-preview`, escluse da Git.

La connessione al database locale risultava rifiutata durante l'implementazione: il caricamento con tornei e squadre attuali va verificato su un ambiente con database disponibile. Le fixture dei test non vengono usate nella sezione pubblicata.
