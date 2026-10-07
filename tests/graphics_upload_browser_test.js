// Run with node tests/graphics_upload_browser_test.js, then open http://127.0.0.1:8765.
// Uses synthetic images and the actual generator scripts; no database or login required.
const fs = require('fs');
const path = require('path');
const http = require('http');
const root = path.join(__dirname, '..');
const policy = fs.readFileSync(path.join(root,'.htaccess'),'utf8').match(/Header set Content-Security-Policy "([^"]+)"/)[1];

function fixture() {
  let page = fs.readFileSync(path.join(root, 'api/grafiche_post_partita.php'), 'utf8');
  page = page.slice(page.indexOf('<!doctype html>'));
  for (const [name, value] of Object.entries({instagramPublishCsrf:'"test"',templateEditor:'false', graphicsTemplatesCsrf:'"test"', tournaments:'[]', matches:'[]', matchPlayers:'{}'})) {
    page = page.replace(new RegExp('const '+name+'\\s*=\\s*<\\?=[\\s\\S]*?\\?>;'), 'const '+name+'='+value+';');
  }
  page = page.replace(/<\?php if \(!\$embedded\): \?>fetch\([\s\S]*?<\?php endif; \?>/, '');
  page = page.replace(/<\?[\s\S]*?\?>/g, '');
  page = page.replace('</body>', `<button id="runPhotoTest">Verifica foto Fulltime e MVP</button><pre id="testResult" role="status"></pre>
<button id="runRealTest">Verifica MODNet su foto reale</button><button id="runPortraitTest">Verifica MODNet su ritratto verticale</button><pre id="realResult" role="status"></pre><div id="realPreview" style="display:flex;background:#ccc;gap:20px;padding:20px"></div>
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
    for(const [type,id] of [['ft','fulltimeCanvas'],['mvp','mvpCanvas']]){
      document.querySelector('[data-panel="'+(type==='ft'?'fulltimePanel':'mvpPanel')+'"]').click();
      const canvas=document.getElementById(id),wrap=canvas.parentElement,selection=wrap.lastElementChild.firstElementChild;
      const image=imageState[type==='ft'?'ftCaptains':'mvpPhoto'];
      for(const key of ['n','ne','e','se','s','sw','w','nw']){
        const handle=selection.querySelector('[data-handle="'+key+'"]');
        const event=(name,x,y)=>new PointerEvent(name,{bubbles:true,pointerId:1,clientX:x,clientY:y,button:0});
        // Synthetic events do not create native pointer capture.
        const capture=selection.setPointerCapture;selection.setPointerCapture=()=>{};
        try{handle.dispatchEvent(event('pointerdown',200,300));handle.dispatchEvent(event('pointermove',240,330));handle.dispatchEvent(event('pointerup',240,330));}finally{selection.setPointerCapture=capture;}
        const b=PhotoTouchEditor.transform(type,image,{x:0,y:0,w:1080,h:1350},{x:0,y:0,w:image.naturalWidth,h:image.naturalHeight});
        expect(Math.abs(b.w/b.h-image.naturalWidth/image.naturalHeight)<1e-8,type+': proporzioni alterate da '+key);
        drawAll();
      }
      const blob=await canvasBlob(canvas),decoded=await createImageBitmap(blob);
      expect(decoded.width===1080&&decoded.height===1350,'Dimensioni PNG errate');decoded.close();
    }
    document.querySelector('[data-panel="fulltimePanel"]').click();
    const base=document.createElement('canvas');base.width=1080;base.height=1350;
    base.getContext('2d').fillRect(0,0,1080,1350);
    const originalFetch=window.fetch;
    window.fetch=async()=>({ok:true,json:async()=>({ft:{image:base.toDataURL(),layout:{}},mvp:{image:base.toDataURL(),layout:{}}})});
    try{await customTemplates.selectTournament('fixture');}finally{window.fetch=originalFetch;}
    verifyPixels('fulltimeCanvas','magenta');verifyPixels('mvpCanvas','green');
    // Exercise actual canvas readback, soft-alpha refinement and PNG decoding.
    const original=imageState.mvpPhoto;
    const createMask=GraphicsCutout.createMask;
    GraphicsCutout.createMask=async image=>{
      const g=GraphicsCutout.geometry(image.naturalWidth,image.naturalHeight),alpha=new Float32Array(g.width*g.height);
      for(let y=0;y<g.height;y++)for(let x=0;x<g.width;x++)alpha[y*g.width+x]=Math.max(.07,Math.min(1,(x/g.width-.4)*5));
      return GraphicsCutout.maskFromMatte(image,{alpha,width:g.width,height:g.height},g);
    };
    const started=performance.now();
    try{await removePhotoBackground('mvpPhoto');}finally{GraphicsCutout.createMask=createMask;}
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
    // A circle in a transparent, non-square PNG must remain circular at every zoom.
    for(const [width,height] of [[400,800],[1200,400]]){
      const source=document.createElement('canvas');source.width=width;source.height=height;
      const ctx=source.getContext('2d');ctx.fillStyle='#00ff00';ctx.beginPath();ctx.arc(width/2,height/2,50,0,Math.PI*2);ctx.fill();
      const img=await loadImage(source.toDataURL());
      for(const zoom of [10,50,100,250]){
        const target=document.createElement('canvas');target.width=600;target.height=800;
        cover(target.getContext('2d'),img,0,0,600,800,{zoom,x:50,y:50});
        const data=target.getContext('2d').getImageData(0,0,600,800).data;
        let left=600,right=-1,top=800,bottom=-1;
        for(let y=0;y<800;y++)for(let x=0;x<600;x++)if(data[(y*600+x)*4+3]>128){left=Math.min(left,x);right=Math.max(right,x);top=Math.min(top,y);bottom=Math.max(bottom,y);}
        expect(right>=left&&Math.abs((right-left)-(bottom-top))<=1,'PNG deformato a zoom '+zoom+'%');
        const expected=100*Math.max(600/width,800/height)*zoom/100;
        expect(Math.abs(right-left+1-expected)<=2,'Scala errata a zoom '+zoom+'%');
      }
    }
    result.textContent='PASS: Full Time e MVP; template; PNG trasparenti; proporzioni 10/50/100/250%; alpha; colori; ripristino. Ritaglio sintetico: '+elapsed+' ms.';
  }catch(error){result.textContent='FAIL: '+error.message;}
};
async function runRealPhotoTest(portrait=false){
  const result=document.getElementById('realResult');result.textContent='Caricamento modello e scontorno reale…';
  try{
    const source=await loadImage(portrait?'/test-portrait.jpg':'/test-team.jpg');
    const photo=document.createElement('canvas');photo.width=portrait?source.naturalWidth:684;photo.height=portrait?source.naturalHeight:680;
    if(portrait)photo.getContext('2d').drawImage(source,0,0);
    else photo.getContext('2d').drawImage(source,260,264,684,680,0,0,684,680);
    const img=await loadImage(photo.toDataURL());
    imageState.ftCaptains=imageState.ftCaptainsOriginal=img;
    const start=performance.now();await removePhotoBackground('ftCaptains');
    if(imageState.ftCaptains===img)throw new Error($('status').textContent);
    const preview=document.getElementById('realPreview');preview.replaceChildren();
    for(const [label,image] of [['Originale',img],['Ritaglio su bianco',imageState.ftCaptains],['Ritaglio su scuro',imageState.ftCaptains]]){
      const figure=document.createElement('figure'),caption=document.createElement('figcaption'),canvas=document.createElement('canvas');
      canvas.width=photo.width;canvas.height=photo.height;canvas.style.width='100%';caption.textContent=label;
      const ctx=canvas.getContext('2d');ctx.fillStyle=label.includes('scuro')?'#07111d':'#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(image,0,0);
      figure.style.cssText='margin:0;flex:1;min-width:0;color:#000';figure.append(caption,canvas);preview.append(figure);
    }
    result.textContent='PASS: modello MODNet reale, '+(portrait?'ritratto verticale':'foto di gruppo')+', PNG e CSP produzione. '+Math.round(performance.now()-start)+' ms.';
  }catch(error){result.textContent='FAIL: '+error.message;}
};
document.getElementById('runRealTest').onclick=()=>runRealPhotoTest();
document.getElementById('runPortraitTest').onclick=()=>runRealPhotoTest(true);
</script></body>`);
  return page;
}

http.createServer((req,res)=>{
  res.setHeader('Content-Security-Policy',policy);
  res.setHeader('Cache-Control','no-store');
  if(req.url==='/'){
    res.setHeader('Content-Type','text/html; charset=utf-8');res.end(fixture());
  }else if(req.url.startsWith('/grafiche_foto_touch.js')){
    res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(fs.readFileSync(path.join(root,'api/grafiche_foto_touch.js')));
  }else if(req.url.startsWith('/grafiche_basi.js')){
    res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(fs.readFileSync(path.join(root,'api/grafiche_basi.js')));
  }else if(req.url.startsWith('/grafiche_scontorno.js')){
    res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(fs.readFileSync(path.join(root,'api/grafiche_scontorno.js')));
  }else if(req.url.startsWith('/grafiche_scontorno_worker.js')){
    res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(fs.readFileSync(path.join(root,'api/grafiche_scontorno_worker.js')));
  }else if(req.url==='/models/modnet-fa2fa546.onnx'){
    res.setHeader('Content-Type','application/octet-stream');fs.createReadStream(path.join(root,'api/models/modnet-fa2fa546.onnx')).pipe(res);
  }else if(req.url==='/test-team.jpg'){
    res.setHeader('Content-Type','image/jpeg');res.end(fs.readFileSync(path.join(root,'img/blog/vincitriceseriea.jpg')));
  }else if(req.url==='/test-portrait.jpg'){
    res.setHeader('Content-Type','image/jpeg');res.end(fs.readFileSync(path.join(root,'img/giocatori/MarcoLiberti.jpg')));
  }else{res.statusCode=404;res.end();}
}).listen(8765,'127.0.0.1',()=>console.log('Graphics photo browser test: http://127.0.0.1:8765'));
