const fs=require('fs');
const vm=require('vm');
const assert=require('assert');
const path=require('path');
const page=fs.readFileSync(path.join(__dirname,'../api/grafiche_post_partita.php'),'utf8');
const fileImageSource=page.match(/async function fileImage\(file,maxSide=0\)\{[\s\S]*?\n\}/)[0];
const urls=new Map(),revoked=[],renders=[];
let nextUrl=0,failEncode=false;
const sandbox={
  URL:{createObjectURL(value){const url='blob:'+ ++nextUrl;urls.set(url,value);return url;},revokeObjectURL(url){revoked.push(url);}},
  loadImage:async url=>{const source=urls.get(url);return source.invalid?null:{naturalWidth:source.width,naturalHeight:source.height,src:url};},
  document:{createElement(){const canvas={width:0,height:0,getContext:()=>({drawImage(){}}),toBlob(callback,type){renders.push({width:canvas.width,height:canvas.height,type});callback(failEncode?null:{width:canvas.width,height:canvas.height,type});}};return canvas;}}
};
vm.createContext(sandbox);vm.runInContext(fileImageSource,sandbox);
(async()=>{
  const landscape=await sandbox.fileImage({width:4032,height:3024,type:'image/jpeg'},2048);
  assert.strictEqual(landscape.naturalWidth,2048);assert.strictEqual(landscape.naturalHeight,1536);
  assert.strictEqual(revoked.length,2,'Photo URLs leaked after resizing');
  const portrait=await sandbox.fileImage({width:3024,height:4032,type:'image/png'},2048);
  assert.strictEqual(portrait.naturalWidth,1536);assert.strictEqual(portrait.naturalHeight,2048);
  assert.strictEqual(renders[1].type,'image/png','Transparent player cutouts must preserve alpha');
  const small=await sandbox.fileImage({width:600,height:800,type:'image/png'},2048);
  assert.strictEqual(small.naturalWidth,600);assert.strictEqual(renders.length,2,'Small photos should not be re-encoded');
  const base=await sandbox.fileImage({width:3000,height:3750,type:'image/png'});
  assert.strictEqual(base.naturalWidth,3000,'Template validation must still see original dimensions');
  const revokedBeforeFailure=revoked.length;
  failEncode=true;
  await assert.rejects(sandbox.fileImage({width:4032,height:3024,type:'image/jpeg'},2048));
  assert.strictEqual(revoked.length,revokedBeforeFailure+1,'Photo URL leaked after encoder failure');
  console.log('Mobile photo resizing, aspect ratio, transparency and memory cleanup: OK');
  const parentPage=fs.readFileSync(path.join(__dirname,'../api/generatore_grafiche.php'),'utf8');
  const parentScript=parentPage.match(/<script>\s*([\s\S]*?)<\/script>/)[1];
  const frames={};
  for(const id of ['postMatchFrame','templatesFrame','footer-container'])frames[id]={style:{},classList:{add(){}},contentWindow:{postMessage(){}},setAttribute(k,v){this[k]=v;},getAttribute(){return '';}};
  let onMessage;
  const parentSandbox={document:{querySelectorAll:()=>[],getElementById:id=>frames[id]},window:{location:{origin:'http://localhost'},addEventListener(name,fn){if(name==='message')onMessage=fn;}},fetch:async()=>({text:async()=>''})};
  vm.createContext(parentSandbox);vm.runInContext(parentScript,parentSandbox);
  let width=390,height=2700,onResize;
  const queued=[];
  const childSandbox={
    window:{location:{origin:'http://localhost'},addEventListener(){},parent:{postMessage(data,origin){onMessage({data,origin,source:frames.postMatchFrame.contentWindow});}}},
    document:{querySelector:()=>({getBoundingClientRect:()=>({width,height})})},
    getComputedStyle:()=>({marginTop:'28px',marginBottom:'60px'}),
    requestAnimationFrame:fn=>queued.push(fn),
    ResizeObserver:class {constructor(fn){onResize=fn;}observe(){}}
  };
  const sizingScript=page.slice(page.indexOf('// Size the embedded page')).split('<?php')[0];
  vm.createContext(childSandbox);vm.runInContext(sizingScript,childSandbox);
  queued.shift()();
  assert.strictEqual(frames.postMatchFrame.style.height,'2788px');
  assert.strictEqual(frames.postMatchFrame.scrolling,'no','Nested scrolling was not removed');
  onResize();queued.shift()();assert.strictEqual(queued.length,0,'Sizing loops indefinitely');
  height=500;onResize();queued.shift()();assert.strictEqual(frames.postMatchFrame.style.height,'588px','Frame cannot shrink after tab changes');
  width=0;onResize();queued.shift()();assert.strictEqual(frames.postMatchFrame.style.height,'588px','Hidden frame collapsed');
  onMessage({origin:'https://other.test',source:frames.postMatchFrame.contentWindow,data:{type:'graphics-frame-height',height:1234}});
  onMessage({origin:'http://localhost',source:{},data:{type:'graphics-frame-height',height:1234}});
  assert.strictEqual(frames.postMatchFrame.style.height,'588px','Untrusted message resized frame');
  console.log('Embedded height updates, shrink, hidden tabs and message source checks: OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
