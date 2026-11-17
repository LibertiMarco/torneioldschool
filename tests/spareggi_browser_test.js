const assert=require('node:assert/strict');
const fs=require('node:fs');
const http=require('node:http');
const {chromium}=require('playwright-core');
const teams=Array.from({length:16},(_,i)=>({nome:`Squadra ${i+1}`,punti:16-i}));
const server=http.createServer((req,res)=>{
  res.setHeader('Content-Type','text/html; charset=utf-8');
  if(req.url==='/tornei/cup-bracket.js') {
    res.setHeader('Content-Type','text/javascript; charset=utf-8');
    return res.end(fs.readFileSync('tornei/cup-bracket.js'));
  }
  if(req.url.startsWith('/api/')) {
    res.setHeader('Content-Type','application/json');
    return res.end(JSON.stringify(req.url.includes('leggiClassifica')?teams:req.url.includes('get_torneo_by_slug')
      ? {config:{spareggio_qualificati:16},mobile_layout:{spareggio:true}}:[]));
  }
  res.end(`<!doctype html><meta name="viewport" content="width=device-width"><style>body{font:16px Arial;background:#f4f6f9;margin:16px}main{max-width:1200px;margin:auto}</style><main><h1>La Liga · Spareggi</h1><div id="playoffContainer"></div></main>
    <script>const TORNEO='LaLiga';document.addEventListener('DOMContentLoaded',()=>caricaSpareggio());</script><script src="/tornei/cup-bracket.js" defer></script>`);
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  try {
    const page=await browser.newPage(),errors=[];
    page.on('pageerror',error=>errors.push(error.message));
    fs.mkdirSync('.mobile-tools/verification',{recursive:true});
    for(const width of [320,390,1280]) {
      await page.setViewportSize({width,height:900});
      await page.goto(`http://127.0.0.1:${server.address().port}/`);
      await page.locator('.spareggio-card').first().waitFor();
      assert.equal(await page.locator('.spareggio-card').count(),8);
      assert((await page.locator('.spareggio-card').first().textContent()).includes('Squadra 16'));
      assert((await page.locator('.spareggio-card').last().textContent()).includes('Squadra 9'));
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
      await page.screenshot({path:`.mobile-tools/verification/spareggi-${width}.png`,fullPage:true});
      await page.getByRole('button',{name:'Apri Coppa Silver'}).click();
      await page.getByRole('heading',{name:'Coppa Silver',exact:true}).waitFor();
      assert((await page.locator('.cup-game').first().textContent()).includes('Perdente spareggio'));
    }
    assert.deepEqual(errors,[]);
    console.log('Spareggi browser: eight pairings, Silver navigation and responsive layout passed at 320/390/1280px.');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>server.close());
