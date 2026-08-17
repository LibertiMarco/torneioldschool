<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
$embedded = isset($_GET['embed']) && $_GET['embed'] === '1';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Grafiche partite giornaliere</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
  <style>
    :root { color-scheme: dark; font-family: Arial, sans-serif; }
    body { margin: 0; background: #08111f; color: #fff; }
    main { width: min(1180px, calc(100% - 32px)); margin: 32px auto 60px; }
    a { color: #8fc7ff; } h1 { margin-bottom: 8px; }
    .toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:end; margin:24px 0; padding:18px; background:#111e31; border-radius:14px; }
    label { display:grid; gap:6px; font-weight:700; }
    input,button { border:0; border-radius:9px; padding:11px 14px; font:inherit; }
    button { cursor:pointer; background:#f2c94c; color:#101722; font-weight:800; }
    .status { min-height:24px; color:#bfd0e5; }
    .grid { display:grid; gap:28px; }
    .card { padding:18px; background:#111e31; border-radius:16px; overflow:auto; }
    .card-head { display:flex; justify-content:space-between; align-items:center; gap:14px; margin-bottom:14px; }
    canvas { display:block; width:min(100%,540px); height:auto; margin:auto; background:#0d1b2d; box-shadow:0 10px 35px #0008; }
    body.with-site-header>main{margin-top:110px}
  </style>
</head>
<body class="<?= $embedded ? 'is-embedded' : 'with-site-header' ?>">
<?php if (!$embedded): ?><?php include __DIR__ . '/../includi/header.php'; ?><?php endif; ?>
<main>
  <?php if (!$embedded): ?><a href="/admin_dashboard.php">Torna alla dashboard</a><?php endif; ?>
  <h1><?= $embedded ? 'MATCHDAY' : 'Grafiche partite giornaliere' ?></h1>
  <p>Il giorno selezionato individua i tornei da pubblicare; ogni PNG contiene tutte le loro partite della settimana, incluse quelle gia giocate.</p>
  <div class="toolbar">
    <label>Giorno delle partite <input id="date" type="date"></label>
    <button id="generate" type="button">Genera grafiche</button>
    <button id="downloadAll" type="button" hidden>Scarica tutte</button>
  </div>
  <div class="status" id="status"></div><div class="grid" id="grid"></div>
</main>
<?php if (!$embedded): ?><div id="footer-container"></div><?php endif; ?>
<script>
const dateInput = document.getElementById('date');
const today = new Date();
dateInput.value = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
const grid = document.getElementById('grid');
const statusEl = document.getElementById('status');
const downloadAll = document.getElementById('downloadAll');
let generated = [];

const loadImage = src => new Promise(resolve => {
  if (!src) return resolve(null);
  const img = new Image(); img.crossOrigin = 'anonymous';
  img.onload = () => resolve(img); img.onerror = () => resolve(null); img.src = src;
});
const rounded = (ctx,x,y,w,h,r=16) => { ctx.beginPath(); ctx.roundRect(x,y,w,h,r); ctx.fill(); };
const drawContainedImage = (ctx,img,x,y,maxWidth,maxHeight) => {
  if (!img?.naturalWidth || !img?.naturalHeight) return;
  const scale=Math.min(maxWidth/img.naturalWidth,maxHeight/img.naturalHeight);
  const width=img.naturalWidth*scale, height=img.naturalHeight*scale;
  ctx.drawImage(img,x+(maxWidth-width)/2,y+(maxHeight-height)/2,width,height);
};
const shortDate = value => new Intl.DateTimeFormat('it-IT',{weekday:'short',day:'2-digit',month:'2-digit'}).format(new Date(value+'T12:00:00'));
const safeName = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();
const tournamentThemes = [
  {bg:'#091522',accent:'#e5b93f',panel:'#102235',alternate:'#0d1d2d',muted:'#aebdca'},
  {bg:'#1b1017',accent:'#df9e32',panel:'#301a28',alternate:'#271620',muted:'#ccb9c4'},
  {bg:'#0b1915',accent:'#5fc49b',panel:'#122b24',alternate:'#10241f',muted:'#abc8bd'},
  {bg:'#151126',accent:'#a98bd4',panel:'#241c3e',alternate:'#1d1833',muted:'#c1b7d0'},
  {bg:'#1e130d',accent:'#e49a3a',panel:'#382117',alternate:'#2d1b13',muted:'#d0bcaf'},
  {bg:'#081820',accent:'#45b6d0',panel:'#102c38',alternate:'#0d252f',muted:'#abc6ce'},
  {bg:'#1d0f10',accent:'#d7645d',panel:'#35191b',alternate:'#2b1517',muted:'#ceb2b3'},
  {bg:'#17190d',accent:'#a8bc49',panel:'#292d15',alternate:'#222512',muted:'#c4c9ab'}
];
const tournamentTheme = tournament => {
  const key=String(tournament.id ?? tournament.nome ?? 'torneo');
  let hash=2166136261;
  for(let i=0;i<key.length;i++){ hash^=key.charCodeAt(i); hash=Math.imul(hash,16777619); }
  const seed=Math.abs(hash);
  return {...tournamentThemes[seed % tournamentThemes.length],seed};
};

async function drawTournament(tournament, week) {
  const graphic = tournament.grafiche[0];
  const sections = graphic.sezioni || [];
  const matchCount = sections.reduce((n,s)=>n+(s.partite||[]).length,0);
  const tournamentDates = sections
    .flatMap(section => section.partite || [])
    .map(match => match.data)
    .filter(Boolean)
    .sort();
  const firstMatchDate = tournamentDates[0] || week.dal;
  const lastMatchDate = tournamentDates[tournamentDates.length-1] || week.al;
  const tournamentDateLabel = firstMatchDate === lastMatchDate
    ? shortDate(firstMatchDate)
    : `${shortDate(firstMatchDate)} — ${shortDate(lastMatchDate)}`;
  const width = 1080, height = 1920, headerH = 410, footerH = 140;
  const sectionH = matchCount===1 ? 92 : (matchCount > 12 ? 38 : 48);
  const availableRowsH = height-headerH-footerH-(sections.length*sectionH);
  const rowH = Math.floor(availableRowsH/Math.max(1,matchCount));
  const compact = rowH < 105;
  const theme = tournamentTheme(tournament);
  const canvas = document.createElement('canvas'); canvas.width=width; canvas.height=height;
  const ctx = canvas.getContext('2d');
  const oldSchoolLogo = await loadImage('/img/logo_old_school.png');
  ctx.fillStyle=theme.bg; ctx.fillRect(0,0,width,height);
  ctx.fillStyle=theme.accent; ctx.fillRect(0,0,14,height); ctx.fillRect(42,375,width-84,4);
  drawContainedImage(ctx,oldSchoolLogo,48,205,132,132);
  ctx.textAlign='left'; ctx.fillStyle='#fff'; ctx.font='900 58px Arial'; ctx.fillText('MATCHDAY',220,250);
  ctx.fillStyle=theme.accent; ctx.font='800 38px Arial'; ctx.fillText(tournament.nome.toUpperCase(),220,304,650);
  ctx.fillStyle=theme.muted; ctx.font='600 23px Arial'; ctx.fillText(tournamentDateLabel,220,348);
  ctx.textAlign='right'; ctx.fillStyle=theme.muted; ctx.font='700 18px Arial'; ctx.fillText('TORNEI OLD SCHOOL',1038,230);
  ctx.font='500 18px Arial'; ctx.fillText(`${matchCount} ${matchCount===1?'PARTITA':'PARTITE'} NEL PERIODO`,1038,262);
  let y=headerH;
  for (const section of sections) {
    if(matchCount===1) {
      ctx.textAlign='center'; ctx.fillStyle=theme.accent; ctx.font='900 46px Arial';
      ctx.fillText((section.nome || 'Partita').toUpperCase(),width/2,y+57,900);
      ctx.fillRect(width/2-70,y+76,140,4);
    } else {
      ctx.fillStyle=theme.accent; ctx.fillRect(42,y+sectionH-7,28,3);
      ctx.textAlign='left'; ctx.fillStyle='#fff'; ctx.font=`800 ${compact?21:26}px Arial`; ctx.fillText((section.nome || 'Partite').toUpperCase(),82,y+sectionH-2);
    }
    y+=sectionH;
    let matchIndex=0;
    for (const match of section.partite || []) {
      const result=match.risultato;
      const centerLabel=result ? `${result.gol_casa} — ${result.gol_ospite}` : 'VS';
      const penaltiesLabel=result?.decisa_rigori && result.rigori_casa!==null && result.rigori_ospite!==null
        ? `  •  d.c.r. ${result.rigori_casa}–${result.rigori_ospite}` : '';
      ctx.fillStyle=matchIndex%2===0?theme.panel:theme.alternate; ctx.fillRect(42,y+3,width-84,rowH-6);
      ctx.fillStyle=theme.accent; ctx.fillRect(42,y+3,5,rowH-6);
      const [homeLogo,awayLogo]=await Promise.all([loadImage(match.squadra_casa.logo_url_assoluto||match.squadra_casa.logo),loadImage(match.squadra_ospite.logo_url_assoluto||match.squadra_ospite.logo)]);
      if(matchCount===1) {
        const heroY=y+(rowH*.43), heroLogoSize=220;
        const homeLogoX=72, awayLogoX=width-72-heroLogoSize;
        drawContainedImage(ctx,homeLogo,homeLogoX,heroY-(heroLogoSize/2),heroLogoSize,heroLogoSize);
        drawContainedImage(ctx,awayLogo,awayLogoX,heroY-(heroLogoSize/2),heroLogoSize,heroLogoSize);
        ctx.textBaseline='middle'; ctx.fillStyle='#fff'; ctx.font='800 38px Arial';
        ctx.textAlign='left'; ctx.fillText(match.squadra_casa.nome,322,heroY,155);
        ctx.textAlign='right'; ctx.fillText(match.squadra_ospite.nome,width-322,heroY,155);
        ctx.textAlign='center'; ctx.fillStyle=theme.accent; ctx.font=`900 ${result?54:48}px Arial`; ctx.fillText(centerLabel,width/2,heroY);
        ctx.textBaseline='alphabetic';
        ctx.fillStyle=theme.accent; ctx.fillRect(180,heroY+165,width-360,3);
        ctx.fillStyle='#fff'; ctx.font='800 31px Arial';
        ctx.fillText(`${shortDate(match.data)}  •  ${match.ora || 'Ora da definire'}${penaltiesLabel}`,width/2,heroY+225);
        ctx.fillStyle=theme.muted; ctx.font='700 28px Arial'; ctx.fillText(match.campo || 'Luogo da definire',width/2,heroY+275,760);
        if(match.risultato_andata) {
          const firstLeg=match.risultato_andata;
          ctx.fillStyle=theme.accent; ctx.font='700 24px Arial';
          ctx.fillText(`ANDATA: ${firstLeg.squadra_casa} ${firstLeg.gol_casa}–${firstLeg.gol_ospite} ${firstLeg.squadra_ospite}`,width/2,heroY+335,760);
        }
        y+=rowH; matchIndex++;
        continue;
      }
      const visualScale=Math.max(.82,Math.min(1.55,rowH/140));
      const logoSize=Math.max(42,Math.min(130,rowH*.36));
      const teamFont=Math.round(Math.max(20,Math.min(36,25*visualScale)));
      const vsFont=Math.round(Math.max(19,Math.min(34,24*visualScale)));
      const metaFont=Math.round(Math.max(16,Math.min(25,20*visualScale)));
      const logoY=y+(rowH-logoSize)/2-8;
      const teamY=logoY+(logoSize/2);
      const homeLogoX=67, awayLogoX=width-67-logoSize;
      const homeTextX=homeLogoX+logoSize+24, awayTextX=awayLogoX-24;
      const teamTextWidth=Math.max(175,420-homeTextX);
      drawContainedImage(ctx,homeLogo,homeLogoX,logoY,logoSize,logoSize);
      drawContainedImage(ctx,awayLogo,awayLogoX,logoY,logoSize,logoSize);
      ctx.textBaseline='middle';
      ctx.fillStyle='#fff'; ctx.font=`700 ${teamFont}px Arial`; ctx.textAlign='left'; ctx.fillText(match.squadra_casa.nome,homeTextX,teamY,teamTextWidth);
      ctx.textAlign='right'; ctx.fillText(match.squadra_ospite.nome,awayTextX,teamY,teamTextWidth);
      ctx.textAlign='center'; ctx.fillStyle=theme.accent; ctx.font=`900 ${result?Math.min(38,vsFont+4):vsFont}px Arial`; ctx.fillText(centerLabel,width/2,teamY);
      ctx.textBaseline='alphabetic';
      ctx.fillStyle=theme.muted; ctx.font=`600 ${metaFont}px Arial`;
      const metaY=teamY+(compact?31:Math.min(72,Math.max(40,rowH*.16)));
      const matchMeta=`${shortDate(match.data)}  •  ${match.ora || 'Ora da definire'}  •  ${match.campo || 'Luogo da definire'}${penaltiesLabel}`;
      const firstLeg=match.risultato_andata;
      if(firstLeg && rowH>=150) {
        const firstLegLabel=`ANDATA: ${firstLeg.squadra_casa} ${firstLeg.gol_casa}–${firstLeg.gol_ospite} ${firstLeg.squadra_ospite}`;
        ctx.fillStyle=theme.accent; ctx.font=`700 ${Math.max(17,metaFont-1)}px Arial`; ctx.fillText(firstLegLabel,width/2,teamY+40,760);
        ctx.fillStyle=theme.muted; ctx.font=`600 ${metaFont}px Arial`; ctx.fillText(matchMeta,width/2,teamY+72,760);
      } else if(firstLeg) {
        ctx.fillText(`ANDATA ${firstLeg.gol_casa}–${firstLeg.gol_ospite}  •  ${matchMeta}`,width/2,metaY,800);
      } else {
        ctx.fillText(matchMeta,width/2,metaY,760);
      }
      y+=rowH; matchIndex++;
    }
  }
  ctx.fillStyle=theme.accent; ctx.fillRect(42,height-53,width-84,2);
  ctx.textAlign='left'; ctx.fillStyle=theme.muted; ctx.font='500 17px Arial'; ctx.fillText('PARTITE DEL GIORNO',42,height-25);
  ctx.textAlign='right'; ctx.fillText('torneioldschool.it',1038,height-25);
  return canvas;
}

const isIPhoneSafari = /iP(hone|ad|od)/.test(navigator.userAgent) && /Safari/.test(navigator.userAgent) && !/CriOS|FxiOS|EdgiOS/.test(navigator.userAgent);
const canvasToBlob = canvas => new Promise((resolve,reject) => canvas.toBlob(
  blob => blob ? resolve(blob) : reject(new Error('Impossibile creare il file PNG.')),
  'image/png'
));
async function saveImage(item) {
  let fallbackWindow=null;
  if(isIPhoneSafari && !navigator.share) fallbackWindow=window.open('about:blank','_blank');
  try {
    const blob=await canvasToBlob(item.canvas);
    const file=new File([blob],item.name,{type:'image/png'});
    if(navigator.share && (!navigator.canShare || navigator.canShare({files:[file]}))) {
      await navigator.share({files:[file],title:item.name});
      return;
    }
    const url=URL.createObjectURL(blob);
    if(fallbackWindow) {
      fallbackWindow.location.href=url;
      setTimeout(()=>URL.revokeObjectURL(url),60000);
      return;
    }
    const a=document.createElement('a'); a.download=item.name; a.href=url; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url),2000);
  } catch(error) {
    if(fallbackWindow) fallbackWindow.close();
    if(error?.name!=='AbortError') throw error;
  }
}
async function saveAllImages() {
  if(isIPhoneSafari) {
    const files=await Promise.all(generated.map(async item => new File(
      [await canvasToBlob(item.canvas)],item.name,{type:'image/png'}
    )));
    if(navigator.share && (!navigator.canShare || navigator.canShare({files}))) {
      await navigator.share({files,title:'Grafiche Tornei Old School'});
      return;
    }
    throw new Error('Questa versione di iOS non supporta il salvataggio multiplo. Usa “Salva immagine” su ogni grafica.');
  }
  generated.forEach((item,index)=>setTimeout(
    ()=>saveImage(item).catch(error=>{statusEl.textContent=error.message}),index*350
  ));
}
async function generate() {
  statusEl.textContent='Recupero partite e generazione immagini…'; grid.innerHTML=''; generated=[]; downloadAll.hidden=true;
  try {
    const res=await fetch(`/api/get_grafiche_settimana.php?data=${encodeURIComponent(dateInput.value)}`);
    const data=await res.json(); if(!res.ok||!data.success) throw new Error(data.error||'Errore durante il recupero');
    for(const tournament of data.tornei) {
      const canvas=await drawTournament(tournament,data.settimana);
      const item={canvas,name:`matchday-${safeName(tournament.nome)}-${data.settimana.dal}.png`}; generated.push(item);
      const card=document.createElement('section'); card.className='card';
      const head=document.createElement('div'); head.className='card-head'; head.innerHTML=`<h2>${tournament.nome}</h2>`;
      const btn=document.createElement('button'); btn.textContent=isIPhoneSafari?'Salva immagine':'Scarica PNG';
      btn.onclick=async()=>{try{await saveImage(item)}catch(error){statusEl.textContent=error.message}};
      head.appendChild(btn); card.append(head,canvas); grid.appendChild(card);
    }
    statusEl.textContent=isIPhoneSafari
      ? `Create ${generated.length} immagini. Tocca “Salva immagine” e scegli “Salva immagine” nel pannello iOS.`
      : `Create ${generated.length} immagini: una per ciascun torneo.`;
    downloadAll.textContent=isIPhoneSafari?'Salva tutte':'Scarica tutte';
    downloadAll.hidden=generated.length<2;
  } catch(error) { statusEl.textContent=error.message; }
}
document.getElementById('generate').onclick=generate;
downloadAll.onclick=async()=>{try{await saveAllImages()}catch(error){if(error?.name!=='AbortError')statusEl.textContent=error.message}};
<?php if (!$embedded): ?>fetch('/includi/footer.html').then(response=>response.text()).then(html=>{document.getElementById('footer-container').innerHTML=html;}).catch(()=>{});<?php endif; ?>
</script></body></html>
