// ====================== PARAMETRI URL ======================
const params     = new URLSearchParams(window.location.search);
const ID_PARTITA = params.get("id");
const TORNEO     = params.get("torneo");

// ====================== FORMATTA DATA ======================
function formattaData(data) {
    if (!data || data === "0000-00-00") return "Data da definire";
    const [y, m, d] = data.split("-");
    return `${d}/${m}/${y}`;
}

// ====================== FORMATTA VOTO ======================
function formatVoto(v) {
    if (v === null || v === undefined) return "";
    const num = parseFloat(v);
    return num % 1 === 0 ? num.toFixed(0) : num.toFixed(1);
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
// ✅ CARICA CARD PARTITA
// =============================================================
async function caricaPartita() {
    let p = null;

    try {
        const resTry = await fetch(`/torneioldschool/api/get_partite.php?id=${ID_PARTITA}&torneo=${TORNEO}`);
        const dataTry = await resTry.json();
        if (Array.isArray(dataTry) && dataTry.length > 0) p = dataTry[0];
    } catch {}

    if (!p) {
        const res = await fetch(`/torneioldschool/api/get_partite.php?torneo=${TORNEO}`);
        const data = await res.json();
        const tutte = Array.isArray(data) ? data : Object.values(data).flat();
        p = tutte.find(x => String(x.id) === String(ID_PARTITA));
    }

    if (!p) return;

    window.PARTITA = p;

    const dataStr = formattaData(p.data_partita);
    const oraStr  = p.ora_partita && p.ora_partita !== "00:00:00" ? p.ora_partita.slice(0,5) : "";
    const campo   = p.campo?.trim() || "Campo da definire";

    document.getElementById("partitaContainer").innerHTML = `
    <div class="match-card match-card-blue">
      <div class="match-header">
        <span>
          ${campo}
          ${campo !== "Campo da definire" ? `
            <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(campo)}"
              target="_blank" class="maps-link">📍</a>` : ""}
        </span>

        <span>${dataStr}${oraStr ? " - " + oraStr : ""}</span>
      </div>

      <div class="match-body">
        <div class="team home">
          <img src="/torneioldschool/img/scudetti/${p.squadra_casa}.png" class="team-logo">
          <span class="team-name">${p.squadra_casa}</span>
        </div>

        <div class="match-center">
          <span class="score">${p.gol_casa}</span>
          <span class="dash">-</span>
          <span class="score">${p.gol_ospite}</span>
        </div>

        <div class="team away">
          <span class="team-name">${p.squadra_ospite}</span>
          <img src="/torneioldschool/img/scudetti/${p.squadra_ospite}.png" class="team-logo">
        </div>
      </div>
    </div>

<div class="match-link-container">

  ${p.link_youtube ? `
    <a href="${p.link_youtube}" target="_blank" class="match-link-btn yt">
      <img src="/torneioldschool/img/icone/youtube.png" alt="YouTube"> Guarda su YouTube
    </a>
  ` : ""}

  ${p.link_instagram ? `
    <a href="${p.link_instagram}" target="_blank" class="match-link-btn ig">
      <img src="/torneioldschool/img/icone/instagram.png" alt="Instagram"> Guarda su Instagram
    </a>
  ` : ""}

</div>

`;

}

// =============================================================
// ✅ EVENTI GIOCATORI — CARD DIVISA A SINISTRA / DESTRA
// =============================================================
async function caricaEventiGiocatori() {

    try {
        const res = await fetch(`/torneioldschool/api/get_eventi_partita.php?partita=${ID_PARTITA}`);
        const eventi = await res.json();

        const home = window.PARTITA.squadra_casa;
        const away = window.PARTITA.squadra_ospite;

        const byTeam = { [home]: [], [away]: [] };

        // assegna correttamente squadra
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

        // ordina
        const sortFn = (a, b) =>
            (b.goal - a.goal) ||
            (b.gialli - a.gialli) ||
            (b.rossi - a.rossi);

        byTeam[home].sort(sortFn);
        byTeam[away].sort(sortFn);

        // funzione lista
        const renderList = arr => {
            if (!arr.length)
                return `<div class="evento-mini-row muted">Nessun evento</div>`;

            return arr.map(g => {
                const abbrev = `${g.cognome} ${g.nome.charAt(0).toUpperCase()}.`;

                return `
                <div class="evento-mini-row">
                    <div class="evento-nome">${abbrev}</div>

                <div class="evento-dettagli">
                    <span class="icons-after-name">
                        ${"⚽".repeat(g.goal)}
                        ${"🟨".repeat(g.gialli)}
                        ${"🟥".repeat(g.rossi)}
                    </span>
                            
                    ${g.voto !== null ? `<span class="voto"><strong>${formatVoto(g.voto)}</strong></span>` : ""}
                </div>

                </div>`;
            }).join("");
        };

        document.getElementById("riepilogoEventi").innerHTML = `
            <div class="stats-card">

                <div class="stats-header">
                    <div class="team-side left">
                        <img src="/torneioldschool/img/scudetti/${home}.png" class="team-logo">
                    </div>

                    <div class="team-side right">
                        <img src="/torneioldschool/img/scudetti/${away}.png" class="team-logo">
                    </div>
                </div>

                <div class="stats-body">
                    <div class="stats-col">${renderList(byTeam[home])}</div>
                    <div class="stats-col">${renderList(byTeam[away])}</div>
                </div>

            </div>
        `;

    } catch (err) {
        console.error("Errore eventi partita:", err);
    }
}
