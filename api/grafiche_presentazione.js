window.PresentationPage = (() => {
  const $=id=>document.getElementById(id);
  const {loadImage}=MatchdayRenderer;
  const safeSource=src=>{
    const value=String(src||'').trim();
    if(!value)return null;
    if(/^https?:\/\//i.test(value)||/^\/(?!\/)/.test(value))return value;
    if(/^[a-z]+:|^\/\//i.test(value))return null;
    return '/'+value.replace(/^(\.\.?\/)+/,'');
  };
  let tournaments=[],tournament=null,team=null,canvas,assets={brand:null},transform={zoom:1,x:0,y:0};
  let selectionVersion=0,photoVersion=0,busy=false,uploading=false,rendered=null;
  const drafts=new Map(),pointers=new Map();let gesture=null;
  const key=()=>tournament&&team?`${tournament.id}:${team.id}`:null;
  const say=message=>{$('status').textContent=message;};
  function remember(){if(key()&&assets.photo)drafts.set(key(),{photo:assets.photo,transform:{...transform}});}
  function stop(){const ids=[...pointers.keys()];pointers.clear();gesture=null;for(const id of ids)if(canvas.hasPointerCapture(id))canvas.releasePointerCapture(id);}
  function controls(){
    const ready=!!(tournament&&team&&!busy);
    $('photo').disabled=!ready;
    $('photoControls').disabled=!ready||!assets.photo||uploading;
    $('download').disabled=!ready||uploading||!assets.photo||!assets.brand||!assets.competition||!assets.crest;
    $('zoom').value=Math.round(transform.zoom*100);$('zoomValue').textContent=`${Math.round(transform.zoom*100)}%`;
    $('positionX').value=Math.round(transform.x*100);$('positionY').value=Math.round(transform.y*100);
    canvas.classList.toggle('editing',!!(ready&&assets.photo&&$('edit').checked&&!uploading));
  }
  function draw(){
    rendered=PresentationRenderer.draw(canvas,tournament||{id:0,nome:'',template:'editorial'},team,assets,transform);
    remember();controls();
  }
  const clamp=(v,min,max)=>Math.max(min,Math.min(max,v));
  function setZoom(value){transform.zoom=clamp(value,.5,Number($('zoom').max)/100);}
  function photoLimits(){
    const [, , w,h]=assets.style.frame,p=assets.photo;
    const contain=Math.min(w/p.naturalWidth,h/p.naturalHeight),cover=Math.max(w/p.naturalWidth,h/p.naturalHeight);
    return cover/contain;
  }
  async function selectTournament(){
    remember();stop();const version=++selectionVersion;++photoVersion;
    tournament=tournaments.find(t=>String(t.id)===$('tournament').value)||null;team=null;
    assets={brand:assets.brand,style:tournament?PresentationRenderer.identity(tournament):null};transform={zoom:1,x:0,y:0};uploading=false;
    $('photo').value='';$('team').replaceChildren(new Option('Seleziona una squadra',''));
    for(const item of tournament?.squadre||[])$('team').append(new Option(item.nome,item.id));
    $('team').disabled=!tournament||!tournament.squadre.length;
    busy=!!tournament;draw();
    if(!tournament){$('templateInfo').textContent='Seleziona il campionato e la squadra per iniziare.';say('');return;}
    say('Caricamento logo del campionato…');
    const competition=await loadImage(safeSource(tournament.img));
    if(version!==selectionVersion)return;
    assets.competition=competition;assets.style=PresentationRenderer.identity(tournament,competition);busy=false;draw();
    $('templateInfo').textContent=`${tournament.nome} · template condiviso da tutte le squadre del campionato.`;
    if(!competition)say('Logo del campionato assente o non caricabile. Verifica l’immagine originale del torneo: il download resta disabilitato.');
    else say(tournament.squadre.length?'Seleziona una squadra e carica la foto dei giocatori.':'Nessuna squadra iscritta trovata per questo campionato.');
  }
  async function selectTeam(){
    remember();stop();const version=++selectionVersion;++photoVersion;
    team=tournament?.squadre.find(t=>String(t.id)===$('team').value)||null;
    assets.crest=null;assets.photo=null;transform={zoom:1,x:0,y:0};uploading=false;$('photo').value='';
    const draft=drafts.get(key());
    if(draft){assets.photo=draft.photo;transform={...draft.transform};$('zoom').max=Math.max(400,Math.ceil(photoLimits()*200));}
    busy=!!team;draw();
    if(!team){say('Seleziona una squadra.');return;}
    say('Caricamento scudetto originale…');
    // Selecting a team while the tournament logo is loading also completes that load.
    const [crest,competition]=await Promise.all([loadImage(safeSource(team.logo)),loadImage(safeSource(tournament.img))]);
    if(version!==selectionVersion)return;
    assets.crest=crest;assets.competition=competition;assets.style=PresentationRenderer.identity(tournament,competition);busy=false;draw();
    $('templateInfo').textContent=`${tournament.nome} · template condiviso da tutte le squadre del campionato.`;
    if(!crest||!competition)say('Scudetto o logo del campionato assente o non caricabile. Verifica gli asset originali: il download resta disabilitato.');
    else say(draft?'Foto e posizione della squadra ripristinate.':'Carica la foto reale del gruppo di giocatori.');
  }
  function fileImage(file){return new Promise((resolve,reject)=>{
    const reader=new FileReader(),image=new Image();
    image.onload=()=>{if(image.naturalWidth*image.naturalHeight>50000000)reject(new Error('La fotografia deve avere al massimo 50 megapixel.'));else resolve(image);};
    image.onerror=()=>reject(new Error('Immagine non leggibile. Usa un file JPG, PNG o WebP valido.'));
    reader.onerror=reader.onabort=()=>reject(new Error('Impossibile leggere la fotografia. Riprova.'));
    reader.onload=()=>{image.src=reader.result;};
    reader.readAsDataURL(file);
  });}
  async function upload(){
    const file=$('photo').files?.[0];if(!file||!key())return;
    const version=++photoVersion,selectedKey=key();stop();
    try{
      if(!['image/jpeg','image/png','image/webp'].includes(file.type))throw new Error('Sono consentite fotografie JPG, PNG e WebP.');
      if(file.size>25*1024*1024)throw new Error('La fotografia deve pesare al massimo 25 MB.');
      uploading=true;controls();say('Preparazione fotografia…');
      const image=await fileImage(file);
      if(version!==photoVersion||selectedKey!==key())return;
      assets.photo=image;transform={zoom:1,x:0,y:0};$('zoom').max=Math.max(400,Math.ceil(photoLimits()*200));
      say(assets.crest&&assets.competition?'Foto pronta. Trascinala o usa i controlli per scegliere il ritaglio.':'Foto pronta. Verifica i loghi originali mancanti prima di esportare.');
    }catch(error){if(version===photoVersion)say(error.message);}
    finally{if(version===photoVersion){uploading=false;draw();}}
  }
  function point(e){const r=canvas.getBoundingClientRect();return{x:(e.clientX-r.left)*1080/r.width,y:(e.clientY-r.top)*1350/r.height};}
  function begin(){
    const pts=[...pointers.values()];
    gesture={transform:{...transform},rect:{...rendered.rect},start:pts[0]};
    if(pts.length===2){gesture.center={x:(pts[0].x+pts[1].x)/2,y:(pts[0].y+pts[1].y)/2};gesture.distance=Math.max(1,Math.hypot(pts[1].x-pts[0].x,pts[1].y-pts[0].y));}
  }
  function moveBy(dx,dy,base){
    transform.x=clamp(base.x+(rendered.rect.travelX?dx/rendered.rect.travelX:0),-1,1);
    transform.y=clamp(base.y+(rendered.rect.travelY?dy/rendered.rect.travelY:0),-1,1);
  }
  function init(data){
    tournaments=data;canvas=$('presentationCanvas');
    for(const item of tournaments)$('tournament').append(new Option(item.nome,item.id));
    $('tournament').addEventListener('change',selectTournament);$('team').addEventListener('change',selectTeam);$('photo').addEventListener('change',upload);
    for(const id of ['zoom','positionX','positionY'])$(id).addEventListener('input',()=>{
      stop();setZoom(Number($('zoom').value)/100);transform.x=Number($('positionX').value)/100;transform.y=Number($('positionY').value)/100;draw();
    });
    $('center').addEventListener('click',()=>{stop();transform.x=transform.y=0;draw();});
    for(const id of ['fit','reset'])$(id).addEventListener('click',()=>{stop();transform={zoom:1,x:0,y:0};draw();});
    $('fill').addEventListener('click',()=>{stop();transform={zoom:photoLimits(),x:0,y:0};draw();});
    $('edit').addEventListener('change',()=>{stop();controls();});
    canvas.addEventListener('pointerdown',e=>{
      if(!canvas.classList.contains('editing')||e.button>0||pointers.size>=2)return;
      const p=point(e),[x,y,w,h]=assets.style.frame;
      if(p.x<x||p.x>x+w||p.y<y||p.y>y+h)return;
      e.preventDefault();pointers.set(e.pointerId,p);canvas.setPointerCapture(e.pointerId);begin();
    });
    canvas.addEventListener('pointermove',e=>{
      if(!gesture||!pointers.has(e.pointerId))return;
      e.preventDefault();pointers.set(e.pointerId,point(e));
      const pts=[...pointers.values()],g=gesture;
      if(pts.length===2){
        const distance=Math.hypot(pts[1].x-pts[0].x,pts[1].y-pts[0].y),center={x:(pts[0].x+pts[1].x)/2,y:(pts[0].y+pts[1].y)/2};
        setZoom(g.transform.zoom*distance/g.distance);
        const scale=transform.zoom/g.transform.zoom,rect=PresentationRenderer.photoRect(assets.photo,assets.style.frame,{zoom:transform.zoom,x:0,y:0});
        const desiredX=center.x+(g.rect.x-g.center.x)*scale,desiredY=center.y+(g.rect.y-g.center.y)*scale;
        transform.x=clamp(rect.travelX?(desiredX-rect.x)/rect.travelX:0,-1,1);transform.y=clamp(rect.travelY?(desiredY-rect.y)/rect.travelY:0,-1,1);
      }else moveBy(pts[0].x-g.start.x,pts[0].y-g.start.y,g.transform);
      draw();
    });
    for(const event of ['pointerup','pointercancel','lostpointercapture'])canvas.addEventListener(event,stop);
    canvas.addEventListener('wheel',e=>{if(!canvas.classList.contains('editing'))return;e.preventDefault();stop();setZoom(transform.zoom*Math.exp(-e.deltaY*.001));draw();},{passive:false});
    $('download').addEventListener('click',async()=>{
      if($('download').disabled)return;
      const name=`presentazione-${tournament.nome}-${team.nome}`.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9_-]+/gi,'-').toLowerCase()+'.png';
      $('download').disabled=true;
      try{await GraphicsDownloads.saveImage({canvas,name});}
      catch(error){say(error.message||'Esportazione non riuscita. Verifica le immagini originali.');}
      finally{controls();}
    });
    draw();
    if(!tournaments.length&&!$('status').textContent)say('Nessun campionato disponibile.');
    loadImage('/img/logo_old_school.png').then(brand=>{assets.brand=brand;draw();if(!brand)say('Logo Tornei Old School non caricabile. Il download resta disabilitato.');});
  }
  return {init};
})();
