const assert=require('node:assert/strict');
const fs=require('node:fs');
const {JSDOM}=require('jsdom');
const bracket=require('../tornei/cup-bracket.js');
const seeds=n=>Array.from({length:n},(_,i)=>({name:`Team ${i+1}`,label:`${i+1}ª`}));
const game=(home,away,extra={})=>({id:1,fase:'GOLD',fase_round:'QUARTI',squadra_casa:home,squadra_ospite:away,gol_casa:2,gol_ospite:0,giocata:1,...extra});
assert.deepEqual(bracket.seedOrder(8),[1,8,4,5,2,7,3,6]);
for (const size of [2,4,8,16,32,64,128]) {
  const rows=bracket.build(seeds(size),[],'GOLD');
  assert.equal(rows.length,size-1); assert.equal(rows.at(-1).bracket_slot,'F1');
}
let rows=bracket.build(seeds(8),[],'GOLD');
assert.equal(rows[4].squadra_casa,'Vincente Q1'); assert.equal(rows[4].squadra_ospite,'Vincente Q2');
assert.equal(rows[6].squadra_casa,'Vincente S1'); assert.equal(rows[6].squadra_ospite,'Vincente S2');
rows=bracket.build(seeds(6),[],'GOLD');
assert.equal(rows.filter(m=>m.bye).length,2); assert.equal(rows[4].squadra_casa,'1ª · Team 1');
rows=bracket.build(seeds(8),[game('Team 1','Team 8')],'GOLD');
assert.equal(rows.filter(m=>m.id).length,1); assert.equal(rows.filter(m=>m.template).length,6);
assert.equal(rows[4].squadra_casa,'Vincente Q1 · Team 1');
rows=bracket.build(seeds(8),[game('Team 1','Team 8',{giocata:0})],'GOLD');
assert.equal(rows[4].squadra_casa,'Vincente Q1');
rows=bracket.build(seeds(8),[game('Team 1','Team 8',{fase_leg:'ANDATA'})],'GOLD');
assert.equal(rows[4].squadra_casa,'Vincente Q1');
rows=bracket.build(seeds(8),[game('Team 1','Team 8',{fase_leg:'ANDATA'}),game('Team 8','Team 1',{id:2,fase_leg:'RITORNO',gol_casa:3})],'GOLD');
assert.equal(rows[5].squadra_casa,'Vincente Q1 · Team 8');
rows=bracket.build(seeds(8),[game('Team 1','Team 8',{gol_casa:1,gol_ospite:1,decisa_rigori:1,rigori_casa:3,rigori_ospite:4})],'GOLD');
assert.equal(rows[4].squadra_casa,'Vincente Q1 · Team 8');
rows=bracket.build(seeds(8),[game('Team 1','Team 8',{fase_round:'KO'})],'GOLD'); assert.equal(rows.filter(m=>m.id).length,1);
rows=bracket.build(seeds(8),[game('Team 1','Team 4')],'GOLD');
assert.equal(rows.filter(m=>m.fase_round==='QUARTI' && JSON.stringify(m).includes('Team 4')).length,1);
assert(rows.find(m=>m.bracket_slot==='Q2').squadra_casa.includes('Team 8'));

const script=fs.readFileSync('tornei/cup-bracket.js','utf8');
async function page(source,config,teams,info={}) {
  const dom=new JSDOM('<main><div id="playoffContainer"></div></main>',{url:'https://example.test/tornei/test.php',runScripts:'outside-only',pretendToBeVisual:true});
  // Page initialization is unrelated to this fixture; only exercise its real global definitions.
  await new Promise(resolve=>dom.window.addEventListener('load',resolve,{once:true}));
  dom.window.__TORNEO_CONFIG__=config;
  dom.window.fetch=async url=>({ok:true,json:async()=>url.includes('leggiClassifica') || url.includes('get_squadre_torneo') ? teams : url.includes('get_torneo_by_slug') ? info : {}});
  dom.window.eval(fs.readFileSync(source,'utf8')+'\n;'+script);
  await dom.window.caricaPlayoff('gold');
  return dom;
}
(async()=>{
  const teams=Array.from({length:16},(_,i)=>({id:i+1,nome:`Team ${i+1}`,punti:16-i}));
  let dom=await page('tornei/script-TorneoTemplate.js',{totale_squadre:16,qualificati_gold:8,qualificati_silver:4,qualificati_bronzo:4},teams);
  const root=dom.window.document;
  assert.equal(root.querySelectorAll('.cup-game').length,7);
  assert(root.querySelector('.cup-game').textContent.includes('8ª in classifica · Team 8'));
  root.querySelector('.cup-scroll').scrollTo=()=>{};
  root.querySelectorAll('.cup-rounds button')[1].click();
  assert.equal([...root.querySelectorAll('.cup-round')].filter(c=>!c.hidden).length,3);
  assert.equal(root.querySelectorAll('.cup-round')[1].querySelector('.cup-team').textContent,'Vincente Q1–');
  await dom.window.caricaPlayoff('silver');
  assert(root.querySelector('.cup-game').textContent.includes('9ª in classifica · Team 9'));
  assert.equal(root.querySelectorAll('.cup-game').length,3);
  await dom.window.caricaPlayoff('bronze');
  assert(root.querySelector('.cup-game').textContent.includes('13ª in classifica · Team 13'));
  dom.window.close();
  const grouped=['A','B'].flatMap(g=>Array.from({length:8},(_,i)=>({id:i+1+(g==='B'?8:0),nome:`${g}${i+1}`,punti:8-i,girone:g})));
  const config={formato:'girone',numero_gironi:2,squadre_per_girone:8,qualificati_gold:8,qualificati_silver:4,qualificati_bronzo:4};
  for (const source of ['tornei/script-TorneoTemplate.js','tornei/script-SerieB.js']) {
    dom=await page(source,config,grouped,{config,mobile_layout:{group_mode:source.includes('SerieB')?'legacy':'template'}});
    const cards=dom.window.document.querySelectorAll('.cup-game');
    assert.equal(cards.length,7,source);
    assert(cards[0].textContent.includes('1ª girone A · A1'),source);
    assert(cards[0].textContent.includes('4ª girone B · B4'),source);
    assert(cards[2].textContent.includes('1ª girone B · B1'),source);
    assert(cards[2].textContent.includes('4ª girone A · A4'),source);
    dom.window.close();
  }
  const oddTables=['A','B','C'].map(label=>({label,teams:[{nome:`${label}1`},{nome:`${label}2`}]}));
  const oddSeeds=bracket.selectSeeds(oddTables,[{key:'gold',start:1,spots:2}],'GOLD');
  for(const row of bracket.build(oddSeeds,[],'GOLD').filter(m=>m.fase_round==='QUARTI' && !m.bye)) {
    assert.notEqual(row.squadra_casa.match(/girone ([ABC])/)[1],row.squadra_ospite.match(/girone ([ABC])/)[1]);
  }
  for(const source of fs.readdirSync('tornei').filter(name=>/^script-.*\.js$/.test(name) && name!=='script-gironi-helper.js')) {
    dom=await page(`tornei/${source}`,{formato:'campionato',totale_squadre:16,qualificati_gold:8,qualificati_silver:4},teams,{mobile_layout:{legacy_gold:8,legacy_silver:4}});
    assert(dom.window.document.querySelector('.cup-game'),`Shared renderer works with ${source}`);
    dom.window.close();
  }
  // API text must remain text, never interpreted as markup; latest cup request wins.
  dom=await page('tornei/script-TorneoTemplate.js',{totale_squadre:8,qualificati_gold:8},[{nome:'<img src=x onerror=alert(1)>',punti:1}]);
  assert.equal(dom.window.document.querySelectorAll('.cup-projection img').length,0);
  const pending=[];
  dom.window.fetch=url=>new Promise(resolve=>pending.push(()=>resolve({ok:true,json:async()=>url.includes('leggiClassifica') || url.includes('get_squadre_torneo') ? teams : {}})));
  const gold=dom.window.caricaPlayoff('gold'),silver=dom.window.caricaPlayoff('silver');
  pending.splice(4).forEach(resolve=>resolve()); await silver;
  pending.forEach(resolve=>resolve()); await gold;
  assert.equal(dom.window.document.querySelector('.cup-projection h3').textContent,'Coppa Silver');
  dom.window.close();
  console.log('Cup brackets: fixed seeds, group crossings, all cups, templates, byes, saved results, two legs, penalties, safe text and stale requests passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
