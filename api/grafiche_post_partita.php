<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
require_once __DIR__ . '/../includi/db.php';
$embedded = isset($_GET['embed']) && $_GET['embed'] === '1';

$partiteGrafiche = [];
$partiteStmt = $conn->prepare(
  "SELECT p.id, p.torneo,
          (SELECT t.id FROM tornei t
           WHERE t.nome = p.torneo
              OR t.filetorneo = p.torneo
              OR REPLACE(REPLACE(t.filetorneo, '.php', ''), '.html', '') = REPLACE(REPLACE(p.torneo, '.php', ''), '.html', '')
           LIMIT 1) AS torneo_id,
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
   WHERE (p.giocata = 1
      OR (p.giocata = 0
          AND p.data_partita BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()))
     AND NOT EXISTS (
       SELECT 1
       FROM tornei tx
       WHERE (tx.nome = p.torneo
          OR tx.filetorneo = p.torneo
          OR REPLACE(REPLACE(tx.filetorneo, '.php', ''), '.html', '') = REPLACE(REPLACE(p.torneo, '.php', ''), '.html', ''))
         AND tx.stato = 'terminato'
         AND tx.data_fine < DATE_SUB(CURDATE(), INTERVAL 1 DAY)
     )
   ORDER BY p.data_partita DESC, p.ora_partita DESC, p.id DESC"
);
if ($partiteStmt && $partiteStmt->execute()) {
  $partiteResult = $partiteStmt->get_result();
  while ($partita = $partiteResult->fetch_assoc()) {
    $partiteGrafiche[] = $partita;
  }
  $partiteStmt->close();
}

$giocatoriGrafiche = [];
$giocatoriStmt = $conn->prepare(
  "SELECT pg.partita_id, g.id, g.nome, g.cognome,
          s.id AS squadra_id, s.nome AS squadra_nome
   FROM partita_giocatore pg
   JOIN partite p ON p.id = pg.partita_id AND p.giocata = 1
   JOIN giocatori g ON g.id = pg.giocatore_id
   JOIN squadre s ON s.id = pg.squadra_id
   WHERE COALESCE(pg.presenza, 1) = 1
   ORDER BY s.nome, g.nome, g.cognome"
);
if ($giocatoriStmt && $giocatoriStmt->execute()) {
  $giocatoriResult = $giocatoriStmt->get_result();
  while ($giocatore = $giocatoriResult->fetch_assoc()) {
    $partitaId = (string)$giocatore['partita_id'];
    $giocatoriGrafiche[$partitaId][] = $giocatore;
  }
  $giocatoriStmt->close();
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Generatore grafiche post partita</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
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
    input[type=range] { padding:4px 0; accent-color:var(--gold); }
    .range-value { color:var(--muted); font-size:12px; font-weight:600; }
    .player-options { display:grid; gap:8px; max-height:260px; overflow:auto; padding:10px; background:#081522; border:1px solid #ffffff18; border-radius:10px; }
    .player-option { display:flex; grid-template-columns:none; align-items:center; gap:9px; padding:7px 8px; border-radius:8px; background:#ffffff08; font-weight:650; }
    .player-option input { width:auto; margin:0; }
    .player-empty { color:var(--muted); font-size:13px; padding:8px; }
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
    @media(max-width:1050px){ .workspace{grid-template-columns:minmax(0,52%) minmax(0,48%);gap:10px}.controls{position:sticky;top:8px;padding:12px}body.with-site-header .controls{top:90px}.previews{grid-template-columns:1fr;gap:12px}.preview-card{padding:8px}.preview-head{align-items:flex-start;flex-direction:column}.preview-head button{width:100%;font-size:12px} }
    @media(max-width:720px){ main{width:min(100% - 10px,1440px);margin-left:auto;margin-right:auto}.fields{grid-template-columns:1fr}.wide{grid-column:auto}.actions{grid-template-columns:1fr}.tabs{gap:5px}.tab{padding:9px 5px;font-size:11px}label{font-size:12px}input,select,button{padding:8px 7px;font-size:12px}.preview-head h2{font-size:14px} }
    body.with-site-header>main{margin-top:110px}
  </style>
</head>
<body class="<?= $embedded ? 'is-embedded' : 'with-site-header' ?>">
<?php if (!$embedded): ?><?php include __DIR__ . '/../includi/header.php'; ?><?php endif; ?>
<main>
  <?php if (!$embedded): ?><a href="/admin_dashboard.php">Torna alla dashboard</a><?php endif; ?>
  <h1><?= $embedded ? 'FULLTIME E MVP' : 'Grafiche post partita' ?></h1>
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
          <label>Zoom foto <span class="range-value" id="ftZoomValue">100%</span><input id="ftZoom" type="range" min="100" max="250" value="100"></label>
          <label>Posizione orizzontale <span class="range-value" id="ftXValue">50%</span><input id="ftX" type="range" min="0" max="100" value="50"></label>
          <label class="wide">Posizione verticale <span class="range-value" id="ftYValue">0%</span><input id="ftY" type="range" min="0" max="100" value="0"></label>
          <label class="wide">Giornata / fase<input id="ftRound" value="" readonly></label>
        </div>
      </div>

      <div id="mvpPanel" class="panel">
        <div class="fields">
          <label class="wide">Torneo<input id="mvpTournament" value="" readonly></label>
          <div class="wide">
            <label>Seleziona MVP</label>
            <div id="mvpPlayers" class="player-options"><div class="player-empty">Seleziona prima una partita nel Full Time.</div></div>
          </div>
          <label class="wide">Selezione<input id="mvpNames" value="" readonly></label>
          <label class="wide">Squadra<input id="mvpTeam" value="" readonly></label>
          <label class="wide">Foto MVP<input id="mvpPhoto" type="file" accept="image/png,image/jpeg,image/webp"></label>
          <label>Zoom foto <span class="range-value" id="mvpZoomValue">100%</span><input id="mvpZoom" type="range" min="100" max="250" value="100"></label>
          <label>Posizione orizzontale <span class="range-value" id="mvpXValue">50%</span><input id="mvpX" type="range" min="0" max="100" value="50"></label>
          <label class="wide">Posizione verticale <span class="range-value" id="mvpYValue">0%</span><input id="mvpY" type="range" min="0" max="100" value="0"></label>
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
<?php if (!$embedded): ?><div id="footer-container"></div><?php endif; ?>
<script>
const $ = id => document.getElementById(id);
const W=1080,H=1350;
let GOLD='#e8bd45',BG='#07131f',PANEL='#102438',MUTED='#aebdca';
let TOURNAMENT_STYLE={motif:0,variant:0,monogram:'OS',concept:'modern'};
const tournamentThemes=[
  {bg:'#091522',accent:'#e5b93f',panel:'#102235',muted:'#aebdca'},
  {bg:'#1b1017',accent:'#df9e32',panel:'#301a28',muted:'#ccb9c4'},
  {bg:'#0b1915',accent:'#5fc49b',panel:'#122b24',muted:'#abc8bd'},
  {bg:'#151126',accent:'#a98bd4',panel:'#241c3e',muted:'#c1b7d0'},
  {bg:'#1e130d',accent:'#e49a3a',panel:'#382117',muted:'#d0bcaf'},
  {bg:'#081820',accent:'#45b6d0',panel:'#102c38',muted:'#abc6ce'},
  {bg:'#1d0f10',accent:'#d7645d',panel:'#35191b',muted:'#ceb2b3'},
  {bg:'#17190d',accent:'#a8bc49',panel:'#292d15',muted:'#c4c9ab'}
];
const matches=<?= json_encode($partiteGrafiche, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const matchPlayers=<?= json_encode($giocatoriGrafiche, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const imageState={ftHomeLogo:null,ftAwayLogo:null,ftCaptains:null,mvpPhoto:null,brand:null};
const imageFields=['ftCaptains','mvpPhoto'];
const safeName=value=>String(value||'grafica').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();
const upper=(value,fallback='')=>String(value||fallback).trim().toUpperCase();

function loadImage(src){return new Promise(resolve=>{if(!src)return resolve(null);const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>resolve(null);img.src=src;});}
function fileImage(file){return new Promise((resolve,reject)=>{if(!file)return resolve(null);const reader=new FileReader();reader.onload=async()=>resolve(await loadImage(reader.result));reader.onerror=reject;reader.readAsDataURL(file);});}
function cover(ctx,img,x,y,w,h,crop={}){if(!img?.naturalWidth)return;const zoom=Math.max(1,Number(crop.zoom||100)/100);const scale=Math.max(w/img.naturalWidth,h/img.naturalHeight)*zoom;const sw=w/scale,sh=h/scale;const px=Math.max(0,Math.min(100,Number(crop.x??50)))/100,py=Math.max(0,Math.min(100,Number(crop.y??0)))/100;const sx=(img.naturalWidth-sw)*px,sy=(img.naturalHeight-sh)*py;ctx.drawImage(img,sx,sy,sw,sh,x,y,w,h);}
function contain(ctx,img,x,y,w,h){if(!img?.naturalWidth)return;const scale=Math.min(w/img.naturalWidth,h/img.naturalHeight);const dw=img.naturalWidth*scale,dh=img.naturalHeight*scale;ctx.drawImage(img,x+(w-dw)/2,y+(h-dh)/2,dw,dh);}
function roundedRect(ctx,x,y,w,h,r,fill){ctx.beginPath();ctx.roundRect(x,y,w,h,r);ctx.fillStyle=fill;ctx.fill();}
function fitText(ctx,text,maxWidth,startSize,minSize=22,weight=800){let size=startSize;do{ctx.font=`${weight} ${size}px Arial`;if(ctx.measureText(text).width<=maxWidth)break;size-=2;}while(size>minSize);return size;}
function tournamentConcept(name){const value=String(name||'').toLowerCase();if(/formula|racing|motorsport|f1/.test(value))return'speed';if(/esport|gaming|ea fc|playstation|fifa open/.test(value))return'esport';if(/christmas|natale|xmas/.test(value))return'festive';if(/africa|african/.test(value))return'africa';if(/saudi|arabia|riyadh/.test(value))return'desert';if(/bundes|german|tedesc/.test(value))return'german';if(/premier|english|inghilterra/.test(value))return'premier';if(/champions|europe|europa/.test(value))return'champions';if(/mondial|world|intercontinental|intercontinentale|nazioni/.test(value))return'international';if(/serie\s*[abc]|italia|italian|calcio/.test(value))return'italian';if(/supercup|supercoppa|coppa|cup/.test(value))return'cup';if(/weekend|short|night/.test(value))return'urban';return'modern';}
function applyTournamentTheme(value,name=''){let hash=2166136261;const key=String(value||name||'torneo');for(let i=0;i<key.length;i++){hash^=key.charCodeAt(i);hash=Math.imul(hash,16777619);}const seed=Math.abs(hash),theme=tournamentThemes[seed%tournamentThemes.length];BG=theme.bg;GOLD=theme.accent;PANEL=theme.panel;MUTED=theme.muted;const words=upper(name,'OS').split(/\s+/).filter(word=>word.length>2&&!['DEL','DELLA','DI'].includes(word));TOURNAMENT_STYLE={motif:Math.floor(seed/8)%6,variant:Math.floor(seed/48)%4,monogram:(words.slice(0,3).map(word=>word[0]).join('')||'OS').slice(0,3),concept:tournamentConcept(name)};}
function photoRadius(){return({international:72,champions:10,italian:26,premier:4,german:0,desert:42,africa:34,festive:55,speed:28,esport:6,cup:46,urban:18,modern:22})[TOURNAMENT_STYLE.concept]??22;}
function cropValues(prefix){return{zoom:$(prefix+'Zoom').value,x:$(prefix+'X').value,y:$(prefix+'Y').value};}
function updateCropLabels(){['ft','mvp'].forEach(prefix=>{$(prefix+'ZoomValue').textContent=$(prefix+'Zoom').value+'%';$(prefix+'XValue').textContent=$(prefix+'X').value+'%';$(prefix+'YValue').textContent=$(prefix+'Y').value+'%';});}
function background(ctx){const gradient=ctx.createLinearGradient(0,0,W,H);gradient.addColorStop(0,'#10263a');gradient.addColorStop(.5,BG);gradient.addColorStop(1,'#050d15');ctx.fillStyle=gradient;ctx.fillRect(0,0,W,H);ctx.fillStyle=GOLD;ctx.fillRect(0,0,14,H);drawTournamentPattern(ctx);}
function drawTournamentPattern(ctx){const style=TOURNAMENT_STYLE;ctx.save();ctx.globalAlpha=.075;ctx.strokeStyle='#fff';ctx.fillStyle='#fff';ctx.lineWidth=2;
  if(style.concept==='champions'){ctx.globalAlpha=.12;for(let i=0;i<32;i++){const x=(i*197+style.variant*43)%W,y=(i*113+47)%H;ctx.beginPath();ctx.arc(x,y,i%5===0?4:2,0,Math.PI*2);ctx.fill();}}
  if(style.concept==='speed'){ctx.globalAlpha=.1;for(let y=260;y<H;y+=180){ctx.beginPath();ctx.moveTo(-100,y);ctx.lineTo(W,y-220);ctx.stroke();ctx.beginPath();ctx.moveTo(150,y+40);ctx.lineTo(W+100,y-160);ctx.stroke();}}
  if(style.concept==='africa'){ctx.globalAlpha=.09;for(let y=0;y<H;y+=105){for(let x=0;x<W;x+=105){ctx.strokeRect(x+18,y+18,48,48);ctx.beginPath();ctx.moveTo(x+42,y);ctx.lineTo(x+84,y+42);ctx.lineTo(x+42,y+84);ctx.lineTo(x,y+42);ctx.closePath();ctx.stroke();}}}
  if(style.concept==='festive'){ctx.globalAlpha=.11;for(let i=0;i<45;i++){const x=(i*83)%W,y=(i*149)%H;ctx.beginPath();ctx.arc(x,y,2+(i%4),0,Math.PI*2);ctx.fill();}}
  if(style.concept==='desert'){ctx.globalAlpha=.09;for(let y=240;y<H;y+=210){ctx.beginPath();ctx.moveTo(0,y);ctx.bezierCurveTo(W*.25,y-90,W*.65,y+90,W,y-25);ctx.stroke();}}
  if(style.concept==='esport'){ctx.globalAlpha=.1;for(let y=100;y<H;y+=170){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(130,y);ctx.lineTo(175,y-45);ctx.lineTo(360,y-45);ctx.stroke();}}
  if(style.motif===0){for(let x=-500;x<W+500;x+=125+style.variant*12){ctx.save();ctx.translate(x,0);ctx.rotate(-.22);ctx.fillRect(0,0,2,H*1.3);ctx.restore();}}
  if(style.motif===1){const step=115+style.variant*15;for(let x=0;x<W;x+=step){ctx.beginPath();ctx.moveTo(x,0);ctx.lineTo(x,H);ctx.stroke();}for(let y=0;y<H;y+=step){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(W,y);ctx.stroke();}}
  if(style.motif===2){const cx=850-style.variant*70,cy=330+style.variant*55;for(let radius=130;radius<720;radius+=105){ctx.beginPath();ctx.arc(cx,cy,radius,0,Math.PI*2);ctx.stroke();}}
  if(style.motif===3){const width=54+style.variant*10;for(let x=0;x<W;x+=width*2){ctx.fillRect(x,0,width,H);}}
  if(style.motif===4){const step=70+style.variant*8;for(let y=30;y<H;y+=step){for(let x=30+(Math.floor(y/step)%2)*step/2;x<W;x+=step){ctx.beginPath();ctx.arc(x,y,3+style.variant,0,Math.PI*2);ctx.fill();}}}
  if(style.motif===5){const step=150+style.variant*18;for(let y=-step;y<H+step;y+=step){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(W/2,y+step/2);ctx.lineTo(W,y);ctx.stroke();}}
  ctx.globalAlpha=.045;ctx.textAlign='right';ctx.fillStyle='#fff';ctx.font='900 360px Arial';ctx.fillText(style.monogram,W+20,H-90);ctx.restore();}
function brandHeader(ctx,tournament,label){const concept=TOURNAMENT_STYLE.concept,title=concept==='international'&&label==='FULL TIME'?'FULL-TIME':label;contain(ctx,imageState.brand,52,45,112,112);ctx.fillStyle='#fff';
  if(concept==='champions'){ctx.textAlign='center';ctx.font='800 46px Georgia';ctx.fillText(title,W/2,82);ctx.fillStyle=GOLD;fitText(ctx,upper(tournament,'TORNEO'),680,25,17,700);ctx.fillText(upper(tournament,'TORNEO'),W/2,128,680);}
  else if(concept==='speed'){ctx.textAlign='left';ctx.font='italic 900 51px Arial';ctx.fillText(title,190,91);ctx.fillStyle=GOLD;fitText(ctx,upper(tournament,'TORNEO'),720,25,17,900);ctx.fillText(upper(tournament,'TORNEO'),190,132,720);}
  else if(concept==='international'||concept==='italian'){ctx.textAlign='left';ctx.font='900 51px Georgia';ctx.fillText(title,190,91);ctx.fillStyle=GOLD;fitText(ctx,upper(tournament,'TORNEO'),720,25,17,700);ctx.fillText(upper(tournament,'TORNEO'),190,132,720);}
  else if(concept==='esport'){ctx.textAlign='left';ctx.font='900 52px Arial';ctx.fillText('['+title+']',190,91);ctx.fillStyle=GOLD;fitText(ctx,upper(tournament,'TORNEO'),720,25,17,900);ctx.fillText(upper(tournament,'TORNEO'),190,132,720);}
  else{ctx.textAlign='left';ctx.font='900 48px Arial';ctx.fillText(title,190,90);ctx.fillStyle=GOLD;fitText(ctx,upper(tournament,'TORNEO'),720,27,18,800);ctx.fillText(upper(tournament,'TORNEO'),190,132,720);}
  ctx.fillStyle=GOLD;if(concept==='premier'||concept==='german')ctx.fillRect(48,166,W-96,10);else if(concept==='champions')ctx.fillRect(W/2-90,169,180,3);else ctx.fillRect(48,172,W-96,4);
}
function placeholder(ctx,x,y,w,h,label){roundedRect(ctx,x,y,w,h,18,'#172b3d');ctx.strokeStyle='#ffffff30';ctx.lineWidth=3;ctx.setLineDash([12,12]);ctx.strokeRect(x+2,y+2,w-4,h-4);ctx.setLineDash([]);ctx.fillStyle=MUTED;ctx.textAlign='center';ctx.font='700 25px Arial';ctx.fillText(label,x+w/2,y+h/2);}

function drawFulltimeClassic(){const selected=matches.find(item=>String(item.id)===String($('ftMatch').value))||matches.find(item=>String(item.torneo)===$('ftTournamentSelect').value);applyTournamentTheme(selected?.torneo_id??$('ftTournamentSelect').value??$('ftTournament').value,$('ftTournament').value);const c=$('fulltimeCanvas'),ctx=c.getContext('2d');background(ctx);brandHeader(ctx,$('ftTournament').value,'FULL TIME');const photo={x:48,y:205,w:984,h:720},radius=photoRadius();if(imageState.ftCaptains){ctx.save();ctx.beginPath();ctx.roundRect(photo.x,photo.y,photo.w,photo.h,radius);ctx.clip();cover(ctx,imageState.ftCaptains,photo.x,photo.y,photo.w,photo.h,cropValues('ft'));const g=ctx.createLinearGradient(0,photo.y+350,0,photo.y+photo.h);g.addColorStop(0,'transparent');g.addColorStop(1,BG+'ee');ctx.fillStyle=g;ctx.fillRect(photo.x,photo.y,photo.w,photo.h);ctx.restore();}else placeholder(ctx,photo.x,photo.y,photo.w,photo.h,'CARICA LA FOTO DEI DUE CAPITANI');
  roundedRect(ctx,48,850,984,345,22,PANEL);ctx.fillStyle=GOLD;ctx.fillRect(48,850,984,6);contain(ctx,imageState.ftHomeLogo||imageState.brand,105,885,130,130);contain(ctx,imageState.ftAwayLogo||imageState.brand,845,885,130,130);
  const home=upper($('ftHome').value,'SQUADRA CASA'),away=upper($('ftAway').value,'SQUADRA OSPITE');ctx.fillStyle='#fff';ctx.textBaseline='middle';ctx.textAlign='center';fitText(ctx,home,240,34,18,800);ctx.fillText(home,170,1060,240);fitText(ctx,away,240,34,18,800);ctx.fillText(away,910,1060,240);
  ctx.fillStyle=GOLD;ctx.font='900 118px Arial';ctx.fillText(`${$('ftHomeScore').value||0} - ${$('ftAwayScore').value||0}`,W/2,950);ctx.fillStyle=MUTED;ctx.font='750 23px Arial';ctx.fillText(upper($('ftRound').value,'PARTITA'),W/2,1130);ctx.textBaseline='alphabetic';footer(ctx,$('ftTournament').value);
}
function drawMvpClassic(){const tournament=matches.find(item=>String(item.torneo_nome||item.torneo).toLowerCase()===String($('mvpTournament').value).trim().toLowerCase());applyTournamentTheme(tournament?.torneo_id??$('mvpTournament').value,$('mvpTournament').value);const c=$('mvpCanvas'),ctx=c.getContext('2d');background(ctx);brandHeader(ctx,$('mvpTournament').value,'MVP');const photo={x:48,y:205,w:984,h:760},radius=photoRadius();if(imageState.mvpPhoto){ctx.save();ctx.beginPath();ctx.roundRect(photo.x,photo.y,photo.w,photo.h,radius);ctx.clip();cover(ctx,imageState.mvpPhoto,photo.x,photo.y,photo.w,photo.h,cropValues('mvp'));const g=ctx.createLinearGradient(0,photo.y+350,0,photo.y+photo.h);g.addColorStop(0,'transparent');g.addColorStop(1,BG+'f5');ctx.fillStyle=g;ctx.fillRect(photo.x,photo.y,photo.w,photo.h);ctx.restore();}else placeholder(ctx,photo.x,photo.y,photo.w,photo.h,'CARICA LA FOTO DEL GIOCATORE');
  const names=upper($('mvpNames').value,'SELEZIONA MVP').split(' • ').filter(Boolean);ctx.fillStyle='#fff';ctx.textAlign='center';if(names.length===1){fitText(ctx,names[0],900,58,30,900);ctx.fillText(names[0],W/2,1045,900);}else if(names.length===2){names.forEach((name,index)=>{fitText(ctx,name,900,42,27,900);ctx.fillText(name,W/2,1025+(index*48),900);});}else{const joined=names.join(' • ');fitText(ctx,joined,920,38,23,900);ctx.fillText(joined,W/2,1050,920);}
  const team=upper($('mvpTeam').value,'SQUADRA');ctx.fillStyle=GOLD;ctx.textAlign='center';fitText(ctx,team,820,30,18,800);ctx.fillText(team,W/2,1150,820);const details=upper($('mvpDetails').value,'MAN OF THE MATCH');ctx.fillStyle=MUTED;ctx.font='700 20px Arial';ctx.fillText(details,W/2,1215);footer(ctx,$('mvpTournament').value);
}
function currentMatch(){return matches.find(item=>String(item.id)===String($('ftMatch').value))||matches.find(item=>String(item.torneo)===$('ftTournamentSelect').value)||null;}
function layoutFamily(concept){if(['international','italian','desert','africa'].includes(concept))return'editorial';if(['champions','cup','festive'].includes(concept))return'cinematic';return'dynamic';}
function prepareTheme(name,match=null){applyTournamentTheme(match?.torneo_id??match?.torneo??name,name);return layoutFamily(TOURNAMENT_STYLE.concept);}
function photoFrame(ctx,img,x,y,w,h,prefix,radius=28,border=true){ctx.save();ctx.beginPath();ctx.roundRect(x,y,w,h,radius);ctx.clip();if(img)cover(ctx,img,x,y,w,h,cropValues(prefix));else placeholder(ctx,x,y,w,h,prefix==='ft'?'CARICA LA FOTO DEI DUE CAPITANI':'CARICA LA FOTO MVP');ctx.restore();if(border){ctx.strokeStyle='#fff';ctx.lineWidth=5;ctx.beginPath();ctx.roundRect(x,y,w,h,radius);ctx.stroke();}}
function drawMvpNameBlock(ctx,x,y,maxWidth,align='center'){const values=upper($('mvpNames').value,'SELEZIONA MVP').split(/\s+•\s+/).filter(Boolean);ctx.fillStyle='#fff';ctx.textAlign=align;if(values.length===1){fitText(ctx,values[0],maxWidth,60,28,900);ctx.fillText(values[0],x,y,maxWidth)}else if(values.length===2){values.forEach((value,index)=>{fitText(ctx,value,maxWidth,42,24,900);ctx.fillText(value,x,y+index*48,maxWidth)})}else{const joined=values.join(' • ');fitText(ctx,joined,maxWidth,36,20,900);ctx.fillText(joined,x,y,maxWidth)}}

function drawFulltimeEditorial(){const match=currentMatch(),name=$('ftTournament').value,c=$('fulltimeCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);ctx.fillStyle='#fff';ctx.textAlign='left';ctx.font='900 73px Georgia';ctx.fillText('FULL-TIME',55,105);ctx.fillStyle=GOLD;fitText(ctx,upper(name,'TORNEO'),650,25,17,700);ctx.fillText(upper(name,'TORNEO'),58,148,650);contain(ctx,imageState.brand,900,48,115,115);photoFrame(ctx,imageState.ftCaptains,55,210,585,890,'ft',72,true);ctx.fillStyle=PANEL;ctx.beginPath();ctx.roundRect(675,240,350,850,24);ctx.fill();ctx.fillStyle=GOLD;ctx.fillRect(675,240,8,850);contain(ctx,imageState.ftHomeLogo||imageState.brand,755,285,190,160);contain(ctx,imageState.ftAwayLogo||imageState.brand,755,805,190,160);ctx.fillStyle='#fff';ctx.textAlign='center';fitText(ctx,upper($('ftHome').value,'CASA'),320,27,16,800);ctx.fillText(upper($('ftHome').value,'CASA'),850,485,320);fitText(ctx,upper($('ftAway').value,'OSPITE'),320,27,16,800);ctx.fillText(upper($('ftAway').value,'OSPITE'),850,1005,320);ctx.font='900 125px Georgia';ctx.fillText(String($('ftHomeScore').value||0),850,625);ctx.fillStyle=GOLD;ctx.fillRect(775,660,150,5);ctx.fillStyle='#fff';ctx.fillText(String($('ftAwayScore').value||0),850,790);ctx.fillStyle=GOLD;ctx.font='800 20px Arial';ctx.fillText(upper($('ftRound').value,'PARTITA'),850,1055);footer(ctx,name)}
function drawFulltimeCinematic(){const match=currentMatch(),name=$('ftTournament').value,c=$('fulltimeCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);photoFrame(ctx,imageState.ftCaptains,35,175,1010,920,'ft',12,false);const g=ctx.createLinearGradient(0,570,0,1110);g.addColorStop(0,'transparent');g.addColorStop(1,BG);ctx.fillStyle=g;ctx.fillRect(35,500,1010,610);ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='800 55px Georgia';ctx.fillText('FULL TIME',W/2,92);ctx.fillStyle=GOLD;ctx.font='700 21px Arial';ctx.fillText(upper(name,'TORNEO'),W/2,135);roundedRect(ctx,105,830,870,315,25,PANEL+'ee');contain(ctx,imageState.ftHomeLogo||imageState.brand,145,865,155,155);contain(ctx,imageState.ftAwayLogo||imageState.brand,780,865,155,155);ctx.fillStyle='#fff';ctx.font='900 112px Arial';ctx.fillText(`${$('ftHomeScore').value||0}  —  ${$('ftAwayScore').value||0}`,W/2,975);fitText(ctx,upper($('ftHome').value,'CASA'),250,28,17,800);ctx.fillText(upper($('ftHome').value,'CASA'),220,1070,250);fitText(ctx,upper($('ftAway').value,'OSPITE'),250,28,17,800);ctx.fillText(upper($('ftAway').value,'OSPITE'),860,1070,250);ctx.fillStyle=GOLD;ctx.font='800 21px Arial';ctx.fillText(upper($('ftRound').value,'PARTITA'),W/2,1120);footer(ctx,name)}
function drawFulltimeDynamic(){const match=currentMatch(),name=$('ftTournament').value,c=$('fulltimeCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);ctx.save();ctx.beginPath();ctx.moveTo(420,170);ctx.lineTo(1040,170);ctx.lineTo(1040,1130);ctx.lineTo(220,1130);ctx.closePath();ctx.clip();if(imageState.ftCaptains)cover(ctx,imageState.ftCaptains,220,170,820,960,cropValues('ft'));else placeholder(ctx,220,170,820,960,'CARICA LA FOTO');ctx.restore();ctx.fillStyle=BG;ctx.beginPath();ctx.moveTo(0,0);ctx.lineTo(610,0);ctx.lineTo(310,1350);ctx.lineTo(0,1350);ctx.closePath();ctx.fill();ctx.fillStyle=GOLD;ctx.beginPath();ctx.moveTo(600,0);ctx.lineTo(622,0);ctx.lineTo(322,1350);ctx.lineTo(300,1350);ctx.closePath();ctx.fill();ctx.fillStyle='#fff';ctx.textAlign='left';ctx.font='italic 900 70px Arial';ctx.fillText('FULL',52,115);ctx.fillText('TIME',52,182);ctx.fillStyle=GOLD;fitText(ctx,upper(name,'TORNEO'),400,22,15,900);ctx.fillText(upper(name,'TORNEO'),55,225,400);contain(ctx,imageState.ftHomeLogo||imageState.brand,65,330,170,170);contain(ctx,imageState.ftAwayLogo||imageState.brand,65,720,170,170);ctx.fillStyle='#fff';ctx.font='900 120px Arial';ctx.fillText(String($('ftHomeScore').value||0),260,460);ctx.fillText(String($('ftAwayScore').value||0),260,850);fitText(ctx,upper($('ftHome').value,'CASA'),350,27,16,800);ctx.fillText(upper($('ftHome').value,'CASA'),55,550,350);fitText(ctx,upper($('ftAway').value,'OSPITE'),350,27,16,800);ctx.fillText(upper($('ftAway').value,'OSPITE'),55,940,350);ctx.fillStyle=GOLD;ctx.font='800 20px Arial';ctx.fillText(upper($('ftRound').value,'PARTITA'),55,1040);footer(ctx,name)}

function drawMvpEditorial(){const match=currentMatch(),name=$('mvpTournament').value,c=$('mvpCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);ctx.fillStyle='#fff';ctx.font='900 78px Georgia';ctx.textAlign='left';ctx.fillText('MVP',60,110);ctx.fillStyle=GOLD;ctx.font='700 23px Arial';ctx.fillText(upper(name,'TORNEO'),62,150,650);photoFrame(ctx,imageState.mvpPhoto,55,210,620,1010,'mvp',72,true);ctx.save();ctx.translate(970,1130);ctx.rotate(-Math.PI/2);ctx.globalAlpha=.12;ctx.fillStyle='#fff';ctx.font='900 185px Georgia';ctx.fillText('MVP',0,0);ctx.restore();drawMvpNameBlock(ctx,710,475,310,'left');ctx.fillStyle=GOLD;ctx.textAlign='left';fitText(ctx,upper($('mvpTeam').value,'SQUADRA'),310,27,16,800);ctx.fillText(upper($('mvpTeam').value,'SQUADRA'),710,610,310);ctx.fillStyle=MUTED;ctx.font='700 20px Arial';ctx.fillText(upper($('mvpDetails').value,'MAN OF THE MATCH'),710,660,310);contain(ctx,imageState.brand,790,930,150,150);footer(ctx,name)}
function drawMvpCinematic(){const match=currentMatch(),name=$('mvpTournament').value,c=$('mvpCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);photoFrame(ctx,imageState.mvpPhoto,35,170,1010,1010,'mvp',12,false);const g=ctx.createLinearGradient(0,520,0,1190);g.addColorStop(0,'transparent');g.addColorStop(1,BG);ctx.fillStyle=g;ctx.fillRect(35,460,1010,730);ctx.globalAlpha=.13;ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 260px Georgia';ctx.fillText('MVP',W/2,1030);ctx.globalAlpha=1;ctx.fillStyle='#fff';ctx.font='800 52px Georgia';ctx.fillText('MAN OF THE MATCH',W/2,95);ctx.fillStyle=GOLD;ctx.font='700 21px Arial';ctx.fillText(upper(name,'TORNEO'),W/2,137);drawMvpNameBlock(ctx,W/2,1040,900,'center');ctx.fillStyle=GOLD;fitText(ctx,upper($('mvpTeam').value,'SQUADRA'),760,29,17,800);ctx.fillText(upper($('mvpTeam').value,'SQUADRA'),W/2,1160,760);footer(ctx,name)}
function drawMvpDynamic(){const match=currentMatch(),name=$('mvpTournament').value,c=$('mvpCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);ctx.save();ctx.beginPath();ctx.moveTo(430,130);ctx.lineTo(1045,130);ctx.lineTo(1045,1215);ctx.lineTo(250,1215);ctx.closePath();ctx.clip();if(imageState.mvpPhoto)cover(ctx,imageState.mvpPhoto,250,130,795,1085,cropValues('mvp'));else placeholder(ctx,250,130,795,1085,'CARICA FOTO MVP');ctx.restore();ctx.fillStyle=BG;ctx.beginPath();ctx.moveTo(0,0);ctx.lineTo(610,0);ctx.lineTo(310,1350);ctx.lineTo(0,1350);ctx.closePath();ctx.fill();ctx.fillStyle=GOLD;ctx.beginPath();ctx.moveTo(600,0);ctx.lineTo(625,0);ctx.lineTo(325,1350);ctx.lineTo(300,1350);ctx.closePath();ctx.fill();ctx.fillStyle='#fff';ctx.textAlign='left';ctx.font='italic 900 104px Arial';ctx.fillText('MVP',52,150);ctx.fillStyle=GOLD;fitText(ctx,upper(name,'TORNEO'),410,22,15,900);ctx.fillText(upper(name,'TORNEO'),55,195,410);drawMvpNameBlock(ctx,55,450,390,'left');ctx.fillStyle=GOLD;ctx.textAlign='left';fitText(ctx,upper($('mvpTeam').value,'SQUADRA'),380,28,16,800);ctx.fillText(upper($('mvpTeam').value,'SQUADRA'),55,610,380);ctx.fillStyle=MUTED;ctx.font='700 20px Arial';ctx.fillText(upper($('mvpDetails').value,'MAN OF THE MATCH'),55,660,390);footer(ctx,name)}

function glow(ctx,x,y,r,color=GOLD){const g=ctx.createRadialGradient(x,y,0,x,y,r);g.addColorStop(0,color+'bb');g.addColorStop(.35,color+'44');g.addColorStop(1,'transparent');ctx.fillStyle=g;ctx.fillRect(x-r,y-r,r*2,r*2)}
function stadium(ctx){ctx.save();for(let i=0;i<11;i++){const x=35+i*101;glow(ctx,x,610,115,'#dff5ff')}ctx.globalAlpha=.2;ctx.strokeStyle='#fff';ctx.lineWidth=3;ctx.beginPath();ctx.ellipse(W/2,760,620,245,0,Math.PI,Math.PI*2);ctx.stroke();ctx.globalAlpha=.16;for(let y=760;y<1160;y+=58){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(W,y);ctx.stroke()}ctx.restore()}
function slash(ctx,x,y,w,h,color=GOLD){ctx.save();ctx.translate(x,y);ctx.transform(1,0,-.24,1,0,0);ctx.fillStyle=color;ctx.fillRect(0,0,w,h);ctx.restore()}
function posterHeader(ctx,title,tournament){contain(ctx,imageState.brand,35,30,105,105);ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='italic 900 88px Impact, Arial';ctx.shadowColor='#000';ctx.shadowBlur=12;ctx.fillText(title,W/2,112);ctx.shadowBlur=0;ctx.fillStyle=GOLD;ctx.font='900 22px Arial';ctx.fillText(upper(tournament,'TORNEO'),W/2,151,700)}
function scoreBoard(ctx,y){const home=upper($('ftHome').value,'CASA'),away=upper($('ftAway').value,'OSPITE');ctx.shadowColor='#000';ctx.shadowBlur=20;roundedRect(ctx,55,y,970,225,8,'#05080df2');ctx.shadowBlur=0;ctx.fillStyle=GOLD;ctx.fillRect(55,y,970,9);contain(ctx,imageState.ftHomeLogo||imageState.brand,75,y+28,165,150);contain(ctx,imageState.ftAwayLogo||imageState.brand,840,y+28,165,150);ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 112px Impact, Arial';ctx.fillText(`${$('ftHomeScore').value||0} - ${$('ftAwayScore').value||0}`,W/2,y+137);fitText(ctx,home,245,27,15,900);ctx.fillText(home,180,y+205,245);fitText(ctx,away,245,27,15,900);ctx.fillText(away,900,y+205,245)}
function drawFulltimePoster(){const match=currentMatch(),name=$('ftTournament').value,c=$('fulltimeCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);stadium(ctx);glow(ctx,150,250,430);glow(ctx,930,360,420,'#285cff');const framed=TOURNAMENT_STYLE.concept==='international';if(framed){posterHeader(ctx,'FULL-TIME',name);photoFrame(ctx,imageState.ftCaptains,55,195,615,890,'ft',65,true);ctx.fillStyle='#03070dcc';ctx.fillRect(690,195,340,890);contain(ctx,imageState.ftHomeLogo||imageState.brand,750,305,220,190);contain(ctx,imageState.ftAwayLogo||imageState.brand,750,760,220,190);ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 130px Impact, Arial';ctx.fillText(String($('ftHomeScore').value||0),860,590);ctx.fillStyle=GOLD;ctx.fillText(String($('ftAwayScore').value||0),860,735);ctx.fillStyle='#fff';ctx.font='900 45px Impact, Arial';ctx.fillText('-',860,650)}else{if(imageState.ftCaptains){ctx.save();ctx.globalAlpha=.98;cover(ctx,imageState.ftCaptains,95,175,890,930,cropValues('ft'));ctx.restore()}else placeholder(ctx,95,175,890,930,'CARICA LA FOTO DEI CAPITANI');ctx.fillStyle='#00000055';ctx.fillRect(0,0,W,260);posterHeader(ctx,'FULL TIME',name);slash(ctx,-80,1085,1240,50);scoreBoard(ctx,965)}ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 23px Arial';ctx.fillText(upper($('ftRound').value,'PARTITA'),W/2,1228);footer(ctx,name)}
function drawMvpPoster(){const match=currentMatch(),name=$('mvpTournament').value,c=$('mvpCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);stadium(ctx);glow(ctx,W/2,620,520,'#246cff');ctx.globalAlpha=.14;ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='italic 900 330px Impact, Arial';ctx.fillText('MVP',W/2,470);ctx.globalAlpha=1;posterHeader(ctx,'MVP',name);if(imageState.mvpPhoto){ctx.save();ctx.beginPath();ctx.ellipse(W/2,690,420,500,0,0,Math.PI*2);ctx.clip();cover(ctx,imageState.mvpPhoto,120,180,840,1000,cropValues('mvp'));ctx.restore();ctx.strokeStyle=GOLD;ctx.lineWidth=8;ctx.beginPath();ctx.ellipse(W/2,690,420,500,0,0,Math.PI*2);ctx.stroke()}else placeholder(ctx,120,185,840,925,'CARICA LA FOTO MVP');ctx.fillStyle='#000000a8';ctx.fillRect(0,925,W,315);slash(ctx,-100,935,1280,32);roundedRect(ctx,90,970,900,190,4,'#05080de8');ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='italic 900 28px Arial';ctx.fillText('MAN OF THE MATCH',W/2,1010);drawMvpNameBlock(ctx,W/2,1085,840,'center');ctx.fillStyle=GOLD;fitText(ctx,upper($('mvpTeam').value,'SQUADRA'),760,30,17,900);ctx.fillText(upper($('mvpTeam').value,'SQUADRA'),W/2,1150,760);ctx.fillStyle='#fff';ctx.font='800 19px Arial';ctx.fillText(upper($('mvpDetails').value,'MIGLIORE IN CAMPO'),W/2,1195);footer(ctx,name)}
function ornateArch(ctx){ctx.save();ctx.strokeStyle=GOLD;ctx.lineWidth=22;ctx.shadowColor=GOLD;ctx.shadowBlur=28;ctx.beginPath();ctx.moveTo(175,960);ctx.lineTo(175,470);ctx.bezierCurveTo(175,145,905,145,905,470);ctx.lineTo(905,960);ctx.stroke();ctx.shadowBlur=0;for(let i=0;i<9;i++){ctx.globalAlpha=.5;ctx.beginPath();ctx.arc(W/2,430,270+i*20,Math.PI,0);ctx.stroke()}ctx.restore()}
function drawFulltimeRoyal(){const match=currentMatch(),name=$('ftTournament').value,c=$('fulltimeCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);const g=ctx.createLinearGradient(0,0,W,H);g.addColorStop(0,'#721111');g.addColorStop(.48,'#d53b17');g.addColorStop(1,'#180303');ctx.fillStyle=g;ctx.fillRect(0,0,W,H);glow(ctx,W/2,430,500,'#ffb52b');ornateArch(ctx);if(imageState.ftCaptains)cover(ctx,imageState.ftCaptains,125,260,830,790,cropValues('ft'));else placeholder(ctx,125,260,830,790,'CARICA LA FOTO DEI CAPITANI');contain(ctx,imageState.brand,35,30,105,105);ctx.fillStyle='#ffe6a0';ctx.textAlign='right';ctx.font='900 23px Arial';ctx.fillText(upper(name,'TORNEO'),1030,70,650);scoreBoard(ctx,920);ctx.fillStyle='#fff';ctx.strokeStyle='#ed2b1e';ctx.lineWidth=14;ctx.textAlign='center';ctx.font='900 96px Impact, Arial';ctx.strokeText('FULL TIME',W/2,1240);ctx.fillText('FULL TIME',W/2,1240);footer(ctx,name)}
function drawFulltimeChampions(){const match=currentMatch(),name=$('ftTournament').value,c=$('fulltimeCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);ctx.fillStyle='#020b2b';ctx.fillRect(0,0,W,H);stadium(ctx);for(let i=0;i<8;i++){ctx.strokeStyle=i%2?GOLD:'#276cff';ctx.globalAlpha=.2;ctx.lineWidth=18;ctx.beginPath();ctx.moveTo(-100+i*160,0);ctx.lineTo(340+i*150,H);ctx.stroke()}ctx.globalAlpha=1;posterHeader(ctx,'FULL TIME',name);if(imageState.ftCaptains)cover(ctx,imageState.ftCaptains,85,155,910,960,cropValues('ft'));else placeholder(ctx,85,155,910,960,'CARICA LA FOTO DEI CAPITANI');const vignette=ctx.createLinearGradient(0,700,0,1170);vignette.addColorStop(0,'transparent');vignette.addColorStop(1,'#020617');ctx.fillStyle=vignette;ctx.fillRect(0,650,W,530);scoreBoard(ctx,930);footer(ctx,name)}
function drawFulltimeTech(){const match=currentMatch(),name=$('ftTournament').value,c=$('fulltimeCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);ctx.fillStyle=GOLD;ctx.beginPath();ctx.moveTo(0,0);ctx.lineTo(400,0);ctx.lineTo(160,H);ctx.lineTo(0,H);ctx.fill();ctx.globalAlpha=.18;ctx.fillStyle='#fff';for(let y=0;y<H;y+=55)ctx.fillRect(0,y,W,2);ctx.globalAlpha=1;if(imageState.ftCaptains){ctx.save();ctx.beginPath();ctx.moveTo(250,110);ctx.lineTo(1040,110);ctx.lineTo(1040,1090);ctx.lineTo(80,1090);ctx.closePath();ctx.clip();cover(ctx,imageState.ftCaptains,80,110,960,980,cropValues('ft'));ctx.restore()}else placeholder(ctx,250,150,780,900,'CARICA LA FOTO');ctx.fillStyle='#05080ddd';ctx.fillRect(0,0,W,175);posterHeader(ctx,'FULL TIME',name);scoreBoard(ctx,930);footer(ctx,name)}
function drawFulltime(){const family=prepareTheme($('ftTournament').value,currentMatch()),concept=TOURNAMENT_STYLE.concept;if(['italian','desert','africa','festive'].includes(concept))return drawFulltimeRoyal();if(['champions','cup'].includes(concept))return drawFulltimeChampions();if(['speed','esport','urban','german','premier'].includes(concept))return drawFulltimeTech();drawFulltimePoster()}
function drawMvpRoyal(){const match=currentMatch(),name=$('mvpTournament').value,c=$('mvpCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);const g=ctx.createLinearGradient(0,0,W,H);g.addColorStop(0,'#450607');g.addColorStop(.55,'#b52617');g.addColorStop(1,'#180102');ctx.fillStyle=g;ctx.fillRect(0,0,W,H);glow(ctx,W/2,400,500,'#ffb02e');ctx.globalAlpha=.16;ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 300px Impact, Arial';ctx.fillText('MVP',W/2,360);ctx.globalAlpha=1;ornateArch(ctx);if(imageState.mvpPhoto)cover(ctx,imageState.mvpPhoto,150,245,780,775,cropValues('mvp'));else placeholder(ctx,150,245,780,775,'CARICA LA FOTO MVP');contain(ctx,imageState.brand,35,30,105,105);ctx.fillStyle='#ffe4a1';ctx.textAlign='right';ctx.font='900 22px Arial';ctx.fillText(upper(name,'TORNEO'),1030,68,650);roundedRect(ctx,70,950,940,255,5,'#130304e8');ctx.fillStyle=GOLD;ctx.fillRect(70,950,940,12);ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='italic 900 25px Arial';ctx.fillText('MAN OF THE MATCH',W/2,1005);drawMvpNameBlock(ctx,W/2,1090,850,'center');ctx.fillStyle=GOLD;fitText(ctx,upper($('mvpTeam').value,'SQUADRA'),780,29,16,900);ctx.fillText(upper($('mvpTeam').value,'SQUADRA'),W/2,1160,780);footer(ctx,name)}
function drawMvpTech(){const match=currentMatch(),name=$('mvpTournament').value,c=$('mvpCanvas'),ctx=c.getContext('2d');prepareTheme(name,match);background(ctx);ctx.fillStyle='#f3f5f7';ctx.beginPath();ctx.moveTo(0,90);ctx.lineTo(875,0);ctx.lineTo(1040,1180);ctx.lineTo(130,1270);ctx.closePath();ctx.fill();ctx.globalAlpha=.12;ctx.fillStyle=GOLD;for(let i=0;i<7;i++)slash(ctx,-120+i*210,100,85,1120);ctx.globalAlpha=1;ctx.fillStyle='#09121d';ctx.textAlign='center';ctx.font='italic 900 310px Impact, Arial';ctx.fillText('MVP',W/2,340);if(imageState.mvpPhoto)cover(ctx,imageState.mvpPhoto,120,260,840,790,cropValues('mvp'));else placeholder(ctx,120,260,840,790,'CARICA LA FOTO MVP');contain(ctx,imageState.brand,35,30,105,105);ctx.fillStyle='#09121d';ctx.textAlign='right';ctx.font='900 21px Arial';ctx.fillText(upper(name,'TORNEO'),1030,66,650);slash(ctx,20,945,1040,155,'#07111c');ctx.fillStyle='#fff';ctx.textAlign='center';drawMvpNameBlock(ctx,W/2,1035,890,'center');ctx.fillStyle=GOLD;fitText(ctx,upper($('mvpTeam').value,'SQUADRA'),760,28,16,900);ctx.fillText(upper($('mvpTeam').value,'SQUADRA'),W/2,1100,760);ctx.fillStyle='#07111c';ctx.font='italic 900 28px Arial';ctx.fillText('MAN OF THE MATCH',W/2,1175);footer(ctx,name)}
function drawMvp(){prepareTheme($('mvpTournament').value,currentMatch());const concept=TOURNAMENT_STYLE.concept;if(['italian','desert','africa','festive'].includes(concept))return drawMvpRoyal();if(['speed','esport','urban','german','premier'].includes(concept))return drawMvpTech();drawMvpPoster()}
function footer(ctx,tournament){ctx.fillStyle=GOLD;ctx.fillRect(48,1258,W-96,2);ctx.fillStyle=MUTED;ctx.font='600 18px Arial';ctx.textAlign='left';ctx.fillText(upper(tournament,'TORNEO'),48,1300,650);ctx.textAlign='right';ctx.fillText('torneioldschool.it',W-48,1300);}
function drawAll(){updateCropLabels();drawFulltime();drawMvp();$('status').textContent='Anteprime aggiornate.';}
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
  const games=filtered.map(item=>[String(item.id),`${item.squadra_casa} ${item.gol_casa??0}-${item.gol_ospite??0} ${item.squadra_ospite}${Number(item.giocata)===1?'':' (non terminata)'}`]);
  setOptions($('ftMatch'),'Seleziona la partita',games);
  clearMatch();
}
function resetMvpSelection(message='Seleziona prima una partita nel Full Time.'){
  $('mvpPlayers').innerHTML='';const empty=document.createElement('div');empty.className='player-empty';empty.textContent=message;$('mvpPlayers').append(empty);
  $('mvpNames').value='';$('mvpTeam').value='';drawMvp();
}
function addMvpOption(container,value,label,data,type='player'){
  const row=document.createElement('label');row.className='player-option';
  const input=document.createElement('input');input.type='checkbox';input.value=value;input.dataset.type=type;input._mvpData=data;
  const text=document.createElement('span');text.textContent=label;row.append(input,text);container.append(row);
  input.addEventListener('change',async()=>{
    const all=[...container.querySelectorAll('input[type=checkbox]')];
    if(input.checked&&type==='group')all.forEach(other=>{if(other!==input)other.checked=false;});
    if(input.checked&&type==='player')all.filter(other=>other.dataset.type==='group').forEach(other=>other.checked=false);
    await updateMvpSelection();
  });
}
function renderMvpPlayers(match){
  const container=$('mvpPlayers');container.innerHTML='';
  addMvpOption(container,'group:home',`IL GRUPPO (${match.squadra_casa})`,{name:'IL GRUPPO',team:match.squadra_casa},'group');
  addMvpOption(container,'group:away',`IL GRUPPO (${match.squadra_ospite})`,{name:'IL GRUPPO',team:match.squadra_ospite},'group');
  const players=matchPlayers[String(match.id)]||[];
  players.forEach(player=>addMvpOption(container,`player:${player.id}`,`${player.nome} ${player.cognome} — ${player.squadra_nome}`,{name:`${player.nome} ${player.cognome}`,team:player.squadra_nome}));
  if(!players.length){const note=document.createElement('div');note.className='player-empty';note.textContent='Nessun giocatore presente nel tabellino: puoi comunque scegliere uno dei due gruppi.';container.append(note);}
  $('mvpNames').value='';$('mvpTeam').value='';drawMvp();
}
async function updateMvpSelection(){
  const selected=[...$('mvpPlayers').querySelectorAll('input[type=checkbox]:checked')].map(input=>input._mvpData);
  $('mvpNames').value=selected.map(item=>item.name).join(' • ');
  $('mvpTeam').value=[...new Set(selected.map(item=>item.team).filter(Boolean))].join(' / ');
  drawMvp();
}
function clearMatch(){
  $('ftTournament').value='';$('ftHome').value='';$('ftAway').value='';$('ftHomeScore').value=0;$('ftAwayScore').value=0;$('ftRound').value='';
  $('ftHomeScore').readOnly=true;$('ftAwayScore').readOnly=true;
  $('mvpTournament').value='';imageState.ftHomeLogo=null;imageState.ftAwayLogo=null;resetMvpSelection();drawFulltime();
}
async function selectMatch(){
  const match=matches.find(item=>String(item.id)===String($('ftMatch').value));
  if(!match){clearMatch();return;}
  $('ftTournament').value=match.torneo_nome||match.torneo||'';
  $('mvpTournament').value=match.torneo_nome||match.torneo||'';
  $('ftHome').value=match.squadra_casa||'';
  $('ftAway').value=match.squadra_ospite||'';
  $('ftHomeScore').value=match.gol_casa??0;
  $('ftAwayScore').value=match.gol_ospite??0;
  const finished=Number(match.giocata)===1;
  $('ftHomeScore').readOnly=finished;
  $('ftAwayScore').readOnly=finished;
  const phase=String(match.fase||'').toUpperCase();
  $('ftRound').value=match.fase_round ? String(match.fase_round).replaceAll('_',' ') : (phase==='REGULAR' && match.giornata ? `GIORNATA ${match.giornata}` : phase);
  [imageState.ftHomeLogo,imageState.ftAwayLogo]=await Promise.all([loadImage(match.logo_casa),loadImage(match.logo_ospite)]);
  renderMvpPlayers(match);
  drawFulltime();
  $('status').textContent=finished?'Partita terminata: risultato caricato e bloccato.':'Partita non terminata degli ultimi 7 giorni: puoi modificare i gol solo nella grafica.';
}
const isIOS=/iP(hone|ad|od)/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
const canvasBlob=canvas=>new Promise((resolve,reject)=>canvas.toBlob(blob=>blob?resolve(blob):reject(new Error('Impossibile creare il PNG.')),'image/png'));
async function download(canvasId,name){const canvas=$(canvasId),fallbackWindow=isIOS&&!navigator.share?window.open('about:blank','_blank'):null;try{const blob=await canvasBlob(canvas),file=new File([blob],name,{type:'image/png'});if(navigator.share&&(!navigator.canShare||navigator.canShare({files:[file]}))){await navigator.share({files:[file],title:name});$('status').textContent='Nel pannello Condividi scegli “Salva immagine”.';return}const url=URL.createObjectURL(blob);if(fallbackWindow){fallbackWindow.location.href=url;$('status').textContent='Tieni premuta l’immagine e scegli “Salva in Foto”.';setTimeout(()=>URL.revokeObjectURL(url),60000);return}const a=document.createElement('a');a.download=name;a.href=url;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),2000)}catch(error){if(fallbackWindow)fallbackWindow.close();if(error?.name!=='AbortError')$('status').textContent=error.message}}

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
document.querySelector('[data-download=mvp]').addEventListener('click',()=>download('mvpCanvas',`mvp-${safeName($('mvpNames').value)}.png`));
(async()=>{imageState.brand=await loadImage('/img/logo_old_school.png');if(isIOS)document.querySelectorAll('[data-download]').forEach(button=>button.textContent='Salva immagine');populateTournaments();drawAll();})();
<?php if (!$embedded): ?>fetch('/includi/footer.html').then(response=>response.text()).then(html=>{document.getElementById('footer-container').innerHTML=html;}).catch(()=>{});<?php endif; ?>
</script>
</body>
</html>
