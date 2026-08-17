<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Generatore grafiche</title>
  <style>
    :root{color-scheme:dark;font-family:Inter,Arial,sans-serif;--bg:#07111d;--panel:#101e2d;--gold:#e8bd45;--muted:#aebdca}
    *{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#13263a 0,var(--bg) 42%);color:#fff}main{width:min(1540px,calc(100% - 24px));margin:24px auto 50px}a{color:#9bcaff}h1{margin:12px 0 5px}.intro{margin:0 0 20px;color:var(--muted)}
    .tabs{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:10px;background:var(--panel);border:1px solid #ffffff12;border-radius:16px 16px 0 0}.tab{border:0;border-radius:10px;padding:14px 16px;background:#203449;color:#dbe7f2;font:800 15px inherit;cursor:pointer}.tab.active{background:var(--gold);color:#101722}
    .frames{background:#081522;border:1px solid #ffffff12;border-top:0;border-radius:0 0 16px 16px;overflow:hidden}.frame{display:none;width:100%;min-height:1450px;border:0;background:#07111d}.frame.active{display:block}
    @media(max-width:650px){.tabs{grid-template-columns:1fr}.frame{min-height:1750px}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includi/header.php'; ?>
<main>
  <a href="/admin_dashboard.php">Torna alla dashboard</a>
  <h1>Generatore grafiche</h1>
  <p class="intro">Tutti gli strumenti grafici Old School in un’unica area.</p>
  <nav class="tabs" aria-label="Sezioni grafiche">
    <button class="tab active" type="button" data-target="matchdayFrame">MATCHDAY</button>
    <button class="tab" type="button" data-target="postMatchFrame">FULLTIME E MVP</button>
    <button class="tab" type="button" data-target="coversFrame">COPERTINE</button>
  </nav>
  <section class="frames">
    <iframe id="matchdayFrame" class="frame active" title="Generatore Matchday" src="/api/grafiche_settimana.php?embed=1"></iframe>
    <iframe id="postMatchFrame" class="frame" title="Generatore Fulltime e MVP" data-src="/api/grafiche_post_partita.php?embed=1"></iframe>
    <iframe id="coversFrame" class="frame" title="Generatore copertine" data-src="/api/copertina_partite.php?embed=1"></iframe>
  </section>
</main>
<div id="footer-container"></div>
<script>
document.querySelectorAll('.tab').forEach(button=>button.addEventListener('click',()=>{
  document.querySelectorAll('.tab').forEach(tab=>tab.classList.toggle('active',tab===button));
  document.querySelectorAll('.frame').forEach(frame=>frame.classList.toggle('active',frame.id===button.dataset.target));
  const frame=document.getElementById(button.dataset.target);
  if(!frame.getAttribute('src')&&frame.dataset.src)frame.src=frame.dataset.src;
}));
fetch('/includi/footer.html').then(response=>response.text()).then(html=>{document.getElementById('footer-container').innerHTML=html;}).catch(()=>{});
</script></body></html>
