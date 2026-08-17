<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
require_once __DIR__ . '/../includi/db.php';

$partiteGrafiche = [];
$partiteStmt = $conn->prepare(
  "SELECT p.id, p.torneo,
          COALESCE((
            SELECT t.nome
            FROM tornei t
            WHERE t.nome = p.torneo
               OR t.filetorneo = p.torneo
               OR REPLACE(REPLACE(t.filetorneo, '.php', ''), '.html', '') = REPLACE(REPLACE(p.torneo, '.php', ''), '.html', '')
            LIMIT 1
          ), p.torneo) AS torneo_nome,
          p.fase, p.fase_round, p.giornata,
          p.squadra_casa, p.squadra_ospite, p.gol_casa, p.gol_ospite,
          p.data_partita, p.giocata,
          sc.logo AS logo_casa, so.logo AS logo_ospite
   FROM partite p
   LEFT JOIN squadre sc ON sc.nome = p.squadra_casa AND sc.torneo = p.torneo
   LEFT JOIN squadre so ON so.nome = p.squadra_ospite AND so.torneo = p.torneo
   WHERE p.giocata = 1
   ORDER BY p.data_partita DESC, p.ora_partita DESC, p.id DESC"
);
if ($partiteStmt && $partiteStmt->execute()) {
  $partiteResult = $partiteStmt->get_result();
  while ($partita = $partiteResult->fetch_assoc()) {
    $partiteGrafiche[] = $partita;
  }
  $partiteStmt->close();
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Generatore grafiche post partita</title>
  <style>
    :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; --bg:#07111d; --panel:#101e2d; --panel2:#152638; --gold:#e8bd45; --muted:#aebdca; }
    * { box-sizing:border-box; }
    body { margin:0; background:radial-gradient(circle at top,#13263a 0,var(--bg) 42%); color:#fff; }
    main { width:min(1440px,calc(100% - 28px)); margin:28px auto 60px; }
    a { color:#9bcaff; }
    h1 { margin:12px 0 6px; }
    .intro { color:var(--muted); margin-top:0; }
    .workspace { display:grid; grid-template-columns:minmax(320px,430px) minmax(0,1fr); gap:24px; align-items:start; }
    .controls,.preview-card { background:rgba(16,30,45,.96); border:1px solid #ffffff12; border-radius:18px; box-shadow:0 18px 50px #0005; }
    .controls { padding:18px; position:sticky; top:16px; }
    .tabs { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:18px; }
    .tab { background:#203449; color:#dbe7f2; }
    .tab.active { background:var(--gold); color:#101722; }
    .panel { display:none; }
    .panel.active { display:block; }
    .fields { display:grid; grid-template-columns:1fr 1fr; gap:13px; }
    .wide { grid-column:1/-1; }
    label { display:grid; gap:6px; color:#e9f1f8; font-size:14px; font-weight:750; }
    input,select,button { width:100%; border:1px solid #ffffff18; border-radius:10px; padding:11px 12px; font:inherit; }
    input,select { background:#081522; color:#fff; }
    input[readonly] { color:#b9c9d7; background:#0b1926; }
    input[type=file] { padding:8px; color:#bac8d5; }
    button { border:0; cursor:pointer; background:var(--gold); color:#101722; font-weight:850; }
    .secondary { background:#263d53; color:#fff; }
    .actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:16px; }
    .hint { color:var(--muted); font-size:13px; line-height:1.45; margin:14px 0 0; }
    .previews { display:grid; grid-template-columns:repeat(2,minmax(280px,1fr)); gap:22px; }
    .preview-card { padding:15px; }
    .preview-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
    .preview-head h2 { margin:0; font-size:18px; }
    .preview-head button { width:auto; padding:9px 13px; }
    canvas { display:block; width:100%; max-width:540px; height:auto; margin:auto; background:#0a1724; box-shadow:0 12px 32px #0008; }
    .status { min-height:22px; margin:14px 0 0; color:#b9cad9; }
    @media(max-width:1050px){ .workspace{grid-template-columns:1fr}.controls{position:static}.previews{grid-template-columns:1fr 1fr} }
    @media(max-width:720px){ .previews{grid-template-columns:1fr}.fields{grid-template-columns:1fr}.wide{grid-column:auto}.actions{grid-template-columns:1fr} }
  </style>
</head>
<body>
<main>
  <a href="/admin_dashboard.php">Torna alla dashboard</a>
  <h1>Grafiche post partita</h1>
  <p class="intro">Genera anteprime Full Time e MVP in formato Instagram 1080 × 1350. In questa versione di prova i dati non vengono salvati.</p>

  <div class="workspace">
    <section class="controls">
      <div class="tabs">
        <button type="button" class="tab active" data-panel="fulltimePanel">Full Time</button>
        <button type="button" class="tab" data-panel="mvpPanel">MVP</button>
      </div>

      <div id="fulltimePanel" class="panel active">
        <div class="fields">
          <label class="wide">Torneo<select id="ftTournamentSelect"><option value="">Seleziona il torneo</option></select></label>
          <label>Fase<select id="ftPhase" disabled><option value="">Seleziona la fase</option></select></label>
          <label>Giornata / turno<select id="ftRoundSelect" disabled><option value="">Seleziona la giornata</option></select></label>
          <label class="wide">Partita<select id="ftMatch" disabled><option value="">Seleziona la partita</option></select></label>
          <label class="wide">Torneo<input id="ftTournament" value="" readonly></label>
          <label>Squadra casa<input id="ftHome" value="" readonly></label>
          <label>Squadra ospite<input id="ftAway" value="" readonly></label>
          <label>Gol casa<input id="ftHomeScore" type="number" min="0" value="0" readonly></label>
          <label>Gol ospite<input id="ftAwayScore" type="number" min="0" value="0" readonly></label>
          <label class="wide">Foto pre-match dei capitani<input id="ftCaptains" type="file" accept="image/png,image/jpeg,image/webp"></label>
          <label class="wide">Giornata / fase<input id="ftRound" value="" readonly></label>
        </div>
      </div>

      <div id="mvpPanel" class="panel">
        <div class="fields">
          <label class="wide">Torneo<input id="mvpTournament" value="TORNEI OLD SCHOOL"></label>
          <label>Nome<input id="mvpName" value="MARIO"></label>
          <label>Cognome<input id="mvpSurname" value="ROSSI"></label>
          <label class="wide">Squadra<input id="mvpTeam" value="NOME SQUADRA"></label>
          <label class="wide">Foto MVP<input id="mvpPhoto" type="file" accept="image/png,image/jpeg,image/webp"></label>
          <label>Logo squadra (facoltativo)<input id="mvpLogo" type="file" accept="image/png,image/jpeg,image/webp"></label>
          <label>Dettaglio (facoltativo)<input id="mvpDetails" value="MAN OF THE MATCH"></label>
        </div>
      </div>

      <div class="actions">
        <button id="generate" type="button">Aggiorna entrambe</button>
        <button id="reset" class="secondary" type="button">Rimuovi immagini</button>
      </div>
      <p class="hint">Suggerimento: usa fotografie verticali o con spazio intorno ai soggetti. Il generatore centra e ritaglia automaticamente le immagini.</p>
      <div id="status" class="status" aria-live="polite"></div>
    </section>

    <section class="previews">
      <article class="preview-card">
        <div class="preview-head"><h2>Full Time</h2><button type="button" data-download="fulltime">Scarica PNG</button></div>
        <canvas id="fulltimeCanvas" width="1080" height="1350"></canvas>
      </article>
      <article class="preview-card">
        <div class="preview-head"><h2>MVP</h2><button type="button" data-download="mvp">Scarica PNG</button></div>
        <canvas id="mvpCanvas" width="1080" height="1350"></canvas>
      </article>
    </section>
  </div>
</main>
<script>
const $ = id => document.getElementById(id);
const W=1080,H=1350,GOLD='#e8bd45',BG='#07131f',PANEL='#102438',MUTED='#aebdca';
const matches=<?= json_encode($partiteGrafiche, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const imageState={ftHomeLogo:null,ftAwayLogo:null,ftCaptains:null,mvpPhoto:null,mvpLogo:null,brand:null};
const imageFields=['ftCaptains','mvpPhoto','mvpLogo'];
const safeName=value=>String(value||'grafica').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();
const upper=(value,fallback='')=>String(value||fallback).trim().toUpperCase();

function loadImage(src){return new Promise(resolve=>{if(!src)return resolve(null);const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>resolve(null);img.src=src;});}
function fileImage(file){return new Promise((resolve,reject)=>{if(!file)return resolve(null);const reader=new FileReader();reader.onload=async()=>resolve(await loadImage(reader.result));reader.onerror=reject;reader.readAsDataURL(file);});}
function cover(ctx,img,x,y,w,h,position='top'){if(!img?.naturalWidth)return;const scale=Math.max(w/img.naturalWidth,h/img.naturalHeight);const sw=w/scale,sh=h/scale,sx=(img.naturalWidth-sw)/2;let sy=0;if(position==='center')sy=(img.naturalHeight-sh)/2;else if(position==='bottom')sy=img.naturalHeight-sh;sy=Math.max(0,Math.min(sy,img.naturalHeight-sh));ctx.drawImage(img,sx,sy,sw,sh,x,y,w,h);}
function contain(ctx,img,x,y,w,h){if(!img?.naturalWidth)return;const scale=Math.min(w/img.naturalWidth,h/img.naturalHeight);const dw=img.naturalWidth*scale,dh=img.naturalHeight*scale;ctx.drawImage(img,x+(w-dw)/2,y+(h-dh)/2,dw,dh);}
function roundedRect(ctx,x,y,w,h,r,fill){ctx.beginPath();ctx.roundRect(x,y,w,h,r);ctx.fillStyle=fill;ctx.fill();}
function fitText(ctx,text,maxWidth,startSize,minSize=22,weight=800){let size=startSize;do{ctx.font=`${weight} ${size}px Arial`;if(ctx.measureText(text).width<=maxWidth)break;size-=2;}while(size>minSize);return size;}
function background(ctx){const gradient=ctx.createLinearGradient(0,0,W,H);gradient.addColorStop(0,'#10263a');gradient.addColorStop(.5,BG);gradient.addColorStop(1,'#050d15');ctx.fillStyle=gradient;ctx.fillRect(0,0,W,H);ctx.fillStyle=GOLD;ctx.fillRect(0,0,14,H);ctx.globalAlpha=.08;for(let x=-500;x<W+500;x+=145){ctx.save();ctx.translate(x,0);ctx.rotate(-.22);ctx.fillStyle='#fff';ctx.fillRect(0,0,2,H*1.3);ctx.restore();}ctx.globalAlpha=1;}
function brandHeader(ctx,tournament,label){contain(ctx,imageState.brand,52,45,112,112);ctx.textAlign='left';ctx.fillStyle='#fff';ctx.font='900 48px Arial';ctx.fillText(label,190,90);ctx.fillStyle=GOLD;fitText(ctx,upper(tournament,'TORNEI OLD SCHOOL'),720,27,18,800);ctx.fillText(upper(tournament,'TORNEI OLD SCHOOL'),190,132,720);ctx.fillStyle=GOLD;ctx.fillRect(48,172,W-96,4);}
function placeholder(ctx,x,y,w,h,label){roundedRect(ctx,x,y,w,h,18,'#172b3d');ctx.strokeStyle='#ffffff30';ctx.lineWidth=3;ctx.setLineDash([12,12]);ctx.strokeRect(x+2,y+2,w-4,h-4);ctx.setLineDash([]);ctx.fillStyle=MUTED;ctx.textAlign='center';ctx.font='700 25px Arial';ctx.fillText(label,x+w/2,y+h/2);}

function drawFulltime(){const c=$('fulltimeCanvas'),ctx=c.getContext('2d');background(ctx);brandHeader(ctx,$('ftTournament').value,'FULL TIME');const photo={x:48,y:205,w:984,h:600};if(imageState.ftCaptains){ctx.save();ctx.beginPath();ctx.roundRect(photo.x,photo.y,photo.w,photo.h,22);ctx.clip();cover(ctx,imageState.ftCaptains,photo.x,photo.y,photo.w,photo.h);const g=ctx.createLinearGradient(0,photo.y+280,0,photo.y+photo.h);g.addColorStop(0,'transparent');g.addColorStop(1,'#07131fe8');ctx.fillStyle=g;ctx.fillRect(photo.x,photo.y,photo.w,photo.h);ctx.restore();}else placeholder(ctx,photo.x,photo.y,photo.w,photo.h,'CARICA LA FOTO DEI DUE CAPITANI');
  roundedRect(ctx,48,765,984,430,22,PANEL);ctx.fillStyle=GOLD;ctx.fillRect(48,765,984,6);contain(ctx,imageState.ftHomeLogo||imageState.brand,80,835,155,155);contain(ctx,imageState.ftAwayLogo||imageState.brand,845,835,155,155);
  const home=upper($('ftHome').value,'SQUADRA CASA'),away=upper($('ftAway').value,'SQUADRA OSPITE');ctx.fillStyle='#fff';ctx.textBaseline='middle';ctx.textAlign='left';fitText(ctx,home,260,34,20,800);ctx.fillText(home,80,1045,260);ctx.textAlign='right';fitText(ctx,away,260,34,20,800);ctx.fillText(away,1000,1045,260);
  ctx.textAlign='center';ctx.fillStyle=GOLD;ctx.font='900 118px Arial';ctx.fillText(`${$('ftHomeScore').value||0} - ${$('ftAwayScore').value||0}`,W/2,910);ctx.fillStyle=MUTED;ctx.font='750 23px Arial';ctx.fillText(upper($('ftRound').value,'PARTITA'),W/2,1120);ctx.textBaseline='alphabetic';footer(ctx,$('ftTournament').value);
}
function drawMvp(){const c=$('mvpCanvas'),ctx=c.getContext('2d');background(ctx);brandHeader(ctx,$('mvpTournament').value,'MVP');const photo={x:48,y:205,w:984,h:760};if(imageState.mvpPhoto){ctx.save();ctx.beginPath();ctx.roundRect(photo.x,photo.y,photo.w,photo.h,22);ctx.clip();cover(ctx,imageState.mvpPhoto,photo.x,photo.y,photo.w,photo.h);const g=ctx.createLinearGradient(0,photo.y+350,0,photo.y+photo.h);g.addColorStop(0,'transparent');g.addColorStop(1,'#07131ff5');ctx.fillStyle=g;ctx.fillRect(photo.x,photo.y,photo.w,photo.h);ctx.restore();}else placeholder(ctx,photo.x,photo.y,photo.w,photo.h,'CARICA LA FOTO DEL GIOCATORE');
  const name=`${upper($('mvpName').value,'NOME')} ${upper($('mvpSurname').value,'COGNOME')}`;ctx.fillStyle='#fff';ctx.textAlign='center';fitText(ctx,name,820,66,32,900);ctx.fillText(name,W/2,1035,820);const team=upper($('mvpTeam').value,'NOME SQUADRA');ctx.fillStyle=GOLD;fitText(ctx,team,650,31,21,800);ctx.fillText(team,W/2,1088,650);contain(ctx,imageState.mvpLogo,72,992,105,105);const details=upper($('mvpDetails').value,'MAN OF THE MATCH');ctx.fillStyle=MUTED;ctx.font='700 22px Arial';ctx.fillText(details,W/2,1132);footer(ctx,$('mvpTournament').value);
}
function footer(ctx,tournament){ctx.fillStyle=GOLD;ctx.fillRect(48,1258,W-96,2);ctx.fillStyle=MUTED;ctx.font='600 18px Arial';ctx.textAlign='left';ctx.fillText(upper(tournament,'TORNEO'),48,1300,650);ctx.textAlign='right';ctx.fillText('torneioldschool.it',W-48,1300);}
function drawAll(){drawFulltime();drawMvp();$('status').textContent='Anteprime aggiornate.';}
async function updateImage(id){imageState[id]=await fileImage($(id).files?.[0]);drawAll();}
function uniqueBy(items,keyFn){const map=new Map();items.forEach(item=>{const key=keyFn(item);if(key&&!map.has(key))map.set(key,item);});return [...map.entries()];}
function setOptions(select,placeholder,options){select.innerHTML='';select.append(new Option(placeholder,''));options.forEach(([value,label])=>select.append(new Option(label,value)));select.disabled=options.length===0;}
function roundKey(match){return match.fase_round ? `round:${match.fase_round}` : `day:${match.giornata??''}`;}
function roundLabel(match){return match.fase_round ? String(match.fase_round).replaceAll('_',' ') : (match.giornata ? `GIORNATA ${match.giornata}` : 'TURNO UNICO');}
function populateTournaments(){
  const tournaments=uniqueBy(matches,item=>String(item.torneo||'')).map(([key,item])=>[key,item.torneo_nome||item.torneo]);
  setOptions($('ftTournamentSelect'),'Seleziona il torneo',tournaments);
}
function selectTournament(){
  const tournament=$('ftTournamentSelect').value;
  const filtered=matches.filter(item=>String(item.torneo)===tournament);
  const phases=uniqueBy(filtered,item=>String(item.fase||'')).map(([key])=>[key,key.replaceAll('_',' ')]);
  setOptions($('ftPhase'),'Seleziona la fase',phases);
  setOptions($('ftRoundSelect'),'Seleziona la giornata',[]);
  setOptions($('ftMatch'),'Seleziona la partita',[]);
  clearMatch();
}
function selectPhase(){
  const filtered=matches.filter(item=>String(item.torneo)===$('ftTournamentSelect').value&&String(item.fase)===$('ftPhase').value);
  const rounds=uniqueBy(filtered,roundKey).map(([key,item])=>[key,roundLabel(item)]);
  setOptions($('ftRoundSelect'),'Seleziona la giornata',rounds);
  setOptions($('ftMatch'),'Seleziona la partita',[]);
  clearMatch();
}
function selectRound(){
  const filtered=matches.filter(item=>String(item.torneo)===$('ftTournamentSelect').value&&String(item.fase)===$('ftPhase').value&&roundKey(item)===$('ftRoundSelect').value);
  const games=filtered.map(item=>[String(item.id),`${item.squadra_casa} ${item.gol_casa??0}-${item.gol_ospite??0} ${item.squadra_ospite}`]);
  setOptions($('ftMatch'),'Seleziona la partita',games);
  clearMatch();
}
function clearMatch(){
  $('ftTournament').value='';$('ftHome').value='';$('ftAway').value='';$('ftHomeScore').value=0;$('ftAwayScore').value=0;$('ftRound').value='';
  imageState.ftHomeLogo=null;imageState.ftAwayLogo=null;drawFulltime();
}
async function selectMatch(){
  const match=matches.find(item=>String(item.id)===String($('ftMatch').value));
  if(!match){clearMatch();return;}
  $('ftTournament').value=match.torneo_nome||match.torneo||'';
  $('ftHome').value=match.squadra_casa||'';
  $('ftAway').value=match.squadra_ospite||'';
  $('ftHomeScore').value=match.gol_casa??0;
  $('ftAwayScore').value=match.gol_ospite??0;
  const phase=String(match.fase||'').toUpperCase();
  $('ftRound').value=match.fase_round ? String(match.fase_round).replaceAll('_',' ') : (phase==='REGULAR' && match.giornata ? `GIORNATA ${match.giornata}` : phase);
  [imageState.ftHomeLogo,imageState.ftAwayLogo]=await Promise.all([loadImage(match.logo_casa),loadImage(match.logo_ospite)]);
  drawFulltime();
  $('status').textContent='Dati e loghi caricati dalla partita selezionata.';
}
function download(canvasId,name){const canvas=$(canvasId);const a=document.createElement('a');a.download=name;a.href=canvas.toDataURL('image/png');document.body.appendChild(a);a.click();a.remove();}

document.querySelectorAll('.tab').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('.tab').forEach(b=>b.classList.toggle('active',b===button));document.querySelectorAll('.panel').forEach(p=>p.classList.toggle('active',p.id===button.dataset.panel));}));
imageFields.forEach(id=>$(id).addEventListener('change',()=>updateImage(id).catch(()=>{$('status').textContent='Impossibile leggere questa immagine.';})));
$('ftTournamentSelect').addEventListener('change',selectTournament);
$('ftPhase').addEventListener('change',selectPhase);
$('ftRoundSelect').addEventListener('change',selectRound);
$('ftMatch').addEventListener('change',()=>selectMatch().catch(()=>{$('status').textContent='Impossibile caricare i dati della partita.';}));
document.querySelectorAll('input:not([type=file])').forEach(input=>input.addEventListener('input',drawAll));
$('generate').addEventListener('click',drawAll);
$('reset').addEventListener('click',()=>{imageFields.forEach(id=>{imageState[id]=null;$(id).value='';});drawAll();$('status').textContent='Immagini rimosse.';});
document.querySelector('[data-download=fulltime]').addEventListener('click',()=>download('fulltimeCanvas',`fulltime-${safeName($('ftHome').value)}-${safeName($('ftAway').value)}.png`));
document.querySelector('[data-download=mvp]').addEventListener('click',()=>download('mvpCanvas',`mvp-${safeName($('mvpName').value)}-${safeName($('mvpSurname').value)}.png`));
(async()=>{imageState.brand=await loadImage('/img/logo_old_school.png');populateTournaments();drawAll();})();
</script>
</body>
</html>
