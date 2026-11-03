const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const page=fs.readFileSync(path.join(__dirname,'../api/grafiche_post_partita.php'),'utf8');
const source=page.slice(page.indexOf('let backgroundRemovalBusy='),page.indexOf('function restoreOriginalPhoto'));
async function scenario(mode,id='mvpPhoto') {
  const original={naturalWidth:100,naturalHeight:200},previous={},cutout={},mask={width:100,height:200};
  const fields=new Map(),$=key=>{if(!fields.has(key))fields.set(key,{});return fields.get(key);};
  let renders=0,calls=0;const operations=[];
  const canvas={getContext:()=>({drawImage(){operations.push(this.globalCompositeOperation||'source-over');}}),toBlob:done=>done(mode==='encoding'?null:{})};
  const sandbox={$,imageState:{[id+'Original']:original,[id]:previous},matchLoadVersion:1,
    document:{createElement:()=>canvas},drawAll(){renders++;},readImageData:async()=> 'image',loadImage:async()=>cutout,
    GraphicsCutout:{clearPhase(){},async createMask(image,options){
      calls++;assert.strictEqual(image,original);assert.strictEqual(options.subjects,id==='ftCaptains'?'all':'main');
      assert($('ftRemoveBg').disabled&&$('mvpRemoveBg').disabled);
      await sandbox.removePhotoBackground(id);assert.strictEqual(calls,1,'Concurrent inference allowed');
      options.onProgress('Working');
      if(mode==='failure')throw new Error('Model failed');
      if(mode==='stale')sandbox.imageState[id+'Original']={};
      return mask;
    }}};
  if(mode==='missing')delete sandbox.GraphicsCutout;
  if(mode==='no-photo')sandbox.imageState[id+'Original']=null;
  vm.createContext(sandbox);vm.runInContext(source,sandbox);await sandbox.removePhotoBackground(id);
  const applied=mode==='success';
  assert.strictEqual(sandbox.imageState[id],applied?cutout:previous);
  assert.strictEqual(renders,applied?1:0);
  assert(!$('ftRemoveBg').disabled&&!$('mvpRemoveBg').disabled);
  if(applied){assert.deepStrictEqual(operations,['source-over','destination-in']);assert.strictEqual($(id==='ftCaptains'?'ftRestorePhoto':'mvpRestorePhoto').hidden,false);}
  if(['success','stale','encoding'].includes(mode)){assert.strictEqual(mask.width,1);assert.strictEqual(mask.height,1);}
  if(mode==='failure')assert.strictEqual($(id==='ftCaptains'?'ftPhotoStatus':'mvpPhotoStatus').textContent,'Model failed');
  if(mode==='missing')assert.match($('mvpPhotoStatus').textContent,/non caricato/);
  if(mode==='no-photo')assert.match($('mvpPhotoStatus').textContent,/Carica una foto/);
}
(async()=>{for(const mode of ['success','stale','failure','encoding','missing','no-photo'])await scenario(mode);await scenario('success','ftCaptains');assert(!/fetch\('graphics_remove_background/.test(source));assert(!page.includes('id="ftZoom"'));assert(!page.includes('id="ftHomeLogo"'));console.log('Browser cutout flow: auto apply, both subject modes, stale photo, inference/encoding errors, missing engine/photo, concurrency and cleanup OK');})().catch(e=>{console.error(e);process.exitCode=1;});
