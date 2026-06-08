<?php
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/seo.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/content_sections.php';

function home_escape(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function home_news_permalink(?string $title): string
{
    return '/articolo.php?titolo=' . rawurlencode((string)($title ?? ''));
}

$baseUrl = seo_base_url();
$pageSeo = [
    'title' => 'Tornei ESPORT | Tornei Old School',
    'description' => 'La homepage dedicata ai tornei esport Tornei Old School: news, ranking in arrivo, albo d\'oro e accesso rapido ai tornei gaming.',
    'url' => $baseUrl . '/esport.php',
    'canonical' => $baseUrl . '/esport.php',
    'image' => $baseUrl . '/img/logo_old_school_1200.png',
];
$pageSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'Tornei Old School - ESPORT',
    'url' => $baseUrl . '/esport.php',
];

$homeNews = [];
$homeNewsSql = "SELECT id,
                       titolo,
                       COALESCE(
                           (SELECT CONCAT('/img/blog_media/', file_path)
                            FROM blog_media
                            WHERE post_id = blog_post.id AND tipo = 'image'
                            ORDER BY ordine ASC, id ASC
                            LIMIT 1),
                           CASE
                               WHEN immagine IS NULL OR immagine = '' THEN ''
                               ELSE CONCAT('/img/blog/', immagine)
                           END
                       ) AS cover,
                       DATE_FORMAT(data_pubblicazione, '%d/%m/%Y') AS data
                FROM blog_post";
$blogSectionReady = ensure_blog_post_section_column($conn);
if ($blogSectionReady) {
    $homeNewsSql .= " WHERE sezione = ?";
}
$homeNewsSql .= " ORDER BY data_pubblicazione DESC LIMIT 4";

if ($blogSectionReady && ($homeNewsStmt = $conn->prepare($homeNewsSql))) {
    if ($blogSectionReady) {
        $pageSection = 'esport';
        $homeNewsStmt->bind_param('s', $pageSection);
    }
    if ($homeNewsStmt->execute()) {
        $homeNewsResult = $homeNewsStmt->get_result();
        while ($row = $homeNewsResult->fetch_assoc()) {
            $homeNews[] = $row;
        }
        $homeNewsResult->free();
    }
    $homeNewsStmt->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php render_seo_tags($pageSeo); ?>
  <?php render_jsonld($pageSchema); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    .home-hero--esport {
      background:
        linear-gradient(135deg, rgba(10, 20, 40, 0.7), rgba(18, 34, 58, 0.35)),
        url('img/home_tornei.png') center/cover no-repeat;
    }
  </style>
</head>

<body>
  <?php include __DIR__ . "/includi/header.php"; ?>

  <div class="content">
    <div class="homepage">

      <section class="home-hero home-hero--esport">
        <div class="hero-overlay">
          <h1>Tornei ESPORT</h1>
          <p>La nostra area dedicata al gaming competitivo con tornei, bracket, risultati e contenuti esclusivi.</p>
          <div class="hero-actions">
            <a href="/tornei-esport.php" class="hero-btn">Tornei Esport</a>
          </div>
        </div>
      </section>

      <section class="home-news">
        <div class="home-news__header">
          <div>
            <p class="home-news__eyebrow">Dal mondo degli esport</p>
            <h2>Ultime Notizie ESPORT</h2>
            <p>Aggiornamenti, bracket, annunci e contenuti dedicati ai nostri tornei gaming.</p>
          </div>
          <a href="/blog.php?sezione=esport" class="hero-btn hero-btn--ghost">Vai al blog</a>
        </div>

        <div id="newsGrid" class="news-grid">
          <?php if (!empty($homeNews)): ?>
            <?php foreach ($homeNews as $post): ?>
              <article onclick="location.href='<?= home_escape(home_news_permalink($post['titolo'] ?? '')) ?>'" style="cursor:pointer">
                <img src="<?= home_escape($post['cover'] ?: '/img/blog/placeholder.jpg') ?>" alt="<?= home_escape($post['titolo'] ?? 'Articolo') ?>">
                <h3><?= home_escape($post['titolo'] ?? 'Articolo') ?></h3>
                <p><?= home_escape($post['data'] ?? '') ?></p>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <p>Nessuna notizia esport disponibile al momento.</p>
          <?php endif; ?>
        </div>
      </section>

      <section class="home-leaders">
        <div class="leaders-header">
          <div>
            <p class="leaders-eyebrow">Ranking giocatori</p>
            <h2>Ranking All Time - Tornei ESPORT</h2>
            <p>Qui mostreremo il ranking ufficiale dei giocatori esport appena definiamo la formula di calcolo dedicata.</p>
          </div>
        </div>

        <div class="leader-list">
          <div class="leader-card">
            <div class="leader-rank">?</div>
            <div class="leader-main">
              <div>
                <div class="leader-name">Ranking in preparazione</div>
                <div class="leader-team">La sezione e pronta: appena mi spieghi il calcolo collego il ranking reale.</div>
              </div>
              <div class="leader-meta">
                <span>Formula dedicata</span>
                <span>Aggiornamento live</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="home-hof">
        <div class="hof-header">
          <div>
            <p class="hof-eyebrow">Albo d'oro</p>
            <h2>Le vincitrici dei nostri tornei esport</h2>
            <p>Uno sguardo rapido alle squadre e ai player che hanno alzato i trofei dell'area gaming.</p>
          </div>
          <a href="/albo.php?sezione=esport" class="hero-btn hero-btn--ghost hero-btn--small">Albo completo</a>
        </div>

        <div id="hallOfFameGrid" class="hof-grid">
          <!-- Caricamento automatico via JS -->
        </div>
      </section>

      <section class="chisiamo-hero">
        <div class="hero-overlay">
          <h1>Chi Siamo</h1>
          <p>Lo facciamo per passione, dentro e fuori dal campo, per costruire community e competizioni che valgano la pena.</p>
          <a href="chisiamo.php" class="hero-btn">Scopri di piu</a>
        </div>
      </section>

      <section class="contatti-hero">
        <div class="hero-overlay">
          <h1>Contattaci</h1>
          <p>Siamo sempre disponibili per domande, iscrizioni o collaborazioni.</p>
          <a href="contatti.php" class="hero-btn">Contatti</a>
        </div>
      </section>

      <?php if (!isset($_SESSION['user_id'])): ?>
      <section class="iscrizione-hero">
        <div class="hero-overlay">
          <h1>Iscriviti ai Tornei Old School</h1>
          <p>Accedi ai contenuti esclusivi, segui i tornei e ricevi via email gli aggiornamenti sulle nuove competizioni.</p>
          <a href="register.php" class="hero-btn">Iscriviti Ora</a>
        </div>
      </section>
      <?php endif; ?>

    </div>
  </div>

  <?php include __DIR__ . '/includi/footer.html'; ?>

<script>
function formatShortDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('it-IT', { month: 'short', year: 'numeric' });
}

function formatSingleDay(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric' });
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
    const singleDayDate = item.data_evento || ((item.data_inizio && item.data_inizio === item.data_fine) ? item.data_inizio : '');
    if (Number(item.giornata_unica || 0) === 1 || singleDayDate) {
        return formatSingleDay(singleDayDate) || formatPeriodo(item.data_inizio, item.data_fine, item.anno);
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
          <span class="hof-badge" style="visibility:hidden;"></span>
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
        const response = await fetch('/api/albo_doro.php?sezione=esport');
        const payload = await response.json();
        const data = Array.isArray(payload.data) ? payload.data.slice(0, 2) : [];

        if (!data.length) {
            grid.innerHTML = '<p class="empty-state">Nessuna vincitrice esport registrata.</p>';
            return;
        }

        grid.innerHTML = data.map(renderHallCard).join('');
    } catch (error) {
        console.error("Errore nel caricamento dell'albo d'oro esport:", error);
        grid.innerHTML = '<p class="empty-state">Impossibile recuperare l\'albo d\'oro esport.</p>';
    }
}

loadHallOfFame();
</script>

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
