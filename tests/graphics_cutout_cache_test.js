const assert=require('assert'),fs=require('fs'),vm=require('vm'),path=require('path');
let runs=0;
const sandbox={URL,WeakRef,setTimeout,clearTimeout,
  document:{currentScript:{src:'https://example.test/api/grafiche_scontorno.js'},createElement(){return {width:0,height:0,getContext(){return {fillRect(){},drawImage(){},getImageData(){return {data:new Uint8ClampedArray(1024*1024*4)};},createImageData(w,h){return {data:new Uint8ClampedArray(w*h*4)};},putImageData(){}};}};}},
  Worker:class {terminate(){}postMessage(message){runs++;Promise.resolve().then(()=>this.onmessage({data:{id:message.id,width:1024,height:1024,alpha:new Float32Array(1024*1024).fill(1)}}));}}
};
vm.createContext(sandbox);vm.runInContext(fs.readFileSync(path.join(__dirname,'../api/grafiche_scontorno.js'),'utf8')+';this.cutout=GraphicsCutout;',sandbox);
(async()=>{
  const photo={naturalWidth:100,naturalHeight:100};
  const first=await sandbox.cutout.createMask(photo);
  first.width=1; // Simulate editing/freeing the returned mask.
  const second=await sandbox.cutout.createMask(photo);
  assert.strictEqual(runs,1);assert.strictEqual(second.width,100);assert.notStrictEqual(first,second);
  await sandbox.cutout.createMask(photo,{subjects:'all'});assert.strictEqual(runs,2);
  await sandbox.cutout.createMask({naturalWidth:100,naturalHeight:100},{subjects:'all'});assert.strictEqual(runs,3);
  console.log('Cutout cache: repeat skips inference, fresh editable mask, subject/photo invalidation OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
