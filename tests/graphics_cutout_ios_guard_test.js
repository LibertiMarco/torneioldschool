const assert=require('assert'),fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../api/grafiche_scontorno.js'),'utf8')+';this.cutout=GraphicsCutout;';
(async()=>{
 for(const navigator of [
  {userAgent:'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X)',platform:'iPhone'},
  {userAgent:'Mozilla/5.0 (iPad)',platform:'iPad'},
  {userAgent:'Mozilla/5.0 (Macintosh)',platform:'MacIntel',maxTouchPoints:5}
 ]){
  const stages=[];let active=0;
  const sandbox={URL,navigator,setTimeout,clearTimeout,
   document:{currentScript:{src:'https://example.test/api/grafiche_scontorno.js'},createElement(){return {width:0,height:0,getContext(){return {fillRect(){},drawImage(){},getImageData(){return {data:new Uint8ClampedArray(1024*1024*4)};},createImageData(w,h){return {data:new Uint8ClampedArray(w*h*4)};},putImageData(){}};}};}},
   Worker:class {
    constructor(){assert.strictEqual(active,0);active++;}
    terminate(){active--;}
    postMessage(message){
     stages.push(message.stage);assert.strictEqual(message.width,1024);
     assert(['detect','encode','segment','matte'].includes(message.stage));
     Promise.resolve().then(()=>this.onmessage({data:{id:message.id,data:message.data,width:1024,height:1024,people:{foreground:[{}],background:[]},embedding:{data:new Float32Array(1),dims:[1]},masks:{foreground:new Uint8Array(1),background:new Uint8Array(1)},alpha:new Float32Array(1024*1024).fill(1)}}));
    }
   }
  };
  vm.createContext(sandbox);vm.runInContext(source,sandbox);
  const mask=await sandbox.cutout.createMask({naturalWidth:960,naturalHeight:1280});
  assert.deepStrictEqual(stages,['detect','encode','segment','matte']);assert.strictEqual(mask.width,960);assert.strictEqual(mask.height,1280);assert.strictEqual(active,0);
 }
 console.log('iPhone/iPad: automatic per-person mask at original output size with MobileSAM, sequential workers OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
