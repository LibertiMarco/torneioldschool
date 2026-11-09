const assert = require('node:assert/strict');
const fs = require('node:fs');
const {JSDOM} = require('jsdom');
const dom = new JSDOM(`<form id="formCrea">
  <input type="hidden" name="_csrf" value="fixture-token">
  <select id="campoCrea" name="campo"><option>Campo 1</option></select>
  <input type="date" id="batchDate" name="data_partita">
  <div id="batchRows"></div><button type="button" id="batchAdd">Aggiungi partita</button>
  <button type="submit" id="batchSave"></button><input id="batchCount" type="hidden" name="partite_count">
</form><section id="matchCalendar"></section>`, {url:'https://example.test/api/gestione_partite.php', runScripts:'outside-only'});
const win = dom.window, doc = win.document;
win.eval(fs.readFileSync(require('node:path').join(__dirname, '../api/match-batch.js'), 'utf8'));
const matches = [{data_partita:'2026-11-09', ora_partita:'18:30:00', squadra_casa:'Esistente', squadra_ospite:'Ospite', torneo:'altro-torneo', fase:'REGULAR', giornata:2, campo:'Campo 1'}];
const initial = {data_partita:'2026-11-09', partite:[{squadra_casa:'A',squadra_ospite:'B',ora_partita:'19:30'}]};
const controller = win.TosMatchBatch.mount({document:doc, matches:()=>matches, availableTeams:()=>['A','B','C','D'], initial});
const model = () => doc.getElementById('matchCalendar').tosCalendarModel;
const values = row => [...doc.querySelectorAll('.batch-match-row')][row].querySelectorAll('select,input');
assert.equal(doc.getElementById('batchCount').value, '1');
assert(model().days.flatMap(d=>d.events).some(e=>e.title==='Esistente – Ospite' && !e.draft), 'Other tournaments on the same field are visible');
assert(model().days.flatMap(d=>d.events).some(e=>e.title==='A – B' && e.draft), 'Drafts are visible');
doc.getElementById('batchAdd').click();
assert.equal(doc.getElementById('batchCount').value, '2');
assert([...values(1)[0].options].find(o=>o.value==='A').disabled, 'Cannot reuse a team selected in another row');
for (const [input,value] of [[values(1)[0],'C'],[values(1)[1],'D'],[values(1)[2],'20:30']]) { input.value=value; input.dispatchEvent(new win.Event('change',{bubbles:true})); }
const payload = new win.FormData(doc.getElementById('formCrea'));
assert.equal(payload.get('partite[0][squadra_casa]'), 'A');
assert.equal(payload.get('partite[1][squadra_casa]'), 'C');
assert.equal(payload.get('partite[1][ora_partita]'), '20:30');
assert.equal(payload.get('_csrf'), 'fixture-token');
assert.equal(model().days.flatMap(d=>d.events).filter(e=>e.draft).length,2);
const buttons = [...doc.querySelectorAll('.calendar-toolbar button')];
buttons.find(b=>b.textContent==='Giorno').click(); assert.equal(model().days.length,1);
buttons.find(b=>b.textContent==='Successivo').click(); assert.equal(model().days[0].date,'2026-11-10');
assert.equal(doc.getElementById('batchDate').value,'2026-11-09','Browsing must not move draft matches');
buttons.find(b=>b.textContent==='Precedente').click(); assert.equal(model().days[0].events.length,3);
doc.querySelectorAll('.batch-match-row')[1].querySelector('button').click();
assert.equal(doc.getElementById('batchCount').value,'1');
controller.saved();
assert.equal(values(0)[0].value,''); assert.equal(doc.getElementById('campoCrea').value,'Campo 1');
assert.equal(doc.getElementById('batchDate').value,'2026-11-09');
dom.window.close();
console.log('Batch rows, payload, field calendar, navigation and draft reset passed.');
