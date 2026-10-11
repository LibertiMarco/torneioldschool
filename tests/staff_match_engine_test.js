// Runs the website's actual lineup controller with local data and the app bridge.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname, '..');
const website = fs.readFileSync(root + '/api/statistiche_partita.php', 'utf8');
const markup = website.slice(website.indexOf('<main class="admin-wrapper">'), website.indexOf('<script>\nconst ID'));
const controller = website.slice(website.indexOf('<script>\nconst ID') + 8, website.lastIndexOf('</script>'))
  .replace(/const ID = [^\n]+;/, 'const ID = 25;');
const bridge = fs.readFileSync(root + '/app-preview/staff-engine.js', 'utf8');
const delay = () => new Promise(resolve => setTimeout(resolve, 180));
(async () => {
  const host = new JSDOM('<iframe id="staff-source"></iframe>', {url:'https://example.test/app-preview/staff-engine.php', runScripts:'outside-only'});
  let source = new JSDOM('<div id="msgBox" style="opacity:0"></div>' + markup,
    {url:'https://example.test/api/statistiche_partita.php?partitaid=25', runScripts:'outside-only'});
  const window = host.window;
  let doc = source.window.document;
  const frame = window.document.getElementById('staff-source');
  Object.defineProperty(frame, 'contentDocument', {get:() => doc});
  const messages = [], writes = [];
  window.tosStaffChannel = 'match-fixture-channel-1234567890';
  window.TosStaff = {postMessage:raw => messages.push(JSON.parse(raw))};
  const players = [
    {giocatore_id:1,squadra_id:10,lato:'casa',squadra:'Go Ahead Eagles',nome:'Marco',cognome:'Rossi',ruolo:'Portiere',is_captain:1,diffidato:1,goal:0,assist:0,autogol:0,voto:6},
    {giocatore_id:2,squadra_id:20,lato:'ospite',squadra:'Napoli',nome:'Luca',cognome:'Bianchi',ruolo:'Difensore',goal:0,assist:0,autogol:0,voto:6},
  ];
  source.window.fetch = async (url, options) => {
    if (options?.method === 'POST') {
      writes.push({action:options.body.get('azione'),match:options.body.get('partita_id'),
        finalize:options.body.get('finalizza'),rows:JSON.parse(options.body.get('stats'))});
      return {ok:true,json:async () => ({success:true,message:'Distinta salvata'})};
    }
    return {ok:true,json:async () => url.startsWith('/api/get_partita') ?
      {squadra_casa:'Go Ahead Eagles',squadra_ospite:'Napoli',data_partita:'2026-10-11',ora_partita:'20:30:00',torneo:'Brasilerao',fase:'GOLD',giocata:0} : players};
  };
  source.window.eval(controller);
  await delay();
  window.eval(bridge); frame.dispatchEvent(new window.Event('load'));
  await delay();
  const snapshot = () => messages.findLast(message => message.type === 'snapshot');
  const lineup = () => snapshot().blocks[0];
  const command = data => window.tosStaffCommand({channel:window.tosStaffChannel,document:snapshot().document,...data});
  const player = side => lineup().teams[side].players[0];
  const field = (side, stat) => player(side).fields.find(field => field.stat === stat);
  assert.equal(lineup().kind, 'match-lineup');
  assert.equal(player(0).name, 'Rossi Marco');
  assert.deepEqual(player(0).badges, ['GK','C','DIFFIDATO']);
  assert(!field(0,'presenza').checked);
  assert.equal(lineup().teams[1].name, 'Napoli');
  assert.equal(field(0,'voto').label, 'Voto', 'Explicit accessible label wins over preceding card label');
  assert(lineup().info.includes(' · 2026-10-11 | 20:30 · '));
  command({type:'click',id:lineup().teams[0].actions[0].id});
  command({type:'click',id:lineup().teams[1].actions[0].id});
  command({type:'change',id:field(0,'goal').id,value:'2'});
  command({type:'change',id:field(1,'autogol').id,value:'1'});
  command({type:'change',id:field(0,'cartellino_giallo').id,checked:true});
  command({type:'change',id:field(0,'cartellino_rosso').id,checked:true});
  command({type:'change',id:field(0,'voto').id,value:'7.5'});
  await delay();
  assert.equal(lineup().score, '3 - 0', 'Away own goals count for the home team');
  assert(field(0,'presenza').checked);
  command({type:'change',id:lineup().finalize.id,checked:true});
  command({type:'click',id:lineup().save.id});
  await delay();
  assert.equal(writes[0].action, 'save_bulk');
  assert.equal(writes[0].match, '25');
  assert.equal(writes[0].finalize, '1');
  assert.equal(writes[0].rows.length, 2);
  assert.equal(writes[0].rows[0].goal, '2');
  assert.equal(writes[0].rows[0].voto, '7.5');
  assert.equal(writes[0].rows[0].cartellino_giallo, 1);
  assert.equal(writes[0].rows[0].cartellino_rosso, 1);
  assert.equal(writes[0].rows[1].autogol, '1');
  assert.equal(lineup().notice, 'Distinta salvata');
  command({type:'click',id:lineup().teams[1].actions[1].id});
  await delay(); assert(!field(1,'presenza').checked);
  const count = writes.length;
  command({type:'click',id:lineup().save.id,document:'stale'});
  assert.equal(writes.length, count);
  doc.getElementById('lineupRoot').innerHTML = '<p>Caricamento distinta...</p>';
  await delay();
  assert(lineup().save.disabled && lineup().finalize.disabled, 'Saving is disabled while attendance is unavailable');
  source.window.close();
  source = new JSDOM(`<main><form id="formModifica">
    <input type="hidden" name="_csrf" value="match-secret">
    <label>Seleziona torneo<select id="selTorneoMod"><option value="25">Brasilerao</option></select></label>
    <label>Partita<select id="selPartitaMod"><option value="25">Eagles - Napoli</option></select></label>
    <button type="button" id="btnStatsMod">Statistiche partita</button>
    <label>Gol casa<input type="number" id="gol_casa_mod" value="0" min="0"></label>
    <label>Gol ospite<input type="number" id="gol_ospite_mod" value="0" min="0"></label>
    <label>Terminata ai rigori<input type="checkbox" id="decisa_rigori_mod"></label>
    <div id="rigoriFieldsMod" style="display:none">
      <label>Rigori casa<input type="number" id="rigori_casa_mod" value="0"></label>
      <label>Rigori ospite<input type="number" id="rigori_ospite_mod" value="0"></label>
    </div>
    <label>Data<input type="date" id="data_partita_mod" value="2026-10-11"></label>
    <label>Arbitro<input id="arbitro_mod" value="Mario"></label>
    <label>Campo aggiuntivo<input id="extra" value="Preservato"></label>
    <button type="submit">Salva modifiche</button>
  </form></main>`, {url:'https://example.test/api/gestione_partite.php',runScripts:'outside-only'});
  doc = source.window.document;
  let saved;
  doc.getElementById('decisa_rigori_mod').onchange = event => {
    doc.getElementById('rigoriFieldsMod').style.display = event.target.checked ? 'block' : 'none';
  };
  doc.getElementById('formModifica').onsubmit = event => {
    event.preventDefault(); saved = {csrf:doc.querySelector('[name="_csrf"]').value,
      goals:doc.getElementById('gol_casa_mod').value,penalties:doc.getElementById('decisa_rigori_mod').checked};
  };
  frame.dispatchEvent(new window.Event('load')); await delay();
  const form = () => snapshot().blocks[0];
  const fields = () => [...form().sections.flatMap(section => section.blocks),...form().blocks];
  const byLabel = label => fields().find(block => block.label === label);
  assert.equal(form().kind, 'match-form');
  assert.deepEqual(form().sections.map(section => section.title), ['Seleziona la partita','Risultato','Dettagli partita','Arbitro e video']);
  assert(byLabel('Gol casa').score);
  assert(!byLabel('Rigori casa'), 'Hidden penalty controls are not rendered');
  assert(byLabel('Campo aggiuntivo'), 'New controller fields remain available');
  assert(!JSON.stringify(form()).includes('match-secret'));
  command({type:'change',id:byLabel('Gol casa').id,value:'5'});
  command({type:'change',id:byLabel('Terminata ai rigori').id,checked:true});
  await delay(); assert(byLabel('Rigori casa').score);
  command({type:'click',id:byLabel('Salva modifiche').id});
  assert.deepEqual(saved,{csrf:'match-secret',goals:'5',penalties:true});
  source.window.close(); window.close();
  console.log('Website lineup: names, GK/C, attendance, own-goal score, cards, vote and bulk finalization passed through native bridge.');
  console.log('Result form: grouped fields, conditional penalties, future fields and original protected submit passed.');
})().catch(error => {console.error(error);process.exit(1)});
