'use strict';
const http = require('http');
const net = require('net');
const fs = require('fs');
const os = require('os');
const path = require('path');
const {spawn} = require('child_process');
const assert = require('assert').strict;
const root = path.resolve(__dirname,'..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const runtime = fs.mkdtempSync(path.join(os.tmpdir(),'tos-schedule-http-'));
const token = 'schedule-test-token-with-at-least-32-characters';
const parts = new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Rome',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
const part = type => parts.find(p => p.type === type).value;
const day = `${part('year')}-${part('month')}-${part('day')}`;
fs.mkdirSync(path.join(runtime,'match-video-daily'));
fs.writeFileSync(path.join(runtime,'match-video-daily/state.json'),JSON.stringify({version:1,day,status:'done',attempt:1}));
let server, holder;
const env = {...process.env,TOS_RUNTIME_DIR:runtime,MATCH_VIDEO_CRON_TOKEN:token};
function request(port,options = {}) {
    return new Promise((resolve,reject) => {
        const req = http.request({host:'127.0.0.1',port,path:'/api/script/sync_match_video_daily.php'+(options.query || ''),method:options.method || 'GET',headers:options.headers || {}},res => {
            let body = '';res.on('data',chunk => body += chunk);res.on('end',() => resolve({status:res.statusCode,body}));
        });req.on('error',reject);req.end();
    });
}
async function main() {
    const port = await new Promise(resolve => {
        const probe = net.createServer();probe.listen(0,'127.0.0.1',() => {const p = probe.address().port;probe.close(() => resolve(p));});
    });
    server = spawn(php,['-S',`127.0.0.1:${port}`,'-t',root],{env,windowsHide:true});
    server.stderr.on('data',()=>{});
    let response;
    for (let i=0;i<50;i++) {
        try {response = await request(port);break;} catch (e) {await new Promise(resolve => setTimeout(resolve,40));}
    }
    assert(response,'PHP fixture server did not start');
    assert.equal(response.status,403,'Unauthenticated cron accepted');
    response = await request(port,{query:'?token=wrong'});assert.equal(response.status,403);
    response = await request(port,{headers:{Authorization:`Bearer ${token}`}});
    assert.equal(response.status,200);assert.equal(JSON.parse(response.body).status,'idle','Completed day ran twice');
    assert(!response.body.includes(token),'Cron response leaked token');
    response = await request(port,{query:`?token=${token}`});assert.equal(JSON.parse(response.body).status,'idle');
    response = await request(port,{method:'POST',headers:{Authorization:`Bearer ${token}`}});assert.equal(response.status,405);
    // Dry run must reach database validation even when its runtime path cannot be a directory.
    const blockedPath = path.join(runtime,'not-a-directory');fs.writeFileSync(blockedPath,'fixture');
    const dry = await new Promise((resolve,reject) => {
        const proc = spawn(php,[path.join(root,'api/script/sync_match_video_daily.php'),'--dry-run'],{
            env:{...env,TOS_RUNTIME_DIR:blockedPath,DB_HOST:'127.0.0.1',DB_USER:'tos_nonexistent_fixture_user',DB_NAME:'tos_fixture',DB_PASSWORD:'fixture'},windowsHide:true});
        let stdout = '',stderr = '';
        proc.stdout.on('data',chunk => stdout += chunk);proc.stderr.on('data',chunk => stderr += chunk);
        proc.on('error',reject);proc.on('close',code => resolve({code,stdout,stderr}));
    });
    assert.equal(JSON.parse(dry.stdout).status,'failed');
    assert(!dry.stdout.includes('Directory privata') && !dry.stderr.includes('mkdir'),'Dry run tried to create cron storage');
    assert.equal(fs.readFileSync(blockedPath,'utf8'),'fixture');fs.unlinkSync(blockedPath);
    const lockPath = path.join(runtime,'match-video-daily/run.lock').replace(/\\/g,'/');
    holder = spawn(php,['-r',"$f=fopen($argv[1],'c');flock($f,LOCK_EX);echo 'ready';fflush(STDOUT);sleep(10);",lockPath],{windowsHide:true});
    await new Promise((resolve,reject) => {holder.stdout.once('data',resolve);holder.once('error',reject);});
    response = await request(port,{headers:{Authorization:`Bearer ${token}`}});
    assert.equal(JSON.parse(response.body).status,'busy','Concurrent cron was allowed');
    console.log('PASS: HTTP cron authentication, completed-day guard, process lock and dry run without writable storage');
}
main().catch(error => {console.error(error);process.exitCode = 1;}).finally(async () => {
    for (const proc of [holder,server]) if (proc && proc.exitCode === null) {
        await new Promise(resolve => {proc.once('exit',resolve);proc.kill();});
    }
    for (const file of ['state.json','run.lock']) fs.unlinkSync(path.join(runtime,'match-video-daily',file));
    fs.rmdirSync(path.join(runtime,'match-video-daily'));fs.rmdirSync(runtime);
});
