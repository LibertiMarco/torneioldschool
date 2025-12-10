const TORNEO = "ChampionsLeagueB"; // Nome base del torneo nel DB (fase girone)
const GOLD_SPOTS = 16;              // prime 16 in Coppa Gold
const TEAM_COUNT = 18;              // totale squadre in regular
const SILVER_SPOTS = TEAM_COUNT - GOLD_SPOTS; // ultime 2 in finale Silver
const teamLogos = {};
const favState = { tournaments: new Set(), teams: new Set() };
let currentRosaTeam = "";

function teamKey(name = "") {
  return `${TORNEO}|||${name}`;
}

function updateFavTournamentButton() {
  const btn = document.getElementById("favTournamentBtn");
  if (!btn) return;
  const isFav = favState.tournaments.has(TORNEO);
  btn.classList.toggle("is-fav", isFav);
  btn.textContent = isFav ? "★ Torneo seguito" : "☆ Segui torneo";
}

function updateFavTeamButton(squadra, btnEl) {
  const btn = btnEl || document.querySelector(".fav-team-btn");
  if (!btn || !squadra) return;
  const isFav = favState.teams.has(teamKey(squadra));
  btn.classList.toggle("is-fav", isFav);
  btn.textContent = isFav ? "★" : "☆";
  btn.setAttribute("aria-label", isFav ? "Smetti di seguire la squadra" : "Segui la squadra");
}

async function toggleTournamentFollow(btn) {
  const wantFollow = !favState.tournaments.has(TORNEO);
  const fd = new FormData();
  fd.append("tipo", "torneo");
  fd.append("azione", wantFollow ? "follow" : "unfollow");
  fd.append("torneo", TORNEO);
  try {
    const res = await fetch("/api/follow.php", { method: "POST", body: fd, credentials: "include" });
    const data = await res.json();
    if (!data.error) {
      if (data.followed) favState.tournaments.add(TORNEO);
      else favState.tournaments.delete(TORNEO);
    }
  } catch (e) {
    console.error("Errore follow torneo", e);
  }
  updateFavTournamentButton(btn);
}

async function toggleTeamFollow(squadra, btn) {
  if (!squadra) return;
  const key = teamKey(squadra);
  const wantFollow = !favState.teams.has(key);
  const fd = new FormData();
  fd.append("tipo", "squadra");
  fd.append("azione", wantFollow ? "follow" : "unfollow");
  fd.append("torneo", TORNEO);
  fd.append("squadra", squadra);
  try {
    const res = await fetch("/api/follow.php", { method: "POST", body: fd, credentials: "include" });
    const data = await res.json();
    if (!data.error) {
      if (data.followed) favState.teams.add(key);
      else favState.teams.delete(key);
    }
  } catch (e) {
    console.error("Errore follow squadra", e);
  }
  updateFavTeamButton(squadra, btn);
}

async function loadFavorites() {
  try {
    const res = await fetch("/api/follow.php", { credentials: "include" });
    if (!res.ok) return;
    const data = await res.json();
    favState.tournaments = new Set(data.tournaments || []);
    favState.teams = new Set((data.teams || []).map(t => `${t.torneo}|||${t.squadra}`));
    updateFavTournamentButton();
    if (currentRosaTeam) updateFavTeamButton(currentRosaTeam);
  } catch (e) {
    console.error("Errore caricamento preferiti", e);
  }
}

function normalizeLogoName(name = "") {
  return name.replace(/[^A-Za-z0-9]/g, "");
}

function resolveLogoPath(name, storedPath) {
  if (storedPath) return storedPath;
  const cached = teamLogos[name];
  if (cached) return cached;
  const slug = normalizeLogoName(name || "");
  if (!slug) return "/img/scudetti/default.png";
  return `/img/scudetti/${slug}.png`;
}

// ====================== UTILS ======================
function formattaData(data) {
  if (!data || data === "2000-01-01") return "Data da definire";
  const [anno, mese, giorno] = data.split("-");
  const d = new Date(Date.UTC(Number(anno), Number(mese) - 1, Number(giorno)));
  const giorniSettimana = ["Domenica", "Lunedi", "Martedi", "Mercoledi", "Giovedi", "Venerdi", "Sabato"];
  const giornoSettimana = giorniSettimana[d.getUTCDay()] || "";
  return `${giornoSettimana} ${giorno}/${mese}`;
}

// Mappa numero giornata -> nome fase playoff
function nomeFaseDaGiornata(g) {
  const n = parseInt(g, 10);
  switch (n) {
    case 1: return "Finale";
    case 2: return "Semifinali";
    case 3: return "Quarti di finale";
    case 4: return "Ottavi di finale";
    case 5: return "Sedicesimi";
    default: return "Fase " + g;
  }
}

// ====================== CLASSIFICA (GIRONE) ======================
async function caricaClassifica(torneoSlug = TORNEO) {
  try {
    const response = await fetch(`/api/leggiClassifica.php?torneo=${encodeURIComponent(torneoSlug)}`);
    const data = await response.json();

    if (data.error) {
      console.error("Errore dal server:", data.error);
      return;
    }

    data.forEach(team => {
      if (team.logo) {
        teamLogos[team.nome] = team.logo;
      }
    });

    mostraClassifica(data);

    // Rende cliccabili le squadre della classifica
    document.querySelectorAll("#tableClassifica tbody .team-cell").forEach(cell => {
      cell.style.cursor = "pointer";
    
      cell.addEventListener("click", () => {
        const squadra = cell.querySelector(".team-name").textContent.trim();
      
        // Cambia TAB
        document.querySelector('[data-tab="rose"]').click();
      
        // Seleziona la squadra nel dropdown
        const select = document.getElementById("selectSquadra");
        select.value = squadra;
      
        // Carica la rosa della squadra
        caricaRosaSquadra(squadra);
      
        // Scroll alla sezione rose
        document.getElementById("rose").scrollIntoView({ behavior: "smooth" });
      });
    });
  } catch (error) {
    console.error("Errore nel caricamento della classifica:", error);
  }
}

function mostraClassifica(classifica) {
  const tbody = document.querySelector("#tableClassifica tbody");
  tbody.innerHTML = "";

  classifica.sort((a, b) => b.punti - a.punti || b.differenza_reti - a.differenza_reti);

  classifica.forEach((team, i) => {
    const tr = document.createElement("tr");
    const posizione = i + 1;

    if (posizione <= GOLD_SPOTS) {
      tr.classList.add("gold-row");
    } else if (posizione > TEAM_COUNT - SILVER_SPOTS) {
      tr.classList.add("silver-row");
    }

    const logoPath = resolveLogoPath(team.nome, team.logo);

    tr.innerHTML = `
      <td>${i + 1}</td>
      <td class="team-cell">
        <div class="team-info">
          <img src="${logoPath}" alt="${team.nome}" class="team-logo">
          <span class="team-name">${team.nome}</span>
        </div>
      </td>
      <td>${team.punti}</td>
      <td>${team.giocate}</td>
      <td>${team.vinte}</td>
      <td>${team.pareggiate}</td>
      <td>${team.perse}</td>
      <td>${team.gol_fatti}</td>
      <td>${team.gol_subiti}</td>
      <td>${team.differenza_reti}</td>
    `;
    tbody.appendChild(tr);
  });

  // ======== GESTIONE LEGENDA ========
  const faseSelect = document.getElementById("faseSelect");
  const legendaEsistente = document.querySelector(".legenda-coppe");

  // rimuove eventuale legenda gia presente
  if (legendaEsistente) legendaEsistente.remove();

  // crea legenda solo se siamo in fase girone
  if (!faseSelect || faseSelect.value === "girone") {
    const legenda = document.createElement("div");
    legenda.classList.add("legenda-coppe");
    legenda.innerHTML = `
      <div class="box gold-box">COPPA GOLD</div>
      <div class="box silver-box">COPPA SILVER</div>
    `;

    const wrapper = document.getElementById("classificaWrapper");
    wrapper.after(legenda);
  }
}


// ====================== MARCATORI TORNEO ======================
const MARCATORI_PER_PAGE = 15;
let marcatoriData = [];
let marcatoriPage = 1;
let marcatoriRanks = [];

function buildMarcatoriRanks() {
  marcatoriRanks = [];
  if (!Array.isArray(marcatoriData) || !marcatoriData.length) return;

  let lastKey = null;
  let lastRank = 0;
  marcatoriData.forEach((p, idx) => {
    const gol = Number(p.gol ?? 0);
    const key = `${gol}`; // ranking pari merito solo sui gol

    if (key === lastKey) {
      marcatoriRanks[idx] = lastRank;
    } else {
      lastRank = idx + 1;
      marcatoriRanks[idx] = lastRank;
      lastKey = key;
    }
  });
}

function renderMarcatoriPagina(page = 1) {
  const list = document.getElementById("marcatoriList");
  if (!list) return;

  if (!Array.isArray(marcatoriData) || marcatoriData.length === 0) {
    list.innerHTML = `<div class="marcatori-empty">Nessun dato marcatori</div>`;
    const info = document.getElementById("marcatoriPageInfo");
    if (info) info.textContent = "";
    const prev = document.getElementById("prevMarcatori");
    const next = document.getElementById("nextMarcatori");
    if (prev) prev.disabled = true;
    if (next) next.disabled = true;
    return;
  }

  const totalPages = Math.max(1, Math.ceil(marcatoriData.length / MARCATORI_PER_PAGE));
  marcatoriPage = Math.min(Math.max(1, page), totalPages);
  const start = (marcatoriPage - 1) * MARCATORI_PER_PAGE;
  const slice = marcatoriData.slice(start, start + MARCATORI_PER_PAGE);

  list.innerHTML = "";
  slice.forEach((p, idx) => {
    const globalIdx = start + idx;
    const rank = marcatoriRanks[globalIdx] ?? globalIdx + 1;
    const logo = resolveLogoPath(p.squadra, p.logo);
    const foto = p.foto || FALLBACK_AVATAR;
    const nomeCompleto = `${p.nome ?? ''} ${p.cognome ?? ''}`.trim();

    const card = document.createElement("div");
    card.className = "scorer-card";
    card.innerHTML = `
      <div class="scorer-rank">${rank}</div>
      <div class="scorer-avatar">
        <img src="${foto}" alt="${nomeCompleto}" onerror="this.onerror=null; this.src='${FALLBACK_AVATAR}';">
      </div>
      <div class="scorer-info">
        <div class="scorer-name">${nomeCompleto || 'Giocatore'}</div>
        <div class="scorer-teamline">
          <img src="${logo}" alt="${p.squadra || ''}" class="scorer-team-logo">
          <span class="scorer-team-name">${p.squadra || ''}</span>
        </div>
      </div>
      <div class="scorer-goals">
        <span class="goals-number">${p.gol ?? 0}</span>
        <span class="goals-label">Gol</span>
      </div>
    `;
    list.appendChild(card);
  });

  const prevBtn = document.getElementById("prevMarcatori");
  const nextBtn = document.getElementById("nextMarcatori");
  const info = document.getElementById("marcatoriPageInfo");
  if (info) info.textContent = `Pagina ${marcatoriPage} di ${totalPages}`;
  if (prevBtn) prevBtn.disabled = marcatoriPage <= 1;
  if (nextBtn) nextBtn.disabled = marcatoriPage >= totalPages;
}

async function caricaMarcatori(torneoSlug = TORNEO) {
  const list = document.getElementById("marcatoriList");
  if (!list) return;
  list.innerHTML = `<div class="marcatori-empty">Caricamento...</div>`;
  try {
    const res = await fetch(`/api/classifica_marcatori.php?torneo=${encodeURIComponent(torneoSlug)}`);
    const data = await res.json();
    if (!Array.isArray(data) || !data.length) {
      marcatoriData = [];
      marcatoriRanks = [];
      renderMarcatoriPagina(1);
      return;
    }
    marcatoriData = data;
    buildMarcatoriRanks();
    renderMarcatoriPagina(1);
  } catch (err) {
    console.error("Errore nel caricamento marcatori:", err);
    body.innerHTML = `<tr><td colspan="5">Errore caricamento marcatori</td></tr>`;
  }
}


// ====================== CALENDARIO (GIRONE) ======================
const roundLabelByKey = {
  "1": "Finale",
  "2": "Semifinale",
  "3": "Quarti di finale",
  "4": "Ottavi di finale",
  "5": "Sedicesimi di finale",
  "6": "Trentaduesimi di finale",
  "KO": "Fase eliminazione"
};

function updateGiornataFilter(faseSelezionata, giornateDisponibili = [], selected = "") {
  const wrapper = document.getElementById("wrapperGiornataSelect");
  const select = document.getElementById("giornataSelect");
  const label = wrapper ? wrapper.querySelector("label[for='giornataSelect']") : null;
  if (!select) return;
  const isRegular = (faseSelezionata || "").toUpperCase() === "REGULAR";

  if (wrapper) wrapper.style.display = "flex";
  if (label) label.textContent = isRegular ? "Giornata:" : "Turno:";

  select.innerHTML = "";
  if (isRegular) {
    giornateDisponibili.forEach(g => {
      select.add(new Option(`Giornata ${g}`, g));
    });
    const first = giornateDisponibili[0] || "";
    select.value = String(selected || first);
    return;
  }

  const disponibili = new Set(giornateDisponibili.map(String));
  const orderedRounds = ["1", "2", "3", "4"]; // Finale -> Ottavi
  let firstVal = "";

  orderedRounds.forEach(g => {
    if (disponibili.has(g)) {
      select.add(new Option(roundLabelByKey[g] || `Fase ${g}`, g));
      if (!firstVal) firstVal = g;
    }
  });

  if (!select.options.length) {
    giornateDisponibili.forEach(g => {
      const key = String(g);
      select.add(new Option(roundLabelByKey[key] || `Fase ${key}`, key));
      if (!firstVal) firstVal = key;
    });
  }

  const target = selected ? String(selected) : firstVal;
  if (target) select.value = target;
}

async function caricaCalendario(giornataSelezionata = "", faseSelezionata = "REGULAR") {
  try {
    const faseParam = faseSelezionata && faseSelezionata !== "REGULAR" ? `&fase=${faseSelezionata}` : "";
    const res = await fetch(`/api/get_partite.php?torneo=${TORNEO}${faseParam}`);
    const data = await res.json();

    if (data.error) {
      console.error("Errore:", data.error);
      return;
    }

    // Filtra per mostrare solo la fase scelta (per REGULAR escludiamo GOLD/SILVER)
    let dataFiltrata = data;
    if ((faseSelezionata || "").toUpperCase() === "REGULAR") {
      dataFiltrata = {};
      Object.keys(data || {}).forEach(g => {
        const matches = (data[g] || []).filter(p => (p.fase || "REGULAR").toUpperCase() === "REGULAR");
        if (matches.length) dataFiltrata[g] = matches;
      });
    }

    const calendarioSection = document.getElementById("contenitoreGiornate");
    calendarioSection.innerHTML = "";

    const giornataSelect = document.getElementById("giornataSelect");
    const wrapperGiornata = document.getElementById("wrapperGiornataSelect");
    const giornateDisponibili = Object.keys(dataFiltrata).sort((a, b) => a - b);

    updateGiornataFilter(faseSelezionata, giornateDisponibili, giornataSelezionata);

    const selectedRound = giornataSelect ? String(giornataSelect.value || "") : "";
    const giornateDaMostrare = selectedRound ? [selectedRound] : giornateDisponibili;

    giornateDaMostrare.forEach(numGiornata => {
      const giornataDiv = document.createElement("div");
      giornataDiv.classList.add("giornata");

      const titolo = document.createElement("h3");
      if ((faseSelezionata || "").toUpperCase() === "REGULAR") {
        titolo.textContent = `Giornata ${numGiornata}`;
      } else {
        const labelRound = roundLabelByKey[String(numGiornata)] || "Fase eliminazione";
        titolo.textContent = labelRound;
      }
      giornataDiv.appendChild(titolo);

            const partiteGiornata = dataFiltrata[numGiornata] || [];
      const isSemifinale = String(numGiornata) === "2";

      const renderPartita = (container, partita) => {
        const partitaDiv = document.createElement("div");
        partitaDiv.classList.add("match-card");

        const hasScore = partita.gol_casa !== null && partita.gol_ospite !== null;
        const giocata = String(partita.giocata) === "1";
        const mostraRisultato = giocata && hasScore;

        if (mostraRisultato) {
          partitaDiv.style.cursor = "pointer";
          partitaDiv.onclick = () => {
            window.location.href = `partita_eventi.php?id=${partita.id}&torneo=${TORNEO}`;
          };
        } else {
          partitaDiv.style.cursor = "default";
        }

        const dataStr = formattaData(partita.data_partita);
        const stadio = partita.campo || "Campo da definire";
        const logoCasa = resolveLogoPath(partita.squadra_casa, partita.logo_casa);
        const logoOspite = resolveLogoPath(partita.squadra_ospite, partita.logo_ospite);
        const showOra = dataStr !== "Data da definire" && partita.ora_partita;

        partitaDiv.innerHTML = `
          <div class="match-header">
            <span>
              ${stadio}
              ${
                stadio && stadio !== "Campo da definire"
                  ? `<a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(stadio)}"
                        target="_blank"
                        class="maps-link">&#128205;</a>`
                  : ""
              }
            </span>
            <span>${dataStr}${showOra ? " - " + partita.ora_partita.slice(0,5) : ""}</span>
          </div>

          <div class="match-body">
            <div class="team home">
              <img src="${logoCasa}" alt="${partita.squadra_casa}" class="team-logo">
              <span class="team-name">${partita.squadra_casa}</span>
            </div>

            <div class="match-center">
              ${
                mostraRisultato
                  ? `<span class="score">${partita.gol_casa}</span>
                     <span class="dash-cal">-</span>
                     <span class="score">${partita.gol_ospite}</span>`
                  : `<span class="vs">VS</span>`
              }
            </div>
                  
            <div class="team away">
              <img src="${logoOspite}" alt="${partita.squadra_ospite}" class="team-logo">
              <span class="team-name">${partita.squadra_ospite}</span>
            </div>
          </div>
        `;

        container.appendChild(partitaDiv);
      };

      if (isSemifinale && partiteGiornata.length) {
        const legs = {};
        partiteGiornata.forEach(p => {
          const leg = (p.fase_leg || "").toUpperCase();
          const key = leg === "RITORNO" ? "RITORNO" : (leg === "ANDATA" ? "ANDATA" : "UNICA");
          if (!legs[key]) legs[key] = [];
          legs[key].push(p);
        });
        const hasAndata = (legs.ANDATA || []).length > 0;
        const hasRitorno = (legs.RITORNO || []).length > 0;
        if (hasAndata && hasRitorno) {
          const h4a = document.createElement("h4");
          h4a.textContent = "Semifinali Andata";
          giornataDiv.appendChild(h4a);
          legs.ANDATA.forEach(p => renderPartita(giornataDiv, p));
          const h4r = document.createElement("h4");
          h4r.textContent = "Semifinali Ritorno";
          giornataDiv.appendChild(h4r);
          legs.RITORNO.forEach(p => renderPartita(giornataDiv, p));
        } else {
          const h4 = document.createElement("h4");
          h4.textContent = "Semifinali";
          giornataDiv.appendChild(h4);
          partiteGiornata.forEach(p => renderPartita(giornataDiv, p));
        }
      } else {
        partiteGiornata.forEach(p => renderPartita(giornataDiv, p));
      }
calendarioSection.appendChild(giornataDiv);
    });
  } catch (err) {
    console.error("Errore nel caricamento del calendario:", err);
  }
}

// ====================== PLAYOFF STILE CALENDARIO ======================
async function caricaPlayoff(tipoCoppa) {
  const faseParam = (tipoCoppa || "gold").toUpperCase(); // GOLD / SILVER
  const container = document.getElementById("playoffContainer");

  (function ensureBracketStyles() {
    if (window.__BRACKET_STYLES__) return;
    window.__BRACKET_STYLES__ = true;
    const style = document.createElement("style");
    style.textContent = `
      .bracket-wrapper { display: flex; gap: 12px; overflow-x: auto; padding: 6px 2px; }
      .bracket-col { flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 10px; }
      .bracket-col-title { font-weight: 800; color: #15293e; margin: 4px 2px; }
      .bracket-match { background: #fff; border: 1px solid #dce3ef; border-radius: 14px; padding: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); display: flex; flex-direction: column; gap: 8px; transition: transform .15s, box-shadow .15s, border-color .15s; cursor: default; }
      .bracket-match.is-played { border-color: #15293e; cursor: pointer; }
      .bracket-match.is-played:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(0,0,0,0.1); }
      .bracket-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
      .bracket-status { font-size: 12px; font-weight: 800; padding: 4px 8px; border-radius: 999px; background: #eef3ff; color: #15293e; text-transform: uppercase; }
      .bracket-status.played { background: #15293e; color: #fff; }
      .bracket-leg { font-size: 12px; font-weight: 700; color: #4c5b71; }
      .bracket-team { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
      .bracket-team .team-side { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
      .bracket-team .team-name { font-weight: 800; color: #15293e; text-transform: none; white-space: normal; word-break: break-word; }
      .bracket-team .team-logo { width: 34px; height: 34px; object-fit: contain; }
      .bracket-team .team-score { font-weight: 800; font-size: 18px; color: #15293e; min-width: 24px; text-align: right; }
      .bracket-meta { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #4c5b71; }
      .bracket-pair { display: flex; flex-direction: column; gap: 10px; position: relative; padding-right: 8px; }
      .bracket-connector { display: flex; flex-direction: column; gap: 8px; font-size: 12px; color: #4c5b71; font-weight: 700; padding: 2px 4px 6px; }
      .bracket-connector .next-label { display: inline-flex; align-items: center; gap: 6px; }
      .bracket-connector .next-label::after { content: ""; flex: 1; height: 1px; background: #dce3ef; border-radius: 999px; }
      .bracket-next { display: flex; flex-direction: column; gap: 6px; background: #f7f9fd; border: 1px dashed #dce3ef; border-radius: 10px; padding: 10px; }
      .bracket-next .next-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
      .bracket-next .team-side { display: inline-flex; align-items: center; gap: 8px; min-width: 0; white-space: normal; }
      .bracket-next .team-logo { width: 26px; height: 26px; object-fit: contain; }
      .bracket-next .team-name { font-weight: 700; color: #15293e; text-transform: none; white-space: normal; word-break: break-word; }
      .bracket-next .team-score { font-weight: 800; min-width: 20px; text-align: right; color: #15293e; }
      .bracket-next .next-meta { font-size: 11px; color: #4c5b71; }
      .leg-toggle { display: inline-flex; gap: 6px; background: #f3f6fb; padding: 4px; border-radius: 999px; }
      .leg-btn { border: 1px solid #cbd5e1; background: #fff; color: #15293e; padding: 6px 10px; border-radius: 999px; font-weight: 700; cursor: pointer; }
      .leg-btn.active { background: #15293e; color: #fff; border-color: #15293e; }
      .leg-btn:disabled { opacity: 0.5; cursor: not-allowed; }
      .leg-content { display: none; }
      .leg-content.active { display: block; }
    `;
    document.head.appendChild(style);
  })();

  container.innerHTML = `
    <h3 class="bracket-titolo">Playoff ${tipoCoppa === "gold" ? "COPPA GOLD" : "COPPA SILVER"}</h3>
    <div class="bracket-wrapper" id="fasiPlayoff"></div>
  `;

  try {
    const res = await fetch(`/api/get_partite.php?torneo=${encodeURIComponent(TORNEO)}&fase=${faseParam}`);
    const data = await res.json();

    if (data.error) {
      container.innerHTML += `<p>Errore nel caricamento delle partite playoff.</p>`;
      return;
    }

    const fasiMap = { 1: "Finale", 2: "Semifinali", 3: "Quarti di finale", 4: "Ottavi di finale" };
    const fasiContainer = document.getElementById("fasiPlayoff");
    fasiContainer.innerHTML = "";

    const baseMatch = (casa, ospite, faseLeg = "") => ({
      squadra_casa: casa,
      squadra_ospite: ospite,
      gol_casa: null,
      gol_ospite: null,
      giocata: 0,
      data_partita: "",
      ora_partita: "",
      campo: "Da definire",
      fase_leg: faseLeg
    });

    const defaultOttavi = [
      [1, 16], [2, 15], [3, 14], [4, 13],
      [5, 12], [6, 11], [7, 10], [8, 9],
    ].map(([a, b]) => baseMatch(`${a}\u00B0 in classifica`, `${b}\u00B0 in classifica`, `${a} vs ${b}`));

    const defaultSemifinaliGold = [
      baseMatch("Vincente Quarto 1", "Vincente Quarto 4"),
      baseMatch("Vincente Quarto 2", "Vincente Quarto 3"),
    ];
    const defaultFinaleGold = [baseMatch("Vincente Semifinale 1", "Vincente Semifinale 2")];

    const defaultSemifinaliSilver = [
      baseMatch("Silver Seed 1", "Silver Seed 4"),
      baseMatch("Silver Seed 2", "Silver Seed 3"),
    ];
    const defaultFinaleSilver = [baseMatch("Vincente Semifinale 1", "Vincente Semifinale 2")];

    const getDefaultMatches = (giornata) => {
      if (faseParam === "GOLD") {
        if (giornata === 4) return defaultOttavi;
        if (giornata === 3) return defaultQuarti;
        if (giornata === 2) return defaultSemifinaliGold;
        if (giornata === 1) return defaultFinaleGold;
      } else {
        if (giornata === 2) return defaultSemifinaliSilver;
        if (giornata === 1) return defaultFinaleSilver;
      }
      return [];
    };

    const ordineGiornate = [4, 3, 2, 1];

    ordineGiornate.forEach(g => {
      const matchList = data[g] || [];
      const elenco = matchList.length ? matchList : getDefaultMatches(g);
      if (!elenco.length) return;

      const col = document.createElement("div");
      col.className = "bracket-col";
      const titolo = document.createElement("div");
      titolo.className = "bracket-col-title";
      titolo.textContent = fasiMap[g] || `Fase ${g}`;
      col.appendChild(titolo);

      const pairMap = {};
      elenco.forEach(p => {
        const key = [p.squadra_casa || "", p.squadra_ospite || ""].sort((a, b) => a.localeCompare(b)).join("|||");
        if (!pairMap[key]) pairMap[key] = [];
        pairMap[key].push(p);
      });

      Object.values(pairMap).forEach(group => {
        const legOrder = { ANDATA: 1, RITORNO: 2 };
        group.sort((a, b) => (legOrder[(a.fase_leg || "").toUpperCase()] || 99) - (legOrder[(b.fase_leg || "").toUpperCase()] || 99));
        const hasAndata = group.some(p => (p.fase_leg || "").toUpperCase() === "ANDATA");
        const hasRitorno = group.some(p => (p.fase_leg || "").toUpperCase() === "RITORNO");
        const defaultLeg = hasAndata ? "ANDATA" : (group[0].fase_leg || "").toUpperCase() || "ANDATA";

        const match = document.createElement("div");
        match.className = "bracket-match";

        const head = document.createElement("div");
        head.className = "bracket-head";
        if (group.length > 1) {
          head.innerHTML = `
            <div class="leg-toggle" data-selected="${defaultLeg}">
              <button type="button" class="leg-btn ${defaultLeg === 'ANDATA' ? 'active' : ''}" data-leg="ANDATA" ${hasAndata ? '' : 'disabled'}>Andata</button>
              <button type="button" class="leg-btn ${defaultLeg === 'RITORNO' ? 'active' : ''}" data-leg="RITORNO" ${hasRitorno ? '' : 'disabled'}>Ritorno</button>
            </div>
          `;
        } else {
          head.innerHTML = "";
        }
        match.appendChild(head);

        const contentWrap = document.createElement("div");
        contentWrap.className = "leg-contents";

        const buildLeg = (partita, activeLeg) => {
          const hasScore = partita.gol_casa !== null && partita.gol_ospite !== null;
          const giocata = (Number(partita.giocata) === 1);
          const mostraRisultato = giocata && hasScore;
          const logoCasa = resolveLogoPath(partita.squadra_casa, partita.logo_casa);
          const logoOspite = resolveLogoPath(partita.squadra_ospite, partita.logo_ospite);
          const dataStr = formattaData(partita.data_partita);
          const showOra = dataStr !== "Data da definire" && partita.ora_partita;
          const body = document.createElement("div");
          body.className = "leg-content" + (activeLeg ? " active" : "");
          body.dataset.leg = (partita.fase_leg || "").toUpperCase() || "UNICA";
          body.innerHTML = `
            <div class="bracket-team">
              <div class="team-side">
                <img class="team-logo" src="${logoCasa}" alt="${partita.squadra_casa}">
                <span class="team-name">${partita.squadra_casa}</span>
              </div>
              <span class="team-score">${mostraRisultato ? partita.gol_casa : "-"}</span>
            </div>
            <div class="bracket-team">
              <div class="team-side">
                <img class="team-logo" src="${logoOspite}" alt="${partita.squadra_ospite}">
                <span class="team-name">${partita.squadra_ospite}</span>
              </div>
              <span class="team-score">${mostraRisultato ? partita.gol_ospite : "-"}</span>
            </div>
            <div class="bracket-meta">
              <span>${dataStr}${showOra ? ' - ' + partita.ora_partita.slice(0,5) : ''}</span>
              <span>${partita.campo || 'Campo da definire'}</span>
            </div>
          `;
          if (mostraRisultato) {
            body.addEventListener("click", () => {
              window.location.href = `partita_eventi.php?id=${partita.id}&torneo=${encodeURIComponent(TORNEO)}`;
            });
            body.style.cursor = "pointer";
          }
          return body;
        };

        group.forEach(p => {
          const legKey = (p.fase_leg || "").toUpperCase() || "UNICA";
          const isActive = legKey === defaultLeg;
          contentWrap.appendChild(buildLeg(p, isActive));
        });

        match.appendChild(contentWrap);

        if (group.length > 1) {
          const buttons = head.querySelectorAll('.leg-btn');
          buttons.forEach(btn => {
            btn.addEventListener('click', () => {
              const target = (btn.dataset.leg || '').toUpperCase();
              contentWrap.querySelectorAll('.leg-content').forEach(el => {
                el.classList.toggle('active', (el.dataset.leg || '') === target);
              });
              buttons.forEach(b => b.classList.toggle('active', b === btn));
            });
          });
        }

        col.appendChild(match);
      });

      fasiContainer.appendChild(col);
    });

  } catch (err) {
    console.error("Errore nel caricamento playoff:", err);
    container.innerHTML += `<p>Errore nel caricamento playoff.</p>`;
  }
}// ====================== ROSE SQUADRE ======================
async function caricaSquadrePerRosa() {
  try {
    const res = await fetch(`/api/leggiClassifica.php?torneo=${TORNEO}`);
    const squadre = await res.json();
    const seenLogos = new Set();
    const filteredSquadre = (squadre || []).filter(sq => {
      const key = (sq.logo || "").trim();
      if (!key) return true;
      if (seenLogos.has(key)) return false;
      seenLogos.add(key);
      return true;
    });

    const select = document.getElementById("selectSquadra");
    select.innerHTML = ""; // Pulisce eventuali opzioni precedenti

    // 1) Ordina le squadre in ordine alfabetico (A - Z)
    filteredSquadre.sort((a, b) => a.nome.localeCompare(b.nome, 'it', { sensitivity: 'base' }));

    // 2) Popola la select e imposta la prima come selezionata
    filteredSquadre.forEach((sq, index) => {
      if (sq.logo) {
        teamLogos[sq.nome] = sq.logo;
      }
      const opt = document.createElement("option");
      opt.value = sq.nome;
      opt.textContent = sq.nome;
      if (index === 0) opt.selected = true; // Prima squadra di default
      select.appendChild(opt);
    });

    // 3) Mostra subito la rosa della prima squadra
    if (filteredSquadre.length > 0) {
      caricaRosaSquadra(filteredSquadre[0].nome);
    }

    // 4) Evento cambio squadra
    select.addEventListener("change", () => {
      const squadra = select.value;
      if (squadra) caricaRosaSquadra(squadra);
      else document.getElementById("rosaContainer").innerHTML = "";
    });

  } catch (err) {
    console.error("Errore nel caricamento squadre per rosa:", err);
  }
}


async function caricaRosaSquadra(squadra) {
  currentRosaTeam = squadra;
  try {
    const res = await fetch(`/api/get_rosa.php?torneo=${TORNEO}&squadra=${encodeURIComponent(squadra)}`);
    const data = await res.json();

    const container = document.getElementById("rosaContainer");
    container.innerHTML = "";

    // intestazione con logo + nome squadra
  const header = document.createElement("div");
  header.classList.add("rosa-header");
  const squadraLogo = resolveLogoPath(squadra, (data[0] && data[0].logo_squadra) || teamLogos[squadra]);

  header.innerHTML = `
      <img src="${squadraLogo}" alt="${squadra}" class="team-logo-large">
      <h3>${squadra}</h3>
      <button type="button" class="fav-toggle fav-toggle--small fav-team-btn" aria-label="Segui la squadra">?</button>
    `;
    const favBtn = header.querySelector(".fav-team-btn");
    if (favBtn) {
      updateFavTeamButton(squadra, favBtn);
      favBtn.addEventListener("click", () => toggleTeamFollow(squadra, favBtn));
    }
    container.appendChild(header);

    // elenco giocatori
    const grid = document.createElement("div");
    grid.classList.add("rosa-grid");

    data.forEach(giocatore => {
      const card = document.createElement("div");
      card.classList.add("player-card");

card.innerHTML = `
  <div class="player-name-row">
    <h4 class="player-name">${giocatore.nome} ${giocatore.cognome}</h4>
  </div>

  <div class="player-team-row">
    <img src="${squadraLogo}" alt="${squadra}" class="player-team-logo">
    <span class="player-team-name">${squadra}</span>
  </div>

  <div class="player-bottom">
    <div class="player-photo">
      <img src="${giocatore.foto}" 
           alt="${giocatore.nome} ${giocatore.cognome}">
    </div>

    <div class="player-stats">
      <div class="row">
        <div class="stat">
          <span class="label">Presenze</span>
          <span class="value">${giocatore.presenze ?? '0'}</span>
        </div>
        <div class="stat">
          <span class="label">Cart. Gialli / Rossi</span>
          <span class="value">
            <span class="yellow">${giocatore.gialli ?? '0'}</span>/
            <span class="red"> ${giocatore.rossi ?? '0'}</span>
          </span>
        </div>
      </div>

      <div class="row">
        <div class="stat">
          <span class="label">Reti</span>
          <span class="value">${giocatore.reti ?? '0'}</span>
        </div>
        <div class="stat rating">
          <span class="label">Media Voti</span>
          <span class="value">${giocatore.media_voti ?? '0'}</span>
        </div>
      </div>
    </div>
  </div>
`;






      grid.appendChild(card);
    });

    container.appendChild(grid);
  } catch (err) {
    console.error("Errore nel caricamento della rosa:", err);
  }
}

// ====================== GESTIONE UI DINAMICA ======================
document.addEventListener("DOMContentLoaded", () => {
  // helper pill toggle per select
  const buildPillToggle = (selectEl) => {
    if (!selectEl) return null;
    const wrap = document.createElement("div");
    wrap.className = "pill-toggle-group";
    Array.from(selectEl.options || []).forEach(opt => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = opt.textContent;
      btn.dataset.value = opt.value;
      btn.className = "pill-btn pill-btn--toggle";
      if (opt.selected) btn.classList.add("active");
      btn.addEventListener("click", () => {
        selectEl.value = opt.value;
        selectEl.dispatchEvent(new Event("change"));
        wrap.querySelectorAll("button").forEach(b => b.classList.toggle("active", b === btn));
      });
      wrap.appendChild(btn);
    });
    selectEl.classList.add("visually-hidden");
    selectEl.after(wrap);
    return wrap;
  };

  const faseSelect = document.getElementById("faseSelect");
  const coppaSelect = document.getElementById("coppaSelect");
  const classificaWrapper = document.getElementById("classificaWrapper");
  const playoffContainer = document.getElementById("playoffContainer");
  const heroImg = document.getElementById("torneoHeroImg");
  const torneoTitle = document.querySelector(".torneo-title .titolo");
  const loadClassifica = (slug) => caricaClassifica(slug || TORNEO);
  const favTorneoBtn = document.getElementById("favTournamentBtn");
  if (favTorneoBtn) {
    favTorneoBtn.addEventListener("click", () => toggleTournamentFollow(favTorneoBtn));
  }
  loadFavorites();

  // carico subito la parte girone
  caricaClassifica();
  const faseCalendario = document.getElementById("faseCalendario");
  const giornataSelect = document.getElementById("giornataSelect");
  if (faseCalendario) {
    faseCalendario.value = "REGULAR";
  }
  const prevMarcatoriBtn = document.getElementById("prevMarcatori");
  const nextMarcatoriBtn = document.getElementById("nextMarcatori");

  const triggerCalendario = () => {
    const faseVal = (faseCalendario?.value || "REGULAR").toUpperCase();
    const gVal = (giornataSelect?.value || "");
    caricaCalendario(gVal, faseVal);
  };

  caricaCalendario("", "REGULAR");
  caricaSquadrePerRosa();
  caricaMarcatori();
  if (heroImg) {
    fetch(`/api/get_torneo_by_slug.php?slug=${encodeURIComponent(TORNEO)}`)
      .then(res => res.json())
      .then(data => {
        if (!data || data.error) return;
        heroImg.src = data.img || "/img/tornei/pallone.png";
        if (torneoTitle && data.nome) torneoTitle.textContent = data.nome;
      })
      .catch(err => console.error("Errore recupero info torneo:", err));
  }

  // filtro calendario giornate
  if (giornataSelect) {
    giornataSelect.addEventListener("change", () => triggerCalendario());
  }

  if (faseCalendario) {
    faseCalendario.addEventListener("change", () => {
      if (giornataSelect) giornataSelect.value = "";
      triggerCalendario();
    });
  }

  if (prevMarcatoriBtn) {
    prevMarcatoriBtn.addEventListener("click", () => renderMarcatoriPagina(marcatoriPage - 1));
  }
  if (nextMarcatoriBtn) {
    nextMarcatoriBtn.addEventListener("click", () => renderMarcatoriPagina(marcatoriPage + 1));
  }

  // cambio fase girone/eliminazione
  faseSelect.addEventListener("change", () => {
    const legendaEsistente = document.querySelector(".legenda-coppe");
    if (legendaEsistente) legendaEsistente.remove();

    if (faseSelect.value === "eliminazione") {
      // mostra bracket playoff
      classificaWrapper.style.display = "none";
      playoffContainer.style.display = "block";

      // se non � selezionata nessuna coppa ancora, default gold
      if (!coppaSelect.value) {
        coppaSelect.value = "gold";
      }

      caricaPlayoff(coppaSelect.value);

    } else {
      // torna alla classifica
      playoffContainer.style.display = "none";
      classificaWrapper.style.display = "block";
      loadClassifica();
    }
  });

  // cambio coppa (gold/silver)
  coppaSelect.addEventListener("change", () => {
    if (faseSelect.value === "eliminazione") {
      caricaPlayoff(coppaSelect.value);
    }
    // la classifica rimane quella del torneo base; le coppe usano solo le partite filtrate per fase
    loadClassifica(TORNEO);
  });

  // toggle pill per select classifica
  const faseToggle = buildPillToggle(faseSelect);
  const coppaToggle = buildPillToggle(coppaSelect);
  const faseCalendarioToggle = buildPillToggle(faseCalendario);
  const syncCoppaToggle = () => {
    const isElim = faseSelect.value === "eliminazione";
    if (coppaToggle) coppaToggle.style.display = isElim ? "flex" : "none";
  };
  if (faseToggle || coppaToggle) {
    syncCoppaToggle();
    faseSelect.addEventListener("change", syncCoppaToggle);
  }
});

// ====================== GESTIONE TAB NAVIGAZIONE ======================
document.querySelectorAll(".tab-button").forEach(btn => {
  btn.addEventListener("click", () => {
    document.querySelectorAll(".tab-button").forEach(b => b.classList.remove("active"));
    document.querySelectorAll(".tab-section").forEach(s => s.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById(btn.dataset.tab).classList.add("active");
    if (btn.dataset.tab === "marcatori") {
      renderMarcatoriPagina(marcatoriPage);
    }
  });
});









