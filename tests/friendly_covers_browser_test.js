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
    await page.waitForFunction(()=>brand?.naturalWidth);
    await page.selectOption('#coverMode','friendly');
    assert.equal(await page.locator('#competitionFields').isVisible(),false);
    assert.equal(await page.locator('#scoreFields').isVisible(),false);
    assert.equal(await page.locator('#friendlyHomeColor, #friendlyAwayColor').count(),0);
    const imageData=await page.evaluate(()=>{
      const canvas=document.createElement('canvas');
      canvas.width=canvas.height=20;
      const ctx=canvas.getContext('2d');
      ctx.fillStyle='#e53935';ctx.fillRect(0,0,20,20);
      return canvas.toDataURL('image/png').split(',')[1];
    });
    await page.locator('#photo').setInputFiles({name:'capitani.png',mimeType:'image/png',buffer:Buffer.from(imageData,'base64')});
    await page.waitForFunction(()=>photo?.naturalWidth);
    const rendered=await page.evaluate(()=>{
      const calls={};
      const proto=CanvasRenderingContext2D.prototype;
      const {fillText,strokeText,drawImage}=proto;
      function record(ctx,value){(calls[ctx.canvas.id]??=[]).push(value)}
      proto.fillText=function(value,x,y,...args){record(this,[value,x,y]);return fillText.call(this,value,x,y,...args)};
      proto.strokeText=function(value,...args){record(this,[value]);return strokeText.call(this,value,...args)};
      proto.drawImage=function(img,x,y,w,h){
        record(this,[img===photo?'photo':img===brand?'brand':'other',x+w/2,y,h]);
        return drawImage.call(this,img,x,y,w,h);
      };
      try{draw()}finally{Object.assign(proto,{fillText,strokeText,drawImage})}
      return calls;
    });
    for(const [id,center] of [['reelCanvas',540],['youtubeCanvas',640]]){
      const [title,photo,logo]=rendered[id];
      assert.equal(rendered[id].length,3);
      assert.deepEqual(title.slice(0,2),['MATCH AMICHEVOLE',center]);
      assert.deepEqual(photo.slice(0,2),['photo',center]);
      assert.deepEqual(logo.slice(0,2),['brand',center]);
      assert(title[2]<photo[2] && photo[2]<logo[2]);
    }
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
    assert.equal(await page.locator('#scoreFields').isVisible(),true);
    assert.equal(await page.inputValue('#scoreHome'),'3');
    assert.equal(await page.inputValue('#scoreAway'),'1');
    assert.deepEqual(errors,[]);
    console.log('Friendly covers: centered title, photo and Old School logo, both PNG sizes, mobile layout and locked tournament results passed.');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>server.close());
