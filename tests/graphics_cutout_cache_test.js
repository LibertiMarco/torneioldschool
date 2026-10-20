const assert=require('assert'),fs=require('fs'),vm=require('vm'),path=require('path');
let runs=0,active=0;
const storage=new Map();
const sandbox={URL,WeakRef,setTimeout,clearTimeout,
  localStorage:{setItem(key,value){storage.set(key,value);},getItem(key){return storage.get(key)||null;},removeItem(key){storage.delete(key);}},
  document:{currentScript:{src:'https://example.test/api/grafiche_scontorno.js'},createElement(){return {width:0,height:0,getContext(){return {fillRect(){},drawImage(){},getImageData(){return {data:new Uint8ClampedArray(1024*1024*4)};},createImageData(w,h){return {data:new Uint8ClampedArray(w*h*4)};},putImageData(){}};}};}},
  Worker:class {
    constructor(){assert.strictEqual(active,0,'Workers overlap');active++;}
    terminate(){active--;}
    postMessage(message){runs++;Promise.resolve().then(()=>this.onmessage({data:{...message,people:{foreground:[],background:[]},embedding:{data:new Float32Array(1),dims:[1]},masks:{foreground:new Uint8Array(1),background:new Uint8Array(1)},alpha:new Float32Array(1024*1024).fill(1)}}));}
  }
};
vm.createContext(sandbox);vm.runInContext(fs.readFileSync(path.join(__dirname,'../api/grafiche_scontorno.js'),'utf8')+';this.cutout=GraphicsCutout;',sandbox);
(async()=>{
  sandbox.cutout.markPhase('Preparation');
  assert.strictEqual(sandbox.cutout.interruptedPhase(),'Preparation');
  assert.strictEqual(sandbox.cutout.interruptedPhase(),null);
  sandbox.cutout.markPhase('Editor');sandbox.cutout.clearPhase();
  assert.strictEqual(sandbox.cutout.interruptedPhase(),null);
  const photo={naturalWidth:100,naturalHeight:100};
  const first=await sandbox.cutout.createMask(photo);
  first.width=1; // Simulate editing/freeing the returned mask.
  const second=await sandbox.cutout.createMask(photo);
  assert.strictEqual(runs,4);assert.strictEqual(second.width,100);assert.notStrictEqual(first,second);
  await sandbox.cutout.createMask(photo,{subjects:'all'});assert.strictEqual(runs,8);
  await sandbox.cutout.createMask({naturalWidth:100,naturalHeight:100},{subjects:'all'});assert.strictEqual(runs,12);
  console.log('Cutout cache: repeat skips inference, fresh editable mask, subject/photo invalidation OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
