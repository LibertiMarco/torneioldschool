// ====================== PARAMETRI URL ======================
const params     = new URLSearchParams(window.location.search);
const ID_PARTITA = params.get("id");
const TORNEO     = params.get("torneo");
function normalizeLogoName(name = "") {
  return name.replace(/[^A-Za-z0-9]/g, "");
}
function logoPathFrom(p, keyLogo, keyNome) {
  const stored = p[keyLogo];
  if (stored) return stored;
  const slug = normalizeLogoName(p[keyNome] || "");
  return slug ? `/img/scudetti/${slug}.png` : "/img/scudetti/default.png";
}

// ====================== FORMATTA DATA ======================
function formattaData(data) {
    if (!data || data === "0000-00-00" || data === "2000-01-01") return "Data da definire";
    const [y, m, d] = data.split("-");
    return `${d}/${m}/${y}`;
}

// ====================== FORMATTA VOTO ======================
function formatVoto(v) {
    if (v === null || v === undefined) return "";
    const num = parseFloat(v);
    return num % 1 === 0 ? num.toFixed(0) : num.toFixed(1);
}
function escapeHtml(str = "") {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
}

// ====================== START ======================
document.addEventListener("DOMContentLoaded", async () => {
    if (!ID_PARTITA || !TORNEO) {
        document.getElementById("partitaContainer").innerHTML =
            "<p>Errore: parametri mancanti.</p>";
        return;
    }

    await caricaPartita();
    await caricaEventiGiocatori();
});

// =============================================================
// ƒo. CARICA CARD PARTITA
// =============================================================
async function caricaPartita() {
    let p = null;

    try {
        const resTry = await fetch(`/api/get_partite.php?id=${ID_PARTITA}&torneo=${TORNEO}`);
        const dataTry = await resTry.json();
        if (Array.isArray(dataTry) && dataTry.length > 0) p = dataTry[0];
    } catch {}

    if (!p) {
        const res = await fetch(`/api/get_partite.php?torneo=${TORNEO}`);
        const data = await res.json();
        const tutte = Array.isArray(data) ? data : Object.values(data).flat();
        p = tutte.find(x => String(x.id) === String(ID_PARTITA));
    }

    if (!p) return;

    window.PARTITA = p;

    const dataStr = formattaData(p.data_partita);
    const oraStr  = p.ora_partita && p.ora_partita !== "00:00:00" ? p.ora_partita.slice(0,5) : "";
  const campo   = p.campo?.trim() || "Campo da definire";
    const logoCasa = logoPathFrom(p, "logo_casa", "squadra_casa");
    const logoOsp = logoPathFrom(p, "logo_ospite", "squadra_ospite");

    const golCasa = (p.gol_casa !== null && p.gol_casa !== undefined) ? p.gol_casa : "-";
    const golOsp = (p.gol_ospite !== null && p.gol_ospite !== undefined) ? p.gol_ospite : "-";
    const arbitro = (p.arbitro || "").trim();

    document.getElementById("partitaContainer").innerHTML = `
    <div class="match-card match-card-blue">
      <div class="match-header">
        <span>
          ${campo}
          ${campo !== "Campo da definire" ? `
            <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(campo)}"
              target="_blank" class="maps-link" aria-label="Apri in Google Maps">
              <span class="maps-icon" aria-hidden="true"></span>
            </a>` : ""}
        </span>

        <span>${dataStr}${oraStr ? " - " + oraStr : ""}</span>
      </div>

      <div class="match-body">
        <div class="team home">
          <span class="team-name">${p.squadra_casa}</span>
          <img src="${logoCasa}" class="team-logo" alt="${p.squadra_casa}">
        </div>

        <div class="match-center">
          <span class="score" id="scoreHome">${golCasa}</span>
          <span class="dash">-</span>
          <span class="score" id="scoreAway">${golOsp}</span>
        </div>

        <div class="team away">
          <span class="team-name">${p.squadra_ospite}</span>
          <img src="${logoOsp}" class="team-logo" alt="${p.squadra_ospite}">
        </div>
      </div>
      <div class="match-referee">Arbitro: ${arbitro ? escapeHtml(arbitro) : 'Da definire'}</div>
    </div>

<div class="match-link-container">

  ${p.link_youtube ? `
    <a href="${p.link_youtube}" target="_blank" class="match-link-btn yt">
      <img src="/img/icone/youtube.png" alt="YouTube"> Guarda su YouTube
    </a>
  ` : ""}

  ${p.link_instagram ? `
    <a href="${p.link_instagram}" target="_blank" class="match-link-btn ig">
      <img src="/img/icone/instagram.png" alt="Instagram"> Guarda su Instagram
    </a>
  ` : ""}

</div>

`;

}

// =============================================================
// ƒo. EVENTI GIOCATORI ƒ?" CARD DIVISA A SINISTRA / DESTRA
// =============================================================
async function caricaEventiGiocatori() {

    let showAllEventi = false;
    let eventiState = null;

    try {
        const res = await fetch(`/api/get_eventi_partita.php?partita=${ID_PARTITA}`);
        const eventi = await res.json();

        const home = window.PARTITA.squadra_casa;
        const away = window.PARTITA.squadra_ospite;

        const byTeam = { [home]: [], [away]: [] };

        eventi.forEach(e => {
            const rec = {
                nome: e.nome,
                cognome: e.cognome,
                goal: (+e.goal || 0),
                gialli: (+e.cartellino_giallo || 0),
                rossi: (+e.cartellino_rosso || 0),
                voto: e.voto,
                squadra: e.squadra
            };

            if (rec.squadra === home) byTeam[home].push(rec);
            else if (rec.squadra === away) byTeam[away].push(rec);
        });

        const sortFn = (a, b) =>
            (b.goal - a.goal) ||
            (b.gialli - a.gialli) ||
            (b.rossi - a.rossi);

        byTeam[home].sort(sortFn);
        byTeam[away].sort(sortFn);

        const homeGoals = byTeam[home].reduce((sum, g) => sum + (g.goal || 0), 0);
        const awayGoals = byTeam[away].reduce((sum, g) => sum + (g.goal || 0), 0);
        const scoreHomeEl = document.getElementById("scoreHome");
        const scoreAwayEl = document.getElementById("scoreAway");
        if (scoreHomeEl) scoreHomeEl.textContent = homeGoals;
        if (scoreAwayEl) scoreAwayEl.textContent = awayGoals;

        const logoHome = logoPathFrom(window.PARTITA, "logo_casa", "squadra_casa");
        const logoAway = logoPathFrom(window.PARTITA, "logo_ospite", "squadra_ospite");
        eventiState = { home, away, byTeam, logoHome, logoAway };

        const renderList = (arr) => {
            const list = Array.isArray(arr) ? arr : [];
            const filtered = showAllEventi
              ? list
              : list.filter(g => (g.goal || 0) > 0 || (g.assist || 0) > 0 || (g.gialli || 0) > 0 || (g.rossi || 0) > 0);

            if (!filtered.length) return `<div class="evento-mini-row muted">Nessun dato</div>`;

            return filtered.map(g => {
                const abbrev = `${g.cognome} ${g.nome.charAt(0).toUpperCase()}.`;
                const icons = [
                  ...(new Array(g.goal || 0)).fill("⚽️"),
                  ...(new Array(g.gialli || 0)).fill("🟨"),
                  ...(new Array(g.rossi || 0)).fill("🟥"),
                ].join("");
                const hasVoto = g.voto !== null && g.voto !== undefined;
                const voto = hasVoto ? `<span class="voto voto-pill">${formatVoto(g.voto)}</span>` : "";
                const iconsMarkup = icons ? `<span class="icons-after-name">${icons}</span>` : "";
                const dettagli = voto || iconsMarkup
                  ? `<div class="evento-dettagli">${voto}${iconsMarkup}</div>`
                  : "";

                return `
                  <div class="evento-mini-row">
                    <div class="evento-nome">${abbrev}</div>
                    ${dettagli}
                  </div>
                `;
            }).join("");
        };

        const renderEventiCard = () => {
            const container = document.getElementById("riepilogoEventi");
            if (!container || !eventiState) return;
            container.innerHTML = `
                <div class="stats-card">
                  <div class="eventi-toggle">
                    <span class="toggle-label">Giocatori:</span>
                    <button type="button" class="eventi-toggle-btn ${showAllEventi ? "" : "active"}" data-mode="main">Principali</button>
                    <button type="button" class="eventi-toggle-btn ${showAllEventi ? "active" : ""}" data-mode="all">Tutti</button>
                  </div>
                  <div class="stats-body">
                      <div class="team-block">
                        <div class="team-block-head">
                          <img src="${eventiState.logoHome}" class="team-logo" alt="${eventiState.home}">
                        </div>
                        <div class="team-block-body">
                          ${renderList(eventiState.byTeam[eventiState.home])}
                        </div>
                      </div>
                      <div class="team-block">
                        <div class="team-block-head">
                          <img src="${eventiState.logoAway}" class="team-logo" alt="${eventiState.away}">
                        </div>
                        <div class="team-block-body">
                          ${renderList(eventiState.byTeam[eventiState.away])}
                        </div>
                      </div>
                  </div>
                </div>
            `;

            const toggleBtns = container.querySelectorAll(".eventi-toggle-btn");
            toggleBtns.forEach(btn => {
              btn.addEventListener("click", () => {
                const mode = btn.dataset.mode;
                showAllEventi = mode === "all";
                renderEventiCard();
              });
            });
        };

        renderEventiCard();

    } catch (err) {
        console.error("Errore eventi partita:", err);
    }
}
