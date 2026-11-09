const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {chromium}=require('playwright-core');
let mode='failure',posted='';
let article=fs.readFileSync('api/gestione_blog.php','utf8').replace(/const postsData = <\?=[\s\S]*?\?>;/g,'const postsData = [];').replace(/<\?(?:php|=)[\s\S]*?\?>/g,'');
article=article.replace(/<script[^>]+src=[^>]*>[\s\S]*?<\/script>/g,'').replace('</body>','<script src="/api/article-form.js"></script></body>');
const teams=Array.from({length:16},(_,i)=>({id:i+1,nome:`Squadra ${i+1}`,punti:16-i,logo:'/fixture-logo.svg'}));
const server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(req.method==='POST') {
    const chunks=[];req.on('data',chunk=>chunks.push(chunk));req.on('end',()=>{posted=Buffer.concat(chunks).toString('utf8');res.setHeader('Content-Type','application/json');
      res.statusCode=mode==='failure'?422:200;res.end(JSON.stringify(mode==='failure'?{ok:false,error:'Caricamento interrotto: il file è arrivato solo in parte.'}:{ok:true,message:'Articolo pubblicato.',article:{id:9,titolo:'Articolo test',contenuto:'**Grassetto** _Corsivo_ ++Sotto++'}}));});return;
  }
  if(url.pathname==='/article-fixture.php'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(article);}
  if(url.pathname==='/bracket-fixture.php'){res.setHeader('Content-Type','text/html');return res.end('<script src="/api/matchday-renderer.js"></script><script src="/tornei/cup-bracket.js"></script><script src="/api/cup-bracket-renderer.js"></script>');}
  if(url.pathname==='/fixture-logo.svg'){res.setHeader('Content-Type','image/svg+xml');return res.end('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><path d="M10 5H70V50L40 75 10 50Z" fill="#326a89"/></svg>');}
  if(url.pathname==='/api/get_torneo_by_slug.php'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({nome:'Coppa Test',config:{formato:'campionato',totale_squadre:16,qualificati_gold:8,qualificati_silver:4,qualificati_bronzo:4},mobile_layout:{group_mode:'template'}}));}
  if(['/api/leggiClassifica.php','/api/get_squadre_torneo.php'].includes(url.pathname)){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify(teams));}
  if(url.pathname==='/api/get_partite.php'){res.setHeader('Content-Type','application/json');return res.end('{}');}
  if(['/api/article-form.js','/api/matchday-renderer.js','/tornei/cup-bracket.js','/api/cup-bracket-renderer.js'].includes(url.pathname)){res.setHeader('Content-Type','text/javascript; charset=utf-8');return res.end(fs.readFileSync(path.join(process.cwd(),url.pathname.slice(1))));}
  if(url.pathname.endsWith('.css')){res.setHeader('Content-Type','text/css');return res.end(fs.readFileSync('style.css'));}
  res.end('');
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  try {
    const page=await browser.newPage({viewport:{width:1100,height:900}}),errors=[];
    page.on('pageerror',error=>errors.push(error.message));
    await page.goto(`http://127.0.0.1:${server.address().port}/article-fixture.php`);
    await page.evaluate(()=>{
      document.querySelectorAll('#formCreaBlog select option').forEach(option=>{if(!option.value)option.value='calcio';});
      const csrf=document.createElement('input');csrf.type='hidden';csrf.name='_csrf';csrf.value='fixture-private-csrf';document.querySelector('#formCreaBlog').append(csrf);
    });
    await page.fill('#titolo','Articolo test');
    await page.locator('#sectionsCreate [contenteditable=true]').evaluate(editor=>{editor.innerHTML='<strong>Grassetto</strong> <em>Corsivo</em> <u>Sotto</u><ul><li>Punto elenco</li></ul>';editor.dispatchEvent(new Event('input',{bubbles:true}));});
    await page.locator('#formCreaBlog input[type=file]').first().setInputFiles({name:'foto.png',mimeType:'image/png',buffer:Buffer.from('fixture-file')});
    await page.waitForTimeout(4100); // The old page alert timer must not detach the persistent save result.
    await page.getByRole('button',{name:'Pubblica articolo',exact:true}).click();
    await page.locator('#formCreaBlog [data-persistent-notice].error').waitFor();
    assert(posted.includes('**Grassetto**') && posted.includes('_Corsivo_') && posted.includes('++Sotto++'));
    assert(posted.includes('- Punto elenco'));
    assert(posted.includes('fixture-private-csrf') && posted.includes('foto.png'));
    assert.equal(await page.inputValue('#titolo'),'Articolo test');
    assert((await page.locator('#sectionsCreate [contenteditable=true]').innerHTML()).includes('<strong>Grassetto</strong>'));
    assert.equal(await page.locator('#formCreaBlog input[type=file]').first().evaluate(input=>input.files.length),1);
    assert((await page.locator('#formCreaBlog [data-persistent-notice]').textContent()).includes('arrivato solo in parte'));
    await page.screenshot({path:'.mobile-tools/verification/article-retry.png',fullPage:true});
    mode='success';await page.getByRole('button',{name:'Pubblica articolo',exact:true}).click();await page.locator('#formCreaBlog [data-persistent-notice].success').waitFor();
    assert.equal(await page.inputValue('#titolo'),'');assert.equal(await page.locator('#formCreaBlog input[type=file]').first().evaluate(input=>input.files.length),0);
    assert.equal(await page.locator('#modSelectArticolo option[value="9"]').count(),1);
    await page.setContent('<form id="formModificaBlog" method="post"><input id="solo_media_mod" name="solo_media_mod" type="hidden" value="1"><input type="file" name="media_mod[]"><span class="file-upload-filename" data-default="Nessun file selezionato">foto.png</span><button type="submit">Salva modifiche</button></form>');
    await page.addScriptTag({url:`http://127.0.0.1:${server.address().port}/api/article-form.js`});
    await page.locator('input[type=file]').setInputFiles({name:'foto.png',mimeType:'image/png',buffer:Buffer.from('fixture-file')});
    await page.evaluate(()=>document.querySelector('form').requestSubmit());
    await page.locator('[data-persistent-notice].success').waitFor();
    assert(posted.includes('foto.png'));
    assert.equal(await page.inputValue('#solo_media_mod'),'0');
    assert.equal(await page.locator('input[type=file]').evaluate(input=>input.files.length),0);
    assert.equal(await page.locator('.file-upload-filename').textContent(),'Nessun file selezionato');
    await page.goto(`http://127.0.0.1:${server.address().port}/bracket-fixture.php`);
    for(const phase of ['GOLD','SILVER','BRONZO']) {
      const result=await page.evaluate(async phase=>{const canvas=await CupBracketRenderer.draw('fixture',phase);document.body.replaceChildren(canvas);return{width:canvas.width,height:canvas.height,png:canvas.toDataURL('image/png').split(',')[1]};},phase);
      assert(result.width>=1080 && result.height>=900);assert(Buffer.from(result.png,'base64').length>10000);
      fs.writeFileSync(`.mobile-tools/verification/matchday-bracket-${phase.toLowerCase()}.png`,Buffer.from(result.png,'base64'));
    }
    assert.deepEqual(errors,[]);console.log('Staff refresh browser: rich text and files retained on failure, successful retry, and downloadable Gold/Silver/Bronzo bracket PNGs passed.');
  }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>server.close());
