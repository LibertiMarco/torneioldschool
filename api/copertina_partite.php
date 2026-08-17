<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
require_once __DIR__ . '/../includi/db.php';
$embedded = isset($_GET['embed']) && $_GET['embed'] === '1';

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
          (SELECT t.img FROM tornei t
            WHERE t.nome=p.torneo OR t.filetorneo=p.torneo
               OR REPLACE(REPLACE(t.filetorneo,'.php',''),'.html','')=REPLACE(REPLACE(p.torneo,'.php',''),'.html','')
            LIMIT 1) AS torneo_img,
          (SELECT t.categoria FROM tornei t
            WHERE t.nome=p.torneo OR t.filetorneo=p.torneo
               OR REPLACE(REPLACE(t.filetorneo,'.php',''),'.html','')=REPLACE(REPLACE(p.torneo,'.php',''),'.html','')
            LIMIT 1) AS torneo_categoria,
          p.fase,p.fase_round,p.giornata,p.squadra_casa,p.squadra_ospite,
          p.gol_casa,p.gol_ospite,p.giocata,p.data_partita,
          sc.logo AS logo_casa,so.logo AS logo_ospite
   FROM partite p
   LEFT JOIN squadre sc ON sc.nome=p.squadra_casa AND sc.torneo=p.torneo
   LEFT JOIN squadre so ON so.nome=p.squadra_ospite AND so.torneo=p.torneo
   WHERE (p.giocata=1 OR (p.giocata=0 AND p.data_partita BETWEEN DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND CURDATE()))
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
  <?php if (!$embedded): ?><a href="/admin_dashboard.php">Torna alla dashboard</a><?php endif; ?>
  <h1><?= $embedded ? 'COPERTINE' : 'Copertina partite' ?></h1>
  <p class="intro">Crea una copertina Reel e una miniatura YouTube dalla stessa partita e foto dei capitani.</p>
  <div class="workspace">
    <section class="controls"><div class="fields">
      <label class="wide">Torneo<select id="tournament"><option value="">Seleziona il torneo</option></select></label>
      <label>Fase<select id="phase" disabled><option value="">Seleziona la fase</option></select></label>
      <label>Giornata / turno<select id="round" disabled><option value="">Seleziona la giornata</option></select></label>
      <label class="wide">Partita<select id="match" disabled><option value="">Seleziona la partita</option></select></label>
      <div id="scoreFields" class="wide fields" hidden>
        <label>Gol casa<input id="scoreHome" type="number" min="0" value="0"></label>
        <label>Gol ospite<input id="scoreAway" type="number" min="0" value="0"></label>
        <div id="scoreNote" class="wide range-value"></div>
      </div>
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
const $=id=>document.getElementById(id);let selected=null,photo=null,brand=null,homeLogo=null,awayLogo=null,tournamentLogo=null;
const coverColors=['#00bf63','#1769e0','#6c39c6','#e43b35','#e77722','#009c9a','#153b8f','#a51e49'];
const upper=(v,f='')=>String(v||f).trim().toUpperCase();const safe=v=>String(v||'copertina').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();
function loadImage(src){return new Promise(resolve=>{if(!src)return resolve(null);const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>resolve(null);img.src=src})}function fileImage(file){return new Promise((resolve,reject)=>{if(!file)return resolve(null);const r=new FileReader();r.onload=async()=>resolve(await loadImage(r.result));r.onerror=reject;r.readAsDataURL(file)})}
function theme(){const name=String(selected?.torneo_nome||'').toLowerCase();if(/saudi|arabia/.test(name))return'#00bf63';let hash=2166136261,key=String(selected?.torneo_id||selected?.torneo||'torneo');for(let i=0;i<key.length;i++){hash^=key.charCodeAt(i);hash=Math.imul(hash,16777619)}return coverColors[Math.abs(hash)%coverColors.length]}
function contain(ctx,img,x,y,w,h){if(!img?.naturalWidth)return;const s=Math.min(w/img.naturalWidth,h/img.naturalHeight),dw=img.naturalWidth*s,dh=img.naturalHeight*s;ctx.drawImage(img,x+(w-dw)/2,y+(h-dh)/2,dw,dh)}
function cover(ctx,img,x,y,w,h){if(!img?.naturalWidth)return;const zoom=Number($('zoom').value)/100,s=Math.max(w/img.naturalWidth,h/img.naturalHeight)*zoom,sw=w/s,sh=h/s,px=Number($('posX').value)/100,py=Number($('posY').value)/100;ctx.drawImage(img,(img.naturalWidth-sw)*px,(img.naturalHeight-sh)*py,sw,sh,x,y,w,h)}
function fit(ctx,text,max,start,min=18,weight=800,font='Arial'){let size=start;do{ctx.font=`${weight} ${size}px ${font}`;if(ctx.measureText(text).width<=max)break;size-=2}while(size>min)}
function score(side){const input=side==='home'?$('scoreHome'):$('scoreAway');return Math.max(0,Number.parseInt(input.value||'0',10)||0)}
function tournamentImageBox(ctx,x,y,size){contain(ctx,tournamentLogo||brand,x,y,size,size)}
function base(ctx,w,h){ctx.fillStyle=theme();ctx.fillRect(0,0,w,h)}
function placeholder(ctx,x,y,w,h){ctx.fillStyle='#15283a';ctx.fillRect(x,y,w,h);ctx.fillStyle='#aebdca';ctx.textAlign='center';ctx.font='700 24px Arial';ctx.fillText('CARICA LA FOTO DEI CAPITANI',x+w/2,y+h/2)}
function drawReel(){const c=$('reelCanvas'),ctx=c.getContext('2d');base(ctx,c.width,c.height);ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 108px Georgia';ctx.fillText('HIGHLIGHTS',540,185);contain(ctx,homeLogo||brand,55,245,245,280);contain(ctx,awayLogo||brand,780,245,245,280);ctx.font='400 174px Arial';ctx.strokeStyle='#fff';ctx.lineWidth=5;ctx.strokeText(`${score('home')} - ${score('away')}`,540,455);ctx.save();ctx.beginPath();ctx.roundRect(38,620,1004,845,48);ctx.clip();photo?cover(ctx,photo,38,620,1004,845):placeholder(ctx,38,620,1004,845);ctx.restore();ctx.strokeStyle='#fff';ctx.lineWidth=7;ctx.beginPath();ctx.roundRect(38,620,1004,845,48);ctx.stroke();ctx.fillStyle='#fff';ctx.beginPath();ctx.arc(310,1605,98,0,Math.PI*2);ctx.fill();contain(ctx,brand,222,1517,176,176);tournamentImageBox(ctx,675,1510,195);ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 82px Arial';fit(ctx,upper(selected?.torneo_categoria,'CALCIO'),900,82,44,900);ctx.fillText(upper(selected?.torneo_categoria,'CALCIO'),540,1850,900)}
function drawYoutube(){const c=$('youtubeCanvas'),ctx=c.getContext('2d');base(ctx,c.width,c.height);ctx.save();ctx.beginPath();ctx.moveTo(835,0);ctx.lineTo(1280,0);ctx.lineTo(1280,720);ctx.lineTo(590,720);ctx.closePath();ctx.clip();photo?cover(ctx,photo,565,0,715,720):placeholder(ctx,565,0,715,720);ctx.restore();ctx.fillStyle='#fff';ctx.textAlign='left';ctx.font='900 72px Georgia';ctx.fillText('HIGHLIGHTS',55,95);contain(ctx,homeLogo||brand,45,175,185,185);contain(ctx,awayLogo||brand,45,385,185,185);ctx.font='400 170px Arial';ctx.strokeStyle='#fff';ctx.lineWidth=5;ctx.strokeText(String(score('home')),275,335);ctx.strokeText(String(score('away')),275,555);tournamentImageBox(ctx,1145,10,125);ctx.fillStyle='#fff';ctx.beginPath();ctx.arc(690,370,75,0,Math.PI*2);ctx.fill();contain(ctx,brand,624,304,132,132);ctx.fillStyle='#fff';ctx.textAlign='left';ctx.font='900 48px Arial';fit(ctx,upper(selected?.torneo_categoria,'CALCIO'),500,48,28,900);ctx.fillText(upper(selected?.torneo_categoria,'CALCIO'),70,675,500)}
function draw(){[$('zoomValue').textContent,$('xValue').textContent,$('yValue').textContent]=[$('zoom').value+'%',$('posX').value+'%',$('posY').value+'%'];drawReel();drawYoutube()}
function unique(items,key){const m=new Map;items.forEach(i=>{const k=key(i);if(k&&!m.has(k))m.set(k,i)});return[...m.entries()]}function options(el,label,items){el.innerHTML='';el.append(new Option(label,''));items.forEach(([v,l])=>el.append(new Option(l,v)));el.disabled=!items.length}
function roundKey(m){return m.fase_round?`r:${m.fase_round}`:`g:${m.giornata??''}`}function roundLabel(m){return m.fase_round?String(m.fase_round).replaceAll('_',' '):(m.giornata?`GIORNATA ${m.giornata}`:'TURNO UNICO')}
function clearSelected(){selected=null;$('scoreFields').hidden=true;homeLogo=null;awayLogo=null;tournamentLogo=null;draw()}
function tournaments(){options($('tournament'),'Seleziona il torneo',unique(matches,m=>String(m.torneo)).map(([k,m])=>[k,m.torneo_nome||m.torneo]))}function chooseTournament(){const rows=matches.filter(m=>String(m.torneo)===$('tournament').value);options($('phase'),'Seleziona la fase',unique(rows,m=>String(m.fase)).map(([k])=>[k,k.replaceAll('_',' ')]));options($('round'),'Seleziona la giornata',[]);options($('match'),'Seleziona la partita',[]);clearSelected()}function choosePhase(){const rows=matches.filter(m=>String(m.torneo)===$('tournament').value&&String(m.fase)===$('phase').value);options($('round'),'Seleziona la giornata',unique(rows,roundKey).map(([k,m])=>[k,roundLabel(m)]));options($('match'),'Seleziona la partita',[]);clearSelected()}function chooseRound(){const rows=matches.filter(m=>String(m.torneo)===$('tournament').value&&String(m.fase)===$('phase').value&&roundKey(m)===$('round').value);options($('match'),'Seleziona la partita',rows.map(m=>[String(m.id),`${m.squadra_casa} ${m.gol_casa??0}-${m.gol_ospite??0} ${m.squadra_ospite}${Number(m.giocata)===1?'':' (non terminata)'}`]));clearSelected()}
async function chooseMatch(){selected=matches.find(m=>String(m.id)===$('match').value)||null;[homeLogo,awayLogo,tournamentLogo]=await Promise.all([loadImage(selected?.logo_casa),loadImage(selected?.logo_ospite),loadImage(selected?.torneo_img)]);$('scoreFields').hidden=!selected;if(selected){const finished=Number(selected.giocata)===1;$('scoreHome').value=selected.gol_casa??0;$('scoreAway').value=selected.gol_ospite??0;$('scoreHome').readOnly=finished;$('scoreAway').readOnly=finished;$('scoreNote').textContent=finished?'Partita terminata: il risultato è bloccato.':'Partita non terminata: puoi modificare i gol solamente per questa copertina.'}$('status').textContent=selected?'Partita, scudetti e torneo caricati. Ora inserisci la foto dei capitani.':'';draw()}
function download(id,name){const a=document.createElement('a');a.download=name;a.href=$(id).toDataURL('image/png');document.body.appendChild(a);a.click();a.remove()}
$('tournament').onchange=chooseTournament;$('phase').onchange=choosePhase;$('round').onchange=chooseRound;$('match').onchange=()=>chooseMatch().catch(()=>{$('status').textContent='Errore durante il caricamento della partita.'});$('photo').onchange=async()=>{photo=await fileImage($('photo').files?.[0]);draw()};['zoom','posX','posY','scoreHome','scoreAway'].forEach(id=>$(id).oninput=draw);$('downloadReel').onclick=()=>download('reelCanvas',`reel-${safe(selected?.squadra_casa)}-${safe(selected?.squadra_ospite)}.png`);$('downloadYoutube').onclick=()=>download('youtubeCanvas',`youtube-${safe(selected?.squadra_casa)}-${safe(selected?.squadra_ospite)}.png`);
(async()=>{brand=await loadImage('/img/logo_old_school.png');tournaments();draw()})();
</script></main></body></html>
