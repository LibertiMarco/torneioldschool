// NODE_PATH=/path/to/node_modules node tests/staff_engine_test.js (jsdom 26)
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const delay = () => new Promise(resolve => setTimeout(resolve, 180));
const bridge = fs.readFileSync(path.join(__dirname, '../app-preview/staff-engine.js'), 'utf8');
(async () => {
  const dom = new JSDOM('<iframe id="staff-source"></iframe>', {url:'https://example.test/app-preview/staff-engine.php', runScripts:'outside-only'});
  const window = dom.window, frame = window.document.getElementById('staff-source');
  const child = new JSDOM(`<main><h1>Tornei</h1><div class="tab-buttons"><button id="create" class="active">Crea</button><button id="edit">Modifica</button></div>
    <form id="form"><input name="_csrf" type="hidden" value="secret-csrf"><input type="hidden" name="record_id" value="15">
      <label for="name">Nome torneo</label><input id="name" name="nome" required>
      <label>Torneo<select id="tournament"><option value="">Scegli…</option><option value="1">Brasilerao</option></select></label>
      <label>Squadra<select id="team"><option value="">Attendi…</option></select></label>
      <label>Partita<select id="selPartitaMod"><option value="42">Home - Away</option></select></label>
      <label>Partita giocata<select id="selPartitaGioc"><option value="43">Played Home - Played Away</option></select></label>
      <label>Partita arbitro e link<select id="selPartitaLink"><option value="43">Played Home - Played Away</option></select></label>
      <input type="password" value="never-share-password"><fieldset disabled><label>Disabilitato<input id="disabled" value="read-only"></label></fieldset>
      <label>Immagine<input id="photo" type="file" style="display:none"></label>
      <div id="editor" contenteditable="true" role="textbox"><p>Testo <strong>bold</strong></p></div>
      <button type="submit">Salva</button><button type="button" id="delete">Elimina</button>
    </form><section id="hidden" style="display:none"><input value="hidden-secret"></section>
    <table><thead><tr><th data-sort="nome">Nome</th><th>Azioni</th></tr></thead><tbody><tr><td>Marco</td><td><button>Modifica giocatore</button></td></tr></tbody></table>
    <canvas width="1080" height="1350"></canvas>
    </main><div class="confirm-modal" style="display:none"><p>Eliminare?</p><button>Annulla</button><button>Conferma</button></div>`, {url:'https://example.test/api/gestione_tornei.php?embed=1', runScripts:'outside-only'});
  Object.defineProperty(frame, 'contentDocument', {get: () => child.window.document});
  const document = child.window.document;
  // Website cookie UI must not replace the native management form.
  document.body.insertAdjacentHTML('beforeend', '<div id="tos-consent-banner"><div role="dialog">Privacy e cookie<button>Accetta</button></div></div>');
  const messages = [], writes = [];
  window.tosStaffChannel = 'fixture-channel-012345678901234567890';
  window.tosStaffCapabilities={checkPicker:true};
  window.TosStaff = {postMessage: raw => messages.push(JSON.parse(raw))};
  window.HTMLCanvasElement.prototype.getContext = () => ({drawImage(){}});
  child.window.HTMLCanvasElement.prototype.getContext = () => ({drawImage(){}});
  child.window.HTMLCanvasElement.prototype.toDataURL = () => 'data:image/png;base64,AA==';
  child.window.HTMLCanvasElement.prototype.toBlob = callback => callback(new window.Blob(['png'], {type:'image/png'}));
  document.getElementById('form').addEventListener('submit', event => {
    event.preventDefault(); writes.push({csrf:document.querySelector('[name="_csrf"]').value, name:document.getElementById('name').value});
  });
  document.getElementById('tournament').addEventListener('change', () => { document.getElementById('team').innerHTML = '<option value="25">Go Ahead Eagles</option>'; });
  document.getElementById('delete').onclick = () => { if (child.window.confirm('Eliminare il torneo?')) writes.push('delete'); };
  document.getElementById('edit').onclick = () => { document.getElementById('edit').classList.add('active'); document.getElementById('create').classList.remove('active'); };
  let photoTransform, photoPointer;
  child.window.PhotoTouchEditor = {
    describe: () => ({type:'ft',width:1080,height:1350,sx:1,sy:1,dx:0,dy:0}),
    apply: (id,values) => {photoTransform=values;return true;},
    pointer: (id,values) => {photoPointer=values;return true;},
  };
  window.eval(bridge);
  frame.dispatchEvent(new window.Event('load'));
  await delay();
  const snapshot = () => messages.findLast(message => message.type === 'snapshot');
  const flat = (blocks) => blocks.flatMap(block => [block, ...flat(block.blocks || []), ...(block.rows || []).flatMap(row => row.cells.flatMap(cell => flat(cell.blocks)))]);
  const find = (kind, label) => flat(snapshot().blocks).find(block => block.kind === kind && block.label === label);
  const command = data => window.tosStaffCommand({channel:window.tosStaffChannel, document:snapshot().document, ...data});
  assert.equal(snapshot().modal, false, 'Cookie dialog is not a staff modal');
  assert.equal(find('field','Partita').appearance, 'match');
  assert.equal(find('field','Partita giocata').appearance, 'match');
  assert.equal(find('field','Partita arbitro e link').appearance, 'match');
  assert.equal(find('field','Partita').options[0].label, 'Home - Away');
  assert(!JSON.stringify(snapshot()).includes('Privacy e cookie'));
  assert(!JSON.stringify(snapshot()).includes('secret-csrf'));
  assert(!JSON.stringify(snapshot()).includes('hidden-secret'));
  assert(!JSON.stringify(snapshot()).includes('never-share-password'));
  assert(find('field','Disabilitato').disabled);
  assert(find('field','Immagine'), 'Hidden custom file input stays available natively');
  assert(flat(snapshot().blocks).find(block => block.type === 'rich').value.includes('<strong>bold</strong>'), 'Rich editor keeps HTML');
  command({type:'change', id:find('field','Nome torneo').id, value:'Coppa nuova'});
  command({type:'change', id:find('field','Torneo').id, value:'1'});
  await delay();
  assert.equal(find('field','Squadra').options[0].label, 'Go Ahead Eagles');
  command({type:'click', id:find('action','Salva').id});
  assert.deepEqual(writes[0], {csrf:'secret-csrf', name:'Coppa nuova'});
  command({type:'click', id:find('action','Elimina').id});
  assert.equal(writes.length, 1, 'Confirmation never silently submits');
  const confirm = messages.findLast(message => message.type === 'confirm');
  assert.equal(confirm.message, 'Eliminare il torneo?');
  command({...confirm.action, approved:confirm.message});
  assert.equal(writes[1], 'delete');
  command({type:'click', id:find('action','Modifica').id});
  await delay(); assert(find('action','Modifica').selected);
  const count = writes.length;
  command({type:'click', id:find('action','Salva').id, document:'stale'});
  window.tosStaffCommand({type:'click', id:find('action','Salva').id, channel:'wrong', document:snapshot().document});
  assert.equal(writes.length, count, 'Stale and wrong-channel commands rejected');
  command({type:'change', id:find('field','Disabilitato').id, value:'changed'});
  assert.equal(document.getElementById('disabled').value, 'read-only');
  const fileInput = document.getElementById('photo');
  Object.defineProperty(fileInput, 'files', {value:[], writable:true});
  child.window.DataTransfer = class {
    constructor() { this.files=[]; this.items={add:file => this.files.push(file)}; }
  };
  const fileId = find('field','Immagine').id;
  command({type:'fileBegin', id:fileId, files:[{name:'capitani.png',mime:'image/png'}]});
  command({type:'fileChunk', id:fileId, index:0, data:'cG5n'});
  command({type:'fileEnd', id:fileId});
  await delay();
  assert.equal(fileInput.files[0].name, 'capitani.png');
  assert.equal(fileInput.files[0].size, 3);
  assert.deepEqual(find('field','Immagine').files, ['capitani.png']);
  document.querySelector('.confirm-modal').style.display = 'flex';
  await delay(); assert(snapshot().modal); assert(!find('field','Nome torneo'));
  document.querySelector('.confirm-modal').style.display = 'none';
  await delay();
  const image = flat(snapshot().blocks).find(block => block.kind === 'image');
  assert.equal(image.photo.type,'ft','Photo editing metadata crosses the existing channel');
  assert.equal(image.pointerEditor,true,'Native covers reuse the website pointer editor');
  command({type:'canvasPointer',id:image.id,event:'pointerdown',pointer:9,x:0.5,y:0.5,handle:'se'});
  assert.equal(photoPointer.handle,'se','Native resize handle reaches the website editor');
  command({type:'photoTransform',id:image.id,values:{sx:1.5,sy:1.5,dx:40,dy:50}});
  assert.equal(photoTransform.dx,40,'Native transforms reach the original compositor');
  command({type:'export', id:image.id, name:'coppa.png'});
  await delay(); assert.equal(messages.findLast(message => message.type === 'download').name, 'coppa.png');
  await child.window.navigator.share({files:[new window.File(['png'],'uno.png',{type:'image/png'}),new window.File(['png'],'due.png',{type:'image/png'})]});
  assert.deepEqual(messages.findLast(message => message.type === 'download').files.map(file=>file.name), ['uno.png','due.png']);
  child.reconfigure({url:'https://example.test/api/statistiche_partita.php?partitaid=1'});
  document.body.innerHTML = `<main><div id="partitaInfo"><b>Home - Away</b><br>2026-11-09 | 20:30<br><span>Coppa - REGULAR</span></div><form id="lineupForm"><div id="lineupRoot"></div></form>
    <details id="recordsStaff" open><summary>Verifica Record</summary><form id="recordVerification">
      <input type="hidden" name="records_csrf" value="records-secret">
      <label>Gol subiti<input id="keeperGoals" type="number" name="keeper_goals[7]" value="0"></label>
      <button type="submit">Salva verifica Record e portieri</button></form></details></main>`;
  let keeperWrite;
  document.querySelector('#recordVerification').addEventListener('submit', event => {
    event.preventDefault(); keeperWrite = {goals:document.querySelector('#keeperGoals').value,csrf:document.querySelector('[name="records_csrf"]').value};
  });
  frame.dispatchEvent(new window.Event('load')); await delay();
  assert(snapshot().blocks.some(block=>block.kind==='match-lineup'));
  assert.equal(snapshot().blocks.find(block=>block.kind==='match-lineup').info, '2026-11-09 | 20:30');
  assert(find('field','Gol subiti'), 'Portieri disponibili insieme alla distinta nativa');
  assert(!JSON.stringify(snapshot()).includes('records-secret'));
  command({type:'change',id:find('field','Gol subiti').id,value:'2'});
  command({type:'click',id:find('action','Salva verifica Record e portieri').id}); await delay();
  assert.equal(keeperWrite.csrf,'records-secret');
  assert.equal(keeperWrite.goals,'2');
  child.reconfigure({url:'https://example.test/api/gestione_partite.php'});
  document.body.innerHTML = '<main><section id="matchCalendar"><button type="button">Successivo</button></section></main>';
  const calendar = document.getElementById('matchCalendar');
  calendar.tosCalendarModel = {title:'Campo 1', days:[{date:'2026-11-09',label:'lun 9 nov',events:[{time:'19:30',title:'A – B',draft:false}]}],firstHour:16,lastHour:22};
  let calendarClicks = 0;
  calendar.querySelector('button').onclick = () => calendarClicks++;
  frame.dispatchEvent(new window.Event('load')); await delay();
  const calendarBlock = flat(snapshot().blocks).find(block=>block.kind==='match-calendar');
  assert.equal(calendarBlock.days[0].events[0].title,'A – B');
  command({type:'click',id:calendarBlock.actions[0].id}); await delay();
  assert.equal(calendarClicks,1,'Native calendar navigation uses the website controller');
  const card = (form, title) => `<div class="form-card"><h3>${title}</h3><form id="${form}"><label>Nome<input value="fixture"></label></form></div>`;
  const tabs = {
    crea: card('formCrea','Crea partite').replace('</div>', '<section id="matchCalendar"></section></div>'),
    modifica: card('formModifica','Modifica partita') + card('formRipristinaGiocata','Modifica partita giocata') + card('formLinkArbitro','Modifica arbitro e link'),
    elimina: card('formElimina','Elimina partita'),
    esitate: card('formPresenzeEsitate','Presenze partite esitate'),
    campi: card('formCampoCrea','Gestione campi'),
  };
  document.body.innerHTML = '<main><div class="tab-buttons">' + Object.keys(tabs).map(tab=>`<button type="button">${tab}</button>`).join('') + '</div>'
    + Object.entries(tabs).map(([tab,html])=>`<section class="tab-section" data-tab="${tab}" style="display:${tab==='crea'?'block':'none'}">${html}</section>`).join('') + '</main>';
  document.getElementById('matchCalendar').tosCalendarModel = {title:'Campo',days:[],firstHour:19,lastHour:22};
  for (const button of document.querySelectorAll('.tab-buttons button')) {
    button.onclick = () => document.querySelectorAll('.tab-section').forEach(section=>section.style.display=section.dataset.tab===button.textContent?'block':'none');
  }
  frame.dispatchEvent(new window.Event('load')); await delay();
  const placements = () => flat(snapshot().blocks).filter(block=>block.kind==='staff-ad').map(block=>block.id);
  assert.equal(snapshot().managementTab,'crea');
  assert.deepEqual(placements(),['create-before','calendar-before','calendar-after']);
  command({type:'change', id:find('field','Nome').id, value:'Updated'}); await delay();
  assert.deepEqual(placements(),['create-before','calendar-before','calendar-after'],'Field edits preserve placements');
  for (const [tab,expected] of Object.entries({
    modifica:['modify-before','played-before','links-before','links-after'],
    elimina:['delete-before','delete-after'],
    esitate:['attendance-before','attendance-after'],
    campi:['fields-before','fields-after'],
    crea:['create-before','calendar-before','calendar-after'],
  })) {
    command({type:'click',id:find('action',tab).id}); await delay();
    assert.equal(snapshot().managementTab,tab);
    assert.deepEqual(placements(),expected,`Ad positions for ${tab}`);
  }
  child.reconfigure({url:'https://example.test/api/gestione_giocatori.php'});
  document.body.innerHTML = '<main><label>Operazione<select id="azione" class="operation-picker"><option value="crea">Aggiungi giocatore</option><option value="modifica">Modifica giocatore</option></select></label><div id="selectedOperation"></div></main>';
  let selectedOperation = '';
  document.getElementById('azione').onchange = event => selectedOperation = event.target.value;
  frame.dispatchEvent(new window.Event('load')); await delay();
  assert.equal(find('field','Operazione').appearance,'operation');
  command({type:'change',id:find('field','Operazione').id,value:'modifica'}); await delay();
  assert.equal(selectedOperation,'modifica','Operation picker invokes the original change handler');
  child.reconfigure({url:'https://example.test/api/gestione_tornei.php'});
  document.body.innerHTML = '<style>.hidden{display:none}</style><main><div class="admin-btn-group">'
    + ['crea','modifica','elimina'].map(name=>`<button type="button" class="action-toggle ${name==='crea'?'active':''}" data-action="${name}">${name}</button>`).join('')
    + '</div>' + ['crea','modifica','elimina'].map(name=>`<form class="form-${name} ${name==='crea'?'':'hidden'}"><input type="hidden" name="_csrf" value="private-tournament-csrf"><label>Nome<input></label></form>`).join('') + '</main>';
  for (const button of document.querySelectorAll('.action-toggle')) button.onclick = () => {
    document.querySelectorAll('.action-toggle').forEach(node=>node.classList.toggle('active',node===button));
    for(const name of ['crea','modifica','elimina']) document.querySelector('.form-'+name).classList.toggle('hidden',name!==button.dataset.action);
  };
  frame.dispatchEvent(new window.Event('load')); await delay();
  for(const operation of ['crea','modifica','elimina','crea']) {
    command({type:'click',id:find('action',operation).id}); await delay();
    assert.equal(snapshot().managementTab,`tournaments:${operation}`);
    assert.deepEqual(placements(),[`tournaments-${operation}-before`,`tournaments-${operation}-after`]);
    assert(!JSON.stringify(snapshot()).includes('private-tournament-csrf'));
  }
  command({type:'change',id:find('field','Nome').id,value:'Coppa nuova'}); await delay();
  assert.deepEqual(placements(),['tournaments-crea-before','tournaments-crea-after']);
  child.reconfigure({url:'https://example.test/api/gestione_squadre.php'});
  document.body.innerHTML='<main><div class="panel-card" data-section="crea"><label>Riutilizza scudetto esistente (opzionale)</label><input id="logo_esistente_search" placeholder="Cerca squadra"><small id="logo_esistente_feedback">Filtro</small><select id="logo_esistente"><option value="">Nessuno</option><option value="8">Squadra demo</option></select><label>Nome<input id="crea_nome"></label></div></main>';
  document.querySelector('#logo_esistente').onchange=()=>{document.querySelector('#crea_nome').value='Squadra demo';};
  frame.dispatchEvent(new window.Event('load'));await delay();
  assert.deepEqual(placements(),['teams-crea-before','teams-crea-after']);
  assert(!JSON.stringify(snapshot()).includes('Cerca squadra'));
  command({type:'change',id:find('field','Riutilizza squadra').id,value:'8'});await delay();
  assert.equal(find('field','Nome').value,'Squadra demo');
  child.reconfigure({url:'https://example.test/api/crea_giornata_automatica.php'});
  document.body.innerHTML='<main><label>Consenti partite di ritorno<input id="allowReturnInput" type="checkbox"></label><div id="teamsList"><label>Squadra A<input type="checkbox" checked value="1"></label><label>Squadra B<input type="checkbox" checked value="2"></label></div><div class="auto-availability-row"><label>09/11 - 19:00<input type="checkbox" checked></label><label>10/11 - 20:00<input type="checkbox"></label></div></main>';
  let observedSelection;
  document.querySelector('#teamsList').onchange=()=>{observedSelection=Array.from(document.querySelectorAll('#teamsList input')).map(input=>input.checked);};
  frame.dispatchEvent(new window.Event('load'));await delay();
  assert.deepEqual(placements(),['crea_giornata_automatica-before','crea_giornata_automatica-after']);
  assert.equal(find('field','Consenti partite di ritorno').appearance,'toggle');
  const picker=flat(snapshot().blocks).find(block=>block.kind==='staff-check-picker' && block.title==='Squadre che giocano');
  assert.deepEqual(picker.options.map(row=>row.checked),[true,true]);
  command({type:'check-selection',id:picker.id,selected:[picker.options[1].id]});await delay();
  assert.deepEqual(observedSelection,[false,true],'One controller event sees the complete selected set');
  window.tosStaffCapabilities={};frame.dispatchEvent(new window.Event('load'));await delay();
  assert(!flat(snapshot().blocks).some(block=>block.kind==='staff-check-picker'),'Installed older clients receive supported fields');
  assert(flat(snapshot().blocks).some(block=>block.kind==='expansion' && block.title==='Squadre che giocano'));
  child.window.close(); window.close();
  console.log('Staff engine: native snapshots, dependent controls, protected submit, confirmation, modal, export and stale commands passed.');
})().catch(error => {console.error(error); process.exit(1);});
