/* Basi personalizzate: le foto delle partite restano locali, base e layout si salvano sul server. */
const customTemplates = (() => {
  const labels = {photo:'Foto giocatori',homeLogo:'Logo casa / squadra MVP',awayLogo:'Logo ospite / secondo MVP',score:'Risultato',home:'Nome squadra casa',away:'Nome squadra ospite',round:'Giornata / fase',names:'Nomi MVP',team:'Squadra MVP',details:'Dettaglio MVP'};
  const element = (x,y,w,h,font=40,visible=true) => ({x,y,w,h,font,visible,color:'#ffffff'});
  const defaults = type => type === 'ft'
    ? {photo:element(80,220,920,700),homeLogo:element(80,950,180,180),awayLogo:element(820,950,180,180),score:element(290,980,500,140,110),home:element(30,1150,400,60,34),away:element(650,1150,400,60,34),round:element(180,1240,720,60,26)}
    : {photo:element(100,200,880,820),homeLogo:element(70,1100,130,130),awayLogo:element(880,1100,130,130),names:element(70,1030,940,100,54),team:element(210,1150,660,70,32),details:element(150,1240,780,60,26)};
  const state = new Map();
  let currentId = '';
  const fresh = type => ({image:null,file:null,layout:defaults(type),dirty:false,busy:false,loading:false,error:''});
  const pair = () => state.get(currentId);
  const message = text => { $('status').textContent = text; };

  function init() {
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
        </div><div class="actions"><button id="${type}SaveBase" type="button">Salva base e posizioni</button><button id="${type}RemoveBase" type="button" class="secondary">Usa grafica automatica</button></div></fieldset>
        <p id="${type}BaseStatus" class="hint" aria-live="polite"></p>`;
      $(type==='ft'?'fulltimePanel':'mvpPanel').prepend(box);
      Object.keys(defaults(type)).forEach(key => $(type+'Element').append(new Option(labels[key],key)));
      $(type+'Element').addEventListener('change',()=>refresh(type));
      $(type+'Base').addEventListener('change',()=>upload(type));
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
    const item = pair()?.[type];
    $(type+'BaseControls').disabled = !item || item.loading || item.busy || !!item.error;
    const rect = (item?.layout || defaults(type))[$(type+'Element').value];
    ['X','Y','W','H','Font','Color'].forEach(field=>{$(type+'Layout'+field).value=rect[field.toLowerCase()];});
    $(type+'Visible').checked=rect.visible;
    $(type+'SaveBase').disabled=!item?.image;
    $(type+'RemoveBase').disabled=!item?.image;
    $(type+'BaseStatus').textContent = !item ? 'Seleziona un torneo per configurare le basi.' : item.error || (item.loading?'Caricamento della base…':item.busy?'Salvataggio…':item.dirty?'Modifiche in anteprima: premi Salva base e posizioni.':item.image?'Base salvata per questo torneo.':'Nessuna base salvata: viene usata la grafica automatica.');
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
          items[type].layout={...defaults(type),...data[type].layout};
        }
      }));
    } catch(error) {for(const item of Object.values(items)) item.error=error.message;}
    finally {for(const item of Object.values(items)) item.loading=false;}
    if(pair()===items) {['ft','mvp'].forEach(refresh);drawAll();}
  }
  async function request(url, options) {
    const response=await fetch(url,options);
    let data;
    try {data=await response.json();} catch(error) {throw new Error('Sessione scaduta o risposta non valida: ricarica la pagina.');}
    if(!response.ok) throw new Error(data.error||'Impossibile salvare la base.');
    return data;
  }
  async function upload(type) {
    const item=pair()?.[type], id=currentId, file=$(type+'Base').files[0];
    if(!item||!file) return;
    item.busy=true;refresh(type);
    try {
      if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>8*1024*1024) throw new Error('Usa PNG, JPG o WebP, massimo 8 MB.');
      const img=await fileImage(file);
      if(!img || img.naturalWidth*img.naturalHeight>16000000) throw new Error('Immagine non valida o superiore a 16 megapixel.');
      item.image=img; item.file=file; item.dirty=true;
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
    item.dirty=true; refresh(type);drawAll();
  }
  async function save(type, remove) {
    const item=pair()?.[type], id=currentId;
    if(!item||item.busy||item.loading) return;
    item.busy=true;refresh(type);
    const body=new FormData();
    body.append('_csrf',graphicsTemplatesCsrf);body.append('torneo_id',id);body.append('type',type);
    body.append('action',remove?'remove':'save');body.append('layout',JSON.stringify(item.layout));
    if(item.file&&!remove) body.append('base',item.file);
    try {
      await request('grafiche_basi.php',{method:'POST',body});
      if(remove) Object.assign(item,fresh(type));
      item.file=null;item.dirty=false;
      if(id===currentId) {drawAll();message(remove?'Grafica automatica ripristinata per questo torneo.':'Base e posizioni salvate per questo torneo.');}
    } catch(error) {message(error.message);}
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
  function draw(type) {
    const item=pair()?.[type];
    if(!item?.image) return false;
    const canvas=$(type==='ft'?'fulltimeCanvas':'mvpCanvas'),ctx=canvas.getContext('2d');
    ctx.save();ctx.clearRect(0,0,W,H);ctx.fillStyle='#07111d';ctx.fillRect(0,0,W,H);
    contain(ctx,item.image,0,0,W,H);
    const teams=new Set([...$('mvpPlayers').querySelectorAll('input:checked')].map(input=>input._mvpData.team));
    const logos=type==='ft'?[imageState.ftHomeLogo,imageState.ftAwayLogo]:[
      teams.has($('ftHome').value)?imageState.ftHomeLogo:teams.has($('ftAway').value)?imageState.ftAwayLogo:null,
      teams.size>1?imageState.ftAwayLogo:null
    ];
    const texts=type==='ft'?{score:`${$('ftHomeScore').value||0} – ${$('ftAwayScore').value||0}`,home:$('ftHome').value,away:$('ftAway').value,round:$('ftRound').value}:{names:$('mvpNames').value,team:$('mvpTeam').value,details:$('mvpDetails').value};
    for (const [key,r] of Object.entries(item.layout)) {
      if(!r.visible) continue;
      if(key==='photo') cover(ctx,imageState[type==='ft'?'ftCaptains':'mvpPhoto'],r.x,r.y,r.w,r.h,cropValues(type));
      else if(key==='homeLogo'||key==='awayLogo') contain(ctx,logos[key==='homeLogo'?0:1],r.x,r.y,r.w,r.h);
      else text(ctx,texts[key],r);
    }
    ctx.restore();return true;
  }
  function ready(type) {
    const item=pair()?.[type];
    if(item?.loading||item?.error) {message(item.error||'Attendi il caricamento della base.');return false;}
    return true;
  }
  return {init,selectTournament,draw,ready};
})();
