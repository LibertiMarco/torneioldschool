const fs = require('fs'), vm = require('vm'), assert = require('assert');
const code = fs.readFileSync(require('path').join(__dirname,'../api/sincronizza_video_partite.js'),'utf8');
async function run(responses) {
    let handler, redirected = null;
    const calls = [], button = {disabled:false}, progress = {};
    const form = {action:'/api/sincronizza_video_partite.php',querySelector:()=>button,addEventListener:(_,cb)=>handler=cb,setAttribute(){},removeAttribute(){}};
    class Data extends Map {constructor(source){super(source ? [['action','scan'],['_csrf','csrf-test'],['platform','instagram'],['from','2026-09-10'],['to','2026-10-09']] : []);}}
    vm.runInNewContext(code, {document:{getElementById:id=>id === 'videoSyncSearch' ? form : progress},FormData:Data,AbortSignal:{timeout:()=>({})},
        location:{href:form.action,pathname:form.action,assign:url=>redirected=url},fetch:async(url,options)=>{
            calls.push({url,body:Object.fromEntries(options.body),credentials:options.credentials});
            const next = responses.shift();
            if (next instanceof Error) throw next;
            return {ok:next.status === undefined || next.status === 200,status:next.status ?? 200,headers:{get:()=>next.type ?? 'application/json'},json:async()=>next.body};
        }});
    await handler({preventDefault(){}});
    assert.equal(button.disabled,false,'Search button did not recover');
    return {calls,progress,redirected};
}
(async()=>{
    const results = [{body:{done:false,job:'job123',progress:'Avvio'}},...Array.from({length:48},()=>({body:{done:false,job:'job123',progress:'Lettura Reel'}})),{body:{done:true}}];
    const success = await run(results);
    assert.equal(success.calls.length,50);
    assert.equal(success.calls[0].body.action,'scan');
    for (const call of success.calls.slice(1)) {assert.equal(call.body.action,'step');assert.equal(call.body.job,'job123');assert.equal(call.body._csrf,'csrf-test');assert.equal(call.credentials,'same-origin');}
    assert.equal(success.redirected,'/api/sincronizza_video_partite.php');
    const failure = await run([{body:{done:false,job:'job123'}},{status:504,type:'text/html'}]);
    assert.equal(failure.redirected,null);assert.equal(failure.progress.className,'error');assert(failure.progress.textContent.includes('interrotto'));
    const expired = await run([{status:400,body:{error:'Ricerca scaduta'}}]);
    assert.equal(expired.progress.textContent,'Ricerca scaduta');assert.equal(expired.redirected,null);
    const timeout = new Error();timeout.name='TimeoutError';
    const slow = await run([timeout]);assert(slow.progress.textContent.includes('in tempo'));
    console.log('PASS: 48-page frontend search, CSRF/job propagation, redirect, 504, expired job and timeout recovery');
})().catch(error=>{console.error(error);process.exitCode=1;});
