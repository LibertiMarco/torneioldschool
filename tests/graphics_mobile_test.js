const fs=require('fs');
const vm=require('vm');
const assert=require('assert');
const path=require('path');
const page=fs.readFileSync(path.join(__dirname,'../api/grafiche_post_partita.php'),'utf8');
// Exercise decoding under the production policy: local previews must work without blob:.
const htaccess=fs.readFileSync(path.join(__dirname,'../.htaccess'),'utf8');
const policy=htaccess.match(/Header set Content-Security-Policy "([^"]+)"/)[1];
assert(policy.includes("'wasm-unsafe-eval'"),'CSP must permit the on-device segmentation runtime');
assert(policy.match(/script-src ([^;]+)/)[1].includes('https://cdn.jsdelivr.net'),'CSP must allow the pinned MediaPipe script');
assert(policy.match(/connect-src ([^;]+)/)[1].includes('https://cdn.jsdelivr.net'),'CSP must allow MediaPipe runtime/model assets');
const imageSources=policy.match(/(?:^|;)\s*img-src\s+([^;]+)/)[1].split(/\s+/);
const fileImageSource=page.slice(page.indexOf('function readImageData('),page.indexOf('function cover('));
const renders=[],canvases=[];
let failEncode=false;
const sandbox={
  FileReader:class {readAsDataURL(file){if(file.readError)return this.onerror();if(file.abort)return this.onabort();this.result='data:'+file.type+';base64,'+Buffer.from(JSON.stringify(file)).toString('base64');this.onload();}},
  loadImage:async url=>{assert(imageSources.includes(url.split(':')[0]+':'),'CSP blocked photo');const source=JSON.parse(Buffer.from(url.split(',')[1],'base64'));return source.invalid?null:{naturalWidth:source.width,naturalHeight:source.height,src:url};},
  document:{createElement(){const canvas={width:0,height:0,getContext:()=>({drawImage(){}}),toBlob(callback,type){renders.push({width:canvas.width,height:canvas.height,type});callback(failEncode?null:{width:canvas.width,height:canvas.height,type});}};canvases.push(canvas);return canvas;}}
};
vm.createContext(sandbox);vm.runInContext(fileImageSource,sandbox);
(async()=>{
  const landscape=await sandbox.fileImage({width:4032,height:3024,type:'image/jpeg'},2048);
  assert.strictEqual(landscape.naturalWidth,2048);assert.strictEqual(landscape.naturalHeight,1536);
  assert(landscape.src.startsWith('data:image/jpeg;'),'Resized photo must remain compatible with CSP');
  const portrait=await sandbox.fileImage({width:3024,height:4032,type:'image/png'},2048);
  assert.strictEqual(portrait.naturalWidth,1536);assert.strictEqual(portrait.naturalHeight,2048);
  assert.strictEqual(renders[1].type,'image/png','Transparent player cutouts must preserve alpha');
  const small=await sandbox.fileImage({width:600,height:800,type:'image/png'},2048);
  assert.strictEqual(small.naturalWidth,600);assert.strictEqual(renders.length,2,'Small photos should not be re-encoded');
  const base=await sandbox.fileImage({width:3000,height:3750,type:'image/png'});
  assert.strictEqual(base.naturalWidth,3000,'Template validation must still see original dimensions');
  failEncode=true;
  await assert.rejects(sandbox.fileImage({width:4032,height:3024,type:'image/jpeg'},2048));
  assert(canvases.every(canvas=>canvas.width===1&&canvas.height===1),'Resize canvas memory was not released');
  await assert.rejects(sandbox.fileImage({readError:true}),/Impossibile leggere/);
  await assert.rejects(sandbox.fileImage({abort:true}),/interrotto/);
  assert.strictEqual(await sandbox.fileImage({invalid:true}),null);
  failEncode=false;
  const fields={ftCaptains:{files:[{width:4032,height:3024,type:'image/jpeg'}]},mvpPhoto:{files:[{width:600,height:800,type:'image/png'}]},status:{}};
  for(const id of ['ftPhotoActions','mvpPhotoActions','ftRemoveBg','mvpRemoveBg','ftRestorePhoto','mvpRestorePhoto'])fields[id]={hidden:true,disabled:false};
  sandbox.$=id=>fields[id];sandbox.matchLoadVersion=0;sandbox.imageState={};sandbox.drawAll=()=>{sandbox.draws=(sandbox.draws||0)+1;};
  vm.runInContext(page.match(/async function updateImage\(id\)\{[^\n]+/)[0],sandbox);
  await sandbox.updateImage('ftCaptains');await sandbox.updateImage('mvpPhoto');
  assert.strictEqual(sandbox.imageState.ftCaptains.naturalWidth,2048);
  assert.strictEqual(sandbox.imageState.mvpPhoto.naturalHeight,800);
  assert.strictEqual(sandbox.imageState.ftCaptainsOriginal,sandbox.imageState.ftCaptains,'Full Time original photo was not kept for restore');
  assert.strictEqual(sandbox.imageState.mvpPhotoOriginal,sandbox.imageState.mvpPhoto,'MVP original photo was not kept for restore');
  assert.strictEqual(fields.ftPhotoActions.hidden,false,'Full Time background removal option did not appear after upload');
  assert.strictEqual(fields.mvpPhotoActions.hidden,false,'MVP background removal option did not appear after upload');
  assert.strictEqual(fields.ftRemoveBg.hidden,false,'Full Time background removal button stayed hidden');
  assert.strictEqual(fields.ftRestorePhoto.hidden,true,'Restore button appeared before processing');
  assert.strictEqual(sandbox.draws,2,'Both photo inputs must update the previews');
  console.log('Photo inputs, CSP-compatible decoding, resizing, transparency, read errors and memory cleanup: OK');
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
