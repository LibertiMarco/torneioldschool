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
  const fresh = type => ({image:null,file:null,layout:defaults(type),dirty:false,busy:false,loading:false,error:'',saveError:''});
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
    if (type === 'ft') {
      for (const key of ['homeLogo','awayLogo']) layout[key] = {...layout[key],radius:Number.isFinite(Number(layout[key]?.radius))?Math.max(0,Math.min(50,Number(layout[key].radius))):12};
    }
    return {...defaults(type),...layout};
  }

  function init(options = {}) {
    editor = options.editor === true;
    if (!editor) return;
    for (const type of ['ft','mvp']) {
      const box = document.createElement('details');
      box.className = 'template-editor'; box.open = true;
      box.innerHTML = `<summary>Base personalizzata ${type==='ft'?'Full Time':'MVP'}</summary>
        <p class="hint">Carica la tua grafica, poi posiziona gli elementi. Consigliato: 1080 × 1350 px; PNG, JPG o WebP, massimo 8 MB. Per giocatori scontornati usa foto PNG trasparenti.</p>
        <fieldset id="${type}BaseControls"><div class="fields">
          <label class="wide">Immagine di base<input id="${type}Base" type="file" accept="image/png,image/jpeg,image/webp"></label>
          <label class="wide">Elemento da posizionare<select id="${type}Element"></select></label>
          <label class="wide template-check"><input id="${type}Visible" type="checkbox"> Mostra elemento</label>
          <label>Posizione X (px)<input id="${type}LayoutX" type="number" min="0" max="1080"></label>
          <label>Posizione Y (px)<input id="${type}LayoutY" type="number" min="0" max="1350"></label>
          <label>Larghezza (px)<input id="${type}LayoutW" type="number" min="1" max="1080"></label>
          <label>Altezza (px)<input id="${type}LayoutH" type="number" min="1" max="1350"></label>
          <label>Dimensione testo<input id="${type}LayoutFont" type="number" min="12" max="240"></label>
          <label>Colore testo<input id="${type}LayoutColor" type="color" value="#ffffff"></label>
          ${type==='ft'?'<label id="ftLogoRadiusField" hidden>Arrotondamento angoli logo <span class="range-value" id="ftLogoRadiusValue">12%</span><input id="ftLogoRadius" type="range" min="0" max="50" value="12"></label>':''}
        </div><div class="actions"><button id="${type}SaveBase" type="button">Salva base e posizioni</button><button id="${type}RemoveBase" type="button" class="secondary">Usa grafica automatica</button></div></fieldset>
        <p id="${type}BaseStatus" class="hint" aria-live="polite"></p>`;
      $(type==='ft'?'fulltimePanel':'mvpPanel').prepend(box);
      Object.keys(defaults(type)).forEach(key => $(type+'Element').append(new Option(labels[key],key)));
      $(type+'Element').addEventListener('change',()=>refresh(type));
      $(type+'Base').addEventListener('change',()=>upload(type));
      $(type+'SaveBase').addEventListener('click',()=>save(type,false));
      $(type+'RemoveBase').addEventListener('click',()=>save(type,true));
      ['X','Y','W','H','Font','Color'].forEach(field=>$(type+'Layout'+field).addEventListener('input',()=>edit(type)));
      if (type==='ft') $('ftLogoRadius').addEventListener('input',()=>edit(type));
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
    if(type==='ft') {
      const isLogo=['homeLogo','awayLogo'].includes($('ftElement').value);
      $('ftLogoRadiusField').hidden=!isLogo;
      $('ftLogoRadiusField').style.display=isLogo?'grid':'none';
      $('ftLogoRadius').value=rect.radius??12;
      $('ftLogoRadiusValue').textContent=`${rect.radius??12}%`;
    }
    $(type+'Visible').checked=rect.visible;
    $(type+'SaveBase').disabled=!item?.image;
    $(type+'RemoveBase').disabled=!item?.image;
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
  function edit(type) {
    const item=pair()?.[type];
    if(!item||item.loading||item.busy) return;
    const rect=item.layout[$(type+'Element').value];
    for (const field of ['X','Y','W','H','Font']) {
      const input=$(type+'Layout'+field);
      if(!input.checkValidity() || input.value==='') return;
      rect[field.toLowerCase()]=Number(input.value);
    }
    rect.color=$(type+'LayoutColor').value; rect.visible=$(type+'Visible').checked;
    if(type==='ft'&&['homeLogo','awayLogo'].includes($('ftElement').value)) {
      rect.radius=Number($('ftLogoRadius').value);
      $('ftLogoRadiusValue').textContent=`${rect.radius}%`;
    }
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
    try {
      const result=await request('grafiche_basi.php',{method:'POST',body});
      if(result.ok!==true)throw new Error('Il server non ha confermato il salvataggio. Riprova.');
      if(remove) Object.assign(item,fresh(type));
      item.file=null;item.dirty=false;
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
  function containRounded(ctx,img,x,y,w,h,radius=12) {
    if(!img?.naturalWidth) return;
    const cornerRadius=Math.min(w,h)*Math.max(0,Math.min(50,radius))/100;
    ctx.save();ctx.beginPath();ctx.roundRect(x,y,w,h,cornerRadius);ctx.clip();contain(ctx,img,x,y,w,h);ctx.restore();
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
    contain(ctx,item.image,0,0,W,H);
    const photoRect=item.layout.photo;
    const photo=imageState[type==='ft'?'ftCaptains':'mvpPhoto'];
    // The uploaded base is first used as a background. Then its continuous
    // background is made transparent so baked-in squares, frames, logos and
    // decorations can be composited above the player photo.
    if(photo&&photoRect?.visible){
      cover(ctx,photo,photoRect.x,photoRect.y,photoRect.w,photoRect.h,cropValues(type));
      ctx.drawImage(graphicOverlay(item.image,photoRect,[item.layout.homeLogo,item.layout.awayLogo]),0,0,W,H);
    }
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
        else containRounded(ctx,logo,r.x,r.y,r.w,r.h,r.radius??12);
      }
      else text(ctx,texts[key],r);
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
