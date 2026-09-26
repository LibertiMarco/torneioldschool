const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const path = require('path');
const nodes = new Map();
const calls = [];
const responses = new Map();
const context = new Proxy({}, {get: (target,key) => target[key] || (()=>{}), set:(target,key,value)=>(target[key]=value,true)});
function node(id) {
  if(!nodes.has(id)) nodes.set(id, {id,value:'',listeners:{},files:[],checked:false,
    addEventListener(event,fn){this.listeners[event]=fn;},
    append(option){if(!this.value)this.value=option.value;}, prepend(){},
    querySelectorAll(){return [];}, checkValidity(){return true;}, getContext(){return context;}});
  return nodes.get(id);
}
const sandbox = {
  console,Set,Map,FormData:class {constructor(){this.data={};}append(k,v){this.data[k]=v;}},
  Option:class {constructor(text,value){this.text=text;this.value=value;}},
  document:{createElement:()=>node('generated-'+nodes.size)},window:{addEventListener(){}},
  $:node,W:1080,H:1350,graphicsTemplatesCsrf:'token',
  loadImage:async src=>({src,naturalWidth:1080,naturalHeight:1350}),
  fileImage:async file=>({src:file.name,naturalWidth:1080,naturalHeight:1350}),
  imageState:{},cropValues:()=>({}),fitText(){},cover(){},
  contain(ctx,img){if(img)calls.push(img.src);},
  drawAll(){sandbox.draws=(sandbox.draws||0)+1;},
  fetch:async(url,options)=>{
    if(options) {sandbox.lastPost=options.body.data;return {ok:true,json:async()=>({ok:true})};}
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
  templates.init();
  assert(node('ftBaseControls').disabled,'No tournament must disable upload');
  responses.set('1',{ft:{image:'base-one',layout:{}},mvp:{image:'mvp-one',layout:{}}});
  responses.set('2',{ft:{image:'base-two',layout:{}},mvp:null});
  await templates.selectTournament('1');
  assert(templates.draw('ft'));assert.strictEqual(calls.pop(),'base-one');
  assert(templates.draw('mvp'));assert.strictEqual(calls.pop(),'mvp-one');
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
  // Parse the PHP page's inline JS with inert fixture values; no database or session required.
  const page=fs.readFileSync(path.join(__dirname,'../api/grafiche_post_partita.php'),'utf8');
  const inline=page.match(/<script>\s*([\s\S]*?)<\/script>/)[1]
    .replace(/<\?=([\s\S]*?)\?>/g,'[]').replace(/<\?php[\s\S]*?\?>/g,'');
  new vm.Script(inline);
  console.log('Graphics template tournament isolation, drafts, save/reset, async switching and JS syntax: OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
