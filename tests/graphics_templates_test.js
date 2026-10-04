const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const path = require('path');
const nodes = new Map();
const calls = [];
const responses = new Map();
const textCalls = [];
const context = new Proxy({}, {get: (target,key) => target[key] || (()=>{}), set:(target,key,value)=>(target[key]=value,true)});
context.fillText=(value,x,y)=>textCalls.push({value,x,y,color:context.fillStyle});
function node(id) {
  if(!nodes.has(id)) nodes.set(id, {id,value:'',style:{},getBoundingClientRect(){return {left:0,top:0,width:540,height:675};},setPointerCapture(id){(this.captures||(this.captures=new Set())).add(id);this.capture=id;},hasPointerCapture(id){return this.captures?.has(id);},releasePointerCapture(id){this.captures.delete(id);this.capture=null;},listeners:{},files:[],checked:false,
    addEventListener(event,fn){this.listeners[event]=fn;},
    replaceChildren(){this.value="";}, append(option){if(!this.value)this.value=option.value;}, prepend(){},
    querySelectorAll(){return [];}, checkValidity(){return true;}, getContext(){return context;}});
  return nodes.get(id);
}
const sandbox = {
  console,Set,Map,FormData:class {constructor(){this.data={};}append(k,v){this.data[k]=v;}},
  Option:class {constructor(text,value){this.text=text;this.value=value;}},
  document:{createElement:()=>node('generated-'+nodes.size)},window:{addEventListener(){},parent:{postMessage(){}},location:{origin:'http://localhost'}},
  $:node,W:1080,H:1350,graphicsTemplatesCsrf:'token',
  loadImage:async src=>({src,naturalWidth:1080,naturalHeight:1350}),
  fileImage:async file=>({src:file.name,naturalWidth:1080,naturalHeight:1350}),
  imageState:{},cropValues:()=>({}),fitText(){},cover(){},
  contain(ctx,img){if(img)calls.push(img.src);},
  drawAll(){sandbox.draws=(sandbox.draws||0)+1;},
  fetch:async(url,options)=>{
    if(options?.method==='POST') {sandbox.lastPost=options.body.data;return {ok:!sandbox.failSave,json:async()=>sandbox.failSave?{error:'Salvataggio non riuscito'}:sandbox.unconfirmedSave?{}:{ok:true}};}
    const id=url.split('=')[1];
    return {ok:true,json:async()=>responses.has(id)?await responses.get(id):{ft:null,mvp:null}};
  }
};
vm.createContext(sandbox);
const source=fs.readFileSync(path.join(__dirname,'../api/grafiche_basi.js'),'utf8');
vm.runInContext(source+'\nthis.templates=customTemplates;',sandbox);
const templates=sandbox.templates;
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
  templates.init({editor:true});
  assert(node('ftBaseControls').disabled,'No tournament must disable upload');
  responses.set('1',{ft:{image:'base-one',layout:{}},mvp:{image:'mvp-one',layout:{}}});
  responses.set('2',{ft:{image:'base-two',layout:{}},mvp:null});
  await templates.selectTournament('1');
  assert(templates.draw('ft'));assert.strictEqual(calls.pop(),'base-one');
  assert(templates.draw('mvp'));assert.strictEqual(calls.pop(),'mvp-one');
  for(const [type,id] of [['ft','fulltimeCanvas'],['mvp','mvpCanvas']]){
    const canvas=node(id),x=Number(node(type+'LayoutX').value),y=Number(node(type+'LayoutY').value);
    const event={pointerId:7,isPrimary:true,button:0,clientX:(x+20)/2,clientY:(y+20)/2,preventDefault(){}};
    canvas.listeners.pointerdown(event);
    canvas.listeners.pointermove({...event,clientX:event.clientX+30,clientY:event.clientY+40});
    assert.strictEqual(Number(node(type+'LayoutX').value),x+60,'Preview scaling');
    assert.strictEqual(Number(node(type+'LayoutY').value),y+80);
    canvas.listeners.pointercancel(event);
    canvas.listeners.pointermove({...event,clientX:0,clientY:0});
    assert.strictEqual(Number(node(type+'LayoutX').value),x+60,'Cancelled gesture');
    assert.strictEqual(canvas.capture,null);
    canvas.listeners.pointerdown({...event,clientX:(x+80)/2,clientY:(y+100)/2});
    canvas.listeners.pointermove({...event,clientX:-100,clientY:-100});
    assert.strictEqual(Number(node(type+'LayoutX').value),0);
    assert.strictEqual(Number(node(type+'LayoutY').value),0);
    canvas.listeners.pointerup(event);
    const w=Number(node(type+'LayoutW').value),h=Number(node(type+'LayoutH').value);
    const first={...event,pointerType:'touch',clientX:10,clientY:10};
    const second={...first,pointerId:8,isPrimary:false,clientX:110};
    canvas.listeners.pointerdown(first);canvas.listeners.pointerdown(second);
    canvas.listeners.pointermove({...second,clientX:60});
    assert.strictEqual(Number(node(type+'LayoutW').value),Math.round(w/2),'Pinch shrinks width');
    assert.strictEqual(Number(node(type+'LayoutH').value),Math.round(h/2),'Pinch preserves proportions');
    canvas.listeners.pointermove(second);
    assert.strictEqual(Number(node(type+'LayoutW').value),w,'Pinch enlarges width');
    canvas.listeners.pointerup(second);
    assert.strictEqual(canvas.captures.size,0,'Both fingers released');

  }

  node('ftBase').files=[{name:'draft-one',type:'image/png',size:20}];
  node('ftBase').listeners.change();await tick();
  await templates.selectTournament('2');
  assert(templates.draw('ft'));assert.strictEqual(calls.pop(),'base-two');
  assert.strictEqual(templates.draw('mvp'),false,'Missing MVP should use automatic layout');
  await templates.selectTournament('1');
  assert(templates.draw('ft'));assert.strictEqual(calls.pop(),'draft-one','Draft lost when switching tournament');
  node('ftSaveBase').listeners.click();await tick();
  assert.strictEqual(sandbox.lastPost.torneo_id,'1');
  assert.strictEqual(sandbox.lastPost.type,'ft');
  assert.strictEqual(sandbox.lastPost.base.name,'draft-one');
  const savedLayout=JSON.parse(sandbox.lastPost.layout);
  assert(savedLayout.homeScore&&savedLayout.awayScore&&!savedLayout.score,'Separate scores missing from saved template');
  sandbox.failSave=true;
  node('ftSaveBase').listeners.click();await tick();
  assert.strictEqual(node('ftBaseStatus').textContent,'Salvataggio non riuscito','Save error was hidden');
  sandbox.failSave=false;sandbox.unconfirmedSave=true;
  node('ftSaveBase').listeners.click();await tick();
  assert(node('ftBaseStatus').textContent.includes('non ha confermato'),'Unconfirmed save reported success');
  sandbox.unconfirmedSave=false;
  node('ftSaveBase').listeners.click();await tick();
  assert.strictEqual(node('ftBaseStatus').textContent,'Base salvata per questo torneo.','Retry did not succeed');
  node('ftRemoveBase').listeners.click();await tick();
  assert.strictEqual(templates.draw('ft'),false);
  assert(templates.draw('mvp'),'Reset removed the MVP base');
  let finish;
  responses.set('3',new Promise(resolve=>{finish=resolve;}));
  const pending=templates.selectTournament('3');
  assert.strictEqual(templates.ready('ft'),false,'Download allowed while loading');
  await templates.selectTournament('2');
  await templates.selectTournament('3');
  const draws=sandbox.draws;
  finish({ft:{image:'base-three',layout:{}},mvp:null});await pending;
  assert(sandbox.draws>draws,'Returning to a pending tournament did not refresh');
  assert.strictEqual(node('ftBaseControls').disabled,false);
  assert(templates.draw('ft'));assert.strictEqual(calls.pop(),'base-three');
  // The production generator loads templates without creating editor controls.
  const readerSandbox={...sandbox,document:{createElement(name){if(name==='details')throw new Error('Editor appeared in generator');return node('generated-'+nodes.size);}}};
  vm.createContext(readerSandbox);
  vm.runInContext(source+'\nthis.templates=customTemplates;',readerSandbox);
  const reader=readerSandbox.templates;
  reader.init();
  await reader.selectTournament('1');
  assert(reader.draw('ft'));assert.strictEqual(calls.pop(),'base-one');
  const photo={src:'match-photo'};readerSandbox.imageState.ftCaptains=photo;
  responses.set('1',{ft:{image:'updated-base',layout:{}},mvp:null});
  await reader.reload('1');
  assert(reader.draw('ft'));assert.strictEqual(calls.pop(),'updated-base');
  assert.strictEqual(readerSandbox.imageState.ftCaptains,photo,'Refreshing templates discarded the match photo');
  responses.set('1',{ft:null,mvp:null});
  await reader.reload();assert.strictEqual(reader.draw('ft'),false,'Removed template remained cached');
  const oldScore={x:100,y:600,w:501,h:100,font:70,color:'#123456',visible:true};
  responses.set('1',{ft:{image:'legacy-base',layout:{score:oldScore}},mvp:null});
  await reader.reload();
  node('ftHomeScore').value='0';node('ftAwayScore').value='12';textCalls.length=0;
  reader.draw('ft');
  assert.deepStrictEqual(textCalls.map(call=>call.value),['0','12'],'Scores were joined or separated by a dash');
  assert.strictEqual(textCalls[0].x,225);assert.strictEqual(textCalls[1].x,475.5);
  assert.strictEqual(textCalls[0].color,'#123456','Legacy score style lost');
  responses.set('1',{ft:{image:'split-base',layout:{homeScore:{...oldScore,x:50,color:'#abcdef'},awayScore:{...oldScore,x:700,y:900,visible:false}}},mvp:null});
  await reader.reload();textCalls.length=0;reader.draw('ft');
  assert.deepStrictEqual(textCalls.map(call=>call.value),['0'],'Score visibility is not independent');
  assert.strictEqual(textCalls[0].x,300.5);assert.strictEqual(textCalls[0].color,'#abcdef');
  // Parse the PHP page's inline JS with inert fixture values; no database or session required.
  const page=fs.readFileSync(path.join(__dirname,'../api/grafiche_post_partita.php'),'utf8');
  const inline=page.match(/<script>\s*([\s\S]*?)<\/script>/)[1]
    .replace(/<\?=([\s\S]*?)\?>/g,'[]').replace(/<\?php[\s\S]*?\?>/g,'');
  new vm.Script(inline);
  console.log('Graphics template tournament isolation, drafts, save/reset, async switching and JS syntax: OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
