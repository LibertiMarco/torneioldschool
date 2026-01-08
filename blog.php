<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includi/seo.php';
$baseUrl = seo_base_url();
$blogSeo = [
    'title' => 'Blog e novita - Tornei Old School',
    'description' => 'Articoli, aggiornamenti e storie dai tornei Old School con risultati, curiosita e approfondimenti.',
    'url' => $baseUrl . '/blog.php',
    'canonical' => $baseUrl . '/blog.php',
];
$blogBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Blog', 'url' => $baseUrl . '/blog.php'],
]);
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php render_seo_tags($blogSeo); ?>
<?php render_jsonld($blogBreadcrumbs); ?>
<link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">

<style>
:root {
    --blog-bg: #0f1b2c;
    --blog-card-bg: #fff;
    --blog-card-border: #e3e8f4;
    --blog-accent: #f97316;
    --blog-text-muted: #74829a;
}

.blog-hero {
    background: linear-gradient(180deg, #1e3c67 0%, #14233b 60%, #0f1b2c 100%);
    color: #fff;
    padding: 120px 20px 80px;
    text-align: center;
    border-bottom-left-radius: 30px;
    border-bottom-right-radius: 30px;
    box-shadow: 0 30px 60px rgba(15, 23, 42, 0.35);
    position: relative;
}

.blog-hero::after {
    content: "";
    position: absolute;
    left: 50%;
    top: 0;
    transform: translateX(-50%);
    width: min(960px, 90%);
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    opacity: 0.5;
}

.blog-hero-content {
    max-width: 900px;
    margin: 0 auto;
    padding-top: 20px;
}

.blog-hero .eyebrow {
    display: none;
}

.blog-hero h1 {
    font-size: clamp(2rem, 6vw, 3.2rem);
    margin-bottom: 16px;
}

.blog-hero p.lead {
    font-size: clamp(1rem, 3vw, 1.25rem);
    color: rgba(255,255,255,0.8);
    margin-bottom: 30px;
    line-height: 1.6;
}

.blog-search {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.12);
    border-radius: 999px;
    padding: 6px 18px;
    gap: 10px;
    width: min(460px, 100%);
    margin: 0 auto;
    border: 1px solid rgba(255,255,255,0.15);
}

.blog-search input {
    border: none;
    background: transparent;
    color: #fff;
    font-size: 1rem;
    width: 100%;
    outline: none;
}

.blog-search svg {
    width: 18px;
    height: 18px;
    fill: rgba(255,255,255,0.8);
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    white-space: nowrap;
    border: 0;
}

.blog-layout {
    max-width: 1200px;
    margin: 0 auto 60px;
    padding: 30px 20px 0;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 32px;
}

.blog-main {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.section-heading {
    margin-bottom: 18px;
}

.section-heading h2 {
    font-size: 1.9rem;
    margin: 6px 0 0;
}

.section-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--blog-text-muted);
    text-transform: uppercase;
    font-size: 0.78rem;
    letter-spacing: 0.3em;
}

.section-eyebrow::before {
    content: "";
    width: 24px;
    height: 1px;
    background: rgba(116, 130, 154, 0.6);
}

.section-heading.with-meta {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    align-items: baseline;
}

.section-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.meta-pill {
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    background: rgba(15,23,42,0.08);
    color: #0f172a;
}

.meta-pill.alt {
    background: rgba(15,23,42,0.15);
    color: #15293e;
}

.featured-section .section-heading h2 {
    color: #fff;
}

.featured-section .section-eyebrow {
    color: rgba(255,255,255,0.75);
}

.featured-section .section-eyebrow::before {
    background: rgba(255,255,255,0.4);
}

.featured-section .meta-pill {
    background: rgba(255,255,255,0.12);
    color: #fff;
}

.featured-section .meta-pill.alt {
    background: rgba(255,255,255,0.2);
    color: #fff;
}

.featured-section {
    background: linear-gradient(120deg, rgba(18, 38, 63, 0.9), rgba(20, 35, 59, 0.6));
    border-radius: 28px;
    padding: 26px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
}

.archive-section {
    background: #fff;
    border-radius: 24px;
    padding: 26px;
    border: 1px solid var(--blog-card-border);
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
}

.featured-card {
    background: #0f172a;
    border-radius: 24px;
    padding: 32px;
    color: #fff;
    display: flex;
    gap: 28px;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.4);
    align-items: stretch;
    flex-wrap: wrap;
}

.featured-image {
    flex: 1 1 45%;
    border-radius: 18px;
    min-height: 320px;
    background: rgba(255,255,255,0.08);
    overflow: hidden;
}

.featured-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.featured-copy {
    flex: 1 1 55%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.featured-copy span {
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.3em;
    color: rgba(255,255,255,0.7);
}

.featured-copy h3 {
    font-size: 2.2rem;
    margin: 16px 0;
}

.featured-copy p {
    color: rgba(255,255,255,0.75);
    line-height: 1.6;
}

.featured-actions {
    margin-top: 24px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.featured-actions a {
    padding: 12px 18px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    transition: transform 0.2s;
}

.featured-actions a.primary {
    background: #fff;
    color: #0f172a;
}

.featured-actions a.secondary {
    border: 1px solid rgba(255,255,255,0.4);
    color: rgba(255,255,255,0.85);
}

.featured-actions a:hover {
    transform: translateY(-3px);
}

.blog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 20px;
}

.blog-card {
    border-radius: 16px;
    background: var(--blog-card-bg);
    border: 1px solid var(--blog-card-border);
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.blog-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
}

.blog-card a {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.card-image {
    width: 100%;
    height: 220px;
    background: #dfe6f6;
    border-bottom: 1px solid #eef2ff;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

@media (max-width: 900px) {
    .featured-card {
        flex-direction: column;
    }

    .featured-image {
        min-height: 260px;
    }
}

@media (max-width: 640px) {
    .card-image {
        height: 180px;
    }
}

.card-body {
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.card-body h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #0f172a;
}

.card-body p {
    margin: 0;
    color: #465167;
    line-height: 1.4;
}

.card-date {
    font-size: 0.85rem;
    color: var(--blog-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3em;
}

.empty-state {
    background: #fff;
    border-radius: 16px;
    border: 1px dashed #c7d5f5;
    padding: 30px;
    text-align: center;
    color: #576077;
}

.single-card-hint {
    text-align: center;
    color: #6d768c;
    padding: 20px 0;
}

.blog-sidebar {
    background: #fff;
    border-radius: 22px;
    padding: 26px;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    border: 1px solid var(--blog-card-border);
    align-self: start;
    position: sticky;
    top: 140px;
    margin-top: 10px;
}

.blog-sidebar h3 {
    margin-top: 0;
    margin-bottom: 18px;
}

.sidebar-desc {
    color: #6b7287;
    margin-bottom: 28px;
}

.mini-card {
    display: flex;
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid #e7ecfb;
    color: inherit;
    text-decoration: none;
}

.mini-card:last-child {
    border-bottom: none;
}

.mini-date {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #94a0b8;
    letter-spacing: 0.2em;
}

.mini-title {
    font-weight: 600;
    color: #0f172a;
}

.mini-thumb {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    background: #e4ebff;
    overflow: hidden;
    flex-shrink: 0;
}

.mini-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mini-card:hover .mini-title {
    color: #15293e;
}

@media (max-width: 1100px) {
    .blog-layout {
        grid-template-columns: 1fr;
    }

    .blog-sidebar {
        position: static;
    }

    .featured-card {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .blog-hero {
        padding: 120px 16px 70px;
        text-align: left;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }

    .blog-search {
        width: 100%;
    }

    .featured-card {
        padding: 22px;
    }

    .featured-copy h3 {
        font-size: 1.8rem;
    }
}
</style>
</head>

<body>

<?php include __DIR__ . '/includi/header.php'; ?>

<section class="blog-hero">
  <div class="blog-hero-content">
    <p class="eyebrow">NovitÃ  dal club</p>
    <h1>Blog &amp; approfondimenti</h1>
    <p class="lead">
      Raccontiamo tornei, backstage e consigli per la community. Filtra gli articoli per trovare subito ciÃ² che ti interessa.
    </p>
    <label class="blog-search" for="blogSearch">
      <span class="sr-only">Cerca nel blog</span>
      <input id="blogSearch" type="search" placeholder="Cerca un torneo, una guida o un racconto...">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M15.5 14h-.79l-.28-.27a6 6 0 10-.71.71l.27.28v.79L20 21.49 21.49 20 15.5 14zm-6 0a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
      </svg>
    </label>
  </div>
</section>

<main class="blog-layout">
  <div class="blog-main">
    <section class="featured-section">
      <div class="section-heading with-meta">
        <div>
          <span class="section-eyebrow">In evidenza</span>
          <h2>L'articolo del momento</h2>
        </div>
        <div class="section-meta">
          <span class="meta-pill" id="featuredUpdated">Aggiornato ora</span>
          <a class="meta-pill alt" href="/tornei.php">Vai ai tornei</a>
        </div>
      </div>
      <div class="featured-card" id="featuredPost">
        <div class="featured-image"></div>
        <div class="featured-copy">
          <span>Caricamento</span>
          <h3>Scarichiamo gli ultimi articoli...</h3>
          <p>Restiamo un secondo in attesa: il nostro feed sta arrivando dal server.</p>
        </div>
      </div>
    </section>

    <section class="archive-section">
      <div class="section-heading with-meta">
        <div>
          <span class="section-eyebrow">Archivio completo</span>
          <h2>Tutti gli articoli</h2>
        </div>
        <div class="section-meta">
          <span class="meta-pill" id="archiveCount">0 articoli</span>
          <span class="meta-pill alt" id="visibleCount">0 visibili</span>
        </div>
      </div>
      <div class="blog-grid" id="articlesGrid"></div>
      <div class="single-card-hint" id="blogEmptyState" hidden>
        Nessun articolo corrisponde alla ricerca. Prova a cambiare parola chiave.
      </div>
    </section>
  </div>

  <aside class="blog-sidebar">
    <h3>Consigli di lettura</h3>
    <p class="sidebar-desc">Gli aggiornamenti piÃ¹ freschi da non perdere.</p>
    <div id="miniList">Stiamo preparando la lista...</div>
  </aside>
</main>

<?php include __DIR__ . '/includi/footer.html'; ?>

<script>
const featuredBox = document.getElementById('featuredPost');
const cardGrid = document.getElementById('articlesGrid');
const miniList = document.getElementById('miniList');
const emptyState = document.getElementById('blogEmptyState');
const searchInput = document.getElementById('blogSearch');
const featuredUpdated = document.getElementById('featuredUpdated');
const archiveCount = document.getElementById('archiveCount');
const visibleCount = document.getElementById('visibleCount');

let cachedPosts = [];

function escapeHTML(str = '') {
    return str.replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[char] || char));
}

function formatPreview(text = '') {
    const clean = text.trim().replace(/\s+/g, ' ');
    if (clean.length <= 160) {
        return clean;
    }
    return clean.slice(0, 157) + '...';
}

function renderPreviewHtml(text = '', fallback = '') {
    const preview = formatPreview(text || fallback);
    const escaped = escapeHTML(preview);
    // sostituisce ==testo== con <strong>testo</strong> mantenendo il resto escaped
    return escaped.replace(/==(.+?)==/g, '<strong>$1</strong>');
}

function setFeaturedMeta(text) {
    if (featuredUpdated) {
        featuredUpdated.textContent = text;
    }
}

function updateArchiveCounters(total, visible = total) {
    if (archiveCount) {
        const label = total === 1 ? 'articolo' : 'articoli';
        archiveCount.textContent = `${total} ${label}`;
    }
    if (visibleCount) {
        const label = visible === 1 ? 'visibile' : 'visibili';
        visibleCount.textContent = `${visible} ${label}`;
    }
}

function updateFeatured(post) {
    if (!post) {
        setFeaturedMeta('In attesa di pubblicazioni');
        featuredBox.innerHTML = `
            <div class="featured-image"></div>
            <div class="featured-copy">
                <span>Nessun articolo</span>
                <h3>Ancora nessun post disponibile</h3>
                <p>Stiamo preparando nuovi contenuti per te. Torna a trovarci presto.</p>
            </div>`;
        return;
    }

    setFeaturedMeta(`Aggiornato ${post.data}`);
    const previewHtml = renderPreviewHtml(
        post.anteprima || '',
        'Scopri cosa e successo dietro le quinte del torneo!'
    );
    const safeTitle = escapeHTML(post.titolo || 'Articolo in evidenza');
    const cover = post.cover || post.immagine || '';
    const imageMarkup = cover
        ? `<img src="${encodeURI(cover)}" alt="${safeTitle}">`
        : '';

    featuredBox.innerHTML = `
        <div class="featured-image">${imageMarkup}</div>
        <div class="featured-copy">
            <span>${escapeHTML(post.data)}</span>
            <h3>${escapeHTML(post.titolo)}</h3>
            <p>${previewHtml}</p>
            <div class="featured-actions">
                <a class="primary" href="/articolo.php?titolo=${encodeURIComponent(post.titolo)}">Leggi ora</a>
                <a class="secondary" href="/tornei.php">Vedi i tornei</a>
            </div>
        </div>`;
}

function createCard(post) {
    const previewHtml = renderPreviewHtml(
        post.anteprima || '',
        'Scopri cosa e successo dietro le quinte del torneo!'
    );
    const safeTitle = escapeHTML(post.titolo || 'Articolo');
    const cover = post.cover || post.immagine || '';
    const imageMarkup = cover
        ? `<img src="${encodeURI(cover)}" alt="${safeTitle}" loading="lazy">`
        : '';

    return `
        <article class="blog-card">
            <a href="/articolo.php?titolo=${encodeURIComponent(post.titolo)}">
                <div class="card-image">${imageMarkup}</div>
                <div class="card-body">
                    <div class="card-date">${escapeHTML(post.data)}</div>
                    <h3>${escapeHTML(post.titolo)}</h3>
                    <p>${previewHtml}</p>
                </div>
            </a>
        </article>`;
}

function renderGrid(posts) {
    if (!posts.length) {
        cardGrid.innerHTML = `
            <div class="blog-card">
                <div class="card-body">
                    <h3>Hai giÃ  letto il pezzo principale!</h3>
                    <p>Quando pubblicheremo nuovi contenuti compariranno qui.</p>
                </div>
            </div>`;
        return;
    }
    const cards = posts.map(createCard);
    if (cards.length > 1) {
        const adBlock = `
        <div class="blog-card ad-card" style="grid-column: 1 / -1;">
          <ins class="adsbygoogle"
               style="display:block; text-align:center;"
               data-ad-layout="in-article"
               data-ad-format="fluid"
               data-ad-client="ca-pub-8390787841690316"
               data-ad-slot="5519228011"></ins>
        </div>`;
        cards.splice(1, 0, adBlock);
    }
    cardGrid.innerHTML = cards.join('');
    // Inizializza eventuali slot AdSense inseriti
    cardGrid.querySelectorAll('.adsbygoogle').forEach(() => {
        try {
            (adsbygoogle = window.adsbygoogle || []).push({});
        } catch (e) {
            console.error('Adsbygoogle push error', e);
        }
    });
}

function renderMiniList(posts, excludeId = null) {
    const suggestions = Array.isArray(posts)
        ? posts.filter(post => post.id !== excludeId)
        : [];

    if (!suggestions.length) {
        miniList.innerHTML = '<p>Ancora nessun consiglio disponibile.</p>';
        return;
    }

    miniList.innerHTML = suggestions.slice(0, 5).map(post => {
        const cover = post.cover || post.immagine || '';
        return `
        <a class="mini-card" href="/articolo.php?titolo=${encodeURIComponent(post.titolo)}">
            <div class="mini-thumb">
                ${cover ? `<img src="${encodeURI(cover)}" alt="${escapeHTML(post.titolo)}">` : ''}
            </div>
            <div>
                <div class="mini-date">${escapeHTML(post.data)}</div>
                <div class="mini-title">${escapeHTML(post.titolo)}</div>
            </div>
        </a>
    `;
    }).join('');
}

function filterPosts(term) {
    if (!term) {
        updateFeatured(cachedPosts[0]);
        renderGrid(cachedPosts.slice(1));
        emptyState.hidden = cachedPosts.length > 0;
        updateArchiveCounters(cachedPosts.length, cachedPosts.length);
        return;
    }

    const needle = term.toLowerCase();
    const filtered = cachedPosts.filter(post => {
        const title = post.titolo?.toLowerCase() || '';
        const preview = post.anteprima?.toLowerCase() || '';
        return title.includes(needle) || preview.includes(needle);
    });

    if (!filtered.length) {
        updateArchiveCounters(cachedPosts.length, 0);
        updateFeatured(null);
        renderGrid([]);
        emptyState.hidden = false;
        return;
    }

    emptyState.hidden = true;
    updateArchiveCounters(cachedPosts.length, filtered.length);
    updateFeatured(filtered[0]);
    renderGrid(filtered);
}

async function loadBlog() {
    try {
        const response = await fetch('/api/blog.php?azione=lista');
        if (!response.ok) {
            throw new Error('Impossibile recuperare gli articoli');
        }

        const posts = await response.json();
        cachedPosts = Array.isArray(posts) ? posts : [];

        updateFeatured(cachedPosts[0]);
        renderGrid(cachedPosts.slice(1));
        const featuredId = cachedPosts[0]?.id ?? null;
        renderMiniList(cachedPosts, featuredId);
        updateArchiveCounters(cachedPosts.length, cachedPosts.length);
        emptyState.hidden = cachedPosts.length > 0;
    } catch (error) {
        setFeaturedMeta('Errore di caricamento');
        updateArchiveCounters(0, 0);
        featuredBox.innerHTML = `
            <div class="featured-copy">
                <span>Errore</span>
                <h3>Ops, qualcosa Ã¨ andato storto</h3>
                <p>${escapeHTML(error.message)}</p>
            </div>`;
        cardGrid.innerHTML = '';
        miniList.innerHTML = '<p>Ricarica la pagina per riprovare.</p>';
        emptyState.hidden = false;
    }
}

searchInput?.addEventListener('input', event => {
    filterPosts(event.target.value.trim());
});

loadBlog();
</script>

</body>
</html>

