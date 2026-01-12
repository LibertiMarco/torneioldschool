<?php
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/seo.php';
$baseUrl = seo_base_url();
$homeSeo = [
  'title' => 'Tornei calcio Napoli (5, 6, 8) | Tornei Old School',
  'description' => 'Tornei amatoriali di calcio a 5, calcio a 6 e calciotto (8) a Napoli: iscrizioni, calendari, classifiche e partite con aggiornamenti in tempo reale.',
  'url' => $baseUrl . '/',
  'canonical' => $baseUrl . '/',
  'image' => $baseUrl . '/img/logo_old_school_1200.png',
];
$localSchema = [
  '@context' => 'https://schema.org',
  '@type' => 'SportsActivityLocation',
  'name' => 'Tornei Old School - Calcio a 5 Napoli',
  'sport' => 'Calcio a 5',
  'areaServed' => 'Napoli',
  'address' => [
    '@type' => 'PostalAddress',
    'addressLocality' => 'Napoli',
    'addressCountry' => 'IT',
  ],
  'url' => $baseUrl . '/',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-VZ982XSRRN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-VZ982XSRRN');
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php render_seo_tags($homeSeo); ?>
  <?php render_jsonld($localSchema); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
</head>

<body>
  
  <!-- HEADER -->
  <?php include __DIR__ . "/includi/header.php"; ?>

  <!-- CONTENUTO PRINCIPALE -->
  <div class="content">
    <div class="homepage">
      
      <!-- HERO PRINCIPALE -->
      <section class="home-hero">
        <div class="hero-overlay">
          <h1>Tornei calcetto Napoli</h1>
          <p>Tornei di calcio a 5, 6 e 8 a Napoli: iscrizioni, calendari, classifiche, regolamenti e risultati.</p>
          <a href="/tornei.php" class="hero-btn">Tornei</a>
        </div>
      </section>

      <!-- ULTIME NOTIZIE -->
      <section class="home-news">
        <div class="home-news__header">
          <div>
            <p class="home-news__eyebrow">Dal mondo dei nostri tornei</p>
            <h2>Ultime Notizie</h2>
            <p>Annunci dei nuovi bracket, le ultime notizie e storie dai nostri eventi.</p>
          </div>
          <a href="blog.php" class="hero-btn hero-btn--ghost">Vai al blog</a>
        </div>

        <div id="newsGrid" class="news-grid">
          <!-- Caricamento automatico via JS -->
        </div>
      </section>

      <!-- CLASSIFICHE GIOCATORI -->
      <section class="home-leaders">
        <div class="leaders-header">
          <div>
            <p class="leaders-eyebrow">Classifiche giocatori</p>
            <h2>Classifiche All Time - Tornei Old School</h2>
            <p>I bomber piu' prolifici dei nostri tornei, aggiornati in tempo reale.</p>
          </div>
          <div class="leaders-actions">
            <div class="leaders-switch">
              <button type="button" class="hero-btn hero-btn--ghost leader-toggle active" data-ordine="gol">Classifica Gol</button>
              <button type="button" class="hero-btn hero-btn--ghost leader-toggle" data-ordine="presenze">Classifica Presenze</button>
            </div>
          </div>
        </div>

        <div class="leader-full-link">
          <a href="/classifica_giocatori.php" class="hero-btn hero-btn--ghost hero-btn--small">Classifica completa</a>
        </div>

        <div id="homeLeadersList" class="leader-list">
          <!-- Caricamento automatico via JS -->
        </div>
      </section>

      <!-- ALBO D'ORO -->
      <section class="home-hof">
        <div class="hof-header">
          <div>
            <p class="hof-eyebrow">Albo d'oro</p>
            <h2>Le vincitrici dei nostri tornei</h2>
            <p>Uno sguardo rapido a tutte le squadre che hanno conquistato i nostri trofei.</p>
          </div>
          <a href="/albo.php" class="hero-btn hero-btn--ghost hero-btn--small">Albo completo</a>
        </div>

        <div id="hallOfFameGrid" class="hof-grid">
          <!-- Caricamento automatico via JS -->
        </div>
      </section>

      <!-- CHI SIAMO -->
      <section class="chisiamo-hero">
        <div class="hero-overlay">
          <h1>Chi Siamo</h1>
          <p>Lo facciamo per passione, per condividere divertimento e amicizia con chiunque voglia partecipare.</p>
          <a href="chisiamo.php" class="hero-btn">Scopri di piÃƒÂ¹</a>
        </div>
      </section>

      <!-- CONTATTI -->
      <section class="contatti-hero">
        <div class="hero-overlay">
          <h1>Contattaci</h1>
          <p>Siamo sempre disponibili per domande, iscrizioni o collaborazioni.</p>
          <a href="contatti.php" class="hero-btn">Contatti</a>
        </div>
      </section>

      <?php if (!isset($_SESSION['user_id'])): ?>
      <!-- ISCRIZIONE HERO (solo se non loggato) -->
      <section class="iscrizione-hero">
        <div class="hero-overlay">
          <h1>Iscriviti ai Tornei Old School</h1>
          <p>Accedi alle classifiche, scopri le squadre e ricevi via email gli aggiornamenti sui nuovi tornei!</p>
          <a href="register.php" class="hero-btn">Iscriviti Ora</a>
        </div>
      </section>
      <?php endif; ?>

      <!-- AdSense tra contatti e footer -->
      <div class="home-adsense" style="max-width: 960px; margin: 30px auto 10px; padding: 0 12px;">
        <script data-cfasync="false" src="https://cmp.gatekeeperconsent.com/min.js"></script>
  <script data-cfasync="false" src="https://the.gatekeeperconsent.com/cmp.min.js"></script><script async src="//www.ezojs.com/ezoic/sa.min.js"></script>
  <script>
    window.ezstandalone = window.ezstandalone || {};
    ezstandalone.cmd = ezstandalone.cmd || [];
  </script>
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

    </div> <!-- fine homepage -->
  </div> <!-- fine content -->

  <!-- FOOTER -->
  <div id="footer-container"></div>

  <!-- SCRIPT FOOTER -->
  <script>
    fetch("/includi/footer.html")
      .then(response => response.text())
      .then(data => document.getElementById("footer-container").innerHTML = data)
      .catch(error => console.error("Errore nel caricamento del footer:", error));
  </script>

<script>
async function loadNews() {
    const r = await fetch('/api/blog.php?azione=ultimi');
    const posts = await r.json();
    const box = document.getElementById("newsGrid");

    box.innerHTML = ""; // pulizia

    if (posts.length === 0) {
        box.innerHTML = "<p>Nessuna notizia disponibile.</p>";
        return;
    }

    posts.forEach(p => {
        const imageSrc = p.immagine ? p.immagine : '/img/blog/placeholder.jpg';
        box.innerHTML += `
        <article onclick="location.href='/articolo.php?titolo=${encodeURIComponent(p.titolo)}'" style="cursor:pointer">
            <img src="${imageSrc}" alt="">
            <h3>${p.titolo}</h3>
            <p>${p.data}</p>
        </article>`;
    });
}

function renderHomeLeaderCard(player, position, torneoLabel, ordine) {
    const foto = player.foto || '/img/giocatori/unknown.jpg';
    const nome = `${player.nome ?? ''} ${player.cognome ?? ''}`.trim() || 'Profilo';
    const squadra = player.squadra ? `${player.squadra}${torneoLabel ? ' - ' + torneoLabel : ''}` : '';

    const metaPresenze = `<span>Presenze: ${player.presenze ?? 0}</span>`;
    const metaGol = `<span>&#x26BD; ${player.gol ?? 0} gol</span>`;
    const metaOrder = ordine === 'presenze' ? [metaPresenze, metaGol] : [metaGol, metaPresenze];

    return `
      <div class="leader-card">
        <div class="leader-rank">${position}</div>
        <div class="leader-avatar">
          <img src="${foto}" alt="${nome}" onerror="this.src='/img/giocatori/unknown.jpg';">
        </div>
        <div class="leader-main">
          <div>
            <div class="leader-name">${nome}</div>
            <div class="leader-team">${squadra}</div>
          </div>
          <div class="leader-meta">
            ${metaOrder.join(' ')}
          </div>
        </div>
      </div>
    `;
}

async function loadTopScorers() {
    const list = document.getElementById("homeLeadersList");
    if (!list) return;

    const params = new URLSearchParams({ per_page: 5, ordine: currentOrderHome });

    try {
        const response = await fetch('/api/classifica_giocatori.php?' + params.toString());
        const data = await response.json();
        const items = Array.isArray(data.data) ? data.data : [];
        if (!items.length) {
            list.innerHTML = '<p class="empty-state">Nessun dato disponibile al momento.</p>';
            return;
        }
        list.innerHTML = items.map((p, idx) => {
          const rank = p.posizione ?? (idx + 1);
          return renderHomeLeaderCard(p, rank, p.torneo || '', currentOrderHome);
        }).join('');
    } catch (error) {
        console.error('Errore nel caricamento classifica giocatori:', error);
        list.innerHTML = '<p class="empty-state">Errore nel recupero della classifica.</p>';
    }
}

function formatShortDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('it-IT', { month: 'short', year: 'numeric' });
}

function formatPeriodo(inizio, fine, anno) {
    const start = formatShortDate(inizio);
    const end = formatShortDate(fine);
    if (start && end) return `${start} - ${end}`;
    if (end) return `Concluso ${end}`;
    if (start) return `Iniziato ${start}`;
    if (anno) return `Stagione ${anno}`;
    return 'Torneo concluso';
}

function labelPeriodo(item) {
    const nome = (item.competizione || '').toLowerCase();
    if (nome.includes("coppa d'africa") && nome.includes('all in one night')) {
        return '12 dic 2025';
    }
    return formatPeriodo(item.data_inizio, item.data_fine, item.anno);
}

function normalizePath(path) {
    if (!path || path === '0' || path === 0) return '';
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/')) return path;
    return '/' + path.replace(/^\/+/, '');
}

function renderHallCard(item) {
    const torneoLogo = item.torneo_logo || item.torneo_img || '/img/logo_old_school.png';
    const fileLink = normalizePath(item.filetorneo);
    const periodo = labelPeriodo(item);
    const premi = Array.isArray(item.premi) ? item.premi : [];
    // Nessun badge testuale richiesto in alto
    const badge = '';

    const premiList = premi.map(p => {
      const logo = p.logo_vincitrice || '/img/tornei/pallone.png';
      const premioNome = p.premio || 'Premio';
      const winner = p.vincitrice || '';
      return `
        <div class="hof-winner-row">
          <div class="hof-logo hof-logo--small">
            <img src="${logo}" alt="Logo ${winner}" onerror="this.src='/img/tornei/pallone.png';">
          </div>
          <div>
            <p class="hof-label">${premioNome}</p>
            <span class="hof-winner-name">${winner}</span>
          </div>
        </div>
      `;
    }).join('');

    return `
      <article class="hof-card">
        <div class="hof-top">
          ${badge ? `<span class="hof-badge">${badge}</span>` : '<span class="hof-badge" style="visibility:hidden;"></span>'}
          <span class="hof-year">${item.anno || ''}</span>
        </div>
        <div class="hof-body">
          <div class="hof-tournament">
            <div class="hof-logo">
              <img src="${torneoLogo}" alt="Logo ${item.competizione}" onerror="this.src='/img/logo_old_school.png';">
            </div>
            <div>
              <p class="hof-label">Torneo</p>
              <h3>${item.competizione || ''}</h3>
              <p class="hof-meta">${periodo}</p>
            </div>
          </div>
          <div class="hof-winner">
            <p class="hof-label">Premi</p>
            ${premiList}
          </div>
        </div>
        ${fileLink ? `<div class="hof-actions">
          <a class="hof-link" href="${fileLink}" target="_blank" rel="noopener">Tabellone</a>
        </div>` : ''}
      </article>
    `;
}

async function loadHallOfFame() {
    const grid = document.getElementById('hallOfFameGrid');
    if (!grid) return;

    grid.innerHTML = '<p class="loading">Caricamento in corso...</p>';

    try {
        const response = await fetch('/api/albo_doro.php');
        const payload = await response.json();
        const data = Array.isArray(payload.data) ? payload.data.slice(0, 2) : [];

        if (!data.length) {
            grid.innerHTML = '<p class="empty-state">Nessuna vincitrice registrata.</p>';
            return;
        }

        grid.innerHTML = data.map(renderHallCard).join('');
    } catch (error) {
        console.error("Errore nel caricamento dell'albo d'oro:", error);
        grid.innerHTML = '<p class="empty-state">Impossibile recuperare l\'albo d\'oro.</p>';
    }
}

let currentOrderHome = 'gol';

function setHomeOrder(order) {
    currentOrderHome = order;
    document.querySelectorAll('.leader-toggle').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.ordine === order);
    });
    loadTopScorers();
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.leader-toggle');
    if (!btn) return;
    const order = btn.dataset.ordine || 'gol';
    setHomeOrder(order);
});

loadNews();
loadTopScorers();
loadHallOfFame();
</script>

  <!-- SCRIPT HEADER -->
  <script src="/includi/app.min.js?v=20251220"></script>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const headerSlot = document.getElementById("header-container");
    if (headerSlot) {
      fetch("/includi/header.php")
        .then(response => response.text())
        .then(data => {
          headerSlot.innerHTML = data;
          initHeaderInteractions(headerSlot);
          attachHeaderExtras();
        })
        .catch(error => console.error("Errore nel caricamento dell'header:", error));
    } else {
      // Header giÃƒÂ  incluso via PHP
      initHeaderInteractions(document);
      attachHeaderExtras();
    }

    function attachHeaderExtras() {
      const header = document.querySelector(".site-header");
      if (header) {
        window.addEventListener("scroll", () => {
          header.classList.toggle("scrolled", window.scrollY > 50);
        });
      }

      const dropdown = document.querySelector(".dropdown");
      if (dropdown) {
        const btn = dropdown.querySelector(".dropbtn");
        const menu = dropdown.querySelector(".dropdown-content");
        if (btn && menu) {
          btn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.toggle("open");
            menu.style.display = dropdown.classList.contains("open") ? "block" : "none";
          });
          document.addEventListener("click", (e) => {
            if (!dropdown.contains(e.target)) {
              dropdown.classList.remove("open");
              menu.style.display = "none";
            }
          });
        }
      }
    }
  });
  </script>

</body>
</html>





