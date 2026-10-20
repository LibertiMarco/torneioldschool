const fs = require('fs');
const vm = require('vm');
const path = require('path');
const assert = require('assert');
const sandbox = {URL, document:{currentScript:{src:'http://localhost/api/grafiche_scontorno.js'}}};
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../api/grafiche_scontorno.js'), 'utf8') + '\nthis.cutout = GraphicsCutout;', sandbox);
const {geometry, cleanMatte} = sandbox.cutout;
for (const [w,h] of [[400,1800],[2400,600],[600,800],[500,500],[1,500]]) {
  const g = geometry(w,h);
  assert(Math.abs(g.dw / g.dh - w / h) < 1e-8, 'Preprocessing stretches the photo');
  assert.strictEqual(g.width % 32,0);assert.strictEqual(g.height % 32,0);
  assert(g.width===1024&&g.height===1024,'Unbounded mobile inference size');
  assert(g.dw<=g.width&&g.dh<=g.height&&g.x>=0&&g.y>=0);
}
assert.throws(()=>geometry(0,100),/Dimensioni/);
assert.throws(()=>geometry(Infinity,100),/Dimensioni/);
assert.deepStrictEqual(Array.from(cleanMatte(new Float32Array([0,.5,1,NaN,-1,2]),3,2)),[0,128,255,0,0,255],'Physical alpha must remain soft and bounded');
assert.throws(()=>cleanMatte(new Float32Array(2),3,2),/Maschera/);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../api/grafiche_scontorno_core.js'),'utf8')+'\nthis.core=GraphicsCutoutCore;',sandbox);
const {selectPeople,composeMatte,resizeRGB}=sandbox.core;
const logits=new Float32Array([9,-8,-8,8,-8,-8,7,-8,-8,6,-8,-8,2,8,-8]);
const boxes=new Float32Array([.33,.55,.3,.75,.63,.55,.28,.72,.8,.25,.04,.12,.33,.55,.3,.75,.5,.5,.6,.8]);
const main=selectPeople(logits,boxes,5,3,'main');
assert.strictEqual(main.foreground.length,2,'Two foreground players must be retained');
assert.strictEqual(main.background.length,1,'A smaller background person must be excluded');
const all=selectPeople(logits,boxes,5,3,'all');
assert.strictEqual(all.foreground.length,3,'Group mode must preserve smaller people');
assert.strictEqual(all.background.length,0);
assert.throws(()=>selectPeople(new Float32Array([-10,5,1]),new Float32Array([.5,.5,.8,.8]),1,3),/persona/);
const width=32,height=32,detail=new Float32Array(width*height).fill(1),foreground=new Uint8Array(width*height),background=new Uint8Array(width*height);
for(let y=5;y<28;y++)for(let x=7;x<26;x++)foreground[y*width+x]=1;
for(let y=5;y<12;y++)for(let x=24;x<28;x++)background[y*width+x]=1;
detail[15*width+16]=0; // grass in an enclosed gap, even inside a coarse SAM mask
const hair=4*width+10;detail[hair]=.4;
const matte=composeMatte(detail,foreground,background,width,height,2);
assert.strictEqual(matte[16*width+12],1,'Foreground body interior erased');
assert.strictEqual(matte[8*width+25],0,'Background person touching a shoulder restored');
assert.strictEqual(matte[8*width+27],0,'Edge recovery restored an excluded person');
assert.strictEqual(matte[15*width+16],0,'Grass between bodies filled back in');
assert(Math.abs(matte[hair]-.4)<1e-6,'Soft hair outside the semantic contour was lost');
assert.strictEqual(matte[0],0,'Matting must not restore distant background');
const recovered=composeMatte(detail,foreground,background,width,height,2,[{left:0,right:1,top:0,bottom:1}]);
assert.strictEqual(recovered[0],1,'Opaque clothing at a photo border was cut');
assert.strictEqual(recovered[8*width+25],0,'Clothing recovery restored an excluded background person');
assert.strictEqual(recovered[15*width+16],0,'Clothing recovery filled an enclosed background gap');
const islands=new Float32Array(detail);
for(let y=0;y<3;y++)for(let x=0;x<3;x++)islands[y*width+x]=0;
islands[0]=1;
const isolated=composeMatte(islands,foreground,background,width,height,2,[{left:0,right:1,top:0,bottom:1}]);
assert.strictEqual(isolated[0],0,'Opaque recovery restored a floating background fragment');
assert.strictEqual(isolated[20*width],1,'Connected opaque clothing at the photo border was lost');
const rgb=new Float32Array([0,1,1,0,.25,.25,.25,.25,.75,.75,.75,.75]);
assert.deepStrictEqual(Array.from(resizeRGB(rgb,2,2,1)),[.5,.25,.75],'Detector resize must interpolate all three RGB channels');
const page=fs.readFileSync(path.join(__dirname,'../api/grafiche_post_partita.php'),'utf8');
vm.runInContext(page.slice(page.indexOf('function cover('),page.indexOf('const visibleImageBounds=')),sandbox);
for(const [iw,ih] of [[400,1800],[2400,600],[600,800]])for(const zoom of [10,50,100,250]) {
  let call,clip,restored=false;
  const ctx={save(){},beginPath(){},rect(...args){clip=args;},clip(){},drawImage(...args){call=args;},restore(){restored=true;}};
  sandbox.cover(ctx,{naturalWidth:iw,naturalHeight:ih},20,30,600,800,{zoom,x:50,y:50});
  assert.strictEqual(call.length,5,'Do not use oversized source crop rectangles');
  const [,dx,dy,dw,dh]=call;
  assert(Math.abs(dw/dh-iw/ih)<1e-8,'Aspect ratio changed');
  assert(Math.abs(dw/iw-dh/ih)<1e-8,'Width and height scaled differently');
  assert(Math.abs(dx+dw/2-320)<1e-8&&Math.abs(dy+dh/2-430)<1e-8,'Position drifted');
  assert.deepStrictEqual(clip,[20,30,600,800]);assert(restored);
}
console.log('Zoom/aspect ratio, person instances, group selection, touching background, enclosed gaps and soft alpha: OK');


// A partially visible head can have low confidence: exclude it in main mode,
// without promoting weak large detections or changing the all-people mode.
const occludedLogits=new Float32Array([8,-8,7,-8,-.4,-8,-.4,-8]);
const occludedBoxes=new Float32Array([.35,.55,.25,.7,.65,.55,.28,.72,.55,.24,.025,.03,.5,.5,.8,.8]);
const occluded=selectPeople(occludedLogits,occludedBoxes,4,2,'main');
assert.strictEqual(occluded.foreground.length,2);
assert.strictEqual(occluded.background.length,1,'Partially occluded head was discarded');
const occludedAll=selectPeople(occludedLogits,occludedBoxes,4,2,'all');
assert.strictEqual(occludedAll.foreground.length,2,'Weak detections must not become group subjects');
assert.strictEqual(occludedAll.background.length,0);
assert.throws(()=>selectPeople(new Float32Array([-.4,-8]),new Float32Array([.5,.5,.5,.5]),1,2,'main'),/persona/);
