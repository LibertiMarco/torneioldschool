const assert = require('assert').strict;
const fs = require('fs');
const {JSDOM} = require('jsdom');
(async () => {
  const host = new JSDOM('<iframe id="staff-source"></iframe>', {url:'https://example.test/app-preview/staff-engine.php', runScripts:'outside-only'});
  const child = new JSDOM(`<main><h3>Richieste di collegamento</h3><table><thead><tr><th>Account</th><th>Giocatore</th><th>Gestisci</th></tr></thead><tbody><tr>
    <td>Mario Rossi · mario@example.test</td><td>Mario Rossi</td><td><form method="POST">
    <input type="hidden" name="_csrf" value="secret-token"><input type="hidden" name="richiesta_id" value="42">
    <label for="reason">Motivo del rifiuto (facoltativo)</label><input id="reason" name="motivo">
    <button type="submit" name="azione" value="accetta_richiesta">Accetta e collega</button>
    <button type="submit" name="azione" value="rifiuta_richiesta">Rifiuta richiesta</button>
    </form></td></tr></tbody></table></main>`, {url:'https://example.test/api/gestione_account_giocatore.php', runScripts:'outside-only'});
  const w = host.window, doc = child.window.document, frame = w.document.getElementById('staff-source');
  Object.defineProperty(frame, 'contentDocument', {get: () => doc});
  const messages = [], posts = [];
  w.tosStaffChannel = 'player-link-admin-channel-0123456789';
  w.TosStaff = {postMessage: raw => messages.push(JSON.parse(raw))};
  let selected;
  doc.querySelector('form').addEventListener('click', e => {if (e.target.tagName === 'BUTTON') selected = e.target.value;});
  doc.querySelector('form').addEventListener('submit', e => {
    e.preventDefault(); posts.push({azione: selected, richiesta_id:doc.querySelector('[name="richiesta_id"]').value,
      motivo:doc.getElementById('reason').value, _csrf:doc.querySelector('[name="_csrf"]').value});
  });
  w.eval(fs.readFileSync('app-preview/staff-engine.js', 'utf8'));
  frame.dispatchEvent(new w.Event('load'));
  await new Promise(r => setTimeout(r, 200));
  const snapshot = [...messages].reverse().find(m => m.type === 'snapshot');
  const flat = blocks => blocks.flatMap(b => [b, ...flat(b.blocks || []), ...(b.rows || []).flatMap(row => row.cells.flatMap(cell => flat(cell.blocks)))]);
  const blocks = flat(snapshot.blocks);
  assert(!JSON.stringify(snapshot).includes('secret-token'));
  const reason = blocks.find(b => b.kind === 'field' && b.label === 'Motivo del rifiuto (facoltativo)');
  const accept = blocks.find(b => b.kind === 'action' && b.label === 'Accetta e collega');
  const reject = blocks.find(b => b.kind === 'action' && b.label === 'Rifiuta richiesta');
  assert(reason && accept && reject && reject.danger, 'Admin actions and rejection note are available natively');
  const send = data => w.tosStaffCommand({channel:w.tosStaffChannel, document:snapshot.document, ...data});
  send({type:'change', id:reason.id, value:'Account non corrispondente'});
  send({type:'click', id:reject.id});
  assert.deepEqual(posts[0], {azione:'rifiuta_richiesta', richiesta_id:'42', motivo:'Account non corrispondente', _csrf:'secret-token'});
  send({type:'click', id:accept.id});
  assert.equal(posts[1].azione, 'accetta_richiesta');
  assert.equal(posts[1].richiesta_id, '42');
  console.log('Player link admin bridge: review actions, reason and private CSRF passed.');
  host.window.close(); child.window.close();
})().catch(error => {console.error(error); process.exitCode=1;});
