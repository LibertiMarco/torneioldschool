const fs = require('node:fs'), path = require('node:path'), http = require('node:http');
const assert = require('node:assert/strict'), {execFileSync} = require('node:child_process');
const {chromium} = require('playwright-core');
const root = path.resolve(__dirname, '..');
let html = execFileSync(process.env.TOS_TEST_PHP || 'C:/xampp/php/php.exe', [path.join(__dirname, 'match_batch_fixture.php')], {encoding:'utf8'});
html = html.replace('id="formCrea">', 'id="formCrea"><input type="hidden" name="_csrf" value="fixture-only">');
const posts = [];
const server = http.createServer((req,res)=>{
  const route = new URL(req.url,'http://localhost').pathname;
  if (req.method === 'POST') {
    let body=''; req.on('data',chunk=>body+=chunk); req.on('end',()=>{
      posts.push(body); res.setHeader('Content-Type','application/json');
      res.end(JSON.stringify(posts.length===1 ? {success:false,error:'Conflitto simulato: nessun salvataggio'} : {success:true,message:'2 partite create'}));
    }); return;
  }
  if (route==='/api/gestione_partite.php') {res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);return;}
  if (['/api/match-batch.js','/api/match-batch.css','/style.css','/style.min.css','/admin.css'].includes(route)) {
    res.setHeader('Content-Type',route.endsWith('.js')?'text/javascript':'text/css'); res.end(fs.readFileSync(root+route));return;
  }
  res.end('');
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const browser=await chromium.launch({executablePath:process.env.TOS_TEST_CHROMIUM||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  try {
    for (const width of [390,1280]) {
      const page=await browser.newPage({viewport:{width,height:1000}}), errors=[];
      page.on('pageerror',error=>errors.push(error.message));
      await page.route('**/*',route=>route.request().url().startsWith('http://127.0.0.1:')?route.continue():route.abort());
      await page.goto(`http://127.0.0.1:${server.address().port}/api/gestione_partite.php`);
      await page.selectOption('#torneoCrea','coppa');
      await page.selectOption('#campoCrea','Campo 1');
      await page.selectOption('#giornataCrea','2');
      await page.fill('#batchDate','2026-11-09');
      await page.locator('#batchDate').dispatchEvent('change');
      let rows=page.locator('.batch-match-row');
      await rows.nth(0).locator('select').nth(0).selectOption('A');
      await rows.nth(0).locator('select').nth(1).selectOption('B');
      await rows.nth(0).locator('input').fill('19:30');
      await page.click('#batchAdd');
      await rows.nth(1).locator('select').nth(0).selectOption('C');
      await rows.nth(1).locator('select').nth(1).selectOption('D');
      await rows.nth(1).locator('input').fill('20:30');
      assert.equal(await rows.count(),2);
      assert.equal(await page.locator('.calendar-event').count(),3);
      const dimensions=await page.evaluate(()=>({body:document.body.scrollWidth,width:innerWidth}));
      assert(dimensions.body<=dimensions.width+1,'No page overflow on mobile');
      fs.mkdirSync(root+'/mobile/build/match-calendar',{recursive:true});
      await page.locator('#matchCalendar').scrollIntoViewIfNeeded();
      await page.screenshot({path:root+`/mobile/build/match-calendar/website-${width}.png`});
      await page.click('#batchSave');
      await page.waitForSelector('.form-inline-message');
      if (posts.length===1) {
        assert.equal(await rows.count(),2,'Server validation preserves draft rows');
        await page.click('#batchSave');
        await page.waitForFunction(()=>document.querySelector('.form-inline-message')?.textContent==='2 partite create');
      }
      assert.equal(await rows.count(),1);
      assert.equal(await page.inputValue('#torneoCrea'),'coppa');
      assert.equal(await page.inputValue('#batchDate'),'2026-11-09');
      await page.click('.tab-buttons [data-tab="modifica"]');
      await page.selectOption('#selTorneoMod','coppa');
      await page.selectOption('#selFaseMod','REGULAR');
      await page.selectOption('#selGiornataMod','1');
      assert.equal(await page.locator('#selPartitaMod option[value="2"]').textContent(),'A - B','Match picker contains only the two teams');
      assert.deepEqual(errors,[],'Real page scripts run without exceptions');
      await page.close();
    }
    assert(posts.every(body=>body.includes('partite[0][squadra_casa]')&&body.includes('partite[1][squadra_casa]')&&body.includes('fixture-only')),'One POST includes all matches and CSRF');
    console.log('Real PHP view: multi-match creation, AJAX error/retry, payload and responsive calendar passed.');
  } finally {await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});

