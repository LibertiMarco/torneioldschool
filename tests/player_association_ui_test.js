const assert=require('node:assert/strict'),fs=require('node:fs'),{JSDOM}=require('jsdom');
const delay=()=>new Promise(resolve=>setTimeout(resolve,180));
(async()=>{
 const host=new JSDOM('<iframe id="staff-source"></iframe>',{url:'https://example.test/app-preview/staff-engine.php',runScripts:'outside-only'});
 const child=new JSDOM(`<style>.hidden{display:none}</style><main>
 <label>Operazione<select id="azione" class="operation-picker">${['crea','modifica','elimina','import','associazioni'].map(value=>`<option>${value}</option>`).join('')}</select></label>
 <form class="form-crea" data-main="crea"><h2>Crea giocatore</h2><label>Nome<input></label></form>
 <form class="form-modifica hidden" data-main="modifica"><h2>Modifica giocatore</h2></form>
 <section class="form-import hidden" data-main="import"><h2>Importa giocatori</h2></section>
 <section class="form-elimina hidden" data-main="elimina"><h2>Elimina giocatore</h2><input id="search" aria-label="Cerca giocatore"><p>Tabella</p></section>
 <section class="form-associazioni hidden" data-main="associazioni"><h2>Associazioni</h2>
 <select id="assocOperation" class="operation-picker">${['aggiungi','modifica','rimuovi'].map(value=>`<option>${value}</option>`).join('')}</select>
 <form class="assoc-form assoc-form-add"><input type="hidden" name="_csrf" value="private-csrf">
 <label>Squadra<select id="assocSquadra"><option value="12">Squadra A</option></select></label>
 <input id="assocGiocatoreSearch" value="Giocatore"><div id="assocSearchResults">${Array.from({length:10},(_,i)=>`<div class="assoc-player-result"><span class="assoc-player-name">Giocatore ${i}</span><span class="assoc-player-meta">Portiere</span><button type="button">Aggiungi ${i}</button></div>`).join('')}</div>
 <div id="assocSelectedPlayers"></div></form>
 <form class="assoc-form assoc-form-edit hidden"><label>Giocatore<select id="modAssocGiocatore"><option value="7">Mario Rossi</option></select></label><label><input type="checkbox" id="mod_assoc_capitano">Capitano della squadra</label></form>
 <form class="assoc-form assoc-form-remove hidden"><select id="remSquadra"><option value="12">Squadra A</option></select><div id="remPlayers"><label><input type="checkbox" name="giocatori_rimozione[]" value="7" data-label="Mario Rossi">Mario Rossi</label></div><p id="remCount">0 selezionati</p></form>
 </section></main>`,{url:'https://example.test/api/gestione_giocatori.php',runScripts:'outside-only'});
 const w=host.window,doc=child.window.document,frame=w.document.getElementById('staff-source');
 Object.defineProperty(frame,'contentDocument',{get:()=>doc});
 const messages=[];w.tosStaffChannel='association-fixture-channel-0123456789';w.TosStaff={postMessage:raw=>messages.push(JSON.parse(raw))};
 const source=fs.readFileSync('api/gestione_giocatori.php','utf8');
 const start=source.indexOf('function renderAssocSelectedPlayers()');
 const render=source.slice(start,source.indexOf('function clearAssocSelection()',start));
 child.window.eval(`const assocSelectedPlayers=document.getElementById('assocSelectedPlayers'); const assocSelectedPlayersMap=new Map([['7',{id:'7',nome:'Mario',cognome:'Rossi',ruolo:'Portiere'}]]); function buildPlayerLabel(p){return p.cognome+' '+p.nome;} function isPortiereRuolo(r){return r==='Portiere';} function syncAssocHiddenSelect(){} ${render}; renderAssocSelectedPlayers();`);
 function show(main,operation='aggiungi') {
  doc.getElementById('azione').value=main;doc.getElementById('assocOperation').value=operation;
  doc.querySelectorAll('[data-main]').forEach(el=>el.classList.toggle('hidden',el.dataset.main!==main));
  for(const [name,value] of [['add','aggiungi'],['edit','modifica'],['remove','rimuovi']]) doc.querySelector('.assoc-form-'+name).classList.toggle('hidden',value!==operation);
 }
 w.eval(fs.readFileSync('app-preview/staff-engine.js','utf8'));frame.dispatchEvent(new w.Event('load'));await delay();
 const snapshot=()=>messages.findLast(message=>message.type==='snapshot');
 const flat=blocks=>blocks.flatMap(block=>[block,...flat(block.blocks||[])]);
 const blocks=()=>flat(snapshot().blocks),ads=()=>blocks().filter(b=>b.kind==='staff-ad').map(b=>b.id);
 const send=value=>w.tosStaffCommand({channel:w.tosStaffChannel,document:snapshot().document,...value});
 for(const action of ['crea','modifica','import','elimina']) {
  show(action);doc.getElementById('azione').dispatchEvent(new child.window.Event('change',{bubbles:true}));await delay();
  assert.deepEqual(ads(),[`players-${action}-before`,`players-${action}-after`]);
  assert.equal(snapshot().managementTab,`players:${action}`);
 }
 show('associazioni');doc.getElementById('azione').dispatchEvent(new child.window.Event('change',{bubbles:true}));await delay();
 assert.deepEqual(ads(),['players-add-before','players-add-after']);
 const results=blocks().find(b=>b.kind==='staff-player-list');assert.equal(results.rows.length,10,'All search results remain available to scroll');
 const fields=blocks().filter(b=>b.kind==='field');
 const role=fields.find(b=>b.label==='Ruolo in squadra'),captain=fields.find(b=>b.appearance==='captain');
 assert(role && role.appearance==='choice');assert(captain);assert(!JSON.stringify(snapshot()).includes('ruolo_associa_player'));
 assert(!JSON.stringify(snapshot()).includes('private-csrf'));
 send({type:'change',id:role.id,value:'Difensore'});send({type:'change',id:captain.id,checked:true});
 const body=new child.window.FormData(doc.querySelector('.assoc-form-add'));
 assert.equal(body.get('ruolo_associa_player[7]'),'Difensore');assert.equal(body.get('capitano_associa_player'),'7');assert.equal(body.get('_csrf'),'private-csrf');
 show('associazioni','modifica');doc.getElementById('assocOperation').dispatchEvent(new child.window.Event('change',{bubbles:true}));await delay();
 assert.deepEqual(ads(),['players-edit-before','players-edit-after']);
 assert(blocks().find(b=>b.appearance==='captain'));assert(blocks().find(b=>b.label==='Giocatore').appearance==='choice');
 show('associazioni','rimuovi');doc.getElementById('assocOperation').dispatchEvent(new child.window.Event('change',{bubbles:true}));await delay();
 assert.deepEqual(ads(),['players-remove-before','players-remove-after']);
 const removal=blocks().find(b=>b.kind==='staff-player-list');assert(removal.removal);assert.equal(removal.rows[0].field.label,'Mario Rossi');
 send({type:'change',id:removal.rows[0].field.id,checked:true});assert.equal(new child.window.FormData(doc.querySelector('.assoc-form-remove')).get('giocatori_rimozione[]'),'7');
 host.window.close();child.window.close();console.log('Player UI: placements, scoped metadata, role labels, captains, searchable list models and original form payloads passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
