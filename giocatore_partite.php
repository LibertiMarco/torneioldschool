<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includi/seo.php';

$giocatoreId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipoParam = strtolower(trim($_GET['tipo'] ?? 'gol'));
$tipoParam = in_array($tipoParam, ['gol', 'presenze'], true) ? $tipoParam : 'gol';

if ($giocatoreId <= 0) {
    http_response_code(400);
    echo "ID giocatore mancante.";
    exit;
}

$baseUrl = seo_base_url();
$seo = [
    'title' => 'Dettaglio giocatore - Tornei Old School',
    'description' => 'Partite giocate, gol segnati e presenze del giocatore.',
    'url' => $baseUrl . '/giocatore_partite.php?id=' . $giocatoreId,
    'canonical' => $baseUrl . '/giocatore_partite.php?id=' . $giocatoreId,
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php render_seo_tags($seo); ?>
    <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
    <style>
        body { background: #0f1f33; }
        .player-detail { max-width: 1000px; margin: 90px auto 60px; padding: 0 16px; color: #0f1f33; }
        .player-card { background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border-radius: 18px; padding: 18px; box-shadow: 0 18px 36px rgba(10,20,35,0.35); display: grid; grid-template-columns: auto 1fr; gap: 14px; align-items: center; }
        .player-avatar { width: 110px; height: 110px; border-radius: 14px; object-fit: cover; border: 2px solid rgba(255,255,255,0.2); background: #0c1a2a; }
        .player-name { margin: 0; font-size: 1.6rem; }
        .player-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .pill { padding: 8px 10px; border-radius: 10px; background: rgba(255,255,255,0.12); font-weight: 700; font-size: 0.95rem; color: #fff; }
        .section-card { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 14px 28px rgba(12,24,38,0.12); margin-top: 16px; border: 1px solid #e3e8f0; }
        .section-card h2 { margin: 0 0 10px; color: #15293e; }
        .toggle-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .toggle-btn { border: 1px solid #d5dbe4; background: #fff; color: #1b2c3f; padding: 10px 14px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; }
        .toggle-btn.active { background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border-color: #1f3f63; box-shadow: 0 10px 20px rgba(21,41,62,0.22); }
        .match-list { display: grid; gap: 12px; margin-top: 12px; }
        .match-card { display: block; text-decoration: none; color: inherit; border: 1px solid #e3e8f0; border-radius: 12px; padding: 12px; box-shadow: 0 10px 24px rgba(12,24,38,0.1); background: #fff; transition: transform 0.16s ease, box-shadow 0.16s ease; }
        .match-card:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(12,24,38,0.16); }
        .match-top { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
        .match-title { font-weight: 800; color: #15293e; }
        .match-meta { color: #526078; font-weight: 700; font-size: 0.95rem; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .stage-badge { background: #eef2f8; color: #1b2c3f; padding: 4px 8px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; }
        .match-body { display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center; margin-top: 8px; }
        .team { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; }
        .team-name { font-weight: 800; color: #15293e; }
        .team-logo { width: 38px; height: 38px; object-fit: cover; border-radius: 50%; background: #f4f6fb; border: 1px solid #e2e8f0; }
        .score { font-size: 1.3rem; font-weight: 800; color: #0f1f33; }
        .stat-line { margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; color: #1f2f44; font-weight: 700; }
        .stat-chip { background: #15293e; color: #fff; border-radius: 8px; padding: 6px 8px; font-size: 0.9rem; }
        .muted { color: #5c6a7c; }
        .back-link { color: #fff; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px; }
        .back-link:hover { text-decoration: underline; }
        .loader { padding: 14px; text-align: center; color: #1f2f44; }
        .error-box { padding: 14px; background: #fff3f3; border: 1px solid #f4c7c7; color: #8a1c1c; border-radius: 10px; font-weight: 700; }
        @media (max-width: 680px) {
            .player-card { grid-template-columns: 1fr; text-align: center; }
            .player-avatar { margin: 0 auto; }
            .match-body { grid-template-columns: 1fr; }
            .score { margin: 0 auto; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includi/header.php'; ?>

<main class="player-detail">
    <a class="back-link" href="/classifica_giocatori.php">← Torna alla classifica</a>
    <section class="player-card" id="playerHero">
        <div class="loader">Caricamento giocatore...</div>
    </section>

    <section class="section-card">
        <div style="display:flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap;">
            <h2 style="margin:0;">Partite del giocatore</h2>
            <div class="toggle-group">
                <button type="button" class="toggle-btn" data-tipo="gol">Gol segnati</button>
                <button type="button" class="toggle-btn" data-tipo="presenze">Presenze</button>
            </div>
        </div>
        <div id="matchList" class="match-list">
            <div class="loader">Caricamento partite...</div>
        </div>
    </section>
</main>

<div id="footer-container"></div>
<script>
const playerId = <?= json_encode($giocatoreId) ?>;
let currentTipo = <?= json_encode($tipoParam) ?>;
const FALLBACK_AVATAR = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Crect width='120' height='120' rx='16' fill='%2315293e'/%3E%3Ctext x='50%25' y='55%25' dominant-baseline='middle' text-anchor='middle' font-size='48' fill='%23fff'%3E%3F%3C/text%3E%3C/svg%3E";
const TEAM_FALLBACK = '/img/logo_old_school.png';

const heroEl = document.getElementById('playerHero');
const matchListEl = document.getElementById('matchList');
const toggleButtons = document.querySelectorAll('.toggle-btn');

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>\"']/g, match => {
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

function formatDate(dateStr, timeStr) {
    const datePart = dateStr || '';
    const timePart = timeStr ? timeStr.slice(0,5) : '';
    if (!datePart) return '';
    return timePart ? `${datePart} · ${timePart}` : datePart;
}

function formatStage(match) {
    const fase = (match.fase || '').toString().toUpperCase().trim();
    const isPlayoff = fase === 'GOLD' || fase === 'SILVER';

    if (match.giornata && !isPlayoff) {
        return `Giornata ${match.giornata}`;
    }
    const parts = [];
    if (match.fase) parts.push(match.fase);
    if (match.fase_round) parts.push(match.fase_round);
    if (match.fase_leg) parts.push(match.fase_leg);
    return parts.length ? parts.join(' - ') : '';
}

function updateToggle(tipo) {
    toggleButtons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tipo === tipo);
    });
}

function renderPlayer(player) {
    if (!player) {
        heroEl.innerHTML = '<div class="error-box">Giocatore non trovato.</div>';
        return;
    }
    const foto = player.foto || FALLBACK_AVATAR;
    const nome = `${escapeHTML(player.nome || '')} ${escapeHTML(player.cognome || '')}`.trim();
    const ruolo = player.ruolo ? `<span class="pill">${escapeHTML(player.ruolo)}</span>` : '';
    const totals = player.totali || {};
    heroEl.innerHTML = `
        <img class="player-avatar" src="${foto}" alt="${nome}" onerror="this.onerror=null; this.src='${FALLBACK_AVATAR}';">
        <div>
            <p class="player-label" style="margin:0 0 6px; color:#d72638; font-weight:800;">Scheda giocatore</p>
            <h1 class="player-name">${nome}</h1>
            <div class="player-meta">
                ${ruolo}
                <span class="pill">Gol totali: ${totals.gol ?? 0}</span>
                <span class="pill">Presenze: ${totals.presenze ?? 0}</span>
                <span class="pill">Assist: ${totals.assist ?? 0}</span>
            </div>
        </div>
    `;
}

function renderMatches(matches) {
    if (!Array.isArray(matches) || matches.length === 0) {
        matchListEl.innerHTML = '<p class="muted">Nessuna partita trovata per questo filtro.</p>';
        return;
    }

    matchListEl.innerHTML = matches.map(match => {
        const torneo = escapeHTML(match.torneo_nome || match.torneo || 'Torneo');
        const stage = formatStage(match);
        const date = formatDate(match.data_partita, match.ora_partita);
        const scoreHome = match.gol_casa ?? '-';
        const scoreAway = match.gol_ospite ?? '-';
        const matchUrl = `/tornei/partita_eventi.php?id=${match.partita_id}${match.torneo ? '&torneo=' + encodeURIComponent(match.torneo) : ''}`;
        const statLabel = currentTipo === 'gol'
            ? `Gol segnati: ${match.goal}`
            : 'Presenza registrata';
        const assistLabel = currentTipo === 'gol' && match.assist ? `<span class="stat-chip">Assist: ${match.assist}</span>` : '';
        const votoLabel = currentTipo === 'presenze' && match.voto !== null && match.voto !== undefined
            ? `<span class="stat-chip">Voto: ${match.voto}</span>`
            : '';

        return `
            <a class="match-card" href="${matchUrl}">
                <div class="match-top">
                    <div class="match-title">${torneo}</div>
                    <div class="match-meta">
                        ${stage ? `<span class="stage-badge">${escapeHTML(stage)}</span>` : ''}
                        ${date ? `<span>${escapeHTML(date)}</span>` : ''}
                    </div>
                </div>
                <div class="match-body">
                    <div class="team">
                        <img class="team-logo" src="${escapeHTML(match.logo_casa || TEAM_FALLBACK)}" alt="${escapeHTML(match.squadra_casa || '')}" onerror="this.src='${TEAM_FALLBACK}';">
                        <div class="team-name">${escapeHTML(match.squadra_casa || '')}</div>
                    </div>
                    <div class="score">${scoreHome} - ${scoreAway}</div>
                    <div class="team">
                        <img class="team-logo" src="${escapeHTML(match.logo_ospite || TEAM_FALLBACK)}" alt="${escapeHTML(match.squadra_ospite || '')}" onerror="this.src='${TEAM_FALLBACK}';">
                        <div class="team-name">${escapeHTML(match.squadra_ospite || '')}</div>
                    </div>
                </div>
                <div class="stat-line">
                    <span class="stat-chip">${statLabel}</span>
                    ${assistLabel}
                    ${votoLabel}
                </div>
            </a>
        `;
    }).join('');
}

async function loadData() {
    matchListEl.innerHTML = '<div class="loader">Caricamento partite...</div>';
    heroEl.innerHTML = '<div class="loader">Caricamento giocatore...</div>';

    try {
        const response = await fetch(`/api/giocatore_partite.php?giocatore_id=${playerId}&tipo=${currentTipo}`);
        const data = await response.json();
        if (data.error) {
            heroEl.innerHTML = `<div class="error-box">${escapeHTML(data.error)}</div>`;
            matchListEl.innerHTML = '';
            return;
        }
        renderPlayer(data.player);
        renderMatches(data.matches);
        updateToggle(data.tipo);
        const url = new URL(window.location.href);
        url.searchParams.set('tipo', data.tipo || currentTipo);
        window.history.replaceState({}, '', url.toString());
    } catch (err) {
        heroEl.innerHTML = '<div class="error-box">Errore nel caricamento del giocatore.</div>';
        matchListEl.innerHTML = '<div class="error-box">Errore nel recupero delle partite.</div>';
    }
}

toggleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        const tipo = btn.dataset.tipo || 'gol';
        if (tipo === currentTipo) return;
        currentTipo = tipo;
        loadData();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    loadData();
    const footer = document.getElementById('footer-container');
    if (footer) {
        fetch('/includi/footer.html')
            .then(r => r.text())
            .then(html => footer.innerHTML = html)
            .catch(err => console.error('Errore nel caricamento del footer:', err));
    }
});
</script>
</body>
</html>
