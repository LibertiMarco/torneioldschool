/* node tests/presentation_browser_test.js -- production page, Canvas and real Chrome input.
 * Uses fixtures, not the database; Chrome remote-debugging pipe is restricted to this process.
 */
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert'),{spawn}=require('child_process');
const root=path.join(__dirname,'..');
const policy=fs.readFileSync(path.join(root,'.htaccess'),'utf8').match(/Header set Content-Security-Policy "([^"]+)"/)[1];
const chrome=process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe';
const main=fs.readFileSync(path.join(root,'api/grafiche_presentazione.php'),'utf8').split('<main>')[1].split('</main>')[0].replace(/<\?(?:php|=)[\s\S]*?\?>/g,'');
const fixture=[
  {id:1,nome:'Brasilerao',img:'/img/tornei/seriea.png',template:'brasileirao',squadre:[{id:1,nome:'Bologna',logo:'/img/scudetti/Bologna.png'},{id:2,nome:'Atalanta',logo:'/img/scudetti/Atalanta.png'}]},
  {id:2,nome:'Conference League',img:'/img/tornei/serieb3.png',template:'conference',squadre:[{id:3,nome:'Squadra di verifica',logo:'/img/scudetti/Bologna.png'}]},
  {id:3,nome:'Champions League',img:'/img/tornei/seriea.png',template:'champions',squadre:[{id:4,nome:'Squadra con un nome particolarmente lungo per verificare la composizione',logo:'/img/scudetti/Atalanta.png'}]},
  {id:4,nome:'Asset mancanti',img:'/missing.png',template:'editorial',squadre:[{id:5,nome:'Squadra',logo:'/missing.png'}]}
];
const html=`<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/style.min.css"><link rel="stylesheet" href="/api/grafiche_presentazione.css"><body><main>${main}</main><script src="/api/matchday-renderer.js"></script><script src="/api/presentazione-renderer.js"></script><script src="/api/grafiche_downloads.js"></script><script src="/api/grafiche_presentazione.js"></script><script>PresentationPage.init(${JSON.stringify(fixture)});</script></body></html>`;
const server=http.createServer((req,res)=>{
  const url=req.url.split('?')[0];
  if(url==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.setHeader('Content-Security-Policy',policy);return res.end(html);}
  if((!/^\/(api|img)\/[a-z0-9_./-]+$/i.test(url)&&url!=='/style.min.css')||url.includes('..')){res.statusCode=404;return res.end();}
  const file=path.join(root,url);if(!fs.existsSync(file)){res.statusCode=404;return res.end();}
  res.setHeader('Content-Type',url.endsWith('.js')?'application/javascript':url.endsWith('.css')?'text/css':url.endsWith('.png')?'image/png':'image/jpeg');res.end(fs.readFileSync(file));
});
const output=path.join(root,'cache','presentation-preview');fs.mkdirSync(output,{recursive:true});
let browser,session,serial=0,buffer='',pending=new Map();
function cdp(method,params={},sid=session){return new Promise((resolve,reject)=>{
  const id=++serial;pending.set(id,{resolve,reject});browser.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');
});}
function receive(data){buffer+=data.toString();let index;while((index=buffer.indexOf('\0'))>=0){const raw=buffer.slice(0,index);buffer=buffer.slice(index+1);if(!raw)continue;const msg=JSON.parse(raw),p=pending.get(msg.id);if(p){pending.delete(msg.id);msg.error?p.reject(Error(JSON.stringify(msg.error))):p.resolve(msg.result);}}}
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.text+' '+JSON.stringify(r.exceptionDetails.exception));return r.result.value;}
const pause=()=>new Promise(r=>setTimeout(r,100));
async function until(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await pause();}throw Error('Timed out: '+expression);}
async function choose(id,value){await evaluate(`document.getElementById(${JSON.stringify(id)}).value=${JSON.stringify(value)};document.getElementById(${JSON.stringify(id)}).dispatchEvent(new Event('change'));`);}
async function upload(){await evaluate(`(async()=>{const c=document.createElement('canvas');c.width=1600;c.height=900;const x=c.getContext('2d');x.fillStyle='#32684e';x.fillRect(0,0,1600,900);for(let i=0;i<8;i++){x.fillStyle=i%2?'#f5c458':'#ecefea';x.fillRect(130+i*175,200,90,500);}const b=await new Promise(r=>c.toBlob(r,'image/png'));const dt=new DataTransfer();dt.items.add(new File([b],'foto-test.png',{type:'image/png'}));const input=document.getElementById('photo');input.files=dt.files;input.dispatchEvent(new Event('change'));})()`);await until(`!document.getElementById('photoControls').disabled`);}
async function screenshot(name){const shot=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(output,name+'.png'),Buffer.from(shot.data,'base64'));}
async function run(){
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  browser=spawn(chrome,['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-pipe','--user-data-dir='+path.join(output,'chrome-profile')],{stdio:['ignore','ignore','pipe','pipe','pipe']});browser.stdio[4].on('data',receive);
  browser.on('exit',()=>{for(const p of pending.values())p.reject(Error('Chrome exited'));pending.clear();});
  const target=await cdp('Target.createTarget',{url:'about:blank'},null);session=(await cdp('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
  await cdp('Page.enable');await cdp('Runtime.enable');
  await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1250,deviceScaleFactor:1,mobile:false});
  await cdp('Page.navigate',{url:`http://127.0.0.1:${server.address().port}/`});await until(`typeof PresentationPage!=='undefined'&&document.getElementById('tournament').options.length===5`);
  await choose('tournament','1');await until(`!document.getElementById('team').disabled&&document.getElementById('status').textContent.includes('Seleziona una squadra e')`);await choose('team','1');await until(`!document.getElementById('photo').disabled`);await upload();
  assert(await evaluate(`!document.getElementById('download').disabled`),'Download should be ready');
  const rect=await evaluate(`(()=>{const r=document.getElementById('presentationCanvas').getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height}})()`);
  const x=rect.x+rect.w*.4,y=rect.y+rect.h*.5;
  await cdp('Input.dispatchMouseEvent',{type:'mousePressed',x,y,button:'left',clickCount:1});await cdp('Input.dispatchMouseEvent',{type:'mouseMoved',x:x+30,y:y+30,button:'left',buttons:1});await cdp('Input.dispatchMouseEvent',{type:'mouseReleased',x:x+30,y:y+30,button:'left'});
  assert(await evaluate(`Number(document.getElementById('positionY').value)>0`),'Desktop drag did not move photo');
  await evaluate(`document.getElementById('reset').click()`);assert.equal(await evaluate(`document.getElementById('zoom').value`),'100');
  const exportResult=await evaluate(`(async()=>{const c=document.getElementById('presentationCanvas'),blob=await GraphicsDownloads.canvasToBlob(c),img=await createImageBitmap(blob),d=document.createElement('canvas');d.width=img.width;d.height=img.height;d.getContext('2d').drawImage(img,0,0);const a=c.getContext('2d').getImageData(0,0,c.width,c.height).data,b=d.getContext('2d').getImageData(0,0,d.width,d.height).data;return {width:img.width,height:img.height,equal:a.every((v,i)=>v===b[i])}})()`);
  assert.deepEqual(exportResult,{width:1080,height:1350,equal:true});await screenshot('desktop');
  const background=await evaluate(`document.getElementById('presentationCanvas').getContext('2d').getImageData(0,0,20,1350).data.join(',')`);
  await choose('team','2');await until(`!document.getElementById('photo').disabled`);assert(await evaluate(`document.getElementById('photoControls').disabled`),'Different team reused first team photo');await upload();
  assert.equal(await evaluate(`document.getElementById('presentationCanvas').getContext('2d').getImageData(0,0,20,1350).data.join(',')`),background,'Same tournament changed template');
  await choose('team','1');await until(`!document.getElementById('photo').disabled`);assert(await evaluate(`!document.getElementById('photoControls').disabled`),'First team draft lost');
  await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await cdp('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:2});
  await evaluate(`document.getElementById('presentationCanvas').scrollIntoView({block:'center'})`);await pause();
  const mobile=await evaluate(`(()=>{const r=document.getElementById('presentationCanvas').getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height*.5}})()`);
  await cdp('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:mobile.x-35,y:mobile.y,id:1},{x:mobile.x+35,y:mobile.y,id:2}]});
  await cdp('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:mobile.x-70,y:mobile.y,id:1},{x:mobile.x+70,y:mobile.y,id:2}]});
  await cdp('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
  assert(await evaluate(`Number(document.getElementById('zoom').value)>150`),'Real touch pinch failed');
  assert(await evaluate(`document.documentElement.scrollWidth<=390`),'Mobile horizontal overflow');await screenshot('mobile');
  await evaluate(`document.getElementById('reset').click()`);await choose('tournament','2');await until(`!document.getElementById('team').disabled&&document.getElementById('status').textContent.includes('Seleziona una squadra e')`);await choose('team','3');await until(`!document.getElementById('photo').disabled`);await upload();
  assert.notEqual(await evaluate(`document.getElementById('presentationCanvas').getContext('2d').getImageData(0,0,20,1350).data.join(',')`),background,'Different tournament has same background');
  await choose('tournament','4');await until(`document.getElementById('status').textContent.includes('assente')`);await choose('team','5');await until(`!document.getElementById('photo').disabled`);await upload();assert(await evaluate(`document.getElementById('download').disabled`),'Missing official assets allowed download');
  const profiles=await evaluate(`Object.keys(PresentationRenderer.profiles)`);
  const phpCatalog=fs.readFileSync(path.join(root,'includi/presentation_templates.php'),'utf8');for(const profile of profiles)assert(phpCatalog.includes("'"+profile+"'"),'Missing PHP association '+profile);
  // Export every identity as a gallery for visual review and retain individual full-resolution PNGs.
  const gallery=await evaluate(`(async()=>{const assets={brand:await MatchdayRenderer.loadImage('/img/logo_old_school.png'),competition:await MatchdayRenderer.loadImage('/img/tornei/seriea.png'),crest:await MatchdayRenderer.loadImage('/img/scudetti/Bologna.png')};const p=document.createElement('canvas');p.width=1600;p.height=900;const x=p.getContext('2d');x.fillStyle='#32684e';x.fillRect(0,0,1600,900);for(let i=0;i<8;i++){x.fillStyle=i%2?'#f5c458':'#ecefea';x.fillRect(130+i*175,200,90,500);}assets.photo=await MatchdayRenderer.loadImage(p.toDataURL());return Object.keys(PresentationRenderer.profiles).map((key,i)=>{const c=document.createElement('canvas');PresentationRenderer.draw(c,{id:i+1,nome:key,template:key},{nome:'BOLOGNA'},assets);return {key,png:c.toDataURL().split(',')[1]}})})()`);
  for(const item of gallery)fs.writeFileSync(path.join(output,item.key+'.png'),Buffer.from(item.png,'base64'));
  console.log('PASS: real desktop drag; real mobile pinch; team drafts; stable/distinct templates; missing assets; pixel-identical 1080x1350 PNG; '+profiles.length+' rendered identities.');
}
const timeout=setTimeout(()=>{console.error('Browser test timeout');if(browser)browser.kill();server.close();process.exitCode=1;},60000);
run().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{clearTimeout(timeout);if(browser)browser.kill();server.close();});
