// Real Chrome form + production JavaScript; HTTP responses are fixtures, no social API or DB writes.
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert'),{spawn}=require('child_process');
const root=path.join(__dirname,'..'),endpoint='/api/sincronizza_video_partite.php';
const source=fs.readFileSync(path.join(root,endpoint),'utf8');
const html=source.slice(source.indexOf('<!doctype html>'),source.indexOf('<?php if ($plan):')).replace(/<\?[\s\S]*?\?>/g,'')+'<script src="/api/sincronizza_video_partite.js?v=3" defer></script></main></body></html>';
const posts=[];let completed=false,browser,session,n=0,buffer='';const pending=new Map();
const server=http.createServer((req,res)=>{
    const url=req.url.split('?')[0];
    if(url==='/api/sincronizza_video_partite.js'){res.setHeader('Content-Type','application/javascript');return res.end(fs.readFileSync(path.join(root,url)));}
    if(url!==endpoint){res.statusCode=404;return res.end('Wrong request endpoint');}
    if(req.method==='GET'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(completed?'<p id="finished">Ricerca completata</p>':html);}
    let body='';req.on('data',data=>body+=data);req.on('end',()=>{
        const field=name=>body.match(new RegExp('name="'+name+'"\\r\\n\\r\\n([^\\r]+)'))?.[1];
        posts.push({url:req.url,action:field('action'),csrf:field('_csrf'),job:field('job')});
        completed=posts.length===3;
        res.setHeader('Content-Type','application/json');res.end(JSON.stringify({done:completed,job:'fixture-job',progress:'Lettura Reel'}));
    });
});
function call(method,params={},sid=session){return new Promise((resolve,reject)=>{const id=++n;pending.set(id,{resolve,reject});browser.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');});}
function receive(data){buffer+=data;let i;while((i=buffer.indexOf('\0'))>=0){const m=JSON.parse(buffer.slice(0,i));buffer=buffer.slice(i+1);if(pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}}}
async function evaluate(expression){const r=await call('Runtime.evaluate',{expression,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;}
async function until(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,50));}throw Error('Browser condition not reached: '+expression);}
async function run(){
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const profile=path.join(root,'cache/presentation-preview/social-form-profile');fs.mkdirSync(profile,{recursive:true});
    browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-pipe','--user-data-dir='+profile],{stdio:['ignore','ignore','ignore','pipe','pipe']});
    browser.stdio[4].on('data',receive);
    const target=await call('Target.createTarget',{url:'about:blank'},null);session=(await call('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
    await call('Page.enable');await call('Runtime.enable');await call('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+endpoint});
    await until('document.readyState === "complete" && !!document.getElementById("videoSyncSearch")');
    assert.equal(await evaluate('document.getElementById("videoSyncSearch").action.tagName'),'INPUT','Fixture did not reproduce native form.action collision');
    await evaluate(`(()=>{const f=document.getElementById('videoSyncSearch');f.elements.namedItem('_csrf').value='fixture-csrf';f.elements.namedItem('from').value='2026-10-09';f.elements.namedItem('to').value='2026-10-10';f.elements.namedItem('platform').value='both';f.requestSubmit()})()`);
    await until('!!document.getElementById("finished")');
    assert.equal(posts.length,3);assert.deepEqual(posts.map(p=>p.action),['scan','step','step']);
    for(const p of posts){assert.equal(p.url,endpoint);assert.equal(p.csrf,'fixture-csrf');}
    for(const p of posts.slice(1))assert.equal(p.job,'fixture-job');
    console.log('PASS: native form.action input collision; actual HTTP scan/step endpoints, CSRF, job and completion redirect in Chrome');
}
const timer=setTimeout(()=>{console.error('Browser test timed out');if(browser)browser.kill();server.close();process.exitCode=1;},20000);
run().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{clearTimeout(timer);if(browser)browser.kill();server.close();});
