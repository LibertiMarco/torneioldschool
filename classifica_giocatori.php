<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifica marcatori - Tornei Old School</title>
    <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
    <link rel="stylesheet" href="/torneioldschool/style.css">
</head>
<body>
<?php include __DIR__ . '/includi/header.php'; ?>

<div class="content leaders-page">
    <section class="leaders-hero">
        <p class="leaders-eyebrow">Statistiche live</p>
        <h1>Classifiche All Time - Tornei Old School</h1>
        <p class="leaders-lead">Classifica marcatori all time. Cerca un giocatore e scorri la lista completa con pagine da 10 risultati.</p>
        <div class="leader-hero-actions">
            <a class="hero-btn hero-btn--ghost" href="/torneioldschool/index.php">Torna alla home</a>
            <a class="hero-btn" href="/torneioldschool/tornei.php">Vai ai tornei</a>
        </div>
    </section>

    <section class="leaders-panel">
        <div class="leaders-controls">
            <div class="leaders-switch">
                <button type="button" class="hero-btn hero-btn--ghost leader-toggle active" data-ordine="gol">Classifica Gol</button>
                <button type="button" class="hero-btn hero-btn--ghost leader-toggle" data-ordine="presenze">Classifica Presenze</button>
            </div>
            <form id="leaderSearch" class="control-group search-group">
                <label for="searchInput">Cerca giocatore</label>
                <div class="search-input">
                    <input type="search" id="searchInput" name="search" placeholder="Nome o cognome" autocomplete="off">
                    <button type="submit" class="hero-btn">Cerca</button>
                </div>
            </form>
        </div>

        <div class="leader-full-link">
            <a href="/torneioldschool/classifica_giocatori.php" class="hero-btn hero-btn--ghost hero-btn--small">Classifica completa</a>
        </div>

        <div id="leaderList" class="leader-list">
            <p class="loading">Caricamento classifica...</p>
        </div>

        <div class="leader-pagination">
            <div id="pageInfo" class="page-info"></div>
            <div class="pagination-actions">
                <button type="button" id="prevPage" class="pill-btn pill-btn--ghost" disabled>Pagina precedente</button>
                <button type="button" id="nextPage" class="pill-btn" disabled>Pagina successiva</button>
            </div>
        </div>
    </section>
</div>

<div id="footer-container"></div>
<script>
fetch("/torneioldschool/includi/footer.html")
    .then(response => response.text())
    .then(html => document.getElementById("footer-container").innerHTML = html)
    .catch(error => console.error("Errore nel caricamento del footer:", error));
</script>

<script>
const perPage = 10;
let currentPage = 1;
let lastMeta = { page: 1, per_page: perPage, total: 0, total_pages: 0 };
let currentOrder = 'gol';

const leaderList = document.getElementById('leaderList');
const pageInfo = document.getElementById('pageInfo');
const prevPageBtn = document.getElementById('prevPage');
const nextPageBtn = document.getElementById('nextPage');
const searchForm = document.getElementById('leaderSearch');
const searchInput = document.getElementById('searchInput');
const toggleButtons = document.querySelectorAll('.leader-toggle');

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, match => {
        switch (match) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
            default: return match;
        }
    });
}

function renderCards(players) {
    if (!players.length) {
        leaderList.innerHTML = '<p class="empty-state">Nessun giocatore trovato per i filtri selezionati.</p>';
        return;
    }

    const startIndex = ((lastMeta.page || 1) - 1) * (lastMeta.per_page || perPage);
    leaderList.innerHTML = players.map((p, idx) => {
        const posizione = startIndex + idx + 1;
        const foto = p.foto || '/torneioldschool/img/giocatori/unknown.jpg';
        const nomeCompleto = `${escapeHTML(p.nome)} ${escapeHTML(p.cognome)}`.trim() || 'Giocatore';
        const ruolo = p.ruolo ? `<span class="leader-role">${escapeHTML(p.ruolo)}</span>` : '';
        const team = p.squadra ? escapeHTML(p.squadra) : 'Squadra non assegnata';
        const media = p.media_voti ? `<span>⭐ ${p.media_voti}</span>` : '';

        return `
            <div class="leader-card">
                <div class="leader-rank">${posizione}</div>
                <div class="leader-avatar">
                    <img src="${foto}" alt="${nomeCompleto}" onerror="this.src='/torneioldschool/img/giocatori/unknown.jpg';">
                </div>
                <div class="leader-main">
                    <div>
                        <div class="leader-name">${nomeCompleto} ${ruolo}</div>
                        <div class="leader-team">${team}</div>
                    </div>
                    <div class="leader-meta">
                        <span>⚽ ${p.gol ?? 0} gol</span>
                        <span>⏱️ ${p.presenze ?? 0} presenze</span>
                        ${media}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Override renderCards to ensure correct meta order and clean icons
function renderCards(players) {
    if (!players.length) {
        leaderList.innerHTML = '<p class="empty-state">Nessun giocatore trovato per i filtri selezionati.</p>';
        return;
    }

    const startIndex = ((lastMeta.page || 1) - 1) * (lastMeta.per_page || perPage);
    leaderList.innerHTML = players.map((p, idx) => {
        const posizione = startIndex + idx + 1;
        const foto = p.foto || '/torneioldschool/img/giocatori/unknown.jpg';
        const nomeCompleto = `${escapeHTML(p.nome)} ${escapeHTML(p.cognome)}`.trim() || 'Giocatore';
        const ruolo = p.ruolo ? `<span class="leader-role">${escapeHTML(p.ruolo)}</span>` : '';
        const team = p.squadra ? escapeHTML(p.squadra) : 'Squadra non assegnata';
        const media = p.media_voti ? `<span>* ${p.media_voti}</span>` : '';
        const metaPresenze = `<span>Presenze: ${p.presenze ?? 0}</span>`;
        const metaGol = `<span>Gol: ${p.gol ?? 0}</span>`;
        const metaOrder = currentOrder === 'presenze' ? [metaPresenze, metaGol] : [metaGol, metaPresenze];

        return `
            <div class="leader-card">
                <div class="leader-rank">${posizione}</div>
                <div class="leader-avatar">
                    <img src="${foto}" alt="${nomeCompleto}" onerror="this.src='/torneioldschool/img/giocatori/unknown.jpg';">
                </div>
                <div class="leader-main">
                    <div>
                        <div class="leader-name">${nomeCompleto} ${ruolo}</div>
                        <div class="leader-team">${team}</div>
                    </div>
                    <div class="leader-meta">
                        ${metaOrder.join('')}
                        ${media}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function updatePagination(meta) {
    if (meta) {
        lastMeta = { ...lastMeta, ...meta };
    }
    currentPage = lastMeta.page || currentPage;
    const totalPages = lastMeta.total_pages || 1;
    const total = lastMeta.total || 0;
    pageInfo.textContent = total
        ? `Pagina ${lastMeta.page} di ${totalPages} - ${total} giocatori`
        : 'Nessun giocatore trovato';

    prevPageBtn.disabled = lastMeta.page <= 1;
    nextPageBtn.disabled = lastMeta.page >= totalPages;
}

async function loadLeaders(resetPage = false) {
    if (resetPage) currentPage = 1;
    const params = new URLSearchParams({
        page: currentPage,
        per_page: perPage,
        ordine: currentOrder
    });
    const search = searchInput.value.trim();
    if (search) params.append('search', search);

    leaderList.innerHTML = '<p class="loading">Caricamento classifica...</p>';

    try {
        const response = await fetch('/torneioldschool/api/classifica_giocatori.php?' + params.toString());
        const payload = await response.json();
        const giocatori = Array.isArray(payload.data) ? payload.data : [];
        updatePagination(payload.pagination);
        renderCards(giocatori);
    } catch (error) {
        console.error('Errore nel caricamento classifica:', error);
        leaderList.innerHTML = '<p class="empty-state">Errore nel recupero dati. Riprova piu\' tardi.</p>';
    }
}

searchForm.addEventListener('submit', (event) => {
    event.preventDefault();
    loadLeaders(true);
});

prevPageBtn.addEventListener('click', () => {
    if (currentPage <= 1) return;
    currentPage -= 1;
    loadLeaders();
});

nextPageBtn.addEventListener('click', () => {
    if (currentPage >= (lastMeta.total_pages || 1)) return;
    currentPage += 1;
    loadLeaders();
});

toggleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        const ordine = btn.dataset.ordine || 'gol';
        currentOrder = ordine;
        toggleButtons.forEach(b => b.classList.toggle('active', b.dataset.ordine === ordine));
        loadLeaders(true);
    });
});

loadLeaders(true);
</script>

</body>
</html>
