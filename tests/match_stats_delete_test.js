const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync(__dirname + '/../api/statistiche_partita.php', 'utf8');
const markup = source.slice(source.indexOf('<main class="admin-wrapper">'), source.indexOf('<script>\nconst ID'));
const script = source.slice(source.indexOf('<script>\nconst ID') + 8, source.lastIndexOf('</script>')).replace(/const ID = [^\n]+;/, 'const ID = 25;');
(async () => {
  const dom = new JSDOM('<div id="msgBox"></div>' + markup, {url:'https://example.test',runScripts:'outside-only'});
  const w = dom.window;
  let stats = [{id:91,giocatore_id:7,nome:'Mario',cognome:'<Rossi>',squadra:null,voto:8,goal:1}];
  let allow = false, fail = false;
  const writes = [];
  w.confirm = () => allow;
  w.fetch = async (url, options) => {
    if (options?.method === 'POST') {
      writes.push(Object.fromEntries(options.body.entries()));
      if (fail) return {ok:false,json:async()=>({error:'Errore prova'})};
      stats = [];
      return {ok:true,json:async()=>({success:true})};
    }
    return {ok:true,json:async()=>url.startsWith('/api/get_partita') ? {squadra_casa:'Casa',squadra_ospite:'Ospite',giocata:1} : url.includes('azione=list&') ? stats : []};
  };
  w.eval(script);
  const settle = () => new Promise(resolve => setTimeout(resolve, 30));
  await settle();
  const root = w.document.getElementById('savedStatsRoot');
  assert.match(root.textContent, /<Rossi> Mario/);
  assert.match(root.textContent, /Squadra non associata/);
  assert.equal(root.querySelector('rossi'), null);
  root.querySelector('button').click();
  await settle();
  assert.equal(writes.length, 0, 'Cancel must not delete');
  allow = true; fail = true;
  root.querySelector('button').click();
  await settle();
  assert.equal(root.querySelector('button').disabled, false);
  assert.match(w.document.getElementById('msgBox').textContent, /Errore prova/);
  fail = false;
  root.querySelector('button').click();
  await settle();
  assert.deepEqual(writes.at(-1), {azione:'delete',id:'91',partita_id:'25'});
  assert.match(root.textContent, /Nessuna statistica/);
  assert.equal(w.document.getElementById('saveLineup').disabled, false);
  dom.window.close();
  console.log('Detached player visible; escaped labels, cancel, failure and scoped deletion passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
