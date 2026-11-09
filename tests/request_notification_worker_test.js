const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const handlers={},notifications=[],requests=[],opened=[];let admin=true,conflict=false;
const self={location:{origin:'https://torneioldschool.it'},addEventListener:(type,handler)=>handlers[type]=handler,
  registration:{showNotification:async(title,options)=>notifications.push({title,options})},clients:{matchAll:async()=>[],openWindow:async url=>opened.push(url)}};
const fetch=async(url,options={})=>{
  requests.push({url,...options});
  if(url.endsWith('action=session'))return{ok:true,json:async()=>({user:{permissions:{admin}},csrf:'fixture-csrf'})};
  return{ok:!conflict,json:async()=>conflict?{error:{message:'La richiesta è già stata gestita.'}}:{ok:true,message:'Richiesta accettata.'}};
};
vm.runInNewContext(fs.readFileSync('service-worker.js','utf8'),{self,fetch,URL,Uint8Array,atob,console});
async function event(type,data){let pending;handlers[type]({...data,waitUntil:promise=>pending=promise});if(pending)await pending;}
(async()=>{
  await event('push',{data:{json:()=>({title:'Richiesta',url:'/api/gestione_account_giocatore.php',data:{kind:'player_link_request',request_id:'12'}})}});
  assert.deepEqual(Array.from(notifications[0].options.actions,a=>a.action),['accept','reject']);
  const notification={data:notifications[0].options.data,close(){}};
  await event('notificationclick',{notification,action:'accept'});
  const body=JSON.parse(requests.find(request=>request.method==='POST').body);
  assert.equal(body.request_id,12);assert.equal(body.accept,true);assert.equal(body._csrf,'fixture-csrf');assert.equal(opened.length,0);
  await event('notificationclick',{notification,action:''});assert.equal(opened[0],'https://torneioldschool.it/api/gestione_account_giocatore.php');
  conflict=true;await event('notificationclick',{notification,action:'reject'});assert.equal(notifications.at(-1).title,'Richiesta non confermata');assert(notifications.at(-1).options.body.includes('già stata gestita'));
  admin=false;const before=requests.filter(request=>request.method==='POST').length;await event('notificationclick',{notification,action:'accept'});assert.equal(requests.filter(request=>request.method==='POST').length,before);
  console.log('Web notification actions: explicit approval/rejection, live CSRF, admin checks, stale errors and body-click navigation passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
