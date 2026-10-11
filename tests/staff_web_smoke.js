const fs=require('node:fs'), path=require('node:path'), https=require('node:https');
const {chromium}=require('playwright-core');
const os=require('node:os'), {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const temporary=fs.mkdtempSync(path.join(os.tmpdir(),'tos-staff-browser-'));
const key=path.join(temporary,'key.pem'),cert=path.join(temporary,'cert.pem');
execFileSync('openssl',['req','-x509','-newkey','rsa:2048','-keyout',key,'-out',cert,'-days','1','-nodes','-subj','/CN=localhost'],{stdio:'ignore'});
const controller=`<!doctype html><style>.hidden{display:none}</style><main><h1>Gestione tornei</h1><div class="tab-buttons"><button id="create" class="active">Crea</button><button id="edit">Modifica</button></div><form id="form"><input type="hidden" name="_csrf" value="fixture-only"><label for="name">Nome torneo</label><input id="name" name="nome" required><label>Torneo<select id="tournament"><option value="">Seleziona…</option><option value="25">Brasilerao</option></select></label><label>Immagine<input type="file" id="photo" accept="image/png"></label><button type="submit">Salva torneo</button><p class="status" id="status"></p></form></main><script>window.fixtureWrites=[];document.getElementById('form').onsubmit=e=>{e.preventDefault();fixtureWrites.push({csrf:e.target._csrf.value,nome:e.target.nome.value});document.getElementById('status').textContent='Torneo salvato: '+e.target.nome.value};document.getElementById('edit').onclick=()=>{document.getElementById('create').classList.remove('active');document.getElementById('edit').classList.add('active')};</script>`;
const server=https.createServer({key:fs.readFileSync(key),cert:fs.readFileSync(cert)},(req,res)=>{
  const url=new URL(req.url,'https://localhost');
  res.setHeader('Cache-Control','no-store');
  if(url.pathname==='/app-preview/api.php') {
    const action=url.searchParams.get('action');
    res.setHeader('Content-Type','application/json');
    res.end(JSON.stringify(action==='session'?{user:{id:1,nome:'Admin',cognome:'Fixture',ruolo:'admin',permissions:{admin:true,graphics:true},feature_flags:{totocalcio:true,fantacalcio:false}},csrf:'fixture-only'}:{tournaments:[]})); return;
  }
  if(url.pathname==='/app-preview/staff-engine.php') {
    res.setHeader('Content-Type','text/html');
    res.end(`<iframe id="staff-source" src="/api/gestione_tornei.php" style="width:100%;height:100vh"></iframe><script>window.tosStaffChannel=${JSON.stringify(url.searchParams.get('channel'))};</script><script src="/app-preview/staff-engine.js"></script>`);return;
  }
  if(url.pathname==='/app-preview/staff-engine.js') {res.setHeader('Content-Type','text/javascript');res.end(fs.readFileSync(root+'/app-preview/staff-engine.js'));return;}
  if(url.pathname==='/api/gestione_tornei.php') {res.setHeader('Content-Type','text/html');res.end(controller);return;}
  if(url.pathname.startsWith('/api/')) {res.setHeader('Content-Type','application/json');res.end('{}');return;}
  const relative=url.pathname.replace(/^\/app-preview\/build\//,'');
  const file=path.resolve(root+'/mobile/build/web',relative||'index.html');
  if(!file.startsWith(root+'/mobile/build/web/')) {res.writeHead(404);res.end();return;}
  if(fs.existsSync(file)&&fs.statSync(file).isFile()) {
    res.setHeader('Content-Type',({'.html':'text/html','.js':'text/javascript','.wasm':'application/wasm','.json':'application/json','.png':'image/png','.ttf':'font/ttf','.otf':'font/otf'})[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));
  } else {res.writeHead(404);res.end();}
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const port=server.address().port;
  const browser=await chromium.launch({executablePath:process.env.TOS_TEST_CHROMIUM||'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--ignore-certificate-errors']});

  const page=await browser.newPage({viewport:{width:390,height:844},ignoreHTTPSErrors:true});
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  page.setDefaultTimeout(20000);
  await page.goto(`https://127.0.0.1:${port}/app-preview/build/index.html`);
  await page.locator('flt-semantics-placeholder').waitFor();
  await page.locator('flt-semantics-placeholder').evaluate(node=>node.click());
  await page.waitForTimeout(1800);
  await page.getByRole('tab',{name:/Gestione/}).click();
  await page.getByRole('button',{name:/Tornei.*Competizioni/}).click();
  await page.getByRole('textbox',{name:/Nome torneo/}).click();
  await page.keyboard.type('Coppa app');
  await page.getByRole('button',{name:'Salva torneo'}).click();
  await page.waitForTimeout(750);
  assert((await page.locator('body').ariaSnapshot()).includes('Torneo salvato: Coppa app'));
  const nested=page.frames().find(frame=>frame.url().includes('/api/gestione_tornei.php'));
  assert.deepEqual(await nested.evaluate(()=>window.fixtureWrites),[{csrf:'fixture-only',nome:'Coppa app'}]);
  const count=await page.locator('iframe[title="Controller staff"]').count();assert.equal(count,1);
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('Compiled web app: native dashboard, background bridge, field edit and CSRF-preserving save passed.');
  await browser.close();server.close();fs.rmSync(temporary,{recursive:true,force:true});
})().catch(error=>{console.error(error);server.close();fs.rmSync(temporary,{recursive:true,force:true});process.exit(1)});
