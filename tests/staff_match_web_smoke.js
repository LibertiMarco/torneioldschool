// End-to-end app UI using the website's actual lineup JavaScript, local data only.
const fs = require('node:fs'), path = require('node:path'), https = require('node:https');
const os = require('node:os'), {execFileSync} = require('node:child_process');
const {chromium} = require('playwright-core');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tos-match-browser-'));
execFileSync('openssl', ['req','-x509','-newkey','rsa:2048','-keyout',temp+'/key.pem','-out',temp+'/cert.pem','-days','1','-nodes','-subj','/CN=localhost'], {stdio:'ignore'});
const website = fs.readFileSync(root+'/api/statistiche_partita.php','utf8');
const markup = website.slice(website.indexOf('<main class="admin-wrapper">'),website.indexOf('<script>\nconst ID'));
const controller = website.slice(website.indexOf('<script>\nconst ID')+8,website.lastIndexOf('</script>')).replace(/const ID = [^\n]+;/,'const ID = 25;');
const players = [
  {giocatore_id:1,squadra_id:10,lato:'casa',squadra:'Go Ahead Eagles',nome:'Marco',cognome:'Rossi',ruolo:'Portiere',is_captain:1,goal:0,assist:0,autogol:0,voto:6},
  {giocatore_id:2,squadra_id:20,lato:'ospite',squadra:'Napoli',nome:'Luca',cognome:'Bianchi',ruolo:'Difensore',goal:0,assist:0,autogol:0,voto:6},
];
const writes = [];
let finalized = 0;
const selection = `<!doctype html><main><form id="formModifica">
  <label>Partita<select id="selPartitaMod"><option value="25">Go Ahead Eagles - Napoli</option></select></label>
  <button id="btnStatsMod" type="button">Statistiche partita</button>
  <label>Gol casa<input id="gol_casa_mod" type="number" min="0" value="0"></label>
  <label>Gol ospite<input id="gol_ospite_mod" type="number" min="0" value="0"></label>
  </form></main><script>document.getElementById('btnStatsMod').onclick=()=>location.href='/api/statistiche_partita.php?partitaid=25';</script>`;
const server = https.createServer({key:fs.readFileSync(temp+'/key.pem'),cert:fs.readFileSync(temp+'/cert.pem')},(req,res)=>{
  const url = new URL(req.url,'https://localhost');
  res.setHeader('Cache-Control','no-store');
  const json = value => {res.setHeader('Content-Type','application/json');res.end(JSON.stringify(value));};
  const html = value => {res.setHeader('Content-Type','text/html');res.end(value);};
  if (url.pathname === '/app-preview/api.php') {
    json(url.searchParams.get('action') === 'session' ? {user:{id:1,nome:'Admin',cognome:'Fixture',ruolo:'admin',permissions:{admin:true,graphics:true},feature_flags:{totocalcio:true}},csrf:'fixture-only'} : {tournaments:[]});return;
  }
  if (url.pathname === '/app-preview/staff-engine.php') {
    html(`<iframe id="staff-source" src="/api/gestione_partite.php"></iframe><script>window.tosStaffChannel=${JSON.stringify(url.searchParams.get('channel'))};</script><script src="/app-preview/staff-engine.js"></script>`);return;
  }
  if (url.pathname === '/app-preview/staff-engine.js') {res.setHeader('Content-Type','text/javascript');res.end(fs.readFileSync(root+'/app-preview/staff-engine.js'));return;}
  if (url.pathname === '/api/gestione_partite.php') {html(selection);return;}
  if (url.pathname === '/api/statistiche_partita.php') {html('<div id="msgBox" style="opacity:0"></div>'+markup+'<script>'+controller+'</script>');return;}
  if (url.pathname === '/api/get_partita.php') {json({squadra_casa:'Go Ahead Eagles',squadra_ospite:'Napoli',data_partita:'2026-10-11',ora_partita:'20:30:00',torneo:'Brasilerao',fase:'GOLD',giocata:finalized});return;}
  if (url.pathname === '/api/partita_giocatore.php') {
    if (req.method !== 'POST') {json(players);return;}
    const chunks=[];req.on('data',chunk=>chunks.push(chunk));req.on('end',()=>{
      const body=Buffer.concat(chunks).toString('utf8');
      const field = name => body.match(new RegExp('name="'+name+'"\\r\\n\\r\\n([\\s\\S]*?)\\r\\n--'))?.[1];
      const rows=JSON.parse(field('stats'));
      writes.push({action:field('azione'),match:field('partita_id'),finalize:field('finalizza'),rows});
      finalized=Number(field('finalizza'));
      rows.forEach(row=>Object.assign(players.find(player=>player.giocatore_id===row.giocatore_id),row,{statistica_id:row.giocatore_id}));
      json({success:true,message:'Distinta salvata'});
    });return;
  }
  if (url.pathname.startsWith('/api/')) {json({});return;}
  const relative=url.pathname.replace(/^\/app-preview\/build\//,'');
  const file=path.resolve(root+'/mobile/build/web',relative||'index.html');
  if (!file.startsWith(root+'/mobile/build/web/') || !fs.existsSync(file) || !fs.statSync(file).isFile()) {res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',({'.html':'text/html','.js':'text/javascript','.wasm':'application/wasm','.json':'application/json','.ttf':'font/ttf','.otf':'font/otf','.png':'image/png'})[path.extname(file)]||'application/octet-stream');
  res.end(fs.readFileSync(file));
});
let browser;
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  browser=await chromium.launch({executablePath:process.env.TOS_TEST_CHROMIUM||'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--ignore-certificate-errors']});
  const page=await browser.newPage({viewport:{width:390,height:844},ignoreHTTPSErrors:true});
  page.setDefaultTimeout(15000);
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.goto(`https://127.0.0.1:${server.address().port}/app-preview/build/index.html`);
  await page.locator('flt-semantics-placeholder').waitFor();
  await page.locator('flt-semantics-placeholder').evaluate(node=>node.click());
  await page.waitForTimeout(1800);
  await page.getByRole('tab',{name:/Gestione/}).click();
  await page.getByRole('button',{name:/Calendario e risultati.*Partite/}).click();
  await page.getByRole('button',{name:'Statistiche partita',exact:true}).click();
  await page.getByRole('button',{name:'Tutti presenti'}).first().click();
  await page.waitForTimeout(700);
  await page.getByRole('button',{name:'Aumenta Gol',exact:true}).first().click();
  await page.getByRole('button',{name:'Aumenta Gol',exact:true}).first().click();
  await page.waitForTimeout(300);
  const scoreSnapshot = await page.locator('body').ariaSnapshot();
  assert(scoreSnapshot.includes('2 - 0'), scoreSnapshot);
  if (process.env.TOS_SCREENSHOTS) await page.screenshot({path:path.join(process.env.TOS_SCREENSHOTS,'match-lineup.png')});
  await page.getByRole('checkbox',{name:'Finalizza partita'}).click();
  await page.getByRole('button',{name:'Salva tutta la distinta'}).click();
  await page.waitForTimeout(900);
  assert.equal(writes.length,1);
  assert.equal(writes[0].action,'save_bulk');assert.equal(writes[0].match,'25');assert.equal(writes[0].finalize,'1');
  assert.equal(writes[0].rows[0].goal,'2');assert.equal(writes[0].rows[0].presenza,1);
  assert.equal(writes[0].rows[1].presenza,0);
  assert((await page.locator('body').ariaSnapshot()).includes('Distinta salvata'));
  await page.getByRole('button',{name:'Ricarica',exact:true}).click();
  await page.waitForTimeout(900);
  assert((await page.locator('body').ariaSnapshot()).includes('2 - 0'), 'Reload stays on the current match');
  assert.equal(writes.length,1);
  await page.getByRole('button',{name:'Torna alla partita'}).click();
  await page.getByRole('button',{name:'Statistiche partita',exact:true}).waitFor();
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('Compiled app: grouped result form, website lineup navigation, attendance, goal controls, bulk finalization and return passed.');
})().catch(error=>{console.error(error);process.exitCode=1}).finally(async()=>{
  if(browser) await browser.close();server.close();fs.rmSync(temp,{recursive:true,force:true});
});
