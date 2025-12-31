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
  <script data-cfasync="false" src="https://cmp.gatekeeperconsent.com/min.js"></script>
  <script data-cfasync="false" src="https://the.gatekeeperconsent.com/cmp.min.js"></script><script async src="//www.ezojs.com/ezoic/sa.min.js"></script>
  <script>
    window.ezstandalone = window.ezstandalone || {};
    ezstandalone.cmd = ezstandalone.cmd || [];
  </script>
  <style>
    .albo-page { max-width: 1100px; margin: 0 auto; padding: 88px 16px 90px; display: flex; flex-direction: column; gap: 20px; }
    .albo-page h1 { margin: 0 0 8px; }
    .albo-filters { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 4px 0 12px; }
    .albo-filters label { font-weight: 700; color: #15293e; }
    .albo-select {
      padding: 12px 14px;
      border: 1px solid #cfd6e3;
      border-radius: 999px;
      min-width: 240px;
      background: linear-gradient(145deg, #f7f9fc, #eef2f7);
      font-weight: 700;
      color: #15293e;
      box-shadow: 0 8px 18px rgba(21,41,62,0.08);
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .albo-select:hover { transform: translateY(-1px); border-color: #9fb2d4; box-shadow: 0 12px 24px rgba(21,41,62,0.12); }
    .albo-select:focus { outline: none; border-color: #4f6fbf; box-shadow: 0 0 0 3px rgba(79,111,191,0.22); }
    .albo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .albo-card { background: #fff; border: 1px solid #e5e9f2; border-radius: 14px; padding: 16px; box-shadow: 0 10px 28px rgba(21,41,62,0.08); }
    .albo-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
    .albo-pill { background: #eef2f7; padding: 6px 10px; border-radius: 999px; font-weight: 800; color: #15293e; letter-spacing: 0.08em; text-transform: uppercase; font-size: 0.85rem; }
    .albo-logo { width: 88px; height: 88px; border-radius: 16px; object-fit: cover; border: 1px solid #e5e8f0; background: #f5f7fb; }
    .albo-title { font-size: 1.28rem; font-weight: 800; color: #15293e; margin: 0 0 6px; }
    .albo-meta { color: #54657a; font-weight: 600; font-size: 0.95rem; margin-bottom: 12px; }
    .albo-premi { display: flex; flex-direction: column; gap: 10px; }
    .albo-premio { display: flex; flex-direction: column; gap: 10px; padding: 12px 14px; border: 1px solid #e5e8f0; border-radius: 12px; background: #f8fafc; }
    .albo-premio-title { text-align: center; font-weight: 800; color: #15293e; font-size: 1rem; letter-spacing: 0.2px; }
    .albo-premio-body { display: flex; align-items: center; gap: 14px; justify-content: flex-start; }
    .albo-premio img { width: 78px; height: 78px; border-radius: 16px; object-fit: cover; background: #fff; border: 1px solid #dfe4ed; }
    .albo-premio .vic { font-weight: 800; color: #0f172a; font-size: 1.02rem; }
    .albo-ad { padding: 0; border: none; box-shadow: none; background: transparent; }
    .albo-ad ins { display: block !important; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/includi/header.php'; ?>
  <main class="albo-page">
    <h1>Albo d'oro completo</h1>
    <div class="albo-filters">
      <label for="filterCompetizione">Torneo</label>
      <select id="filterCompetizione" class="albo-select">
        <option value="">Tutti i tornei</option>
      </select>
    </div>
    <div class="albo-ad">
      <!-- Pub orizz -->
      <ins class="adsbygoogle"
           style="display:block"
           data-ad-client="ca-pub-8390787841690316"
           data-ad-slot="3707275285"
           data-ad-format="auto"
           data-full-width-responsive="true"></ins>
      <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
      </script>
    </div>
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

    function labelPeriodo(item) {
      const nome = (item.competizione || '').toLowerCase();
      if (nome.includes("coppa d'africa") && nome.includes('all in one night')) {
        return '12 dic 2025';
      }
      return formatPeriodo(item.data_inizio, item.data_fine, item.anno);
    }

    function renderCard(item) {
      const periodo = labelPeriodo(item);
      const logoTorneo = item.torneo_logo || '/img/logo_old_school.png';
      const nomeTorneo = item.competizione || 'Torneo';
      const premi = (item.premi || []).map(p => `
        <div class="albo-premio">
          <div class="albo-premio-title">${p.premio || ''}</div>
          <div class="albo-premio-body">
            <img src="${p.logo_vincitrice || '/img/tornei/pallone.png'}" alt="">
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

    const grid = document.getElementById('alboGrid');
    const select = document.getElementById('filterCompetizione');
    let alboData = [];

    function renderList(items) {
      if (!items.length) {
        grid.innerHTML = '<p>Nessun dato disponibile.</p>';
        return;
      }
      const chunks = [];
      items.forEach((item, idx) => {
        chunks.push(renderCard(item));
        // Inserisci un ad tra i tornei (non dopo l'ultimo)
        if (idx < items.length - 1) {
          chunks.push(`
            <div class="albo-card albo-ad">
              <ins class="adsbygoogle"
                   style="display:block"
                   data-ad-client="ca-pub-8390787841690316"
                   data-ad-slot="3707275285"
                   data-ad-format="auto"
                   data-full-width-responsive="true"></ins>
              <script>
                (adsbygoogle = window.adsbygoogle || []).push({});
              </scr` + `ipt>
            </div>
          `);
        }
      });
      grid.innerHTML = chunks.join('');
    }

    function populateSelect(items) {
      const unique = Array.from(new Set(items.map(i => i.competizione).filter(Boolean))).sort();
      select.innerHTML = '<option value=\"\">Tutti i tornei</option>' + unique.map(name => `<option value=\"${name}\">${name}</option>`).join('');
    }

    function applyFilter() {
      const val = select.value;
      if (!val) {
        renderList(alboData);
      } else {
        renderList(alboData.filter(i => i.competizione === val));
      }
    }

    fetch('/api/albo_doro.php')
      .then(r => r.json())
      .then(data => {
        alboData = Array.isArray(data.data) ? data.data : [];
        populateSelect(alboData);
        applyFilter();
      })
      .catch(() => {
        grid.innerHTML = '<p>Errore nel caricamento.</p>';
      });

    select.addEventListener('change', applyFilter);

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
