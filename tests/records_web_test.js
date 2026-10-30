const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
(async () => {
  const script = fs.readFileSync(path.join(__dirname,'../records.js'),'utf8');
  const dom = new JSDOM('<div id="records" data-player="7"></div>',{url:'https://example.test/record.php',runScripts:'outside-only'});
  const queries=[];
  const match={id:1,torneo:'Coppa',torneo_nome:'Coppa Old School',squadra_casa:'A',squadra_ospite:'B',gol_casa:4,gol_ospite:0,data_partita:'2026-10-01'};
  let data={coverage:'Solo dati verificati',tournaments:['Coppa'],formats:['Calcio a 5'],groups:{'Calcio a 5':{
    eligible_matches:5,verified_keeper_matches:2,goalkeeper:{appearances:2,goals_conceded:3,average:'1.50',clean_sheets:1,clean_sheet_percentage:'50.00'},
    records:{total:{label:'Partita con più gol',value:4,holders:Array.from({length:4},()=>({subject:'<script>non eseguire</script>',player_id:7,match}))}}
  }}};
  dom.window.fetch=async url=>{queries.push(url);return {ok:true,json:async()=>data};};
  dom.window.eval(script); await new Promise(resolve=>setTimeout(resolve,0));
  const doc=dom.window.document;
  assert.equal(doc.querySelectorAll('.record-card li').length,4);
  assert.equal(doc.querySelector('summary').textContent,'Altri 1 detentori');
  assert(doc.body.textContent.includes('Media: 1.50'));
  assert(doc.body.textContent.includes('Coppa Old School'));
  assert(!doc.querySelector('script'),'Contenuti mostrati come testo');
  assert(doc.querySelector('a').href.includes('/tornei/partita_eventi.php?'));
  const select=doc.querySelector('select'); select.value='Coppa'; select.dispatchEvent(new dom.window.Event('change'));
  await new Promise(resolve=>setTimeout(resolve,0));
  assert(queries.at(-1).includes('torneo=Coppa'));
  doc.querySelector('#records').dataset.keepersOnly='1';
  dom.window.eval(script); await new Promise(resolve=>setTimeout(resolve,0));
  assert.equal(doc.querySelectorAll('.records-grid').length,0);
  data={...data,groups:{}};dom.window.eval(script); await new Promise(resolve=>setTimeout(resolve,0));
  assert(doc.body.textContent.includes('Nessun dato verificato'));
  dom.window.close();
  console.log('Record web: pari merito espandibili, filtri, link, statistiche condivise e stati vuoti superati.');
})().catch(error=>{console.error(error);process.exit(1);});
