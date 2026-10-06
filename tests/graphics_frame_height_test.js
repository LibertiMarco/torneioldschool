const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const read=name=>fs.readFileSync(path.join(__dirname,'../api',name),'utf8');
const frames={};
for(const id of ['matchdayFrame','postMatchFrame','templatesFrame','coversFrame','footer-container'])frames[id]={style:{},classList:{add(){}},contentWindow:{postMessage(){}},setAttribute(k,v){this[k]=v;},getAttribute(){return '';}};
let message;
const parent={document:{querySelectorAll:()=>[],getElementById:id=>frames[id]},window:{location:{origin:'http://localhost'},addEventListener(name,fn){if(name==='message')message=fn;}},fetch:async()=>({text:async()=>''})};
vm.createContext(parent);vm.runInContext(read('generatore_grafiche.php').match(/<script>\s*([\s\S]*?)<\/script>/)[1],parent);
for(const id of ['matchdayFrame','coversFrame']){
  let width=390,height=1200,resize;const queued=[];
  const child={window:{location:{origin:'http://localhost'},addEventListener(){},parent:{postMessage(data,origin){message({data,origin,source:frames[id].contentWindow});}}},document:{querySelector:()=>({getBoundingClientRect:()=>({width,height})})},getComputedStyle:()=>({marginTop:'32px',marginBottom:'60px'}),requestAnimationFrame:fn=>queued.push(fn),ResizeObserver:class{constructor(fn){resize=fn;}observe(){}}};
  vm.createContext(child);vm.runInContext(read('grafiche_frame_height.js'),child);queued.shift()();
  assert.equal(frames[id].style.height,'1292px');assert.equal(frames[id].scrolling,'no');
  height=65000;resize();resize();assert.equal(queued.length,1);queued.shift()();assert.equal(frames[id].style.height,'65092px','Long graphics list was clipped');
  resize();queued.shift()();assert.equal(queued.length,0,'Height loop');
  height=800;resize();queued.shift()();assert.equal(frames[id].style.height,'892px','Frame failed to shrink');
  width=0;resize();queued.shift()();assert.equal(frames[id].style.height,'892px','Hidden frame changed height');
  for(const event of [{origin:'https://other.test',source:frames[id].contentWindow},{origin:'http://localhost',source:{}}])message({...event,data:{type:'graphics-frame-height',height:50000}});
  assert.equal(frames[id].style.height,'892px','Untrusted resize accepted');
}
for(const name of ['grafiche_settimana.php','copertina_partite.php'])assert(read(name).includes('grafiche_frame_height.js'));
console.log('PASS: Matchday and covers grow beyond 30,000px, shrink, ignore hidden state and reject untrusted messages.');
