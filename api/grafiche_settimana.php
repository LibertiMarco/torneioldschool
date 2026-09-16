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
  <p>Il giorno selezionato individua i tornei da pubblicare; ogni PNG contiene tutte le loro partite della settimana, incluse quelle già giocate. Grafica con stemmi, colori della competizione e calendario diviso per giorno.</p>
  <div class="toolbar">
    <label>Giorno delle partite <input id="date" type="date"></label>
    <button id="generate" type="button">Genera grafiche</button>
    <button id="downloadAll" type="button" hidden>Scarica tutte</button>
  </div>
  <div class="status" id="status"></div><div class="grid" id="grid"></div>
</main>
<?php if (!$embedded): ?><div id="footer-container"></div><?php endif; ?>
<script src="/api/matchday-renderer.js?v=20260916-2"></script>
<script>
const dateInput = document.getElementById('date');
const today = new Date();
dateInput.value = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
const grid = document.getElementById('grid');
const statusEl = document.getElementById('status');
const downloadAll = document.getElementById('downloadAll');
let generated = [];

const drawTournament = window.MatchdayRenderer.drawTournament;
const safeName = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();

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
