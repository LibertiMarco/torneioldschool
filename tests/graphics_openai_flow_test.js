const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const page = fs.readFileSync(require('path').join(__dirname, '../api/grafiche_post_partita.php'), 'utf8');
const source = page.slice(page.indexOf('let backgroundRemovalBusy='), page.indexOf('function restoreOriginalPhoto'));
async function scenario(mode) {
  const original = {naturalWidth:100,naturalHeight:200}, previous = {}, cutout = {};
  const fields = new Map();
  const $ = id => {if (!fields.has(id)) fields.set(id,{disabled:false});return fields.get(id);};
  let calls=0, draws=0;
  const canvas={getContext:()=>({drawImage(){}}),toBlob:done=>done({})};
  const sandbox={$,imageState:{mvpPhotoOriginal:original,mvpPhoto:previous},matchLoadVersion:1,
    graphicsCutoutCsrf:'test-token',document:{createElement:()=>canvas},
    FormData:class{constructor(){this.fields={};}append(k,v){this.fields[k]=v;}},
    drawAll(){draws++;},loadImage:async()=>cutout,
    async fetch(url,options){
      calls++;assert.strictEqual(url,'graphics_remove_background.php');
      assert.strictEqual(options.body.fields._csrf,'test-token');
      assert.strictEqual(options.body.fields.subjects,'main');
      assert($('ftRemoveBg').disabled && $('mvpRemoveBg').disabled);
      await sandbox.removePhotoBackground('mvpPhoto');
      if(mode==='stale')sandbox.imageState.mvpPhotoOriginal={};
      if(mode==='network')throw new Error('Offline');
      return {ok:mode!=='error',json:async()=>{if(mode==='invalid')throw new Error();return mode==='error'?{error:'Credito esaurito'}:{image:'data:image/png;base64,test'};}};
    }};
  vm.createContext(sandbox);vm.runInContext(source,sandbox);
  await sandbox.removePhotoBackground('mvpPhoto');
  assert.strictEqual(calls,1,'Request repeated');
  assert.strictEqual(sandbox.imageState.mvpPhoto,mode==='success'?cutout:previous);
  assert.strictEqual(draws,mode==='success'?1:0);
  assert(!$('ftRemoveBg').disabled && !$('mvpRemoveBg').disabled);
  assert.strictEqual(canvas.width,1);
  if(mode==='error')assert.strictEqual($('status').textContent,'Credito esaurito');
}
(async()=>{for(const mode of ['success','stale','error','invalid','network'])await scenario(mode);console.log('OpenAI flow: success, stale upload, API/JSON/network errors, concurrency and cleanup OK');})().catch(e=>{console.error(e);process.exitCode=1;});
