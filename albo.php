<?php
session_start();
require_once __DIR__ . '/includi/seo.php';
$baseUrl = seo_base_url();
$pageSeo = [
  'title' => 'Albo d\'oro | Tornei Old School',
  'description' => 'Tutte le vincitrici dei tornei Old School con premi e tabelloni stagione per stagione.',
  'url' => $baseUrl . '/albo.php',
  'canonical' => $baseUrl . '/albo.php',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($pageSeo); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    .albo-page { max-width: 1100px; margin: 0 auto; padding: 20px 16px 60px; display: flex; flex-direction: column; gap: 20px; }
    .albo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .albo-card { background: #fff; border: 1px solid #e5e9f2; border-radius: 14px; padding: 16px; box-shadow: 0 10px 28px rgba(21,41,62,0.08); }
    .albo-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
    .albo-pill { background: #eef2f7; padding: 6px 10px; border-radius: 999px; font-weight: 800; color: #15293e; letter-spacing: 0.08em; text-transform: uppercase; font-size: 0.85rem; }
    .albo-logo { width: 50px; height: 50px; border-radius: 12px; object-fit: cover; border: 1px solid #e5e8f0; background: #f5f7fb; }
    .albo-title { font-size: 1.2rem; font-weight: 800; color: #15293e; margin: 0 0 4px; }
    .albo-meta { color: #54657a; font-weight: 600; font-size: 0.95rem; margin-bottom: 10px; }
    .albo-premi { display: flex; flex-direction: column; gap: 8px; }
    .albo-premio { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid #e5e8f0; border-radius: 10px; background: #f8fafc; }
    .albo-premio img { width: 38px; height: 38px; border-radius: 10px; object-fit: cover; background: #fff; border: 1px solid #dfe4ed; }
    .albo-premio .tit { font-weight: 800; color: #15293e; }
    .albo-premio .vic { font-weight: 700; color: #0f172a; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/includi/header.php'; ?>
  <main class="albo-page">
    <h1>Albo d'oro completo</h1>
    <div id="alboGrid" class="albo-grid">
      <p>Caricamento...</p>
    </div>
  </main>
  <div id="footer-container"></div>

  <script src="/includi/app.min.js?v=20251204"></script>
  <script>
    function formatPeriodo(inizio, fine, anno) {
      const f = (d) => {
        if (!d) return '';
        const dt = new Date(d);
        if (isNaN(dt.getTime())) return '';
        return dt.toLocaleDateString('it-IT', { month: 'short', year: 'numeric' });
      };
      const s = f(inizio), e = f(fine);
      if (s && e) return `${s} - ${e}`;
      if (e) return `Concluso ${e}`;
      if (s) return `Iniziato ${s}`;
      if (anno) return `Stagione ${anno}`;
      return '';
    }

    function renderCard(item) {
      const periodo = formatPeriodo(item.data_inizio, item.data_fine, item.anno);
      const logoTorneo = item.torneo_logo || '/img/logo_old_school.png';
      const nomeTorneo = item.competizione || 'Torneo';
      const premi = (item.premi || []).map(p => `
        <div class="albo-premio">
          <img src="${p.logo_vincitrice || '/img/tornei/pallone.png'}" alt="">
          <div>
            <div class="tit">${p.premio || ''}</div>
            <div class="vic">${p.vincitrice || ''}</div>
          </div>
        </div>
      `).join('');
      return `
        <article class="albo-card">
          <div class="albo-header">
            <span class="albo-pill">${item.anno || ''}</span>
            <img class="albo-logo" src="${logoTorneo}" alt="${nomeTorneo}" onerror="this.src='/img/logo_old_school.png'">
          </div>
          <div>
            <p class="albo-meta">Torneo</p>
            <h3 class="albo-title">${nomeTorneo}</h3>
            <p class="albo-meta">${periodo}</p>
          </div>
          <div class="albo-premi">
            ${premi}
          </div>
        </article>
      `;
    }

    fetch('/api/albo_doro.php')
      .then(r => r.json())
      .then(data => {
        const list = Array.isArray(data.data) ? data.data : [];
        const grid = document.getElementById('alboGrid');
        if (!list.length) {
          grid.innerHTML = '<p>Nessun dato disponibile.</p>';
          return;
        }
        grid.innerHTML = list.map(renderCard).join('');
      })
      .catch(() => {
        const grid = document.getElementById('alboGrid');
        grid.innerHTML = '<p>Errore nel caricamento.</p>';
      });

    document.addEventListener("DOMContentLoaded", () => {
      fetch("/includi/footer.html")
        .then(r => r.text())
        .then(html => {
          const footer = document.getElementById("footer-container");
          if (footer) footer.innerHTML = html;
        })
        .catch(err => console.error("Errore nel caricamento del footer:", err));
      if (typeof initHeaderInteractions === "function") {
        initHeaderInteractions();
      }
    });
  </script>
</body>
</html>
