# Emblemi originali nell'app

Squadre e tornei nelle schermate Flutter usano `OriginalEmblem`: iniziali del
nome, cerchio per le squadre, quadrato arrotondato per i tornei e una palette
deterministica indipendente dai colori dei club. Non caricano i file dei loghi.
La stessa identità mantiene colore e iniziali nelle diverse schermate.
Il testo tra parentesi viene ignorato per iniziali e colore: ad esempio
`Eredivisie (Fascia C)` usa l'emblema di `Eredivisie`. Il titolo visibile
e l'etichetta accessibile conservano il nome completo.

La sostituzione comprende home, preferiti, albo d'oro, premi, elenco e dettaglio
tornei, classifiche, squadre, partite, storico e statistiche personali, selettore
delle notifiche. Le foto delle persone e il logo Tornei Old School restano tali.

I file originali, il sito e le grafiche esportate non sono modificati.
Articoli, immagini editoriali e anteprime delle grafiche possono ancora contenere
marchi incorporati: questa modifica non li ridisegna. I nomi delle competizioni
non sono rinominati. Non costituisce una garanzia di approvazione Apple.

Prima della nuova submission occorre compilare e distribuire una nuova build e
acquisire nuovi screenshot coerenti. Questo intervento non modifica il login.

## Interruttore in Gestione

Gli amministratori trovano `Loghi originali di squadre e tornei` in Gestione.
Disattivato (valore iniziale): emblemi a iniziali. Attivato: file originali
restituiti dalle API. Non cancella o sostituisce i file e non influenza il sito
o le grafiche generate.

L'impostazione globale è salvata fuori dalla directory pubblica, nel runtime
`mobile/original-logos`. La lettura nativa è pubblica; il salvataggio richiede
il permesso admin. Nella preview il salvataggio verifica anche il token CSRF
e il ruolo corrente nel database. App e preview leggono lo stesso valore.
La schermata corrente si aggiorna dopo il salvataggio; gli altri dispositivi
lo rileggono all'avvio o al ritorno in primo piano (nel browser ricaricare).
In assenza di rete viene mantenuto il valore già letto nella sessione;
all'avvio il valore di ripiego è emblemi a iniziali.

Il deploy deve includere entrambi gli endpoint PHP e i sorgenti condivisi,
oltre alla build web ricompilata e alla nuova build mobile.

## Eccezione account

L'account autenticato `oldschooltornei@gmail.com` visualizza sempre gli emblemi
a iniziali per squadre e tornei, anche quando il flag globale abilita i loghi
originali. Il confronto email ignora maiuscole e spazi esterni. Il comportamento
si aggiorna con login, logout e cambio account, senza modificare il flag globale.
L'eccezione è indicata nel controllo riservato agli admin. È condivisa tra app
e preview; non riguarda foto, immagini editoriali o loghi incorporati in grafiche.
Il bundle Android build 14 preparato prima di questa modifica non la contiene:
per distribuirla occorre una nuova compilazione mobile.
