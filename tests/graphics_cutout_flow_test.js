const fs = require('fs');
const vm = require('vm');
const path = require('path');
const assert = require('assert');
const page = fs.readFileSync(path.join(__dirname, '../api/grafiche_post_partita.php'), 'utf8');
const source = page.slice(page.indexOf('let backgroundRemovalBusy='), page.indexOf('function restoreOriginalPhoto'));
async function scenario({accept = true, stale = '', failure = false} = {}) {
  const original = {naturalWidth:100, naturalHeight:100}, previous = {};
  const mask = {width:100, height:100}, cutout = {};
  const elements = new Map();
  const element = id => {
    if (!elements.has(id)) elements.set(id, {value:'main', hidden:false, disabled:false});
    return elements.get(id);
  };
  let renders = 0, refinements = 0, options;
  const sandbox = {
    imageState:{mvpPhotoOriginal:original, mvpPhoto:previous}, matchLoadVersion:1,
    $:element, drawAll(){renders++;},
    document:{createElement(){return {width:0, height:0, getContext(){return {drawImage(){}};}, toBlob(done){done({});}};}},
    GraphicsCutout:{async createMask(image, opts){
      assert.strictEqual(image, original);options = opts;
      if (stale === 'inference') sandbox.matchLoadVersion++;
      return mask;
    }},
    GraphicsCutoutEditor:{async refine(){
      refinements++;
      assert(element('ftRemoveBg').disabled && element('mvpRemoveBg').disabled, 'Concurrent inference was allowed');
      if (stale === 'editor') sandbox.imageState.mvpPhotoOriginal = {};
      if (failure) throw new Error('Editor failed');
      return accept;
    }},
    async loadImage(){return cutout;}, async readImageData(){return 'image';}
  };
  vm.createContext(sandbox);vm.runInContext(source, sandbox);
  await sandbox.removePhotoBackground('mvpPhoto');
  assert.strictEqual(options.subjects, 'main');
  assert.strictEqual(sandbox.imageState.mvpPhoto, accept && !stale && !failure ? cutout : previous);
  assert.strictEqual(renders, accept && !stale && !failure ? 1 : 0);
  assert.strictEqual(refinements, stale === 'inference' ? 0 : 1);
  assert(!element('ftRemoveBg').disabled && !element('mvpRemoveBg').disabled, 'Buttons remained disabled');
  assert.strictEqual(mask.width, 1, 'Mask memory was not released');
  assert.strictEqual(mask.height, 1);
  if (failure) assert.strictEqual(element('status').textContent, 'Editor failed');
}
(async () => {
  await scenario();await scenario({accept:false});await scenario({stale:'inference'});
  await scenario({stale:'editor'});await scenario({failure:true});
  console.log('Cutout flow: apply, cancel, stale photo/match, editor failure, button state and cleanup: OK');
})().catch(error => {console.error(error);process.exitCode = 1;});
