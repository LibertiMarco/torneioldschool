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
    'description' => 'La homepage dedicata ai tornei esport Tornei Old School: news, ranking EA FC, albo d\'oro e accesso rapido ai tornei gaming.',
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
$isLoggedIn = isset($_SESSION['user_id']);

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
    .leaders-actions {
      display: flex;
      justify-content: center;
      margin-top: 18px;
    }
    .leaders-toggle-btn {
      min-height: 46px;
      padding: 0 18px;
      border: 1px solid #d0daeb;
      border-radius: 999px;
      background: #fff;
      color: #15293e;
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .leaders-toggle-btn:hover:not(:disabled) {
      transform: translateY(-1px);
      border-color: #9fb2d4;
      box-shadow: 0 10px 22px rgba(21, 41, 62, 0.08);
    }
    .leaders-toggle-btn:disabled {
      opacity: 0.6;
      cursor: default;
      box-shadow: none;
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
            <h2>Ranking EA FC</h2>
            <p>Classifica generale dei player esport calcolata su tutti i tornei con categoria EA FC.</p>
          </div>
        </div>

        <div id="esportRankingList" class="leader-list">
          <p class="loading">Caricamento in corso...</p>
        </div>
        <?php if ($isLoggedIn): ?>
        <div id="esportRankingActions" class="leaders-actions" hidden>
          <button
            type="button"
            id="esportRankingToggle"
            class="leaders-toggle-btn"
            aria-expanded="false">
            Visualizza classifica completa
          </button>
        </div>
        <?php endif; ?>
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
          <a href="/chisiamo.php?sezione=esport" class="hero-btn">Scopri di piu</a>
        </div>
      </section>

      <section class="contatti-hero">
        <div class="hero-overlay">
          <h1>Contattaci</h1>
          <p>Siamo sempre disponibili per domande, iscrizioni o collaborazioni.</p>
          <a href="/contatti.php?sezione=esport" class="hero-btn">Contatti</a>
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

function renderEsportLeaderCard(player, position) {
    const foto = player.foto || '/img/giocatori/unknown.jpg';
    const nome = `${player.nome ?? ''} ${player.cognome ?? ''}`.trim() || 'Giocatore';
    const torneiGiocati = Number(player.tornei_giocati || 0);
    const subtitleBase = torneiGiocati === 1 ? '1 torneo EA FC' : `${torneiGiocati} tornei EA FC`;
    const subtitle = player.best_result ? `${subtitleBase} - ${player.best_result}` : subtitleBase;
    const puntiGironi = Number(player.punti_gironi || 0) + Number(player.bonus_gironi || 0);
    const puntiCoppe = Number(player.punti_gold || 0) + Number(player.punti_silver || 0);

    return `
      <div class="leader-card">
        <div class="leader-rank">${position}</div>
        <div class="leader-avatar">
          <img src="${foto}" alt="${nome}" onerror="this.src='/img/giocatori/unknown.jpg';">
        </div>
        <div class="leader-main">
          <div>
            <div class="leader-name">${nome}</div>
            <div class="leader-team">${subtitle}</div>
          </div>
          <div class="leader-meta">
            <span>Totale: ${player.punti ?? 0} pt</span>
            <span>Presenza: ${player.punti_partecipazione ?? 0} pt</span>
            <span>Gironi: ${puntiGironi} pt</span>
            <span>Coppe: ${puntiCoppe} pt</span>
          </div>
        </div>
      </div>
    `;
}

const CAN_VIEW_FULL_RANKING = <?= $isLoggedIn ? 'true' : 'false' ?>;
const DEFAULT_ESPORT_RANKING_LIMIT = 5;
let esportRankingExpanded = false;
let esportRankingLoading = false;

function updateEsportRankingToggle(meta = {}) {
    const actions = document.getElementById('esportRankingActions');
    const toggle = document.getElementById('esportRankingToggle');
    if (!actions || !toggle) return;

    const canViewFull = Boolean(meta.can_view_full);
    const hasMore = Boolean(meta.has_more);
    const totalPlayers = Number(meta.total_players || 0);
    const shouldShow = canViewFull && (hasMore || esportRankingExpanded) && totalPlayers > DEFAULT_ESPORT_RANKING_LIMIT;

    actions.hidden = !shouldShow;
    if (!shouldShow) return;

    toggle.textContent = esportRankingExpanded ? 'Mostra solo top 5' : 'Visualizza classifica completa';
    toggle.setAttribute('aria-expanded', esportRankingExpanded ? 'true' : 'false');
    toggle.disabled = esportRankingLoading;
}

async function loadEsportRanking(limit = DEFAULT_ESPORT_RANKING_LIMIT) {
    const list = document.getElementById('esportRankingList');
    if (!list) return;

    esportRankingLoading = true;
    list.innerHTML = '<p class="loading">Caricamento in corso...</p>';
    updateEsportRankingToggle({
      can_view_full: CAN_VIEW_FULL_RANKING,
      has_more: esportRankingExpanded,
      total_players: esportRankingExpanded ? limit : 0,
    });

    try {
        const params = new URLSearchParams({
          categoria: 'ea fc',
          limit: String(limit)
        });
        const response = await fetch('/api/esport_ranking.php?' + params.toString());
        const payload = await response.json();
        const items = Array.isArray(payload.data) ? payload.data : [];
        const meta = payload && typeof payload.meta === 'object' ? payload.meta : {};

        if (!items.length) {
            list.innerHTML = '<p class="empty-state">Nessun ranking EA FC disponibile al momento.</p>';
            esportRankingLoading = false;
            updateEsportRankingToggle(meta);
            return;
        }

        list.innerHTML = items.map((player, idx) => {
            const rank = player.posizione ?? (idx + 1);
            return renderEsportLeaderCard(player, rank);
        }).join('');
        esportRankingLoading = false;
        updateEsportRankingToggle(meta);
    } catch (error) {
        console.error('Errore nel caricamento del ranking esport:', error);
        list.innerHTML = '<p class="empty-state">Impossibile recuperare il ranking EA FC.</p>';
        esportRankingLoading = false;
        updateEsportRankingToggle({
          can_view_full: CAN_VIEW_FULL_RANKING,
          has_more: esportRankingExpanded,
          total_players: 0,
        });
    }
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

if (CAN_VIEW_FULL_RANKING) {
    document.getElementById('esportRankingToggle')?.addEventListener('click', () => {
        if (esportRankingLoading) return;
        esportRankingExpanded = !esportRankingExpanded;
        loadEsportRanking(esportRankingExpanded ? 50 : DEFAULT_ESPORT_RANKING_LIMIT);
    });
}

loadEsportRanking(DEFAULT_ESPORT_RANKING_LIMIT);
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
