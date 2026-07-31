<?php
require_once __DIR__ . '/../includi/admin_guard.php';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Grafiche partite settimanali</title>
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
  </style>
</head>
<body><main>
  <a href="/admin_dashboard.php">Torna alla dashboard</a>
  <h1>Grafiche partite settimanali</h1>
  <p>Viene generato un unico PNG per torneo, contenente tutte le partite programmate con data, ora e luogo.</p>
  <div class="toolbar">
    <label>Data di riferimento <input id="date" type="date"></label>
    <button id="generate" type="button">Genera grafiche</button>
    <button id="downloadAll" type="button" hidden>Scarica tutte</button>
  </div>
  <div class="status" id="status"></div><div class="grid" id="grid"></div>
</main>
<script>
const dateInput = document.getElementById('date');
dateInput.value = new Date().toISOString().slice(0, 10);
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
  const width = 1080, height = 1920, headerH = 260, footerH = 70;
  const sectionH = matchCount > 12 ? 38 : 48;
  const availableRowsH = height-headerH-footerH-(sections.length*sectionH);
  const rowH = Math.min(145, Math.floor(availableRowsH/Math.max(1,matchCount)));
  const compact = rowH < 105;
  const theme = tournamentTheme(tournament);
  const canvas = document.createElement('canvas'); canvas.width=width; canvas.height=height;
  const ctx = canvas.getContext('2d');
  const oldSchoolLogo = await loadImage('/img/logo_old_school.png');
  ctx.fillStyle=theme.bg; ctx.fillRect(0,0,width,height);
  ctx.fillStyle=theme.accent; ctx.fillRect(0,0,14,height); ctx.fillRect(42,226,width-84,4);
  drawContainedImage(ctx,oldSchoolLogo,48,38,132,132);
  ctx.textAlign='left'; ctx.fillStyle='#fff'; ctx.font='900 58px Arial'; ctx.fillText('MATCHDAY',220,86);
  ctx.fillStyle=theme.accent; ctx.font='800 38px Arial'; ctx.fillText(tournament.nome.toUpperCase(),220,140,650);
  ctx.fillStyle=theme.muted; ctx.font='600 23px Arial'; ctx.fillText(`${shortDate(week.dal)} — ${shortDate(week.al)}`,220,184);
  ctx.textAlign='right'; ctx.fillStyle=theme.muted; ctx.font='700 18px Arial'; ctx.fillText('TORNEI OLD SCHOOL',1038,66);
  ctx.font='500 18px Arial'; ctx.fillText(`${matchCount} ${matchCount===1?'PARTITA':'PARTITE'} IN PROGRAMMA`,1038,98);
  let y=headerH;
  for (const section of sections) {
    ctx.fillStyle=theme.accent; ctx.fillRect(42,y+sectionH-7,28,3);
    ctx.textAlign='left'; ctx.fillStyle='#fff'; ctx.font=`800 ${compact?18:21}px Arial`; ctx.fillText((section.nome || 'Partite').toUpperCase(),82,y+sectionH-2); y+=sectionH;
    let matchIndex=0;
    for (const match of section.partite || []) {
      ctx.fillStyle=matchIndex%2===0?theme.panel:theme.alternate; ctx.fillRect(42,y+3,width-84,rowH-6);
      ctx.fillStyle=theme.accent; ctx.fillRect(42,y+3,5,rowH-6);
      const [homeLogo,awayLogo]=await Promise.all([loadImage(match.squadra_casa.logo_url_assoluto||match.squadra_casa.logo),loadImage(match.squadra_ospite.logo_url_assoluto||match.squadra_ospite.logo)]);
      const logoSize=Math.max(42,Math.min(72,rowH-34));
      const logoY=y+(rowH-logoSize)/2-8;
      const teamY=logoY+(logoSize/2);
      drawContainedImage(ctx,homeLogo,67,logoY,logoSize,logoSize);
      drawContainedImage(ctx,awayLogo,width-67-logoSize,logoY,logoSize,logoSize);
      ctx.textBaseline='middle';
      ctx.fillStyle='#fff'; ctx.font=`700 ${compact?20:25}px Arial`; ctx.textAlign='left'; ctx.fillText(match.squadra_casa.nome,155,teamY,265);
      ctx.textAlign='right'; ctx.fillText(match.squadra_ospite.nome,width-155,teamY,265);
      ctx.textAlign='center'; ctx.fillStyle=theme.accent; ctx.font=`900 ${compact?19:24}px Arial`; ctx.fillText('VS',width/2,teamY);
      ctx.textBaseline='alphabetic';
      ctx.fillStyle=theme.muted; ctx.font=`600 ${compact?16:20}px Arial`;
      ctx.fillText(`${shortDate(match.data)}  •  ${match.ora || 'Ora da definire'}  •  ${match.campo || 'Luogo da definire'}`,width/2,y+rowH-(compact?15:28),760);
      y+=rowH; matchIndex++;
    }
  }
  ctx.fillStyle=theme.accent; ctx.fillRect(42,height-53,width-84,2);
  ctx.textAlign='left'; ctx.fillStyle=theme.muted; ctx.font='500 17px Arial'; ctx.fillText('CALENDARIO SETTIMANALE',42,height-25);
  ctx.textAlign='right'; ctx.fillText('torneioldschool.it',1038,height-25);
  return canvas;
}

function download(item) { const a=document.createElement('a'); a.download=item.name; a.href=item.canvas.toDataURL('image/png'); a.click(); }
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
      const btn=document.createElement('button'); btn.textContent='Scarica PNG'; btn.onclick=()=>download(item); head.appendChild(btn); card.append(head,canvas); grid.appendChild(card);
    }
    statusEl.textContent=`Create ${generated.length} immagini: una per ciascun torneo.`; downloadAll.hidden=generated.length<2;
  } catch(error) { statusEl.textContent=error.message; }
}
document.getElementById('generate').onclick=generate;
downloadAll.onclick=()=>generated.forEach((item,index)=>setTimeout(()=>download(item),index*250));
</script></body></html>
