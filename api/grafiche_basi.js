/* Basi personalizzate: le foto delle partite restano locali, base e layout si salvano sul server. */
const customTemplates = (() => {
  const labels = {photo:'Foto giocatori',homeLogo:'Logo casa / squadra MVP',awayLogo:'Logo ospite / secondo MVP',homeScore:'Gol squadra casa',awayScore:'Gol squadra ospite',home:'Nome squadra casa',away:'Nome squadra ospite',round:'Giornata / fase',names:'Nomi MVP',team:'Squadra MVP',details:'Dettaglio MVP'};
  const element = (x,y,w,h,font=40,visible=true) => ({x,y,w,h,font,visible,color:'#ffffff'});
  const defaults = type => type === 'ft'
    ? {photo:element(80,220,920,700),homeLogo:element(80,950,180,180),awayLogo:element(820,950,180,180),homeScore:element(290,980,250,140,110),awayScore:element(540,980,250,140,110),home:element(30,1150,400,60,34),away:element(650,1150,400,60,34),round:element(180,1240,720,60,26)}
    : {photo:element(100,200,880,820),homeLogo:element(70,1100,130,130),awayLogo:element(880,1100,130,130),names:element(70,1030,940,100,54),team:element(210,1150,660,70,32),details:element(150,1240,780,60,26)};
  const state = new Map();
  let currentId = '';
  let editor = false;
  const fresh = type => ({image:null,file:null,overlays:[],selectedOverlayId:'',layout:defaults(type),dirty:false,busy:false,loading:false,error:'',saveError:''});
  const pair = () => state.get(currentId);
  const message = text => { $('status').textContent = text; };

  function normalizeLayout(type, saved = {}) {
    const layout = {...saved};
    // Preserve the old result's position and styling when opening an existing template.
    if (type === 'ft' && layout.score) {
      const old = layout.score, half = Math.floor(old.w / 2);
      if (!layout.homeScore) layout.homeScore = {...old,w:Math.max(1,half)};
      if (!layout.awayScore) layout.awayScore = {...old,x:Math.min(1080,old.x+half),w:Math.max(1,old.w-half)};
    }
    delete layout.score;
    delete layout.scoreOverlay;
    return {...defaults(type),...layout};
  }

  function init(options = {}) {
    editor = options.editor === true;
    if (!editor) return;
    for (const type of ['ft','mvp']) {
      const box = document.createElement('details');
      box.className = 'template-editor'; box.open = true;
      box.innerHTML = `<summary>Base personalizzata ${type==='ft'?'Full Time':'MVP'}</summary>
        <p class="hint">Carica la tua grafica, poi seleziona un elemento e trascinalo nell?anteprima con il dito o il mouse, oppure usa i campi X e Y. Usa due dita per ingrandire o rimpicciolire l?elemento selezionato. Consigliato: 1080 × 1350 px; PNG, JPG o WebP, massimo 8 MB. Per giocatori scontornati usa foto PNG trasparenti.</p>
        <fieldset id="${type}BaseControls"><div class="fields">
          <label class="wide">Immagine di base<input id="${type}Base" type="file" accept="image/png,image/jpeg,image/webp"></label>
          ${type==='ft'?'<label class="wide">Aggiungi immagini livello (selezione multipla)<input id="ftOverlayFiles" type="file" accept="image/png,image/jpeg,image/webp" multiple></label><label class="wide">Immagine selezionata<select id="ftOverlaySelect"></select></label><label class="wide">Livello<select id="ftOverlayLayer"><option value="behind_graphic">Dietro la grafica</option><option value="between_graphic_photo">Tra grafica e foto</option><option value="between_photo_content">Sopra grafica e foto, sotto loghi e testi</option><option value="front">Davanti a tutto</option></select></label><label>Posizione X (px)<input id="ftOverlayX" type="number" step="1"></label><label>Posizione Y (px)<input id="ftOverlayY" type="number" step="1"></label><label>Larghezza (px, proporzionale)<input id="ftOverlayW" type="number" min="0.01" step="any"></label><label>Altezza (px, proporzionale)<input id="ftOverlayH" type="number" min="0.01" step="any"></label><div class="wide actions"><button id="ftRemoveOverlay" type="button" class="secondary">Rimuovi immagine selezionata</button><span id="ftOverlayStatus" class="hint" aria-live="polite">Carica una o più immagini. Le misure iniziali corrispondono ai pixel del file.</span></div>':''}
          <label class="wide">Elemento da posizionare<select id="${type}Element"></select></label>
          <label class="wide template-check"><input id="${type}Visible" type="checkbox"> Mostra elemento</label>
          <label>Posizione X (px)<input id="${type}LayoutX" type="number" min="0" max="1080"></label>
          <label>Posizione Y (px)<input id="${type}LayoutY" type="number" min="0" max="1350"></label>
          <label>Larghezza (px)<input id="${type}LayoutW" type="number" min="1" max="1080"></label>
          <label>Altezza (px)<input id="${type}LayoutH" type="number" min="1" max="1350"></label>
          <label>Dimensione testo<input id="${type}LayoutFont" type="number" min="12" max="240"></label>
          <label>Colore testo<input id="${type}LayoutColor" type="color" value="#ffffff"></label>
        </div><div class="actions"><button id="${type}SaveBase" type="button">Salva base e posizioni</button><button id="${type}RemoveBase" type="button" class="secondary">Usa grafica automatica</button></div></fieldset>
        <p id="${type}BaseStatus" class="hint" aria-live="polite"></p>`;
      $(type==='ft'?'fulltimePanel':'mvpPanel').prepend(box);
      Object.keys(defaults(type)).forEach(key => $(type+'Element').append(new Option(labels[key],key)));
      $(type+'Element').addEventListener('change',()=>{refresh(type);drawAll();});
      $(type+'Base').addEventListener('change',()=>upload(type));
      if(type==='ft'){
        $('ftOverlayFiles').addEventListener('change',()=>uploadOverlays());
        $('ftOverlaySelect').addEventListener('change',()=>{const item=pair()?.ft;if(item){item.selectedOverlayId=$('ftOverlaySelect').value;refresh('ft');drawAll();}});
        $('ftOverlayLayer').addEventListener('change',()=>editSelectedOverlay());
        ['X','Y','W','H'].forEach(field=>$('ftOverlay'+field).addEventListener('change',()=>editSelectedOverlay(field)));
        $('ftRemoveOverlay').addEventListener('click',removeSelectedOverlay);
      }
      const canvas=$(type==='ft'?'fulltimeCanvas':'mvpCanvas');let drag=null;const pointers=new Map();
      canvas.style.touchAction='none';
      const point=event=>{const rect=canvas.getBoundingClientRect();return{x:(event.clientX-rect.left)*W/rect.width,y:(event.clientY-rect.top)*H/rect.height};};
      const contains=(r,p)=>r&&p.x>=r.x&&p.y>=r.y&&p.x<=r.x+r.w&&p.y<=r.y+r.h;
      canvas.addEventListener('pointerdown',event=>{
        const item=pair()?.[type];
        if(event.button>0||!item?.image||item.busy||item.loading||item.error)return;
        if(drag){
          if(event.pointerType!=='touch'||pointers.size!==1||item!==drag.item||currentId!==drag.torneo)return;
          pointers.set(event.pointerId,point(event));canvas.setPointerCapture(event.pointerId);
          const [a,b]=[...pointers.values()];
          drag.pinch={distance:Math.max(1,Math.hypot(b.x-a.x,b.y-a.y)),cx:(a.x+b.x)/2,cy:(a.y+b.y)/2,...drag.target};
          event.preventDefault();return;
        }
        if(event.isPrimary===false)return;
        const p=point(event),selected=item.layout[$(type+'Element').value];
        const overlay=type==='ft'?item.overlays.find(entry=>entry.id===item.selectedOverlayId):null;
        const isLayout=selected?.visible&&contains(selected,p);
        const target=isLayout?selected:contains(overlay,p)?overlay:null;
        if(!target)return;
        drag={pointerId:event.pointerId,torneo:currentId,item,target,isLayout,dx:p.x-target.x,dy:p.y-target.y};
        pointers.set(event.pointerId,p);canvas.setPointerCapture(event.pointerId);event.preventDefault();
      });
      canvas.addEventListener('pointermove',event=>{
        if(!drag||!pointers.has(event.pointerId))return;
        const item=pair()?.[type];
        if(currentId!==drag.torneo||item!==drag.item||item.busy||item.loading||item.error){stopDrag(event);return;}
        const p=point(event),target=drag.target;
        pointers.set(event.pointerId,p);
        if(drag.pinch&&pointers.size===2){
          const [a,b]=[...pointers.values()],start=drag.pinch;
          let scale=Math.hypot(b.x-a.x,b.y-a.y)/start.distance;
          const textElement=drag.isLayout&&!['photo','homeLogo','awayLogo'].includes($(type+'Element').value);
          const minimum=Math.max(1/start.w,1/start.h,textElement?12/start.font:0);
          const maximum=drag.isLayout?Math.min(W/start.w,H/start.h,textElement?240/start.font:Infinity):Infinity;
          scale=Math.max(minimum,Math.min(maximum,scale));
          target.w=Math.max(1,Math.round(start.w*scale));target.h=Math.max(1,Math.round(start.h*scale));
          if(textElement)target.font=Math.max(12,Math.min(240,Math.round(start.font*scale)));
          target.x=Math.round((a.x+b.x)/2+(start.x-start.cx)*scale);
          target.y=Math.round((a.y+b.y)/2+(start.y-start.cy)*scale);
        }else{target.x=Math.round(p.x-drag.dx);target.y=Math.round(p.y-drag.dy);}
        if(drag.isLayout){target.x=Math.max(0,Math.min(W,target.x));target.y=Math.max(0,Math.min(H,target.y));}
        item.dirty=true;refresh(type);drawAll();
      });
      const stopDrag=event=>{
        if(!drag||!pointers.has(event.pointerId))return;
        const captured=[...pointers.keys()];drag=null;pointers.clear();
        for(const id of captured)if(canvas.hasPointerCapture(id))canvas.releasePointerCapture(id);
      };
      canvas.addEventListener('pointerup',stopDrag);canvas.addEventListener('pointercancel',stopDrag);canvas.addEventListener('lostpointercapture',stopDrag);
      $(type+'SaveBase').addEventListener('click',()=>save(type,false));
      $(type+'RemoveBase').addEventListener('click',()=>save(type,true));
      ['X','Y','W','H','Font','Color'].forEach(field=>$(type+'Layout'+field).addEventListener('input',()=>edit(type)));
      $(type+'Visible').addEventListener('change',()=>edit(type));
      refresh(type);
    }
    window.addEventListener('beforeunload',event=>{
      if ([...state.values()].some(items=>items.ft.dirty||items.mvp.dirty)) {event.preventDefault();event.returnValue='';}
    });
  }
  function refresh(type) {
    if (!editor) {
      const items = pair();
      const status = $('templateStatus');
      if (status) status.textContent = !items ? '' : items.ft.error || items.mvp.error ||
        (items.ft.loading || items.mvp.loading ? 'Caricamento dei template del torneo…' :
          ['ft','mvp'].map(key => `${key === 'ft' ? 'Full Time' : 'MVP'}: ${items[key].image ? 'template del torneo' : 'grafica automatica'}`).join(' · '));
      return;
    }
    const item = pair()?.[type];
    $(type+'BaseControls').disabled = !item || item.loading || item.busy || !!item.error;
    const rect = (item?.layout || defaults(type))[$(type+'Element').value];
    ['X','Y','W','H','Font','Color'].forEach(field=>{$(type+'Layout'+field).value=rect[field.toLowerCase()];});
    $(type+'Visible').disabled=!item||item.loading||item.busy;
    $(type+'Visible').checked=rect.visible;
    $(type+'SaveBase').disabled=!item?.image;
    $(type+'RemoveBase').disabled=!item?.image;
    if(type==='ft'){
      refreshOverlays(item);
    }
    $(type+'BaseStatus').textContent = !item ? 'Seleziona un torneo per configurare le basi.' : item.error || item.saveError || (item.loading?'Caricamento della base…':item.busy?'Salvataggio…':item.dirty?'Modifiche in anteprima: premi Salva base e posizioni.':item.image?'Base salvata per questo torneo.':'Nessuna base salvata: viene usata la grafica automatica.');
  }
  async function selectTournament(id) {
    currentId=String(id||'');
    if (!currentId) { ['ft','mvp'].forEach(refresh); drawAll(); return; }
    if (state.has(currentId) && !pair().ft.error) {['ft','mvp'].forEach(refresh);drawAll();return;}
    const items={ft:fresh('ft'),mvp:fresh('mvp')};
    state.set(currentId,items);
    for (const item of Object.values(items)) item.loading=true;
    ['ft','mvp'].forEach(refresh); drawAll();
    try {
      const data=await request(`grafiche_basi.php?torneo_id=${encodeURIComponent(currentId)}`);
      await Promise.all(['ft','mvp'].map(async type=>{
        if(data[type]) {
          const img=await loadImage(data[type].image);
          if(!img) throw new Error('Impossibile caricare la base salvata. Riseleziona il torneo per riprovare.');
          items[type].image=img;
          items[type].layout=normalizeLayout(type,data[type].layout);
          if(type==='ft'){
            const legacy=data[type].score_overlay?[{id:'legacy-score-overlay',name:'Box risultato',image:data[type].score_overlay,x:data[type].layout?.scoreOverlay?.x||0,y:data[type].layout?.scoreOverlay?.y||0,w:data[type].layout?.scoreOverlay?.w||1080,h:data[type].layout?.scoreOverlay?.h||1350,layer:'between_photo_content'}]:[];
            const saved=data[type].overlays||legacy;
            items[type].overlays=await Promise.all(saved.map(async layer=>({...layer,image:await loadImage(layer.image),file:null,sourceWidth:Number(layer.source_width||layer.w),sourceHeight:Number(layer.source_height||layer.h)})));
            if(items[type].overlays.some(layer=>!layer.image)) throw new Error('Impossibile caricare un livello immagine salvato. Riseleziona il torneo per riprovare.');
            items[type].selectedOverlayId=items[type].overlays[0]?.id||'';
          }
        }
      }));
    } catch(error) {for(const item of Object.values(items)) item.error=error.message;}
    finally {for(const item of Object.values(items)) item.loading=false;}
    if(pair()===items) {['ft','mvp'].forEach(refresh);drawAll();}
  }
  async function request(url, options) {
    const response=await fetch(url,{...options,cache:'no-store'});
    let data;
    try {data=await response.json();} catch(error) {throw new Error('Sessione scaduta o risposta non valida: ricarica la pagina.');}
    if(!response.ok) throw new Error(data.error||'Impossibile salvare la base.');
    return data;
  }
  async function upload(type) {
    const item=pair()?.[type], id=currentId, file=$(type+'Base').files[0];
    if(!item||!file) return;
    item.busy=true;item.saveError='';refresh(type);
    try {
      if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>8*1024*1024) throw new Error('Usa PNG, JPG o WebP, massimo 8 MB.');
      const img=await fileImage(file);
      if(!img || img.naturalWidth*img.naturalHeight>16000000) throw new Error('Immagine non valida o superiore a 16 megapixel.');
      item.image=img; item.file=file; item.dirty=true;item.saveError='';
      if(id===currentId) {refresh(type);drawAll();}
    } catch(error) {message(error.message);}
    finally {item.busy=false;$(type+'Base').value='';if(id===currentId)refresh(type);}
  }
  function refreshOverlays(item) {
    const select=$('ftOverlaySelect'),layers=item?.overlays||[],selected=layers.find(layer=>layer.id===item?.selectedOverlayId)||layers[0]||null;
    select.replaceChildren(new Option(layers.length?'Seleziona immagine':'Nessuna immagine caricata',''));
    for(const [index,layer] of layers.entries())select.add(new Option(`${index+1}. ${layer.name||'Livello immagine'}`,layer.id));
    if(selected){item.selectedOverlayId=selected.id;select.value=selected.id;}
    const disabled=!item||item.loading||item.busy||!selected;
    select.disabled=!item||item.loading||item.busy||!layers.length;
    $('ftOverlayLayer').disabled=disabled;
    $('ftRemoveOverlay').disabled=disabled;
    for(const field of ['X','Y','W','H']){
      const input=$('ftOverlay'+field);input.disabled=disabled;input.min=field==='X'||field==='Y'?-100000:.01;input.max=100000;
      input.value=selected?selected[field.toLowerCase()]||0:'';
    }
    $('ftOverlayLayer').value=selected?.layer||'between_photo_content';
    $('ftOverlayStatus').textContent=selected?`${layers.length} ${layers.length===1?'immagine caricata':'immagini caricate'}. Dimensioni iniziali: ${selected.sourceWidth} × ${selected.sourceHeight} px.`:'Carica una o più immagini. Le misure iniziali corrispondono ai pixel del file.';
  }
  async function uploadOverlays() {
    const item=pair()?.ft,id=currentId,files=Array.from($('ftOverlayFiles').files||[]);
    if(!item||!files.length)return;
    item.busy=true;item.saveError='';refresh('ft');
    try {
      for(const file of files){
        if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>8*1024*1024) throw new Error('Usa PNG, JPG o WebP, massimo 8 MB per immagine.');
        const img=await fileImage(file);
        if(!img||img.naturalWidth*img.naturalHeight>16000000) throw new Error(`Immagine non valida o superiore a 16 megapixel: ${file.name}`);
        const idValue=globalThis.crypto?.randomUUID?.()||`overlay-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        item.overlays.push({id:idValue,name:file.name,image:img,file,sourceWidth:img.naturalWidth,sourceHeight:img.naturalHeight,x:0,y:0,w:img.naturalWidth,h:img.naturalHeight,layer:'between_photo_content'});
        item.selectedOverlayId=idValue;item.dirty=true;
      }
    } catch(error) {message(error.message);}
    finally {
      $('ftOverlayFiles').value='';item.busy=false;
      if(id===currentId){refresh('ft');drawAll();}
    }
  }
  function editSelectedOverlay(field='layer') {
    const item=pair()?.ft;
    const selected=item?.overlays.find(layer=>layer.id===item.selectedOverlayId);
    if(!selected||item.loading||item.busy)return;
    if(field==='layer')selected.layer=$('ftOverlayLayer').value;
    else if(field==='X'||field==='Y'){
      const input=$('ftOverlay'+field);if(!input.checkValidity()||input.value==='')return;
      selected[field.toLowerCase()]=Number(input.value);
    }else if(field==='W'||field==='H'){
      const input=$('ftOverlay'+field);if(!input.checkValidity()||input.value==='')return;
      const ratio=selected.sourceWidth/selected.sourceHeight,value=Math.max(1,Number(input.value));
      selected[field.toLowerCase()]=value;
      if(field==='W'){selected.h=Math.max(.01,Number((value/ratio).toFixed(3)));$('ftOverlayH').value=selected.h;}
      else{selected.w=Math.max(.01,Number((value*ratio).toFixed(3)));$('ftOverlayW').value=selected.w;}
    }
    item.dirty=true;
    refresh('ft');drawAll();
  }
  function removeSelectedOverlay() {
    const item=pair()?.ft;
    if(!item||item.loading||item.busy)return;
    item.overlays=item.overlays.filter(layer=>layer.id!==item.selectedOverlayId);
    item.selectedOverlayId=item.overlays[0]?.id||'';item.dirty=true;
    refresh('ft');drawAll();
  }
  function edit(type) {
    const item=pair()?.[type];
    if(!item||item.loading||item.busy) return;
    const rect=item.layout[$(type+'Element').value];
    for (const field of ['X','Y','W','H','Font']) {
      const input=$(type+'Layout'+field);
      if(!input.checkValidity() || input.value==='') return;
      rect[field.toLowerCase()]=Number(input.value);
    }
    rect.color=$(type+'LayoutColor').value;
    rect.visible=$(type+'Visible').checked;
    item.dirty=true; refresh(type);drawAll();
  }
  async function save(type, remove) {
    const item=pair()?.[type], id=currentId;
    if(!item||item.busy||item.loading) return;
    item.busy=true;item.saveError='';refresh(type);
    const body=new FormData();
    body.append('_csrf',graphicsTemplatesCsrf);body.append('torneo_id',id);body.append('type',type);
    body.append('action',remove?'remove':'save');body.append('layout',JSON.stringify(item.layout));
    if(item.file&&!remove) body.append('base',item.file);
    if(type==='ft'&&!remove){
      body.append('overlays',JSON.stringify(item.overlays.map(({id,name,x,y,w,h,layer,sourceWidth,sourceHeight})=>({id,name,x,y,w,h,layer,source_width:sourceWidth,source_height:sourceHeight}))));
      item.overlays.forEach((overlay,index)=>{if(overlay.file)body.append(`overlay_files[${index}]`,overlay.file);});
    }
    try {
      const result=await request('grafiche_basi.php',{method:'POST',body});
      if(result.ok!==true)throw new Error('Il server non ha confermato il salvataggio. Riprova.');
      if(remove) Object.assign(item,fresh(type));
      item.file=null;for(const overlay of item.overlays)overlay.file=null;item.dirty=false;
      if (window.parent !== window) window.parent.postMessage({type:'graphics-template-saved',torneoId:id},window.location.origin);
      if(id===currentId) {drawAll();message(remove?'Grafica automatica ripristinata per questo torneo.':'Base e posizioni salvate per questo torneo.');}
    } catch(error) {item.saveError=error.message;message(error.message);}
    finally {item.busy=false;if(id===currentId)refresh(type);}
  }
  function text(ctx,value,r) {
    if(!value) return;
    ctx.save();ctx.beginPath();ctx.rect(r.x,r.y,r.w,r.h);ctx.clip();
    ctx.fillStyle=r.color;ctx.textAlign='center';ctx.textBaseline='middle';
    const lines=String(value).toUpperCase().split(' • ');
    const size=Math.min(r.font,r.h/(lines.length*1.2));
    lines.forEach((line,index)=>{
      fitText(ctx,line,r.w,size,Math.min(12,size),900);
      ctx.fillText(line,r.x+r.w/2,r.y+r.h/2+(index-(lines.length-1)/2)*size*1.2,r.w);
    });
    ctx.restore();
  }
  function containRounded(ctx,img,x,y,w,h) {
    if(!img?.naturalWidth) return;
    const radius=Math.min(w,h)*0.12;
    ctx.save();ctx.beginPath();ctx.roundRect(x,y,w,h,radius);ctx.clip();contain(ctx,img,x,y,w,h);ctx.restore();
  }
  const overlayCache=new WeakMap();
  function graphicOverlay(img,hole=null,protectedRects=[]) {
    const signature=[hole?[hole.x,hole.y,hole.w,hole.h].join(','):'none',...protectedRects.map(r=>[r.x,r.y,r.w,r.h,r.visible].join(','))].join('|');
    let variants=overlayCache.get(img);if(!variants){variants=new Map();overlayCache.set(img,variants);}
    if(variants.has(signature))return variants.get(signature);
    const layer=document.createElement('canvas');layer.width=W;layer.height=H;
    try {
      const source=document.createElement('canvas');source.width=W;source.height=H;
      const sourceCtx=source.getContext('2d',{willReadFrequently:true});sourceCtx.drawImage(img,0,0,W,H);
      const pixels=sourceCtx.getImageData(0,0,W,H),data=pixels.data;
      const originalAlpha=new Uint8Array(data.length);
      for(let i=3;i<data.length;i+=4) originalAlpha[i]=data[i];
      const key=[];
      for(const [x,y] of [[0,0],[W-1,0],[0,H-1],[W-1,H-1]]){const i=(y*W+x)*4;key.push([data[i],data[i+1],data[i+2]]);}
      const nearBackground=(i)=>key.some(([r,g,b])=>Math.abs(data[i]-r)+Math.abs(data[i+1]-g)+Math.abs(data[i+2]-b)<54);
      const seen=new Uint8Array(W*H),queue=new Int32Array(W*H);let head=0,tail=0;
      for(let x=0;x<W;x++){if(nearBackground(x*4)){seen[x]=1;queue[tail++]=x;}const p=(H-1)*W+x;if(nearBackground(p*4)){seen[p]=1;queue[tail++]=p;}}
      for(let y=1;y<H-1;y++)for(const x of [0,W-1]){const p=y*W+x;if(nearBackground(p*4)&&!seen[p]){seen[p]=1;queue[tail++]=p;}}
      while(head<tail){const p=queue[head++],x=p%W,y=(p/W)|0;data[p*4+3]=0;for(const [dx,dy] of [[1,0],[-1,0],[0,1],[0,-1]]){const nx=x+dx,ny=y+dy;if(nx<0||nx>=W||ny<0||ny>=H)continue;const q=ny*W+nx;if(!seen[q]&&nearBackground(q*4)){seen[q]=1;queue[tail++]=q;}}}
      // The photo window can be enclosed by a square/frame, so its white
      // backing is not connected to the canvas edge. Remove that backing too;
      // coloured and dark parts of the square remain opaque above the photo.
      if(hole)for(let y=Math.max(0,Math.floor(hole.y));y<Math.min(H,Math.ceil(hole.y+hole.h));y++)for(let x=Math.max(0,Math.floor(hole.x));x<Math.min(W,Math.ceil(hole.x+hole.w));x++){const p=y*W+x;if(nearBackground(p*4)&&data[p*4+3]>0)data[p*4+3]=0;}
      // Logo frames are part of the uploaded graphic. Restore their complete
      // rectangles after removing the photo window backing, so the player
      // image can never show through the shield squares.
      for(const rect of protectedRects)if(rect?.visible!==false)for(let y=Math.max(0,Math.floor(rect.y));y<Math.min(H,Math.ceil(rect.y+rect.h));y++)for(let x=Math.max(0,Math.floor(rect.x));x<Math.min(W,Math.ceil(rect.x+rect.w));x++){const p=y*W+x;data[p*4+3]=originalAlpha[p*4+3];}
      layer.getContext('2d').putImageData(pixels,0,0);
    }catch(error){layer.width=layer.height=1;variants.set(signature,img);return img;}
    variants.set(signature,layer);return layer;
  }
  function draw(type) {
    const item=pair()?.[type];
    if(!item?.image) return false;
    const canvas=$(type==='ft'?'fulltimeCanvas':'mvpCanvas'),ctx=canvas.getContext('2d');
    ctx.save();ctx.clearRect(0,0,W,H);ctx.fillStyle='#07111d';ctx.fillRect(0,0,W,H);
    const drawOverlayLevel=level=>{if(type==='ft')for(const overlay of pair()?.ft.overlays||[])if(overlay.layer===level&&overlay.image)ctx.drawImage(overlay.image,overlay.x,overlay.y,overlay.w,overlay.h);};
    drawOverlayLevel('behind_graphic');
    contain(ctx,item.image,0,0,W,H);
    const photoRect=item.layout.photo;
    const photo=imageState[type==='ft'?'ftCaptains':'mvpPhoto'];
    // Draw the cutout base first, then place the captain photo over its photo
    // window. Editable logos and text are drawn afterward and stay in front.
    if(photo&&photoRect?.visible){
      ctx.drawImage(graphicOverlay(item.image,photoRect,[item.layout.homeLogo,item.layout.awayLogo]),0,0,W,H);
      drawOverlayLevel('between_graphic_photo');
      cover(ctx,photo,photoRect.x,photoRect.y,photoRect.w,photoRect.h,cropValues(type));
    }else drawOverlayLevel('between_graphic_photo');
    drawOverlayLevel('between_photo_content');
    const teams=new Set([...$('mvpPlayers').querySelectorAll('input:checked')].map(input=>input._mvpData.team));
    const logos=type==='ft'?[imageState.ftHomeLogo,imageState.ftAwayLogo]:[
      teams.has($('ftHome').value)?imageState.ftHomeLogo:teams.has($('ftAway').value)?imageState.ftAwayLogo:null,
      teams.size>1?imageState.ftAwayLogo:null
    ];
    const texts=type==='ft'?{homeScore:String($('ftHomeScore').value||0),awayScore:String($('ftAwayScore').value||0),home:$('ftHome').value,away:$('ftAway').value,round:$('ftRound').value}:{names:$('mvpNames').value,team:$('mvpTeam').value,details:$('mvpDetails').value};
    for (const [key,r] of Object.entries(item.layout)) {
      if(!r.visible) continue;
      if(key==='photo') { if(editor&&!photo) guide(ctx,r,'FOTO GIOCATORI'); }
      else if(key==='homeLogo'||key==='awayLogo') {
        const logo=logos[key==='homeLogo'?0:1];
        if(editor&&!logo) guide(ctx,r,key==='homeLogo'?'LOGO 1':'LOGO 2');
        else containRounded(ctx,logo,r.x,r.y,r.w,r.h);
      }
      else text(ctx,texts[key],r);
    }
    drawOverlayLevel('front');
    if(editor){
      const selected=item.layout[$(type+'Element').value];
      if(selected?.visible){ctx.save();ctx.strokeStyle='#55dfff';ctx.lineWidth=4;ctx.setLineDash([12,8]);ctx.strokeRect(selected.x,selected.y,selected.w,selected.h);ctx.restore();}
    }
    if(editor&&type==='ft'){
      const selected=pair()?.ft.overlays.find(overlay=>overlay.id===pair()?.ft.selectedOverlayId);
      if(selected){ctx.save();ctx.strokeStyle='#ffd54a';ctx.lineWidth=5;ctx.setLineDash([16,10]);ctx.strokeRect(selected.x,selected.y,selected.w,selected.h);ctx.restore();}
    }
    ctx.restore();return true;
  }
  function ready(type) {
    const item=pair()?.[type];
    if(item?.loading||item?.error) {message(item.error||'Attendi il caricamento della base.');return false;}
    return true;
  }
  function guide(ctx,r,label) {
    ctx.save();ctx.fillStyle='#ffffff20';ctx.fillRect(r.x,r.y,r.w,r.h);
    ctx.strokeStyle='#e8bd45';ctx.lineWidth=3;ctx.setLineDash([12,8]);ctx.strokeRect(r.x,r.y,r.w,r.h);
    text(ctx,label,{...r,font:Math.min(30,r.font),color:'#ffffff'});ctx.restore();
  }
  async function reload(id = currentId) {
    if(editor||!id) return;
    state.delete(String(id));
    if(String(id)===currentId) await selectTournament(currentId);
  }
  return {init,selectTournament,draw,ready,reload};
})();
