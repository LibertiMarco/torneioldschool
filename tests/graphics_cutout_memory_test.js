const assert=require('assert');
const fs=require('fs');
const vm=require('vm');
const source=fs.readFileSync(require('path').join(__dirname,'../api/grafiche_scontorno_worker.js'),'utf8');
async function scenario(subjects,fail=false) {
  let active=0,maximum=0;
  const side=1024;
  const loaded=[],released=[],messages=[];
  class Tensor {
    constructor(type,data,dims){this.data=data;this.dims=dims;}
    dispose(){}
  }
  const sandbox={Float32Array,Uint8Array,Map,URL,console,importScripts(){},
    GraphicsCutoutCore:{resizeRGB(){return new Float32Array(3);},selectPeople(a,b,c,d,mode){assert.strictEqual(mode,subjects);return {foreground:[{left:0,top:0,right:1,bottom:1}],background:mode==='main'?[{left:.52,top:.22,right:.55,bottom:.25}]:[]};},composeMatte(detail){return detail;}},
    ort:{Tensor,env:{wasm:{}},InferenceSession:{async create(url){
      const name=url.includes('rtdetr')?'detector':url.includes('encoder')?'encoder':url.includes('decoder')?'decoder':'matte';
      loaded.push(name);maximum=Math.max(maximum,++active);
      return {inputNames:['input'],outputNames:['alpha'],async release(){active--;released.push(name);},async run(feeds){
        if(name==='detector')return {logits:new Tensor('',new Float32Array(1),[1,1,1]),pred_boxes:new Tensor('',new Float32Array(4),[1,1,4])};
        if(name==='encoder'){assert.deepStrictEqual(Array.from(feeds.input.dims),[1024,1024,3]);assert.deepStrictEqual(Array.from(feeds.input.data.slice(0,3)),[0,127.5,255]);if(fail)throw new Error('Inference failed');return {image_embeddings:new Tensor('',new Float32Array([42]),[1,1])};}
        if(name==='decoder') {
          const labels=Array.from(feeds.point_labels.data);
          assert.deepStrictEqual(labels,subjects==='main' && labels.length>2?[2,3,0]:[2,3]);
          assert.strictEqual(feeds.point_coords.dims[1],labels.length);
          if(subjects==='all')assert.strictEqual(labels.length,2);
          return {masks:new Tensor('',new Float32Array(side*side),[1,1,side,side])};
        }
        return {alpha:new Tensor('',new Float32Array(side*side),[1,1,side,side])};
      }};
    }}},self:{location:{href:'https://example.test/api/worker.js'},postMessage(message){messages.push(message);}}};
  vm.createContext(sandbox);vm.runInContext(source,sandbox);
  const rgb=new Float32Array(3*side*side);rgb[side*side]=.5;rgb[2*side*side]=1;
  let state={id:1,data:rgb,width:side,height:side,subjects};
  for(const stage of ['detect','encode','segment','matte']) {
    // A fresh global/runtime per stage, matching the browser worker lifecycle.
    const context={...sandbox,self:{...sandbox.self}};
    vm.createContext(context);vm.runInContext(source,context);
    await context.self.onmessage({data:{...state,stage}});
    const result=messages[messages.length-1];
    if(result.error)break;
    state={...state,...result};
    assert.strictEqual(active,0,'Stage retained a model');
  }
  assert.strictEqual(maximum,1,'Models overlapped in memory');
  assert.strictEqual(active,0,'Model leaked');
  assert.deepStrictEqual(released,loaded);
  assert.strictEqual(Boolean(messages[messages.length-1].error),fail);
  if(!fail)assert.deepStrictEqual(loaded,['detector','encoder','decoder','matte']);
}
(async()=>{await scenario('main');await scenario('all');await scenario('main',true);console.log('Full pipeline: subject modes, sequential model lifetime and failure cleanup OK');})().catch(error=>{console.error(error);process.exitCode=1;});
