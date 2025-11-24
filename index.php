<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Home - Tornei Old School</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
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
          <h1>TORNEI</h1>
          <p>Accedi a tutte le informazioni e alle regole dei nostri tornei!</p>
          <a href="/tornei.php" class="hero-btn">Tornei</a>
        </div>
      </section>

      <!-- ULTIME NOTIZIE -->
      <section class="home-news">
        <div class="home-news__header">
          <div>
            <p class="home-news__eyebrow">Dal mondo dei nostri tornei</p>
            <h2>Ultime Notizie</h2>
            <p>Annunci dei nuovi bracket, risultati in tempo reale e storie dai nostri eventi.</p>
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

      <!-- CHI SIAMO -->
      <section class="chisiamo-hero">
        <div class="hero-overlay">
          <h1>Chi Siamo</h1>
          <p>Lo facciamo per passione, per condividere divertimento e amicizia con chiunque voglia partecipare.</p>
          <a href="chisiamo.php" class="hero-btn">Scopri di più</a>
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
        <article onclick="location.href='/articolo.php?id=${p.id}'" style="cursor:pointer">
            <img src="${imageSrc}" alt="">
            <h3>${p.titolo}</h3>
            <p>${p.data}</p>
        </article>`;
    });
}

function renderHomeLeaderCard(player, position, torneoLabel, ordine) {
    const foto = player.foto || '/img/giocatori/unknown.jpg';
    const nome = `${player.nome ?? ''} ${player.cognome ?? ''}`.trim() || 'Giocatore senza nome';
    const squadra = player.squadra ? `${player.squadra}${torneoLabel ? ' - ' + torneoLabel : ''}` : 'Giocatore';

    const metaPresenze = `<span>⏱️ ${player.presenze ?? 0} presenze</span>`;
    const metaGol = `<span>⚽ ${player.gol ?? 0} gol</span>`;
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
            ${metaOrder.join('')}
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
        list.innerHTML = items.map((p, idx) => renderHomeLeaderCard(p, idx + 1, p.torneo || '', currentOrderHome)).join('');
    } catch (error) {
        console.error('Errore nel caricamento classifica giocatori:', error);
        list.innerHTML = '<p class="empty-state">Errore nel recupero della classifica.</p>';
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
</script>

  <!-- SCRIPT HEADER -->
  <script src="/includi/header-interactions.js"></script>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    fetch("/includi/header.php")
      .then(response => response.text())
      .then(data => {
        document.getElementById("header-container").innerHTML = data;
        initHeaderInteractions();

        // Effetto scroll header
        const header = document.querySelector(".site-header");
        window.addEventListener("scroll", () => {
          if (window.scrollY > 50) header.classList.add("scrolled");
          else header.classList.remove("scrolled");
        });

        // Dropdown Tornei
        const dropdown = document.querySelector(".dropdown");
        const btn = dropdown?.querySelector(".dropbtn");
        const menu = dropdown?.querySelector(".dropdown-content");

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
              if (menu) menu.style.display = "none";
            }
          });
        }
      })
      .catch(error => console.error("Errore nel caricamento dell'header:", error));
  });
  </script>

</body>
</html>
