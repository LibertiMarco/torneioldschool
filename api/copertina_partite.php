<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
require_once __DIR__ . '/../includi/db.php';

$partite = [];
$stmt = $conn->prepare(
  "SELECT p.id, p.torneo,
          COALESCE((SELECT t.nome FROM tornei t
            WHERE t.nome=p.torneo OR t.filetorneo=p.torneo
               OR REPLACE(REPLACE(t.filetorneo,'.php',''),'.html','')=REPLACE(REPLACE(p.torneo,'.php',''),'.html','')
            LIMIT 1),p.torneo) AS torneo_nome,
          (SELECT t.id FROM tornei t
            WHERE t.nome=p.torneo OR t.filetorneo=p.torneo
               OR REPLACE(REPLACE(t.filetorneo,'.php',''),'.html','')=REPLACE(REPLACE(p.torneo,'.php',''),'.html','')
            LIMIT 1) AS torneo_id,
          p.fase,p.fase_round,p.giornata,p.squadra_casa,p.squadra_ospite,
          p.gol_casa,p.gol_ospite,sc.logo AS logo_casa,so.logo AS logo_ospite
   FROM partite p
   LEFT JOIN squadre sc ON sc.nome=p.squadra_casa AND sc.torneo=p.torneo
   LEFT JOIN squadre so ON so.nome=p.squadra_ospite AND so.torneo=p.torneo
   WHERE p.giocata=1
     AND NOT EXISTS (SELECT 1 FROM tornei tx
       WHERE (tx.nome=p.torneo OR tx.filetorneo=p.torneo
          OR REPLACE(REPLACE(tx.filetorneo,'.php',''),'.html','')=REPLACE(REPLACE(p.torneo,'.php',''),'.html',''))
         AND tx.stato='terminato' AND tx.data_fine<DATE_SUB(CURDATE(),INTERVAL 1 DAY))
   ORDER BY p.data_partita DESC,p.ora_partita DESC,p.id DESC"
);
if ($stmt && $stmt->execute()) {
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) $partite[] = $row;
  $stmt->close();
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Copertina partite</title>
  <style>
    :root{color-scheme:dark;font-family:Inter,Arial,sans-serif;--gold:#e8bd45;--muted:#aebdca}
    *{box-sizing:border-box}body{margin:0;background:#07111d;color:#fff}main{width:min(1500px,calc(100% - 28px));margin:28px auto 60px}a{color:#9bcaff}h1{margin:12px 0 6px}.intro{color:var(--muted)}
    .workspace{display:grid;grid-template-columns:minmax(320px,420px) minmax(0,1fr);gap:24px;align-items:start}.controls,.card{background:#101e2d;border:1px solid #ffffff12;border-radius:18px;box-shadow:0 18px 50px #0005}.controls{padding:18px;position:sticky;top:16px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:13px}.wide{grid-column:1/-1}label{display:grid;gap:6px;font-size:14px;font-weight:750}input,select,button{width:100%;border:1px solid #ffffff18;border-radius:10px;padding:11px 12px;font:inherit}input,select{background:#081522;color:#fff}input[type=file]{padding:8px;color:#bac8d5}input[type=range]{padding:4px 0;accent-color:var(--gold)}button{border:0;cursor:pointer;background:var(--gold);color:#101722;font-weight:850}.range-value{color:var(--muted);font-size:12px}.status{min-height:22px;margin-top:14px;color:#b9cad9}
    .previews{display:grid;grid-template-columns:minmax(280px,.75fr) minmax(360px,1.25fr);gap:22px}.card{padding:15px}.head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.head h2{margin:0;font-size:18px}.head button{width:auto;padding:9px 13px}canvas{display:block;width:100%;height:auto;margin:auto;background:#091522;box-shadow:0 12px 32px #0008}#reelCanvas{max-width:420px}#youtubeCanvas{max-width:720px}
    @media(max-width:1050px){.workspace{grid-template-columns:1fr}.controls{position:static}}@media(max-width:760px){.fields,.previews{grid-template-columns:1fr}.wide{grid-column:auto}}
  </style>
</head>
<body><main>
  <a href="/admin_dashboard.php">Torna alla dashboard</a>
  <h1>Copertina partite</h1>
  <p class="intro">Crea una copertina Reel e una miniatura YouTube dalla stessa partita e foto dei capitani.</p>
  <div class="workspace">
    <section class="controls"><div class="fields">
      <label class="wide">Torneo<select id="tournament"><option value="">Seleziona il torneo</option></select></label>
      <label>Fase<select id="phase" disabled><option value="">Seleziona la fase</option></select></label>
      <label>Giornata / turno<select id="round" disabled><option value="">Seleziona la giornata</option></select></label>
      <label class="wide">Partita<select id="match" disabled><option value="">Seleziona la partita</option></select></label>
      <label class="wide">Foto dei capitani<input id="photo" type="file" accept="image/png,image/jpeg,image/webp"></label>
      <label>Zoom <span id="zoomValue" class="range-value">100%</span><input id="zoom" type="range" min="100" max="250" value="100"></label>
      <label>Posizione orizzontale <span id="xValue" class="range-value">50%</span><input id="posX" type="range" min="0" max="100" value="50"></label>
      <label class="wide">Posizione verticale <span id="yValue" class="range-value">0%</span><input id="posY" type="range" min="0" max="100" value="0"></label>
    </div><div id="status" class="status" aria-live="polite"></div></section>
    <section class="previews">
      <article class="card"><div class="head"><h2>Copertina Reel</h2><button id="downloadReel" type="button">Scarica PNG</button></div><canvas id="reelCanvas" width="1080" height="1920"></canvas></article>
      <article class="card"><div class="head"><h2>Copertina YouTube</h2><button id="downloadYoutube" type="button">Scarica PNG</button></div><canvas id="youtubeCanvas" width="1280" height="720"></canvas></article>
    </section>
  </div>
<script>
const matches=<?= json_encode($partite,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const $=id=>document.getElementById(id);let selected=null,photo=null,brand=null,homeLogo=null,awayLogo=null;
const themes=[['#091522','#e5b93f','#102235'],['#1b1017','#df9e32','#301a28'],['#0b1915','#5fc49b','#122b24'],['#151126','#a98bd4','#241c3e'],['#1e130d','#e49a3a','#382117'],['#081820','#45b6d0','#102c38'],['#1d0f10','#d7645d','#35191b'],['#17190d','#a8bc49','#292d15']];
const upper=(v,f='')=>String(v||f).trim().toUpperCase();const safe=v=>String(v||'copertina').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();
function loadImage(src){return new Promise(resolve=>{if(!src)return resolve(null);const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>resolve(null);img.src=src})}function fileImage(file){return new Promise((resolve,reject)=>{if(!file)return resolve(null);const r=new FileReader();r.onload=async()=>resolve(await loadImage(r.result));r.onerror=reject;r.readAsDataURL(file)})}
function theme(){let hash=2166136261,key=String(selected?.torneo_id||selected?.torneo||'torneo');for(let i=0;i<key.length;i++){hash^=key.charCodeAt(i);hash=Math.imul(hash,16777619)}return themes[Math.abs(hash)%themes.length]}
function contain(ctx,img,x,y,w,h){if(!img?.naturalWidth)return;const s=Math.min(w/img.naturalWidth,h/img.naturalHeight),dw=img.naturalWidth*s,dh=img.naturalHeight*s;ctx.drawImage(img,x+(w-dw)/2,y+(h-dh)/2,dw,dh)}
function cover(ctx,img,x,y,w,h){if(!img?.naturalWidth)return;const zoom=Number($('zoom').value)/100,s=Math.max(w/img.naturalWidth,h/img.naturalHeight)*zoom,sw=w/s,sh=h/s,px=Number($('posX').value)/100,py=Number($('posY').value)/100;ctx.drawImage(img,(img.naturalWidth-sw)*px,(img.naturalHeight-sh)*py,sw,sh,x,y,w,h)}
function fit(ctx,text,max,start,min=18,weight=800,font='Arial'){let size=start;do{ctx.font=`${weight} ${size}px ${font}`;if(ctx.measureText(text).width<=max)break;size-=2}while(size>min)}
function base(ctx,w,h){const [bg,accent]=theme(),g=ctx.createLinearGradient(0,0,w,h);g.addColorStop(0,bg);g.addColorStop(1,'#040a11');ctx.fillStyle=g;ctx.fillRect(0,0,w,h);ctx.globalAlpha=.08;ctx.fillStyle='#fff';for(let x=-400;x<w+400;x+=135){ctx.save();ctx.translate(x,0);ctx.rotate(-.25);ctx.fillRect(0,0,3,h*1.3);ctx.restore()}ctx.globalAlpha=1;ctx.fillStyle=accent;ctx.fillRect(0,0,14,h)}
function placeholder(ctx,x,y,w,h){ctx.fillStyle='#15283a';ctx.fillRect(x,y,w,h);ctx.fillStyle='#aebdca';ctx.textAlign='center';ctx.font='700 24px Arial';ctx.fillText('CARICA LA FOTO DEI CAPITANI',x+w/2,y+h/2)}
function drawReel(){const c=$('reelCanvas'),ctx=c.getContext('2d'),[bg,accent,panel]=theme();base(ctx,c.width,c.height);contain(ctx,brand,55,55,120,120);ctx.fillStyle='#fff';ctx.textAlign='left';ctx.font='900 70px Arial';ctx.fillText('FULL TIME',205,115);ctx.fillStyle=accent;fit(ctx,upper(selected?.torneo_nome,'COPERTINA PARTITA'),780,34,22);ctx.fillText(upper(selected?.torneo_nome,'COPERTINA PARTITA'),205,165,780);ctx.save();ctx.beginPath();ctx.roundRect(55,220,970,1080,36);ctx.clip();photo?cover(ctx,photo,55,220,970,1080):placeholder(ctx,55,220,970,1080);const g=ctx.createLinearGradient(0,780,0,1300);g.addColorStop(0,'transparent');g.addColorStop(1,bg+'f5');ctx.fillStyle=g;ctx.fillRect(55,700,970,600);ctx.restore();ctx.fillStyle=panel;ctx.beginPath();ctx.roundRect(55,1230,970,510,32);ctx.fill();ctx.fillStyle=accent;ctx.fillRect(55,1230,970,8);contain(ctx,homeLogo||brand,105,1320,180,180);contain(ctx,awayLogo||brand,795,1320,180,180);ctx.fillStyle=accent;ctx.textAlign='center';ctx.font='900 150px Arial';ctx.fillText(`${selected?.gol_casa??0} - ${selected?.gol_ospite??0}`,540,1455);ctx.fillStyle='#fff';fit(ctx,upper(selected?.squadra_casa,'CASA'),280,38,20);ctx.fillText(upper(selected?.squadra_casa,'CASA'),195,1570,280);fit(ctx,upper(selected?.squadra_ospite,'OSPITE'),280,38,20);ctx.fillText(upper(selected?.squadra_ospite,'OSPITE'),885,1570,280);ctx.fillStyle='#aebdca';ctx.font='700 25px Arial';ctx.fillText('GUARDA GLI HIGHLIGHTS',540,1665);ctx.fillStyle=accent;ctx.fillRect(55,1825,970,3);ctx.fillStyle='#aebdca';ctx.textAlign='right';ctx.font='600 21px Arial';ctx.fillText('torneioldschool.it',1025,1870)}
function drawYoutube(){const c=$('youtubeCanvas'),ctx=c.getContext('2d'),[bg,accent,panel]=theme();base(ctx,c.width,c.height);ctx.save();ctx.beginPath();ctx.roundRect(34,34,735,652,28);ctx.clip();photo?cover(ctx,photo,34,34,735,652):placeholder(ctx,34,34,735,652);const g=ctx.createLinearGradient(360,0,769,0);g.addColorStop(0,'transparent');g.addColorStop(1,bg);ctx.fillStyle=g;ctx.fillRect(360,34,409,652);ctx.restore();ctx.fillStyle=panel;ctx.beginPath();ctx.roundRect(735,34,511,652,28);ctx.fill();contain(ctx,brand,790,70,85,85);ctx.fillStyle='#fff';ctx.textAlign='left';ctx.font='900 48px Arial';ctx.fillText('FULL TIME',900,120);ctx.fillStyle=accent;fit(ctx,upper(selected?.torneo_nome,'TORNEO'),400,24,16);ctx.fillText(upper(selected?.torneo_nome,'TORNEO'),790,175,400);contain(ctx,homeLogo||brand,790,225,110,110);contain(ctx,awayLogo||brand,1080,225,110,110);ctx.textAlign='center';ctx.fillStyle=accent;ctx.font='900 90px Arial';ctx.fillText(`${selected?.gol_casa??0} - ${selected?.gol_ospite??0}`,990,315);ctx.fillStyle='#fff';fit(ctx,upper(selected?.squadra_casa,'CASA'),190,27,16);ctx.fillText(upper(selected?.squadra_casa,'CASA'),845,385,190);fit(ctx,upper(selected?.squadra_ospite,'OSPITE'),190,27,16);ctx.fillText(upper(selected?.squadra_ospite,'OSPITE'),1135,385,190);ctx.fillStyle=accent;ctx.fillRect(790,455,400,4);ctx.fillStyle='#fff';ctx.font='800 30px Arial';ctx.fillText('HIGHLIGHTS',990,515);ctx.fillStyle='#aebdca';ctx.font='600 19px Arial';ctx.fillText('TORNEI OLD SCHOOL',990,565);ctx.fillText('torneioldschool.it',990,625)}
function draw(){[$('zoomValue').textContent,$('xValue').textContent,$('yValue').textContent]=[$('zoom').value+'%',$('posX').value+'%',$('posY').value+'%'];drawReel();drawYoutube()}
function unique(items,key){const m=new Map;items.forEach(i=>{const k=key(i);if(k&&!m.has(k))m.set(k,i)});return[...m.entries()]}function options(el,label,items){el.innerHTML='';el.append(new Option(label,''));items.forEach(([v,l])=>el.append(new Option(l,v)));el.disabled=!items.length}
function roundKey(m){return m.fase_round?`r:${m.fase_round}`:`g:${m.giornata??''}`}function roundLabel(m){return m.fase_round?String(m.fase_round).replaceAll('_',' '):(m.giornata?`GIORNATA ${m.giornata}`:'TURNO UNICO')}
function tournaments(){options($('tournament'),'Seleziona il torneo',unique(matches,m=>String(m.torneo)).map(([k,m])=>[k,m.torneo_nome||m.torneo]))}function chooseTournament(){const rows=matches.filter(m=>String(m.torneo)===$('tournament').value);options($('phase'),'Seleziona la fase',unique(rows,m=>String(m.fase)).map(([k])=>[k,k.replaceAll('_',' ')]));options($('round'),'Seleziona la giornata',[]);options($('match'),'Seleziona la partita',[]);selected=null;draw()}function choosePhase(){const rows=matches.filter(m=>String(m.torneo)===$('tournament').value&&String(m.fase)===$('phase').value);options($('round'),'Seleziona la giornata',unique(rows,roundKey).map(([k,m])=>[k,roundLabel(m)]));options($('match'),'Seleziona la partita',[]);selected=null;draw()}function chooseRound(){const rows=matches.filter(m=>String(m.torneo)===$('tournament').value&&String(m.fase)===$('phase').value&&roundKey(m)===$('round').value);options($('match'),'Seleziona la partita',rows.map(m=>[String(m.id),`${m.squadra_casa} ${m.gol_casa}-${m.gol_ospite} ${m.squadra_ospite}`]));selected=null;draw()}
async function chooseMatch(){selected=matches.find(m=>String(m.id)===$('match').value)||null;[homeLogo,awayLogo]=await Promise.all([loadImage(selected?.logo_casa),loadImage(selected?.logo_ospite)]);$('status').textContent=selected?'Partita e scudetti caricati. Ora inserisci la foto dei capitani.':'';draw()}
function download(id,name){const a=document.createElement('a');a.download=name;a.href=$(id).toDataURL('image/png');document.body.appendChild(a);a.click();a.remove()}
$('tournament').onchange=chooseTournament;$('phase').onchange=choosePhase;$('round').onchange=chooseRound;$('match').onchange=()=>chooseMatch().catch(()=>{$('status').textContent='Errore durante il caricamento della partita.'});$('photo').onchange=async()=>{photo=await fileImage($('photo').files?.[0]);draw()};['zoom','posX','posY'].forEach(id=>$(id).oninput=draw);$('downloadReel').onclick=()=>download('reelCanvas',`reel-${safe(selected?.squadra_casa)}-${safe(selected?.squadra_ospite)}.png`);$('downloadYoutube').onclick=()=>download('youtubeCanvas',`youtube-${safe(selected?.squadra_casa)}-${safe(selected?.squadra_ospite)}.png`);
(async()=>{brand=await loadImage('/img/logo_old_school.png');tournaments();draw()})();
</script></main></body></html>
