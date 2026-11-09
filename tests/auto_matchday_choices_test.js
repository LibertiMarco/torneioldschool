const assert=require('node:assert/strict'),fs=require('node:fs');const {JSDOM}=require('jsdom');
const source=fs.readFileSync('api/crea_giornata_automatica.php','utf8');
const dom=new JSDOM('<div class="auto-availability-team" data-team-id="1"><div id="rules"></div></div>',{runScripts:'outside-only'});
const functions=['escapeHtml','escapeAttr','formatAvailabilityDateLabel','formatAvailabilityTimeLabel','normalizeAvailabilityDates','normalizeAvailabilityTimes','snapshotAvailabilityFromDom','mergeAvailabilityRules','addAvailabilityRow'].map(name=>{
  const start=source.indexOf(`  function ${name}(`);assert(start>=0,name);const end=source.indexOf('\n  }',start+12);assert(end>start,name);return source.slice(start,end+4);
}).join('\n');
dom.window.eval('var state={slots:[{data:"2026-11-09",ora:"19:00"},{data:"2026-11-09",ora:"20:00"},{data:"2026-11-10",ora:"19:00"},{data:"2026-11-10",ora:"20:00"}]};'+functions);
const rules=[{dates:['2026-11-09'],times:['19:00']},{dates:['2026-11-10'],times:['20:00']}];
const merged=dom.window.mergeAvailabilityRules(rules);
assert.deepEqual(Array.from(merged.slots),['2026-11-09|19:00','2026-11-10|20:00']);
dom.window.addAvailabilityRow(dom.window.document.getElementById('rules'),merged);
const options=[...dom.window.document.querySelectorAll('[data-role="availability-slot-option"]')];assert.equal(options.length,4);
assert.deepEqual(options.filter(input=>input.checked).map(input=>input.value),['2026-11-09|19:00','2026-11-10|20:00']);
assert.deepEqual(JSON.parse(JSON.stringify(dom.window.snapshotAvailabilityFromDom()['1'])),rules.map(rule=>({...rule,preferred_times:[]})));
assert.equal(dom.window.document.querySelectorAll('[data-role="preferred-time-option"],[data-role="availability-date-option"]').length,0);
assert(dom.window.document.querySelector('details.auto-slot-picker summary'));
dom.window.close();console.log('Automatic matchday choices: bounded date/time picklist, exact pairs, preserved selections and no preference-day controls passed.');
