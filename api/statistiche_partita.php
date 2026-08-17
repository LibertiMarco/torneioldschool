<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$partita_id = isset($_GET['partitaid']) ? (int)$_GET['partitaid'] : 0;
if (!$partita_id) {
  die("ID partita mancante.");
}

$assetVersion = '20260520b';
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
<title>Statistiche Partita</title>
<link rel="stylesheet" href="/style.min.css?v=<?php echo urlencode($assetVersion); ?>">
<link rel="icon" type="image/png" href="/img/logo_old_school.png">
<link rel="apple-touch-icon" href="/img/logo_old_school.png">

<style>
:root {
    --stats-admin-header-space: 72px;
}

html {
    background: #f8f9fb;
}

body.stats-admin-page {
    min-height: auto;
    display: block;
    background: #f8f9fb !important;
    overflow-x: hidden;
}

body.stats-admin-page .header-spacer {
    height: var(--stats-admin-header-space);
}

body.stats-admin-page .admin-wrapper {
    min-height: 0;
    padding: 30px 20px calc(48px + env(safe-area-inset-bottom));
    background: #f8f9fb;
}

body.stats-admin-page .admin-container {
    max-width: 1100px;
    margin: 0 auto;
}

/* === HEADER PAGINA === */
.page-header {
    width: 100%;
    text-align: center;
    margin-bottom: 25px;
    padding-top: 10px;

    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Titolo */
.page-header h1 {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.page-header h1::after {
    content: "";
    display: block;
    width: 140px;
    height: 3px;
    background: #c8102e;
    margin: 10px auto 0;
    border-radius: 10px;
}

/* Pulsante indietro */
.page-header .btn-back {
    align-self: flex-start;
    background: #15293e;
    border: none;
    padding: 10px 16px;
    color: white;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    box-shadow: 0 8px 20px rgba(31,63,99,0.25);
    transition: transform .15s, box-shadow .15s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.page-header .btn-back:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 26px rgba(31,63,99,0.32);
}
.page-header .btn-back .icon-back {
    width: 12px;
    height: 12px;
    display: inline-block;
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23ffffff' d='M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z'/%3E%3C/svg%3E");
}

/* === BOX PARTITA === */
#partitaBox {
    background: #f8f8f8;
    border-left: 5px solid #c8102e;
    padding: 12px 15px;
    margin-bottom: 25px;
    border-radius: 6px;
    font-size: 18px;
    color: #333;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

#partitaBox b {
    font-size: 20px;
    color: #111;
}

/* Versione desktop */
@media (min-width: 768px) {
    .page-header {
        flex-direction: row;
        justify-content: center;
        align-items: center;
        position: relative;
    }

    .page-header .btn-back {
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
    }
}

.hidden { display:none; }

/* POPUP */
.msg-box {
  position: fixed;
  bottom: 25px;
  right: 25px;
  padding: 14px 20px;
  background: #333;
  color: #fff;
  border-radius: 8px;
  font-size: 15px;
  opacity: 0;
  transition: opacity .4s;
  box-shadow: 0 4px 14px rgba(0,0,0,.25);
}

.msg-success { background: #28a745 !important; }
.msg-error   { background: #dc3545 !important; }

/* Tabella scroll */
.table-scroll {
    max-height: 350px;
    overflow-y: auto;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    position: sticky;
    top: 0;
    background: #222;
    color: white;
    z-index: 5;
    padding: 10px;
}

.admin-table td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    white-space: nowrap;
}

/* Colonne */
.admin-table th:nth-child(1), .admin-table td:nth-child(1) {
    min-width: 180px;
}

.admin-table th:nth-child(2), .admin-table td:nth-child(2) {
    min-width: 150px;
}

.admin-table th:nth-child(3),
.admin-table th:nth-child(4),
.admin-table th:nth-child(5),
.admin-table th:nth-child(6),
.admin-table th:nth-child(7),
.admin-table th:nth-child(8),
.admin-table td:nth-child(3),
.admin-table td:nth-child(4),
.admin-table td:nth-child(5),
.admin-table td:nth-child(6),
.admin-table td:nth-child(7),
.admin-table td:nth-child(8) {
    min-width: 70px;
}

.admin-table th:nth-child(9), .admin-table td:nth-child(9) {
    min-width: 120px;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 14px;
}
.confirm-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.45);
    backdrop-filter: blur(2px);
    z-index: 9999;
}
.confirm-modal.active { display: flex; }
.confirm-card {
    background: #fff;
    border-radius: 14px;
    padding: 22px;
    width: min(420px, 90vw);
    box-shadow: 0 18px 34px rgba(0,0,0,0.15);
    border: 1px solid #e5eaf0;
}
.confirm-card h4 { margin: 0 0 8px; color: #15293e; }
.confirm-card p { margin: 0 0 16px; color: #345; }
.confirm-actions { display: flex; gap: 12px; justify-content: center; }
.confirm-actions button { flex: 1 1 0; min-width: 140px; text-align: center; }
.btn-ghost { border: 1px solid #d5dbe4; background: #fff; color: #1c2a3a; border-radius: 10px; padding: 12px 14px; cursor: pointer; font-weight: 700; }
.btn-ghost:hover { border-color: #15293e; color: #15293e; }
.modern-danger { background: linear-gradient(135deg, #d72638, #b1172a); border: none; color: #fff; padding: 12px 18px; border-radius: 12px; box-shadow: 0 10px 25px rgba(183, 23, 42, 0.3); transition: transform .15s, box-shadow .15s; font-weight: 700; letter-spacing: 0.2px; }
.modern-danger:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(183, 23, 42, 0.4); }

.last-stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border: 1px solid #d8e1ec;
    border-radius: 14px;
    background: linear-gradient(135deg, #f8fbff 0%, #eef4fb 100%);
    box-shadow: 0 10px 24px rgba(21, 41, 62, 0.08);
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, background .15s ease;
}

.last-stat-card:hover {
    transform: translateY(-1px);
    border-color: #b8c8da;
    box-shadow: 0 14px 28px rgba(21, 41, 62, 0.12);
}

.last-stat-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.last-stat-switch {
    position: relative;
    flex: 0 0 auto;
    width: 52px;
    height: 30px;
    border-radius: 999px;
    background: #c7d2df;
    box-shadow: inset 0 2px 5px rgba(15, 23, 42, 0.18);
    transition: background .18s ease;
}

.last-stat-switch::after {
    content: "";
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.18);
    transition: transform .18s ease;
}

.last-stat-copy {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.last-stat-title {
    font-size: 15px;
    font-weight: 800;
    color: #15293e;
    letter-spacing: 0.2px;
}

.last-stat-desc {
    font-size: 13px;
    line-height: 1.45;
    color: #516274;
}

.last-stat-input:checked + .last-stat-switch {
    background: linear-gradient(135deg, #1f3d5a, #15293e);
}

.last-stat-input:checked + .last-stat-switch::after {
    transform: translateX(22px);
}

.last-stat-input:focus-visible + .last-stat-switch {
    outline: 3px solid rgba(21, 41, 62, 0.18);
    outline-offset: 3px;
}

.player-inline-meta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-left: 8px;
    vertical-align: middle;
}

.player-meta-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    background: #e7eef8;
    color: #1f3d5a;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.3;
}

.player-meta-badge.captain {
    background: #fdebed;
    color: #a11c35;
}

@media (max-width: 640px) {
    .last-stat-card {
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    :root {
        --stats-admin-header-space: 68px;
    }

    body.stats-admin-page .admin-wrapper {
        padding: 18px 10px calc(26px + env(safe-area-inset-bottom));
    }
}

.lineup-toolbar, .lineup-footer, .lineup-score {
    display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.lineup-toolbar { margin-bottom: 16px; }
.lineup-score { background: #15293e; color: #fff; padding: 12px 18px; border-radius: 12px; font-weight: 800; }
.lineup-score strong { font-size: 22px; color: #fff; }
.team-lineup { background: #fff; border: 1px solid #dde3ea; border-radius: 16px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(21,41,62,.07); }
.team-lineup h2 { margin: 0; padding: 15px 18px; background: #15293e; color: #fff; font-size: 20px; }
.team-actions { padding: 10px 14px; background: #f3f6f9; border-bottom: 1px solid #dde3ea; display: flex; gap: 8px; }
.team-actions button { border: 1px solid #cbd5e1; background: #fff; border-radius: 8px; padding: 7px 10px; cursor: pointer; font-weight: 700; }
.player-row { display: grid; grid-template-columns: minmax(190px, 1fr) repeat(3, 112px) 70px 70px 92px; gap: 10px; align-items: center; padding: 12px 14px; border-bottom: 1px solid #edf0f3; }
.player-row:last-child { border-bottom: 0; }
.player-row:not(.is-present) { opacity: .55; background: #f5f5f5; }
.player-name { display: flex; gap: 9px; align-items: center; font-weight: 750; min-width: 0; }
.presence-toggle { width: 20px; height: 20px; accent-color: #15293e; flex: 0 0 auto; }
.stat-stepper { display: grid; grid-template-columns: 32px 1fr 32px; align-items: center; border: 1px solid #ccd5df; border-radius: 9px; overflow: hidden; }
.stat-control-label { display: none; }
.stat-stepper button { height: 34px; border: 0; background: #eef2f6; font-size: 19px; cursor: pointer; }
.stat-stepper input { width: 100%; height: 34px; padding: 0; border: 0; text-align: center; font-weight: 800; background: #fff; -moz-appearance: textfield; }
.stat-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; }
.event-toggle { display: flex; justify-content: center; align-items: center; gap: 5px; font-weight: 700; cursor: pointer; }
.event-toggle input { width: 20px; height: 20px; }
.vote-input { width: 100%; min-height: 36px; border: 1px solid #ccd5df; border-radius: 9px; text-align: center; font-weight: 700; }
.lineup-labels { display: grid; grid-template-columns: minmax(190px, 1fr) repeat(3, 112px) 70px 70px 92px; gap: 10px; padding: 8px 14px; background: #f8fafc; color: #607083; font-size: 12px; font-weight: 800; text-align: center; }
.lineup-labels span:first-child { text-align: left; }
.lineup-footer { position: sticky; bottom: 0; z-index: 20; background: rgba(248,249,251,.96); padding: 14px 0 calc(14px + env(safe-area-inset-bottom)); border-top: 1px solid #dce3ea; }
.lineup-footer .btn-primary { min-width: 190px; }
.saving-note { color: #607083; font-size: 13px; }
@media (max-width: 820px) {
  .lineup-labels { display: none; }
  .player-row { grid-template-columns: 1fr 1fr 1fr; gap: 9px; }
  .player-name { grid-column: 1 / -1; }
  .stat-control { min-width: 0; }
  .stat-control-label { display: block; margin: 0 0 5px; color: #516274; font-size: 12px; font-weight: 800; text-align: center; }
  .stat-stepper { position: relative; }
  .event-toggle { margin-top: 8px; }
  .vote-input { margin-top: 8px; }
}

</style>
</head>

<body class="admin-page stats-admin-page">

<?php include __DIR__ . '/../includi/header.php'; ?>

<div id="msgBox" class="msg-box"></div>

<main class="admin-wrapper">
<section class="admin-container">

<!-- HEADER FINALE -->
<div class="page-header">
    <button class="btn-back" id="btnBackStats">
      <span class="icon-back" aria-hidden="true"></span>
      Torna indietro
    </button>
    <h1>Statistiche Partita</h1>
</div>

<!-- BOX INFO PARTITA -->
<div id="partitaBox">
    <span id="partitaInfo"></span>
</div>

<form id="lineupForm">
  <div class="lineup-toolbar">
    <p class="saving-note">Seleziona chi ha giocato e inserisci tutti gli eventi direttamente nella distinta.</p>
    <div class="lineup-score"><span id="homeScoreName">Casa</span> <strong id="liveScore">0 - 0</strong> <span id="awayScoreName">Ospite</span></div>
  </div>
  <div id="lineupRoot"><p>Caricamento distinta...</p></div>
  <div class="lineup-footer">
    <label class="last-stat-card">
      <input class="last-stat-input" type="checkbox" id="finalizzaPartita" value="1">
      <span class="last-stat-switch" aria-hidden="true"></span>
      <span class="last-stat-copy">
        <span class="last-stat-title">Finalizza partita</span>
        <span class="last-stat-desc">Segna la partita come giocata e invia le notifiche.</span>
      </span>
    </label>
    <button class="btn-primary" id="saveLineup" type="submit">Salva tutta la distinta</button>
  </div>
</form>

</section>
</main>

<script>
const ID = <?php echo $partita_id; ?>;
const API = "/api/partita_giocatore.php";
let matchInfo = null;

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, char => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[char]));
}

function showLineupMessage(message, type = "success") {
  const box = document.getElementById("msgBox");
  box.textContent = message;
  box.className = "msg-box " + (type === "error" ? "msg-error" : "msg-success");
  box.style.opacity = "1";
  window.setTimeout(() => box.style.opacity = "0", 2600);
}

function playerBadges(player) {
  const badges = [];
  if (/portiere|\bgk\b|^p$/i.test(String(player.ruolo || "").trim())) badges.push("GK");
  if (String(player.is_captain || 0) === "1") badges.push("C");
  return badges.map(label => `<span class="player-meta-badge${label === "C" ? " captain" : ""}">${label}</span>`).join("");
}

function stepper(field, label, value) {
  return `<div class="stat-control">
    <span class="stat-control-label">${label}</span>
    <div class="stat-stepper">
      <button type="button" data-step="-1" aria-label="Diminuisci ${label}">−</button>
      <input type="number" min="0" inputmode="numeric" data-field="${field}" value="${Math.max(0, Number(value) || 0)}" aria-label="${label}">
      <button type="button" data-step="1" aria-label="Aumenta ${label}">+</button>
    </div>
  </div>`;
}

function renderPlayerRow(player) {
  const present = player.statistica_id ? Number(player.presenza) === 1 : true;
  const vote = player.voto === null || player.voto === undefined ? "6" : player.voto;
  return `<div class="player-row ${present ? "is-present" : ""}" data-player-id="${Number(player.giocatore_id)}" data-team-id="${Number(player.squadra_id)}" data-side="${escapeHtml(player.lato)}">
    <label class="player-name">
      <input class="presence-toggle" type="checkbox" data-field="presenza" ${present ? "checked" : ""}>
      <span>${escapeHtml(`${player.cognome || ""} ${player.nome || ""}`.trim())}</span>${playerBadges(player)}
    </label>
    ${stepper("goal", "Gol", player.goal)}
    ${stepper("assist", "Assist", player.assist)}
    ${stepper("autogol", "Autogol", player.autogol)}
    <label class="event-toggle" title="Cartellino giallo"><input type="checkbox" data-field="cartellino_giallo" ${Number(player.cartellino_giallo) ? "checked" : ""}> 🟨</label>
    <label class="event-toggle" title="Cartellino rosso"><input type="checkbox" data-field="cartellino_rosso" ${Number(player.cartellino_rosso) ? "checked" : ""}> 🟥</label>
    <input class="vote-input" type="number" min="0" max="10" step="0.5" inputmode="decimal" data-field="voto" value="${escapeHtml(vote)}" aria-label="Voto">
  </div>`;
}

function renderTeam(side, players) {
  const name = players[0]?.squadra || (side === "casa" ? "Squadra casa" : "Squadra ospite");
  return `<section class="team-lineup" data-team-side="${side}">
    <h2>${escapeHtml(name)}</h2>
    <div class="team-actions"><button type="button" data-select-team="1">Tutti presenti</button><button type="button" data-select-team="0">Nessuno</button></div>
    <div class="lineup-labels"><span>Giocatore</span><span>Gol</span><span>Assist</span><span>Autogol</span><span>Giallo</span><span>Rosso</span><span>Voto</span></div>
    ${players.map(renderPlayerRow).join("")}
  </section>`;
}

function updateLiveScore() {
  let home = 0, away = 0, homeOwn = 0, awayOwn = 0;
  document.querySelectorAll(".player-row.is-present").forEach(row => {
    const goals = Number(row.querySelector('[data-field="goal"]')?.value) || 0;
    const own = Number(row.querySelector('[data-field="autogol"]')?.value) || 0;
    if (row.dataset.side === "casa") { home += goals; homeOwn += own; }
    else { away += goals; awayOwn += own; }
  });
  document.getElementById("liveScore").textContent = `${home + awayOwn} - ${away + homeOwn}`;
}

async function loadLineup() {
  const [matchResponse, lineupResponse] = await Promise.all([
    fetch(`/api/get_partita.php?id=${ID}`),
    fetch(`${API}?azione=lineup&partita_id=${ID}`)
  ]);
  matchInfo = await matchResponse.json();
  const players = await lineupResponse.json();
  if (!lineupResponse.ok || !Array.isArray(players)) throw new Error(players?.error || "Distinta non disponibile");

  document.getElementById("partitaInfo").innerHTML = `<b>${escapeHtml(matchInfo.squadra_casa)} - ${escapeHtml(matchInfo.squadra_ospite)}</b><br>${escapeHtml(matchInfo.data_partita || "")} | ${escapeHtml(String(matchInfo.ora_partita || "").slice(0,5))}<br><span style="font-size:14px;color:#444;">${escapeHtml(matchInfo.torneo || "")} - ${escapeHtml(matchInfo.fase || "REGULAR")}</span>`;
  document.getElementById("homeScoreName").textContent = matchInfo.squadra_casa || "Casa";
  document.getElementById("awayScoreName").textContent = matchInfo.squadra_ospite || "Ospite";
  document.getElementById("finalizzaPartita").checked = Number(matchInfo.giocata || 0) === 1;
  document.getElementById("lineupRoot").innerHTML = ["casa", "ospite"].map(side => renderTeam(side, players.filter(p => p.lato === side))).join("");
  updateLiveScore();
}

document.getElementById("lineupRoot").addEventListener("click", event => {
  const stepButton = event.target.closest("[data-step]");
  if (stepButton) {
    const input = stepButton.parentElement.querySelector("input");
    input.value = Math.max(0, (Number(input.value) || 0) + Number(stepButton.dataset.step));
    input.dispatchEvent(new Event("input", {bubbles:true}));
    return;
  }
  const teamButton = event.target.closest("[data-select-team]");
  if (teamButton) {
    const checked = teamButton.dataset.selectTeam === "1";
    teamButton.closest(".team-lineup").querySelectorAll(".presence-toggle").forEach(input => {
      input.checked = checked;
      input.closest(".player-row").classList.toggle("is-present", checked);
    });
    updateLiveScore();
  }
});

document.getElementById("lineupRoot").addEventListener("change", event => {
  if (event.target.matches(".presence-toggle")) event.target.closest(".player-row").classList.toggle("is-present", event.target.checked);
  updateLiveScore();
});
document.getElementById("lineupRoot").addEventListener("input", updateLiveScore);

document.getElementById("lineupForm").addEventListener("submit", async event => {
  event.preventDefault();
  const button = document.getElementById("saveLineup");
  const rows = [...document.querySelectorAll(".player-row")].map(row => ({
    giocatore_id: Number(row.dataset.playerId), squadra_id: Number(row.dataset.teamId),
    presenza: row.querySelector('[data-field="presenza"]').checked ? 1 : 0,
    goal: row.querySelector('[data-field="goal"]').value,
    assist: row.querySelector('[data-field="assist"]').value,
    autogol: row.querySelector('[data-field="autogol"]').value,
    cartellino_giallo: row.querySelector('[data-field="cartellino_giallo"]').checked ? 1 : 0,
    cartellino_rosso: row.querySelector('[data-field="cartellino_rosso"]').checked ? 1 : 0,
    voto: row.querySelector('[data-field="voto"]').value
  }));
  const fd = new FormData();
  fd.set("azione", "save_bulk"); fd.set("partita_id", ID); fd.set("stats", JSON.stringify(rows));
  fd.set("finalizza", document.getElementById("finalizzaPartita").checked ? "1" : "0");
  button.disabled = true; button.textContent = "Salvataggio...";
  try {
    const response = await fetch(API, {method:"POST", body:fd});
    const output = await response.json();
    if (!response.ok || !output.success) throw new Error(output.error || "Errore durante il salvataggio");
    showLineupMessage(output.message || "Distinta salvata");
    await loadLineup();
  } catch (error) { showLineupMessage(error.message, "error"); }
  finally { button.disabled = false; button.textContent = "Salva tutta la distinta"; }
});

document.getElementById("btnBackStats")?.addEventListener("click", event => {
  event.preventDefault();
  if (window.history.length > 1) window.history.back(); else window.location.href = "/api/gestione_partite.php";
});

loadLineup().catch(error => {
  document.getElementById("lineupRoot").innerHTML = `<p>Impossibile caricare la distinta: ${escapeHtml(error.message)}</p>`;
  showLineupMessage(error.message, "error");
});

if (false) {
let currentStats = [];
let pendingDelete = null;
const KEYBOARD_VIEWPORT_DELTA = 140;
let keyboardWasOpen = false;
let viewportRecoveryTimer = null;
let lastVisualViewportHeight = 0;

window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    window.location.reload();
  }
});

function getVisualViewportHeight() {
  return Math.round(window.visualViewport?.height || window.innerHeight || 0);
}

function keyboardSeemsOpen() {
  if (!window.visualViewport) return false;
  return (window.innerHeight - window.visualViewport.height) > KEYBOARD_VIEWPORT_DELTA;
}

function getMaxScrollY() {
  const doc = document.documentElement;
  const body = document.body;
  const scrollHeight = Math.max(
    doc?.scrollHeight || 0,
    body?.scrollHeight || 0,
    doc?.offsetHeight || 0,
    body?.offsetHeight || 0
  );
  return Math.max(0, scrollHeight - window.innerHeight);
}

function clampScrollAfterKeyboard() {
  const nextY = Math.min(window.scrollY, getMaxScrollY());
  window.scrollTo(0, nextY);
}

function scheduleViewportRecovery(delay = 0) {
  if (viewportRecoveryTimer) {
    window.clearTimeout(viewportRecoveryTimer);
  }

  viewportRecoveryTimer = window.setTimeout(() => {
    requestAnimationFrame(() => {
      clampScrollAfterKeyboard();
      requestAnimationFrame(clampScrollAfterKeyboard);
      window.setTimeout(clampScrollAfterKeyboard, 120);
      window.setTimeout(clampScrollAfterKeyboard, 280);
    });
  }, delay);
}

function dismissKeyboardAndRecover() {
  if (document.activeElement && typeof document.activeElement.blur === "function") {
    document.activeElement.blur();
  }
  scheduleViewportRecovery(keyboardSeemsOpen() ? 120 : 0);
}

document.querySelectorAll("#formAdd button[type='submit'], #formEdit button[type='submit']").forEach((button) => {
  button.addEventListener("pointerdown", dismissKeyboardAndRecover);
  button.addEventListener("touchstart", dismissKeyboardAndRecover, { passive: true });
});

if (window.visualViewport) {
  lastVisualViewportHeight = getVisualViewportHeight();
  keyboardWasOpen = keyboardSeemsOpen();

  window.visualViewport.addEventListener("resize", () => {
    const nextHeight = getVisualViewportHeight();
    const grewAfterKeyboard = nextHeight > (lastVisualViewportHeight + 80);
    const keyboardOpenNow = keyboardSeemsOpen();

    if (keyboardWasOpen && grewAfterKeyboard && !keyboardOpenNow) {
      scheduleViewportRecovery(50);
    }

    keyboardWasOpen = keyboardOpenNow;
    lastVisualViewportHeight = nextHeight;
  });
}

function playerBaseName(player = {}) {
  return `${player.cognome || ""} ${player.nome || ""}`.trim();
}

function isGoalkeeperRole(ruolo) {
  return /portiere|\bgk\b|^p$/i.test(String(ruolo || "").trim());
}

function playerMetaLabels(player = {}) {
  const labels = [];
  if (isGoalkeeperRole(player.ruolo)) labels.push("GK");
  if (String(player.is_captain || player.captain || 0) === "1") labels.push("C");
  return labels;
}

function formatPlayerLabel(player = {}, includeTeam = false) {
  const parts = [playerBaseName(player)];
  if (includeTeam && player.squadra) parts.push(`(${player.squadra})`);

  const meta = playerMetaLabels(player);
  if (meta.length) parts.push(`- ${meta.join(" • ")}`);

  return parts.filter(Boolean).join(" ");
}

function renderPlayerName(player = {}) {
  const meta = playerMetaLabels(player);
  if (!meta.length) return playerBaseName(player);

  const badges = meta.map(label => {
    const cls = label === "C" ? "player-meta-badge captain" : "player-meta-badge";
    return `<span class="${cls}">${label}</span>`;
  }).join("");

  return `${playerBaseName(player)}<span class="player-inline-meta">${badges}</span>`;
}

/* Popup elegante */
function showMsg(msg, type="success"){
    const box = document.getElementById("msgBox");
    box.textContent = msg;
    box.className = "msg-box " + (type === "error" ? "msg-error" : "msg-success");
    box.style.opacity = "1";
    setTimeout(() => box.style.opacity = "0", 2500);
}

/* SWITCH AZIONE */
document.getElementById("azioneStat").addEventListener("change", e => {
  document.getElementById("formAdd").classList.add("hidden");
  document.getElementById("sectionEdit").classList.add("hidden");
  document.getElementById("sectionDelete").classList.add("hidden");

  if (e.target.value === "add") document.getElementById("formAdd").classList.remove("hidden");
  if (e.target.value === "edit") document.getElementById("sectionEdit").classList.remove("hidden");
  if (e.target.value === "delete") document.getElementById("sectionDelete").classList.remove("hidden");
});

/* INFO PARTITA */
async function loadPartita(){
  const r = await fetch(`/api/get_partita.php?id=${ID}`);
  const p = await r.json();
  let torneoNome = p.torneo || '';
  try {
    const tr = await fetch(`/api/get_torneo_by_slug.php?slug=${encodeURIComponent(p.torneo || '')}`);
    const td = await tr.json();
    if (td && td.nome) torneoNome = td.nome;
  } catch (e) {}

  document.getElementById("partitaInfo").innerHTML = `
    <b>${p.squadra_casa} - ${p.squadra_ospite}</b><br>
    ${p.data_partita} | ${p.ora_partita.substring(0,5)}<br>
    <span style="font-size:14px;color:#444;">${torneoNome} - ${p.fase || 'REGULAR'}</span>
  `;

}

/* CARICA GIOCATORI */
async function loadPlayers(){
  const r = await fetch(`${API}?azione=list_giocatori&partita_id=${ID}`);
  const list = await r.json().catch(() => []);
  const sel = document.getElementById("add_giocatore");

  if (!Array.isArray(list) || list.length === 0) {
    sel.innerHTML = `<option value="">Nessun giocatore disponibile</option>`;
    return;
  }

  sel.innerHTML = `<option value="">-- Seleziona giocatore --</option>`;
  list.forEach(g => {
    sel.innerHTML += `<option value="${g.id}" data-squadra-id="${g.squadra_id || ""}">${formatPlayerLabel(g, true)}</option>`;
  });
}

/* CARICA STATISTICHE */
async function loadStats(){
  const r = await fetch(`${API}?azione=list&partita_id=${ID}`);
  const stats = await r.json();

  currentStats = Array.isArray(stats) ? stats : [];
  const TD = document.querySelector("#tabellaDelete tbody");

  // Popola select modifica
  const editSel = document.getElementById("edit_giocatore_sel");
  if (editSel) {
    const previous = editSel.value;
    editSel.innerHTML = `<option value="">-- Seleziona giocatore --</option>`;
    currentStats.forEach(s => {
      editSel.innerHTML += `<option value="${s.id}">${formatPlayerLabel(s, true)}</option>`;
    });
    if (previous && currentStats.some(s => String(s.id) === String(previous))) {
      editSel.value = previous;
    } else {
      editSel.value = "";
    }
    populateEditFromSelect();
  }

  TD.innerHTML = "";

  if (TD) {
    TD.innerHTML = "";
    currentStats.forEach(s => {
      TD.innerHTML += `
        <tr>
          <td>${renderPlayerName(s)}</td>
          <td>${s.squadra}</td>
          <td>${s.goal}</td>
          <td>${s.assist}</td>
          <td>${s.autogol ?? 0}</td>
          <td>${s.cartellino_giallo}</td>
          <td>${s.cartellino_rosso}</td>
          <td>${s.voto ?? '-'}</td>
          <td><button data-del="${s.id}" class="btn-danger btn-sm">Elimina</button></td>
        </tr>`;
    });
  }
}

/* Aggiunta */
document.getElementById("formAdd").addEventListener("submit", async e => {
  e.preventDefault();
  dismissKeyboardAndRecover();
  const playerSelect = document.getElementById("add_giocatore");
  const selectedPlayerOption = playerSelect?.selectedOptions?.[0] || null;
  const fd = new FormData(e.target);
  fd.append("azione","add");
  fd.set("cartellino_giallo", e.target.cartellino_giallo?.checked ? 1 : 0);
  fd.set("cartellino_rosso", e.target.cartellino_rosso?.checked ? 1 : 0);
  fd.set("autogol", e.target.autogol?.value || 0);
  fd.set("ultima_statistica", e.target.ultima_statistica?.checked ? 1 : 0);
  fd.set("squadra_id", selectedPlayerOption?.dataset?.squadraId || "");

  const r = await fetch(API, { method:"POST", body:fd });
  const out = await r.json();

  if(out.error === "exists"){
      showMsg("Attenzione: giocatore già aggiunto", "error");
      return;
  }

  if(out.error === "invalid_team"){
      showMsg("Selezione squadra non valida per questo giocatore", "error");
      return;
  }

  if(out.success){
      showMsg("Statistica creata!", "success");
      e.target.reset();
      await loadStats();
      await loadPlayers();
      scheduleViewportRecovery(60);
  }
});

/* Popola modifica tramite select */
function populateEditFromSelect() {
  const sel = document.getElementById("edit_giocatore_sel");
  if (!sel || !sel.value) return;
  const stat = currentStats.find(s => String(s.id) === String(sel.value));
  if (!stat) return;
  document.getElementById("edit_id").value = stat.id;
  document.getElementById("edit_goal").value = stat.goal ?? 0;
  document.getElementById("edit_assist").value = stat.assist ?? 0;
  document.getElementById("edit_autogol").value = stat.autogol ?? 0;
  document.getElementById("edit_giallo").checked = (stat.cartellino_giallo ?? 0) > 0;
  document.getElementById("edit_rosso").checked = (stat.cartellino_rosso ?? 0) > 0;
  document.getElementById("edit_voto").value = stat.voto ?? "";
}
document.getElementById("edit_giocatore_sel")?.addEventListener("change", populateEditFromSelect);

/* Salva modifica */
document.getElementById("formEdit").addEventListener("submit", async e => {
  e.preventDefault();
  dismissKeyboardAndRecover();

  const fd = new FormData(e.target);
  fd.append("azione","edit");
  fd.set("cartellino_giallo", e.target.cartellino_giallo?.checked ? 1 : 0);
  fd.set("cartellino_rosso", e.target.cartellino_rosso?.checked ? 1 : 0);
  fd.set("autogol", e.target.autogol?.value || 0);

  const r = await fetch(API, { method:"POST", body:fd });
  const out = await r.json();

  if(out.success){
      showMsg("Modifica salvata!", "success");
      await loadStats();
      scheduleViewportRecovery(60);
  }
});

/* Eliminazione con modale custom */
const modalDel = document.getElementById("modalDeleteStat");
const btnCancelDel = document.getElementById("btnCancelDeleteStat");
const btnConfirmDel = document.getElementById("btnConfirmDeleteStat");
const deleteStatText = document.getElementById("deleteStatText");
const tableDelete = document.querySelector("#tabellaDelete tbody");

tableDelete?.addEventListener("click", async e => {
  const id = e.target.dataset.del;
  if (!id) return;

  pendingDelete = currentStats.find(s => String(s.id) === String(id)) || null;
  if (pendingDelete && deleteStatText) {
    deleteStatText.textContent = `Eliminare ${formatPlayerLabel(pendingDelete)}?`;
  }
  modalDel?.classList.add("active");
});

btnCancelDel?.addEventListener("click", () => {
  pendingDelete = null;
  modalDel?.classList.remove("active");
});

btnConfirmDel?.addEventListener("click", async () => {
  if (!pendingDelete) return;
  const fd = new FormData();
  fd.append("azione","delete");
  fd.append("id", pendingDelete.id);
  const r = await fetch(API, { method:"POST", body:fd });
  const out = await r.json();
  if(out.success){
      showMsg("Statistica eliminata", "success");
      await loadStats();
      await loadPlayers();
  }
  pendingDelete = null;
  modalDel?.classList.remove("active");
});

/* Pulsante indietro: prova history, altrimenti torna alla gestione partite */
document.getElementById("btnBackStats")?.addEventListener("click", (e) => {
  e.preventDefault();
  if (window.history.length > 1) {
    window.history.back();
  } else {
    window.location.href = "/api/gestione_partite.php";
  }
});

/* INIT */
(async () => {
  await loadPartita();
  await loadPlayers();
  await loadStats();
})();
}
</script>

</body>
</html>



