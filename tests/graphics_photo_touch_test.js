const fs=require('fs'),vm=require('vm'),assert=require('assert');
class Element {
  constructor(){this.style={};this.dataset={};this.children=[];this.listeners={};this.captures=new Set();}
  append(...items){this.children.push(...items);}
  before(...items){this.beforeItems=items;}
  setAttribute(){}
  addEventListener(key,fn){this.listeners[key]=fn;}
  setPointerCapture(id){this.captures.add(id);}
  hasPointerCapture(id){return this.captures.has(id);}
  releasePointerCapture(id){this.captures.delete(id);}
  getBoundingClientRect(){return{left:0,top:0,width:540,height:675};}
}
const canvases={fulltimeCanvas:new Element(),mvpCanvas:new Element()};
const sandbox={window:{},document:{getElementById:id=>canvases[id],createElement:()=>new Element()}};
vm.createContext(sandbox);vm.runInContext(fs.readFileSync(require('path').join(__dirname,'../api/grafiche_foto_touch.js'),'utf8'),sandbox);
const editor=sandbox.window.PhotoTouchEditor;
const frame={x:50,y:200,w:900,h:900},base={x:50,y:200,w:800,h:700};
editor.init({autoEnable:true});
const productionPage=fs.readFileSync(require('path').join(__dirname,'../api/grafiche_post_partita.php'),'utf8');
sandbox.PhotoTouchEditor=editor;
vm.runInContext(productionPage.slice(productionPage.indexOf('function cover('),productionPage.indexOf('const visibleImageBounds')),sandbox);
for(const [type,id] of [['ft','fulltimeCanvas'],['mvp','mvpCanvas']]){
  const image={},wrap=canvases[id].beforeItems[0],button=wrap.beforeItems[0],layer=wrap.children[1],selection=layer.children[0];
  let box;const redraw=()=>{box=editor.transform(type,image,frame,base);};sandbox.drawAll=redraw;redraw();
  assert.equal(layer.hidden,false,'Uploaded photo did not enable direct editing');
  assert.equal(wrap.style.touchAction,'none','Browser scrolling can cancel photo drag');
  button.listeners.click();assert.equal(layer.hidden,true);assert.equal(wrap.style.touchAction,'pan-y pinch-zoom');
  button.listeners.click();assert.equal(layer.hidden,false);
  const event=(pointerId,x,y,target=selection)=>({pointerId,clientX:x/2,clientY:y/2,button:0,target,preventDefault(){},stopPropagation(){}});
  const drag=(key,dx,dy)=>{const handle=selection.children.find(n=>n.dataset.handle===key)||selection;selection.listeners.pointerdown(event(1,300,400,handle));selection.listeners.pointermove(event(1,300+dx,400+dy,handle));selection.listeners.pointerup(event(1,300+dx,400+dy,handle));};
  const ratio=base.w/base.h;
  const proportional=()=>assert(Math.abs(box.w/box.h-ratio)<1e-10,'Photo aspect ratio changed');
  for(const key of ['e','s','nw','n','ne','se','sw','w']){
    const before={...box};
    drag(key,50,60);proportional();
    if(key.includes('w'))assert(Math.abs(box.x+box.w-before.x-before.w)<1e-8,'Opposite horizontal edge moved');
    if(key.includes('n'))assert(Math.abs(box.y+box.h-before.y-before.h)<1e-8,'Opposite vertical edge moved');
  }
  drag('se',-100000,-100000);proportional();assert(Math.min(box.w,box.h)>=20-1e-8);
  drag('se',100000,100000);proportional();assert(box.w<=21600&&box.h<=27000);
  const beforeMove={...box};drag('',40,30);assert.equal(box.x,beforeMove.x+40);assert.equal(box.y,beforeMove.y+30);assert.equal(box.w,beforeMove.w);
  const beforePinch={...box};
  selection.listeners.pointerdown(event(1,200,400));selection.listeners.pointerdown(event(2,400,400));selection.listeners.pointermove(event(2,600,400));assert.equal(box.w,beforePinch.w*2);assert.equal(box.h,beforePinch.h*2);proportional();
  selection.listeners.pointercancel(event(2,600,400));assert.equal(selection.captures.size,0);
  const old=box.x;selection.listeners.pointermove(event(1,900,900));assert.equal(box.x,old,'Cancelled gesture continued');
  editor.sync(type,null);assert.equal(layer.hidden,true);
  box=editor.transform(type,{},frame,base);assert.equal(box.w,800,'New photo retained old stretch');assert.equal(box.x,50);
  const uploaded={naturalWidth:800,naturalHeight:700};let rendered;
  const ctx={save(){},beginPath(){},rect(){},clip(){},restore(){},drawImage(image,x,y,w,h){rendered={image,x,y,w,h};}};
  sandbox.drawAll=()=>sandbox.cover(ctx,uploaded,50,200,800,700,{prefix:type,zoom:100,x:50,y:0});
  sandbox.drawAll();assert.equal(layer.hidden,false,'Final graphic photo must be draggable immediately');
  drag('',80,120);assert.equal(rendered.x,130);assert.equal(rendered.y,320);assert.equal(rendered.image,uploaded,'Dragged a template rather than the uploaded photo');
  assert.equal(rendered.w,800);assert.equal(rendered.h,700);
  sandbox.drawAll();assert.equal(rendered.x,130,'Export redraw lost photo position');
  drag('e',100,0);assert.equal(rendered.w,900);assert.equal(rendered.h,787.5,'Final photo did not scale proportionally');
  sandbox.drawAll();assert.equal(rendered.h,787.5,'Export redraw lost photo proportions');
}
console.log('PASS: Full Time and MVP photo drag, all eight proportional handles, limits, pinch, cancellation, reset and export redraw.');
