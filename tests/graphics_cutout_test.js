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
  assert(g.width<=768&&g.height<=768,'Unbounded mobile inference size');
  assert(g.dw<=g.width&&g.dh<=g.height&&g.x>=0&&g.y>=0);
}
const w=40,h=30,alpha=new Float32Array(w*h);
for(let y=5;y<25;y++)for(let x=3;x<12;x++)alpha[y*w+x]=1;
for(let y=8;y<22;y++)for(let x=26;x<36;x++)alpha[y*w+x]=.95;
for(let y=10;y<20;y++)for(let x=17;x<21;x++)alpha[y*w+x]=.45;
alpha[4*w+5]=.5;
alpha[0]=.04;
const out=cleanMatte(alpha,w,h);
assert.strictEqual(out[10*w+5],255,'First person removed');
assert(out[10*w+30]>245,'Second disconnected person removed');
assert.strictEqual(out[12*w+18],0,'Detached ghost silhouette remains');
assert(out[4*w+5]>90&&out[4*w+5]<180,'Connected soft detail became binary');
assert.strictEqual(out[0],0,'Faint haze remains');
assert(cleanMatte(new Float32Array(100).fill(.4),10,10).every(a=>a===0),'An uncertain mask must not become a ghost photo');
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
console.log('Proportional zoom 10-250%, portrait/landscape padding, multiple subjects, ghost removal and soft edges: OK');
