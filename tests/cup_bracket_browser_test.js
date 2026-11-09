const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const http=require('node:http');
const {chromium}=require('playwright-core');
const teams=Array.from({length:16},(_,i)=>({id:i+1,nome:`Squadra ${i+1}`,punti:16-i}));
const fixture=`<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/style.css"></head><body>
<main style="width:100%;max-width:1240px;margin:24px auto;padding:16px"><h1>Classifica</h1><label>Coppa <select id="cup"><option value="gold">Coppa Gold</option><option value="silver">Coppa Silver</option><option value="bronze">Coppa Bronzo</option></select></label><div id="playoffContainer"></div></main>
<!-- Same deferred ordering as the shared PHP header followed by the tournament script. -->
<script src="/tornei/cup-bracket.js" defer></script>
<script>const TORNEO='fixture';const CONFIG={};const FORMATO_TORNEO='campionato';
const SINGLE_CUP_RANGES=[{key:'gold',start:1,spots:8},{key:'silver',start:9,spots:4},{key:'bronze',start:13,spots:4}];
function caricaPlayoff(){throw new Error('Original renderer should have been replaced');}
document.addEventListener('DOMContentLoaded',()=>{caricaPlayoff('gold');document.getElementById('cup').onchange=e=>caricaPlayoff(e.target.value);});</script></body></html>`;
const server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/tornei/fixture.php') {res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(fixture);}
  if(url.pathname.startsWith('/api/')) {
    res.setHeader('Content-Type','application/json');
    return res.end(JSON.stringify(url.pathname.includes('leggiClassifica') || url.pathname.includes('get_squadre_torneo') ? teams : url.pathname.includes('get_torneo_by_slug') ? {mobile_layout:{}} : {'3':[{id:123,fase:'GOLD',fase_round:'QUARTI',squadra_casa:'Squadra 1',squadra_ospite:'Squadra 8',gol_casa:2,gol_ospite:0,giocata:1,data_partita:'2026-11-09',ora_partita:'20:00:00',ha_statistiche_giocatori:1}]}));
  }
  if(['/style.css','/tornei/cup-bracket.js'].includes(url.pathname)) {
    res.setHeader('Content-Type',url.pathname.endsWith('.js')?'text/javascript; charset=utf-8':'text/css');
    return res.end(fs.readFileSync(path.join(process.cwd(),url.pathname.slice(1))));
  }
  res.statusCode=404;res.end();
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  try {
    const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
    fs.mkdirSync('.mobile-tools/verification',{recursive:true});
    for(const width of [390,1280]) {
      await page.setViewportSize({width,height:900});
      await page.goto(`http://127.0.0.1:${server.address().port}/tornei/fixture.php`);
      await page.locator('.cup-game').first().waitFor();
      assert.equal(await page.locator('.cup-game').count(),7);
      assert.equal(await page.locator('a.cup-details').count(),1);
      assert.equal(await page.locator('.cup-game.is-template').count(),6);
      await page.waitForFunction(()=>document.querySelectorAll('.cup-lines path').length===7);
      assert.equal(await page.locator('.cup-round:visible').count(),3);
      const centers=await page.locator('.cup-node').evaluateAll(nodes=>Object.fromEntries(nodes.map(n=>[n.dataset.slot,Number(n.dataset.center)])));
      assert.equal((centers.Q1+centers.Q2)/2,centers.S1);
      assert.equal((centers.Q3+centers.Q4)/2,centers.S2);
      assert.equal((centers.S1+centers.S2)/2,centers.F1);
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`No horizontal overflow at ${width}`);
      await page.screenshot({path:`.mobile-tools/verification/cup-bracket-${width}.png`,fullPage:true});
      await page.getByRole('button',{name:'Semifinali',exact:true}).click();
      assert.equal(await page.locator('.cup-round:visible .cup-game').count(),7);
      if(width<1000) await page.waitForFunction(()=>document.querySelector('.cup-scroll').scrollLeft>100);
      assert((await page.locator('.cup-round').nth(1).textContent()).includes('Vincente Q1 · Squadra 1'));
      await page.selectOption('#cup','silver');
      await page.getByRole('heading',{name:'Coppa Silver',exact:true}).waitFor();
      assert.equal(await page.locator('.cup-game').count(),3);
      assert((await page.locator('.cup-game').first().textContent()).includes('Squadra 9'));
      await page.selectOption('#cup','bronze');
      await page.getByRole('heading',{name:'Coppa Bronzo',exact:true}).waitFor();
      assert((await page.locator('.cup-game').first().textContent()).includes('Squadra 13'));
    }
    assert.deepEqual(errors,[]);
    console.log('Cup browser: real deferred integration, cup/round navigation, existing match links and responsive layout passed at 390px/1280px.');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>server.close());
