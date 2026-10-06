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
editor.init();
for(const [type,id] of [['ft','fulltimeCanvas'],['mvp','mvpCanvas']]){
  const image={},wrap=canvases[id].beforeItems[0],button=wrap.beforeItems[0],layer=wrap.children[1],selection=layer.children[0];
  let box;const redraw=()=>{box=editor.transform(type,image,frame,base);};sandbox.drawAll=redraw;redraw();
  button.listeners.click();assert.equal(layer.hidden,false);
  const event=(pointerId,x,y,target=selection)=>({pointerId,clientX:x/2,clientY:y/2,button:0,target,preventDefault(){},stopPropagation(){}});
  const drag=(key,dx,dy)=>{const handle=selection.children.find(n=>n.dataset.handle===key)||selection;selection.listeners.pointerdown(event(1,300,400,handle));selection.listeners.pointermove(event(1,300+dx,400+dy,handle));selection.listeners.pointerup(event(1,300+dx,400+dy,handle));};
  drag('e',100,90);assert.equal(box.w,900);assert.equal(box.h,700,'Horizontal handle changed height');
  drag('s',90,100);assert.equal(box.w,900,'Vertical handle changed width');assert.equal(box.h,800);
  drag('nw',50,60);assert.equal(box.x,100);assert.equal(box.y,260);assert.equal(box.w,850);assert.equal(box.h,740);
  drag('',40,30);assert.equal(box.x,140);assert.equal(box.y,290);assert.equal(box.w,850);
  selection.listeners.pointerdown(event(1,200,400));selection.listeners.pointerdown(event(2,400,400));selection.listeners.pointermove(event(2,600,400));assert.equal(box.w,1700);assert.equal(box.h,1480);
  selection.listeners.pointercancel(event(2,600,400));assert.equal(selection.captures.size,0);
  const old=box.x;selection.listeners.pointermove(event(1,900,900));assert.equal(box.x,old,'Cancelled gesture continued');
  editor.sync(type,null);assert.equal(layer.hidden,true);
  box=editor.transform(type,{},frame,base);assert.equal(box.w,800,'New photo retained old stretch');assert.equal(box.x,50);
}
console.log('PASS: Full Time and MVP photo drag, independent dimensions, corners, pinch, cancellation and reset.');
