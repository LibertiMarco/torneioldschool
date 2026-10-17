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
  const messages = [], writes = [];
  window.tosStaffChannel = 'fixture-channel-012345678901234567890';
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
  let photoTransform;
  child.window.PhotoTouchEditor = {
    describe: () => ({type:'ft',width:1080,height:1350,sx:1,sy:1,dx:0,dy:0}),
    apply: (id,values) => {photoTransform=values;return true;},
  };
  window.eval(bridge);
  frame.dispatchEvent(new window.Event('load'));
  await delay();
  const snapshot = () => messages.findLast(message => message.type === 'snapshot');
  const flat = (blocks) => blocks.flatMap(block => [block, ...flat(block.blocks || []), ...(block.rows || []).flatMap(row => row.cells.flatMap(cell => flat(cell.blocks)))]);
  const find = (kind, label) => flat(snapshot().blocks).find(block => block.kind === kind && block.label === label);
  const command = data => window.tosStaffCommand({channel:window.tosStaffChannel, document:snapshot().document, ...data});
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
  command({type:'photoTransform',id:image.id,values:{sx:1.5,sy:1.5,dx:40,dy:50}});
  assert.equal(photoTransform.dx,40,'Native transforms reach the original compositor');
  command({type:'export', id:image.id, name:'coppa.png'});
  await delay(); assert.equal(messages.findLast(message => message.type === 'download').name, 'coppa.png');
  await child.window.navigator.share({files:[new window.File(['png'],'uno.png',{type:'image/png'}),new window.File(['png'],'due.png',{type:'image/png'})]});
  assert.deepEqual(messages.findLast(message => message.type === 'download').files.map(file=>file.name), ['uno.png','due.png']);
  child.window.close(); window.close();
  console.log('Staff engine: native snapshots, dependent controls, protected submit, confirmation, modal, export and stale commands passed.');
})().catch(error => {console.error(error); process.exit(1);});
