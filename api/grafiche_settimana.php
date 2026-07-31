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
const shortDate = value => new Intl.DateTimeFormat('it-IT',{weekday:'short',day:'2-digit',month:'2-digit'}).format(new Date(value+'T12:00:00'));
const safeName = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();

async function drawTournament(tournament, week) {
  const graphic = tournament.grafiche[0];
  const sections = graphic.sezioni || [];
  const matchCount = sections.reduce((n,s)=>n+(s.partite||[]).length,0);
  const width = 1080, headerH = 255, sectionH = 58, rowH = 138, footerH = 80;
  const height = headerH + sections.length*sectionH + matchCount*rowH + footerH;
  const canvas = document.createElement('canvas'); canvas.width=width; canvas.height=height;
  const ctx = canvas.getContext('2d');
  const bg = ctx.createLinearGradient(0,0,width,height); bg.addColorStop(0,'#071426'); bg.addColorStop(1,'#173456');
  ctx.fillStyle=bg; ctx.fillRect(0,0,width,height);
  ctx.fillStyle='#f2c94c'; ctx.fillRect(0,0,18,height);
  ctx.textAlign='center'; ctx.fillStyle='#f2c94c'; ctx.font='900 62px Arial'; ctx.fillText('MATCHDAY',width/2,82);
  ctx.fillStyle='#fff'; ctx.font='800 42px Arial'; ctx.fillText(tournament.nome,width/2,140);
  ctx.fillStyle='#b9cbe0'; ctx.font='600 26px Arial';
  ctx.fillText(`${shortDate(week.dal)} — ${shortDate(week.al)}`,width/2,190);
  ctx.font='500 21px Arial'; ctx.fillText(`${matchCount} ${matchCount===1?'partita':'partite'}`,width/2,225);
  let y=headerH;
  for (const section of sections) {
    ctx.textAlign='left'; ctx.fillStyle='#f2c94c'; ctx.font='800 25px Arial'; ctx.fillText(section.nome || 'Partite',54,y+35); y+=sectionH;
    for (const match of section.partite || []) {
      ctx.fillStyle='rgba(255,255,255,.08)'; rounded(ctx,42,y+5,width-84,rowH-14,18);
      const [homeLogo,awayLogo]=await Promise.all([loadImage(match.squadra_casa.logo_url_assoluto||match.squadra_casa.logo),loadImage(match.squadra_ospite.logo_url_assoluto||match.squadra_ospite.logo)]);
      if(homeLogo) ctx.drawImage(homeLogo,67,y+27,72,72); if(awayLogo) ctx.drawImage(awayLogo,width-139,y+27,72,72);
      ctx.fillStyle='#fff'; ctx.font='700 25px Arial'; ctx.textAlign='left'; ctx.fillText(match.squadra_casa.nome,155,y+58,265);
      ctx.textAlign='right'; ctx.fillText(match.squadra_ospite.nome,width-155,y+58,265);
      ctx.textAlign='center'; ctx.fillStyle='#f2c94c'; ctx.font='900 24px Arial'; ctx.fillText('VS',width/2,y+54);
      ctx.fillStyle='#d9e5f2'; ctx.font='600 20px Arial';
      ctx.fillText(`${shortDate(match.data)}  •  ${match.ora || 'Ora da definire'}  •  ${match.campo || 'Luogo da definire'}`,width/2,y+101,700);
      y+=rowH;
    }
  }
  ctx.textAlign='center'; ctx.fillStyle='#8299b2'; ctx.font='500 18px Arial'; ctx.fillText('TORNEI OLD SCHOOL',width/2,height-32);
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
