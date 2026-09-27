// Run with node tests/graphics_upload_browser_test.js, then open http://127.0.0.1:8765.
// Uses synthetic images and the actual generator scripts; no database or login required.
const fs = require('fs');
const path = require('path');
const http = require('http');
const root = path.join(__dirname, '..');
const policy = "default-src 'self'; img-src 'self' data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'";

function fixture() {
  let page = fs.readFileSync(path.join(root, 'api/grafiche_post_partita.php'), 'utf8');
  page = page.slice(page.indexOf('<!doctype html>'));
  for (const [name, value] of Object.entries({templateEditor:'false', graphicsTemplatesCsrf:'"test"', tournaments:'[]', matches:'[]', matchPlayers:'{}'})) {
    page = page.replace(new RegExp('const '+name+'=<\\?=[\\s\\S]*?\\?>;'), 'const '+name+'='+value+';');
  }
  page = page.replace(/<\?php if \(!\$embedded\): \?>fetch\([\s\S]*?<\?php endif; \?>/, '');
  page = page.replace(/<\?[\s\S]*?\?>/g, '');
  page = page.replace('</body>', `<button id="runPhotoTest">Verifica foto Fulltime e MVP</button><pre id="testResult" role="status"></pre>
<script>
document.getElementById('runPhotoTest').onclick=async()=>{
  const result=document.getElementById('testResult');
  result.textContent='Verifica in corso…';
  try {
    function expect(value,message){if(!value)throw new Error(message);}
    async function upload(id,width,height,color){
      const canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
      const ctx=canvas.getContext('2d');ctx.fillStyle=color;ctx.fillRect(0,0,width,height);
      const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
      const files=new DataTransfer();files.items.add(new File([blob],id+'.png',{type:'image/png'}));
      const input=document.getElementById(id);input.files=files.files;
      imageState[id]=null;input.dispatchEvent(new Event('change',{bubbles:true}));
      const deadline=performance.now()+10000;
      while(!imageState[id]&&performance.now()<deadline)await new Promise(requestAnimationFrame);
      expect(imageState[id]?.naturalWidth>0,id+': caricamento fallito');
      expect(Math.max(imageState[id].naturalWidth,imageState[id].naturalHeight)<=2048,'Ridimensionamento fallito');
    }
    function verifyPixels(id,color){
      const canvas=document.getElementById(id);
      const pixels=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data;
      let count=0;
      for(let i=0;i<pixels.length;i+=4){
        if(color==='magenta'&&pixels[i]>200&&pixels[i+1]<30&&pixels[i+2]>200)count++;
        if(color==='green'&&pixels[i]<30&&pixels[i+1]>200&&pixels[i+2]<30)count++;
      }
      expect(count>10000,id+': foto assente dalla grafica');
      expect(canvas.toDataURL('image/png').startsWith('data:image/png;'),'Esportazione PNG fallita');
    }
    await upload('ftCaptains',4032,3024,'#ff00ff');
    await upload('mvpPhoto',600,800,'#00ff00');
    verifyPixels('fulltimeCanvas','magenta');verifyPixels('mvpCanvas','green');
    const base=document.createElement('canvas');base.width=1080;base.height=1350;
    base.getContext('2d').fillRect(0,0,1080,1350);
    const originalFetch=window.fetch;
    window.fetch=async()=>({ok:true,json:async()=>({ft:{image:base.toDataURL(),layout:{}},mvp:{image:base.toDataURL(),layout:{}}})});
    try{await customTemplates.selectTournament('fixture');}finally{window.fetch=originalFetch;}
    verifyPixels('fulltimeCanvas','magenta');verifyPixels('mvpCanvas','green');
    // Exercise actual canvas readback, soft-alpha refinement and PNG decoding.
    const mask=document.createElement('canvas');mask.width=600;mask.height=800;
    const maskCtx=mask.getContext('2d'),gradient=maskCtx.createLinearGradient(0,0,600,0);
    gradient.addColorStop(0,'rgba(255,255,255,0.07)');gradient.addColorStop(.4,'rgba(255,255,255,0.07)');
    gradient.addColorStop(.6,'white');gradient.addColorStop(1,'white');
    maskCtx.fillStyle=gradient;maskCtx.fillRect(0,0,600,800);
    const original=imageState.mvpPhoto;
    const model={onResults(callback){this.callback=callback;},async send(){this.callback({segmentationMask:mask});}};
    selfieSegmentationPromise=Promise.resolve(model);
    const started=performance.now();
    await removePhotoBackground('mvpPhoto');
    const elapsed=Math.round(performance.now()-started);
    expect(imageState.mvpPhoto!==original,'Rimozione sfondo fallita: '+$('status').textContent);
    const cutout=document.createElement('canvas');cutout.width=600;cutout.height=800;
    cutout.getContext('2d').drawImage(imageState.mvpPhoto,0,0);
    const rgba=cutout.getContext('2d').getImageData(0,400,600,1).data;
    expect(rgba[50*4+3]<5,'Residui di sfondo ancora presenti');
    expect(rgba[300*4+3]>50&&rgba[300*4+3]<220,'Bordi morbidi persi');
    expect(rgba[550*4+3]===255,'Soggetto diventato trasparente');
    expect(rgba[550*4+1]===255,'Colore della foto alterato');
    restoreOriginalPhoto('mvpPhoto');expect(imageState.mvpPhoto===original,'Ripristino originale fallito');
    result.textContent='PASS: foto capitani e MVP; template; ridimensionamento; PNG; CSP senza blob; rifinitura alpha; colori; ripristino. Ritaglio 600×800: '+elapsed+' ms.';
  }catch(error){result.textContent='FAIL: '+error.message;}
};
</script></body>`);
  return page;
}

http.createServer((req,res)=>{
  res.setHeader('Content-Security-Policy',policy);
  res.setHeader('Cache-Control','no-store');
  if(req.url==='/'){
    res.setHeader('Content-Type','text/html; charset=utf-8');res.end(fixture());
  }else if(req.url.startsWith('/grafiche_basi.js')){
    res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(fs.readFileSync(path.join(root,'api/grafiche_basi.js')));
  }else if(req.url.startsWith('/grafiche_scontorno.js')){
    res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(fs.readFileSync(path.join(root,'api/grafiche_scontorno.js')));
  }else{res.statusCode=404;res.end();}
}).listen(8765,'127.0.0.1',()=>console.log('Graphics photo browser test: http://127.0.0.1:8765'));
