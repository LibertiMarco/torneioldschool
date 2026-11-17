const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {chromium}=require('playwright-core');
const fixture=[{id:1,torneo:'demo',torneo_nome:'Demo',fase:'REGULAR',giornata:1,squadra_casa:'Casa',squadra_ospite:'Ospite',gol_casa:3,gol_ospite:1,giocata:1}];
let html=fs.readFileSync('api/copertina_partite.php','utf8');
html=html.slice(html.indexOf('<!doctype html>')).replace(/const matches=<\?=[\s\S]*?\?>;/,`const matches=${JSON.stringify(fixture)};`)
  .replace(/<\?php if \(!\$embedded\): \?>fetch\([\s\S]*?<\?php endif; \?>/,'').replace(/<\?[\s\S]*?\?>/g,'');
const server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(html);}
  if(!/^\/(api|img)\/[a-z0-9_./-]+\.(js|css|png|jpg|webp)$|^\/style\.min\.css$/i.test(url.pathname)||url.pathname.includes('..')){res.statusCode=404;return res.end();}
  const file=path.join(process.cwd(),url.pathname.slice(1));
  if(!fs.existsSync(file)){res.statusCode=404;return res.end();}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.png')?'image/png':'image/jpeg');res.end(fs.readFileSync(file));
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  try {
    const page=await browser.newPage(),errors=[];
    page.on('pageerror',error=>errors.push(error.message));
    await page.goto(`http://127.0.0.1:${server.address().port}/`);
    await page.waitForFunction(()=>document.getElementById('friendlyHomeColor').options.length===11);
    await page.selectOption('#coverMode','friendly');
    assert.equal(await page.locator('#competitionFields').isVisible(),false);
    assert.equal(await page.locator('#friendlyFields').isVisible(),true);
    await page.fill('#scoreHome','4');await page.fill('#scoreAway','2');
    const pixels=await page.evaluate(()=>{
      function pixel(id,x,y){return Array.from(document.getElementById(id).getContext('2d').getImageData(x,y,1,1).data).slice(0,3)}
      return [pixel('reelCanvas',177,385),pixel('reelCanvas',902,385),pixel('youtubeCanvas',137,267),pixel('youtubeCanvas',137,477)];
    });
    assert.deepEqual(pixels,[[21,21,21],[255,255,255],[21,21,21],[255,255,255]]);
    const text=await page.evaluate(()=>{
      const calls=[],fill=CanvasRenderingContext2D.prototype.fillText,stroke=CanvasRenderingContext2D.prototype.strokeText;
      CanvasRenderingContext2D.prototype.fillText=function(value,...args){calls.push(value);return fill.call(this,value,...args)};
      CanvasRenderingContext2D.prototype.strokeText=function(value,...args){calls.push(value);return stroke.call(this,value,...args)};
      try{draw()}finally{CanvasRenderingContext2D.prototype.fillText=fill;CanvasRenderingContext2D.prototype.strokeText=stroke}
      return calls;
    });
    assert(text.includes('4 - 2'));assert(text.includes('AMICHEVOLE'));
    assert(!text.some(value=>/neri|bianchi|nero|bianco/i.test(value)));
    await page.selectOption('#friendlyHomeColor','#e53935');
    assert.equal(await page.inputValue('#scoreHome'),'4');
    assert.deepEqual(await page.evaluate(()=>Array.from(document.getElementById('reelCanvas').getContext('2d').getImageData(177,385,1,1).data).slice(0,3)),[229,57,53]);
    await page.selectOption('#friendlyHomeColor','#151515');
    fs.mkdirSync('.mobile-tools/verification',{recursive:true});
    for(const id of ['reelCanvas','youtubeCanvas']) {
      const encoded=await page.evaluate(id=>document.getElementById(id).toDataURL('image/png').split(',')[1],id);
      fs.writeFileSync(`.mobile-tools/verification/friendly-${id}.png`,Buffer.from(encoded,'base64'));
      const dimensions=await page.evaluate(async id=>{
        const blob=await new Promise(resolve=>document.getElementById(id).toBlob(resolve,'image/png'));
        const image=await createImageBitmap(blob);const result=[image.width,image.height];image.close();return result;
      },id);
      assert.deepEqual(dimensions,id==='reelCanvas'?[1080,1920]:[1280,720]);
    }
    await page.setViewportSize({width:390,height:844});
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    await page.selectOption('#coverMode','tournament');
    await page.selectOption('#tournament','demo');await page.selectOption('#phase','REGULAR');await page.selectOption('#round','g:1');await page.selectOption('#match','1');
    await page.waitForFunction(()=>document.getElementById('scoreHome').readOnly);
    assert.equal(await page.inputValue('#scoreHome'),'3');
    assert.equal(await page.inputValue('#scoreAway'),'1');
    assert.deepEqual(errors,[]);
    console.log('Friendly covers: colors, score, no team text, both PNG sizes, mobile layout and locked tournament results passed.');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>server.close());
