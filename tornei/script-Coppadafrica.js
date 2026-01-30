const TORNEO = "Coppadafrica"; // Nome base del torneo nel DB (fase girone)
const GIRONE_CONFIG = {
  B: { ids: [], names: ["ALGERIA", "COSTA D'AVORIO", "GHANA", "NIGERIA"] },
  A: { ids: [], names: ["CAMERUN", "EGITTO", "MAROCCO", "SENEGAL"] },
};
const teamLogos = {};
const FALLBACK_AVATAR = "/img/giocatori/unknown.jpg";
const favState = { tournaments: new Set() };
let partiteCache = null;

(function applyGironiOverride() {
  const override = (typeof window !== "undefined" && window.COPPAAF_GIRONI_OVERRIDE) || null;
  if (!override) return;
  ["A", "B"].forEach((g) => {
    const cfg = override[g];
    if (!cfg) return;
    if (Array.isArray(cfg)) {
      GIRONE_CONFIG[g].ids = cfg.map((n) => Number(n)).filter((n) => !Number.isNaN(n));
      GIRONE_CONFIG[g].names = [];
    } else {
      if (Array.isArray(cfg.ids)) {
        GIRONE_CONFIG[g].ids = cfg.ids.map((n) => Number(n)).filter((n) => !Number.isNaN(n));
      }
      if (Array.isArray(cfg.names)) {
        GIRONE_CONFIG[g].names = cfg.names.map((n) => String(n));
      }
    }
  });
})();

function normalizeLogoName(name = "") {
  return name.replace(/[^A-Za-z0-9]/g, "");
}

function resolveLogoPath(name, storedPath) {
  if (storedPath) return storedPath;
  const cached = teamLogos[name];
  if (cached) return cached;
  const slug = normalizeLogoName(name || "");
  const lower = (name || "").toLowerCase();
  const isPlaceholderName = lower.includes("vincente") || lower.includes("seconda") || lower.includes("girone");
  if (!slug || isPlaceholderName) return "/img/scudetti/placeholder-dark.svg";
  return `/img/scudetti/${slug}.png`;
}

function updateFavTournamentButton() {
  const btn = document.getElementById("favTournamentBtn");
  if (!btn) return;
  const isFav = favState.tournaments.has(TORNEO);
  btn.classList.toggle("is-fav", isFav);
  btn.textContent = isFav ? "★ Torneo seguito" : "☆ Segui torneo";
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

async function loadFavorites() {
  try {
    const res = await fetch("/api/follow.php", { credentials: "include" });
    if (!res.ok) return;
    const data = await res.json();
    favState.tournaments = new Set(data.tournaments || []);
    updateFavTournamentButton();
  } catch (e) {
    console.error("Errore caricamento preferiti", e);
  }
}

// ====================== UTILS ======================
function formattaData(data) {
  if (!data || data === "2000-01-01") return "Data da definire";
  const [anno, mese, giorno] = data.split("-");
  return `${giorno}/${mese}/${anno}`;
}

function formattaDataOra(data, ora) {
  const base = formattaData(data);
  if (ora && ora !== "00:00:00") return `${base} - ${ora.slice(0, 5)}`;
  return base;
}

function faseKey(fase = "") {
  const f = (fase || "").trim().toUpperCase();
  return f === "" || f === "GIRONE" ? "REGULAR" : f;
}

async function loadPartiteTorneo() {
  if (partiteCache) return partiteCache;
  try {
    const res = await fetch(`/api/get_partite.php?torneo=${TORNEO}&fase=REGULAR`);
    const data = await res.json();
    const list = Array.isArray(data) ? data : Object.values(data || {}).flat();
    partiteCache = list;
    return list;
  } catch (e) {
    console.error("Errore nel caricamento partite torneo", e);
    partiteCache = [];
    return [];
  }
}

function ensureTeamMatchesStyle() {
  if (document.getElementById("team-matches-style")) return;
  const style = document.createElement("style");
  style.id = "team-matches-style";
  style.textContent = `
    #teamMatchesModal {position:fixed; inset:0; background:rgba(12,24,38,0.65); display:flex; align-items:flex-start; justify-content:center; padding:32px 16px; z-index:9999;}
    #teamMatchesCard {width:100%; max-width:560px; background:#fff; border-radius:14px; padding:16px 18px 10px; box-shadow:0 14px 36px rgba(0,0,0,0.18); border:1px solid #d7e0ec;}
    #teamMatchesHeader {display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px;}
    #teamMatchesHeader h3 {margin:0; font-size:18px; color:#102033;}
    #teamMatchesClose {border:none; background:#f0f4fa; width:34px; height:34px; border-radius:10px; cursor:pointer; font-size:18px; line-height:1;}
    #teamMatchesList {list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; max-height:60vh; overflow:auto;}
    #teamMatchesList li {padding:10px 12px; border:1px solid #e5edf7; border-radius:10px; display:flex; justify-content:space-between; gap:12px; align-items:center; background:#f9fbff;}
    #teamMatchesList .match-meta {display:block; color:#6b7892; font-size:12px;}
    #teamMatchesList .match-vs {font-weight:700; color:#0f2742;}
    #teamMatchesList .score {font-weight:800; font-size:15px; padding:6px 10px; border-radius:10px; min-width:64px; text-align:center;}
    #teamMatchesList .score.win {background:#e7f7ed; color:#1f7a4d;}
    #teamMatchesList .score.draw {background:#f4f4f4; color:#555;}
    #teamMatchesList .score.lose {background:#ffecef; color:#c12d3c;}
    #teamMatchesEmpty {padding:8px 10px; color:#6b7892;}
  `;
  document.head.appendChild(style);
}

function ensureTeamMatchesModal() {
  ensureTeamMatchesStyle();
  let modal = document.getElementById("teamMatchesModal");
  if (modal) return modal;
  modal = document.createElement("div");
  modal.id = "teamMatchesModal";
  modal.style.display = "none";
  modal.innerHTML = `
    <div id="teamMatchesCard" role="dialog" aria-modal="true">
      <div id="teamMatchesHeader">
        <h3>Partite squadra</h3>
        <button id="teamMatchesClose" aria-label="Chiudi">×</button>
      </div>
      <ul id="teamMatchesList"></ul>
      <div id="teamMatchesEmpty" style="display:none;"></div>
    </div>
  `;
  document.body.appendChild(modal);
  modal.addEventListener("click", (e) => {
    if (e.target.id === "teamMatchesModal") modal.style.display = "none";
  });
  document.getElementById("teamMatchesClose").addEventListener("click", () => {
    modal.style.display = "none";
  });
  return modal;
}

function esitoLabel(p, squadra) {
  const gf = squadra === p.squadra_casa ? p.gol_casa : p.gol_ospite;
  const gs = squadra === p.squadra_casa ? p.gol_ospite : p.gol_casa;
  if (gf === null || gf === undefined || gs === null || gs === undefined) return { label: "ND", cls: "draw" };
  if (gf > gs) return { label: "V", cls: "win" };
  if (gf === gs) return { label: "N", cls: "draw" };
  return { label: "P", cls: "lose" };
}

async function mostraPartiteSquadra(squadra) {
  const modal = ensureTeamMatchesModal();
  const listEl = document.getElementById("teamMatchesList");
  const emptyEl = document.getElementById("teamMatchesEmpty");
  if (!listEl || !emptyEl) return;

  listEl.innerHTML = "";
  emptyEl.style.display = "none";
  document.querySelector("#teamMatchesHeader h3").textContent = `Partite ${squadra}`;

  const partite = (await loadPartiteTorneo()).filter(p => faseKey(p.fase) === "REGULAR" && (p.squadra_casa === squadra || p.squadra_ospite === squadra));
  partite.sort((a, b) => (a.data_partita || "").localeCompare(b.data_partita || "") || (a.ora_partita || "").localeCompare(b.ora_partita || ""));

  if (!partite.length) {
    emptyEl.textContent = "Nessuna partita giocata in Regular Season per questa squadra.";
    emptyEl.style.display = "block";
  } else {
    partite.forEach(p => {
      const casa = p.squadra_casa === squadra;
      const avversario = casa ? p.squadra_ospite : p.squadra_casa;
      const score = (p.gol_casa === null || p.gol_casa === undefined || p.gol_ospite === null || p.gol_ospite === undefined)
        ? "—"
        : `${p.gol_casa} - ${p.gol_ospite}`;
      const esito = esitoLabel(p, squadra);
      const li = document.createElement("li");
      li.innerHTML = `
        <div>
          <span class="match-vs">${casa ? "Casa" : "Trasferta"} vs ${avversario}</span>
          <span class="match-meta">${formattaDataOra(p.data_partita, p.ora_partita)} · ${p.campo || "Campo da definire"}</span>
        </div>
        <div class="score ${esito.cls}" title="Esito">${score} ${esito.label !== "ND" ? "(" + esito.label + ")" : ""}</div>
      `;
      listEl.appendChild(li);
    });
  }

  modal.style.display = "flex";
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

    // Clic su una squadra: mostra elenco partite giocate in Regular Season
    document.querySelectorAll("#tableClassificaA tbody .team-cell, #tableClassificaB tbody .team-cell").forEach(cell => {
      const squadra = cell.querySelector(".team-name")?.textContent.trim();
      if (!squadra) return;

      const apriPartite = (e) => {
        if (e) {
          e.preventDefault();
          e.stopPropagation();
        }
        mostraPartiteSquadra(squadra);
      };

      cell.style.cursor = "pointer";
      cell.setAttribute("role", "button");
      cell.setAttribute("tabindex", "0");

      cell.addEventListener("click", apriPartite);
      cell.addEventListener("touchend", apriPartite, { passive: false });
      cell.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") apriPartite(e);
      });
    });
  } catch (error) {
    console.error("Errore nel caricamento della classifica:", error);
  }
}

function mostraClassifica(classifica) {
  const tbodyA = document.querySelector("#tableClassificaA tbody");
  const tbodyB = document.querySelector("#tableClassificaB tbody");
  if (tbodyA) tbodyA.innerHTML = "";
  if (tbodyB) tbodyB.innerHTML = "";

  const sortTeams = (a, b) =>
    b.punti - a.punti ||
    b.differenza_reti - a.differenza_reti ||
    b.gol_fatti - a.gol_fatti ||
    a.gol_subiti - b.gol_subiti ||
    a.nome.localeCompare(b.nome, "it", { sensitivity: "base" });

  const normalizeId = (team) => Number(team.id);
  const normalizeName = (name = "") => name.trim().toLowerCase();
  const extractSeedFromName = (name = "") => {
    const m = String(name).match(/\d+/);
    return m ? Number(m[0]) : NaN;
  };
  const matchTeamToGirone = (team) => {
    const idNum = normalizeId(team);
    const name = normalizeName(team.nome);
    const seed = extractSeedFromName(team.nome);
    const matchesCfg = (cfg) =>
      (idNum && cfg.ids.includes(idNum)) ||
      cfg.names.some((n) => normalizeName(n) === name) ||
      (!Number.isNaN(seed) && cfg.ids.includes(seed));
    if (matchesCfg(GIRONE_CONFIG.A)) return "A";
    if (matchesCfg(GIRONE_CONFIG.B)) return "B";
    return "";
  };

  const gironeA = [];
  const gironeB = [];
  const leftovers = [];

  classifica.forEach((team) => {
    const g = matchTeamToGirone(team);
    if (g === "A") gironeA.push(team);
    else if (g === "B") gironeB.push(team);
    else leftovers.push(team);
  });

  leftovers.sort(sortTeams);

  const fillGroup = (group, targetSize) => {
    while (group.length < targetSize && leftovers.length) {
      group.push(leftovers.shift());
    }
    group.sort(sortTeams);
    if (group.length > targetSize) group.length = targetSize;
  };

  fillGroup(gironeA, 4);
  fillGroup(gironeB, 4);

  const renderGroup = (rows, tbody) => {
    if (!tbody) return;
    rows.forEach((team, idx) => {
      const tr = document.createElement("tr");
      if (idx + 1 <= 2) tr.classList.add("gold-row");
      const logoPath = resolveLogoPath(team.nome, team.logo);
      tr.innerHTML = `
        <td class="pos-cell">${idx + 1}</td>
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
  };

  renderGroup(gironeA, tbodyA);
  renderGroup(gironeB, tbodyB);

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
      <div class="box gold-box">Prime 2: accesso alle semifinali</div>
    `;

    const wrapper = document.getElementById("classificaWrapper");
    wrapper.after(legenda);
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

async function caricaCalendario(giornataSelezionata = "", faseSelezionata = "REGULAR") {
  try {
    const faseKey = (faseSelezionata || "REGULAR").toUpperCase();
    const isGironi = faseKey === "REGULAR" || faseKey === "GIRONI";
    const faseParam = isGironi ? "" : `&fase=GOLD`;
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
    let giornateDisponibili = Object.keys(dataFiltrata).sort((a, b) => a - b);
    const previousTurn = giornataSelect ? giornataSelect.value : "";

    // mostra la select anche nei gironi (rinominata "Turno")
    if (wrapperGiornata) {
      wrapperGiornata.style.display = "flex";
    }

    // Gestione select giornate: nascosta sui gironi, attiva solo per fase finale (semi/finale)
    if (giornataSelect) {
      if (isGironi) {
        giornataSelect.innerHTML = '<option value="">Tutti</option>';
        giornateDisponibili.forEach(g => {
          const opt = document.createElement("option");
          opt.value = g;
          opt.textContent = `Turno ${g}`;
          giornataSelect.appendChild(opt);
        });
        if (previousTurn) {
          const hasPrev = Array.from(giornataSelect.options).some(opt => opt.value === previousTurn);
          if (hasPrev) giornataSelect.value = previousTurn;
        }
      } else {
        // mostra solo semifinali (2) e finale (1)
        giornateDisponibili = giornateDisponibili.filter(g => g === "1" || g === "2");
        if (!giornateDisponibili.includes("1")) giornateDisponibili.unshift("1");
        if (!giornateDisponibili.includes("2")) giornateDisponibili.push("2");
        giornataSelect.innerHTML = '<option value="">Tutte</option>';
        giornateDisponibili.forEach(g => {
          const opt = document.createElement("option");
          opt.value = g;
          opt.textContent = g === "1" ? "Finale" : g === "2" ? "Semifinali" : `Fase ${g}`;
          giornataSelect.appendChild(opt);
        });
        if (previousTurn && giornateDisponibili.includes(previousTurn)) {
          giornataSelect.value = previousTurn;
        }
      }
    }

    const giornateDaMostrare = (giornataSelect && giornataSelect.value)
      ? [giornataSelect.value]
      : giornateDisponibili;

    giornateDaMostrare.forEach(numGiornata => {
      const giornataDiv = document.createElement("div");
      giornataDiv.classList.add("giornata");

      const partiteGiornata = dataFiltrata[numGiornata] || [];
      const isRegularBlock = partiteGiornata.every(
        (p) => (p.fase || "REGULAR").toUpperCase() === "REGULAR"
      );
      const isSemifinale = !isGironi && !isRegularBlock && String(numGiornata) === "2";

      if (!isGironi && !isRegularBlock) {
        const titolo = document.createElement("h3");
        const labelRound = roundLabelByKey[String(numGiornata)] || "Fase eliminazione";
        titolo.textContent = labelRound;
        giornataDiv.appendChild(titolo);
      }

      const renderPartita = (container, partita) => {
        const partitaDiv = document.createElement("div");
        partitaDiv.classList.add("match-card");

        const hasScore = partita.gol_casa !== null && partita.gol_ospite !== null;
        const giocata = String(partita.giocata) === "1";
        const mostraRisultato = giocata && hasScore;
        const aiRigori = mostraRisultato && Number(partita.decisa_rigori || 0) === 1;
        const hasPenalties = aiRigori && partita.rigori_casa !== null && partita.rigori_casa !== undefined && partita.rigori_ospite !== null && partita.rigori_ospite !== undefined;

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
          ${hasPenalties ? `<div class="match-penalties">d.c.r. ${partita.rigori_casa}-${partita.rigori_ospite}</div>` : ""}
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
          // Semifinali a gara secca: non duplicare il titolo
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
async function caricaPlayoff() {
  if (!window.__COPPA_AF_BRACKET_STYLE__) {
    window.__COPPA_AF_BRACKET_STYLE__ = true;
    const st = document.createElement("style");
    st.textContent = `
      .bracket-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
      .bracket-filters { display:flex; gap:8px; }
      .bracket-filter-btn { border:1px solid #d7deea; background:#f4f6fb; color:#15293e; padding:8px 12px; border-radius:10px; font-weight:700; cursor:pointer; transition:all 0.15s ease; }
      .bracket-filter-btn.active { background:#15293e; color:#fff; border-color:#15293e; box-shadow:0 8px 18px rgba(21,41,62,0.25); }
      .bracket-wrapper { display:flex; gap:12px; overflow-x:auto; padding:6px 2px; }
      .bracket-col { flex:1; min-width:260px; display:flex; flex-direction:column; gap:10px; }
      .bracket-col-title { font-weight:800; color:#15293e; margin:4px 2px; }
      .bracket-match { background:#fff; border:1px solid #dce3ef; border-radius:14px; padding:12px; box-shadow:0 8px 20px rgba(0,0,0,0.06); display:flex; flex-direction:column; gap:8px; transition:transform .15s, box-shadow .15s, border-color .15s; cursor:default; }
      .bracket-match.is-played { border-color:#15293e; cursor:pointer; }
      .bracket-match.is-played:hover { transform:translateY(-2px); box-shadow:0 12px 26px rgba(0,0,0,0.1); }
      .bracket-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
      .bracket-leg { font-size:12px; font-weight:700; color:#4c5b71; }
      .bracket-team { display:flex; align-items:center; justify-content:space-between; gap:10px; }
      .bracket-team .team-side { display:inline-flex; align-items:center; gap:8px; min-width:0; }
      .bracket-team .team-name { font-weight:800; color:#15293e; text-transform:none; white-space:normal; word-break:break-word; }
      .bracket-team .team-logo { width:34px; height:34px; object-fit:contain; }
      .bracket-team .team-score { font-weight:800; font-size:18px; color:#15293e; min-width:24px; text-align:right; }
      .bracket-meta { display:flex; flex-wrap:wrap; gap:8px; font-size:12px; color:#4c5b71; }
      .leg-toggle { display:inline-flex; gap:6px; background:#f3f6fb; padding:4px; border-radius:999px; }
      .leg-btn { border:1px solid #cbd5e1; background:#fff; color:#15293e; padding:6px 10px; border-radius:999px; font-weight:700; cursor:pointer; }
      .leg-btn.active { background:#15293e; color:#fff; border-color:#15293e; }
      .leg-btn:disabled { opacity:0.5; cursor:not-allowed; }
      .leg-content { display:none; }
      .leg-content.active { display:block; }
    `;
    document.head.appendChild(st);
  }

  const faseParam = "GOLD";
  const container = document.getElementById("playoffContainer");

  container.innerHTML = `
    <div class="bracket-header">
      <h3 class="bracket-titolo">Fase Finale</h3>
      <div class="bracket-filters">
        <button type="button" class="bracket-filter-btn active" data-stage="semi">Semifinali</button>
        <button type="button" class="bracket-filter-btn" data-stage="finale">Finale</button>
      </div>
    </div>
    <div class="bracket-wrapper" id="fasiPlayoff"></div>
    <div id="bracketPlaceholder" class="bracket-col" style="display:none;">
      <div class="bracket-col-title"></div>
      <div class="bracket-match">
        <div class="bracket-team"><span class="team-name"></span><span class="team-score">-</span></div>
        <div class="bracket-team"><span class="team-name"></span><span class="team-score">-</span></div>
      </div>
    </div>
  `;

  try {
    const res = await fetch(`/api/get_partite.php?torneo=${encodeURIComponent(TORNEO)}&fase=${faseParam}`);
    const data = await res.json();

    if (data.error) {
      container.innerHTML += `<p>Errore nel caricamento delle partite playoff.</p>`;
      return;
    }

    const fasiContainer = document.getElementById("fasiPlayoff");

    const giornateData = Object.keys(data || {})
      .map(g => parseInt(g, 10))
      .filter(g => g >= 1 && g <= 4);
    const giornate = Array.from(new Set([...giornateData, 2, 1])).sort((a, b) => a - b);

    const renderBracket = (stage = "semi") => {
      fasiContainer.innerHTML = "";
      const filtered = giornate.filter(g => {
        if (stage === "semi") return g === 2;
        if (stage === "finale") return g === 1;
        return g === 1 || g === 2;
      });

      filtered.forEach(g => {
        const col = document.createElement("div");
        col.className = "bracket-col";
        const isSemi = g === 2;
        const isFinale = g === 1;
        const colTitle = document.createElement("div");
        colTitle.className = "bracket-col-title";
        colTitle.textContent = isSemi ? "Semifinali" : (isFinale ? "Finale" : nomeFaseDaGiornata(g));
        col.appendChild(colTitle);

        let matchList = Array.isArray(data[g]) ? data[g] : [];
        if (!matchList.length) {
          if (isSemi) {
            matchList = [
              { squadra_casa: "Vincente Girone A", squadra_ospite: "Seconda Girone B", gol_casa: null, gol_ospite: null, giocata: 0, data_partita: "", ora_partita: "", campo: "Campo da definire", fase_leg: "" },
              { squadra_casa: "Vincente Girone B", squadra_ospite: "Seconda Girone A", gol_casa: null, gol_ospite: null, giocata: 0, data_partita: "", ora_partita: "", campo: "Campo da definire", fase_leg: "" }
            ];
          } else if (isFinale) {
            matchList = [
              { squadra_casa: "Vincente Semifinale 1", squadra_ospite: "Vincente Semifinale 2", gol_casa: null, gol_ospite: null, giocata: 0, data_partita: "", ora_partita: "", campo: "Campo da definire", fase_leg: "" }
            ];
          }
        }

        const pairMap = {};
        matchList.forEach(p => {
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
              <button type="button" class="leg-btn ${defaultLeg === 'ANDATA' ? 'active' : ''}" data-leg="ANDATA" ${hasAndata ? "" : "disabled"}>Andata</button>
              <button type="button" class="leg-btn ${defaultLeg === 'RITORNO' ? 'active' : ''}" data-leg="RITORNO" ${hasRitorno ? "" : "disabled"}>Ritorno</button>
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
            const giocata = Number(partita.giocata) === 1;
            const mostraRisultato = giocata && hasScore;
            const aiRigori = mostraRisultato && Number(partita.decisa_rigori || 0) === 1;
            const hasPenalties = aiRigori && partita.rigori_casa !== null && partita.rigori_casa !== undefined && partita.rigori_ospite !== null && partita.rigori_ospite !== undefined;
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
              ${hasPenalties ? `<div class="bracket-penalties">d.c.r. ${partita.rigori_casa}-${partita.rigori_ospite}</div>` : ""}
              <div class="bracket-meta">
                <span>${dataStr}${showOra ? " - " + partita.ora_partita.slice(0,5) : ""}</span>
                <span>${partita.campo || "Campo da definire"}</span>
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
            const buttons = head.querySelectorAll(".leg-btn");
            buttons.forEach(btn => {
              btn.addEventListener("click", () => {
                const target = (btn.dataset.leg || "").toUpperCase();
                contentWrap.querySelectorAll(".leg-content").forEach(el => {
                  el.classList.toggle("active", (el.dataset.leg || "") === target);
                });
                buttons.forEach(b => b.classList.toggle("active", b === btn));
              });
            });
          }

          col.appendChild(match);
        });

        fasiContainer.appendChild(col);
      });
    };

    renderBracket("semi");

    const filterBtns = container.querySelectorAll(".bracket-filter-btn");
    filterBtns.forEach(btn => {
      btn.addEventListener("click", () => {
        filterBtns.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        const stage = btn.dataset.stage || "semi";
        renderBracket(stage);
      });
    });
  } catch (err) {
    console.error("Errore nel caricamento playoff:", err);
    container.innerHTML += `<p>Errore nel caricamento playoff.</p>`;
  }
}
// ====================== ROSE SQUADRE ======================
async function caricaSquadrePerRosa() {
  try {
    const res = await fetch(`/api/leggiClassifica.php?torneo=${TORNEO}`);
    const squadre = await res.json();

    const select = document.getElementById("selectSquadra");
    select.innerHTML = ""; // Pulisce eventuali opzioni precedenti

    // 1ï¸âƒ£ Ordina le squadre in ordine alfabetico (A â†’ Z)
    squadre.sort((a, b) => a.nome.localeCompare(b.nome, 'it', { sensitivity: 'base' }));

    // 2ï¸âƒ£ Popola la select e imposta la prima come selezionata
    squadre.forEach((sq, index) => {
      if (sq.logo) {
        teamLogos[sq.nome] = sq.logo;
      }
      const opt = document.createElement("option");
      opt.value = sq.nome;
      opt.textContent = sq.nome;
      if (index === 0) opt.selected = true; // Prima squadra di default
      select.appendChild(opt);
    });

    // 3ï¸âƒ£ Mostra subito la rosa della prima squadra
    if (squadre.length > 0) {
      caricaRosaSquadra(squadre[0].nome);
    }

    // 4ï¸âƒ£ Evento cambio squadra
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
    `;
    container.appendChild(header);

    // elenco giocatori
    const grid = document.createElement("div");
    grid.classList.add("rosa-grid");

    data.forEach(giocatore => {
      const card = document.createElement("div");
      card.classList.add("player-card");

      const ruolo = (giocatore.ruolo || "").trim();
      const isGK = /^portiere/i.test(ruolo) || /^GK$/i.test(ruolo);
      const isCaptain = Number(giocatore.is_captain || 0) === 1;
      const badges = [];
      if (isGK) badges.push('<span class="role-badge gk-badge">GK</span>');
      if (isCaptain) badges.push('<span class="role-badge captain-badge">C</span>');

      card.innerHTML = `
        <div class="player-name-row">
          <h4 class="player-name">${giocatore.nome} ${giocatore.cognome}</h4>
          ${badges.length ? `<div class="player-tags">${badges.join("")}</div>` : ""}
        </div>

        <div class="player-bottom">
          <div class="player-photo">
            <img src="${giocatore.foto || FALLBACK_AVATAR}" 
                 alt="${giocatore.nome} ${giocatore.cognome}"
                 onerror="this.onerror=null; this.src='${FALLBACK_AVATAR}';">
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

// ====================== MARCATORI (stile Serie B) ======================
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
    const key = `${gol}`;
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
    marcatoriData = [];
    marcatoriRanks = [];
    list.innerHTML = `<div class="marcatori-empty">Errore caricamento marcatori</div>`;
  }
}

// Trasforma una select in un toggle di pillole (come Serie B)
function buildPillToggle(selectEl) {
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
}

// ====================== GESTIONE UI DINAMICA ======================
document.addEventListener("DOMContentLoaded", () => {
  const faseSelect = document.getElementById("faseSelect");
  const coppaSelect = document.getElementById("coppaSelect");
  const classificaWrapper = document.getElementById("classificaWrapper");
  const playoffContainer = document.getElementById("playoffContainer");
  const heroImg = document.getElementById("torneoHeroImg");
  const torneoTitle = document.querySelector(".torneo-title .titolo");
  const loadClassifica = (slug) => caricaClassifica(slug || TORNEO);
  const prevMarcatoriBtn = document.getElementById("prevMarcatori");
  const nextMarcatoriBtn = document.getElementById("nextMarcatori");
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

  const triggerCalendario = () => {
    const faseVal = (faseCalendario?.value || "REGULAR").toUpperCase();
    const gVal = (giornataSelect?.value || "");
    caricaCalendario(gVal, faseVal);
  };

  caricaCalendario("", "REGULAR");
  caricaSquadrePerRosa();
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
    buildPillToggle(faseCalendario);
  }

  // cambio fase girone/eliminazione (toggle bottoni)
  const applyFase = (faseVal) => {
    const legendaEsistente = document.querySelector(".legenda-coppe");
    if (legendaEsistente) legendaEsistente.remove();

    if (faseVal === "eliminazione") {
      classificaWrapper.style.display = "none";
      playoffContainer.style.display = "block";
      caricaPlayoff();
    } else {
      playoffContainer.style.display = "none";
      classificaWrapper.style.display = "block";
      loadClassifica();
    }
  };

  if (faseSelect) {
    buildPillToggle(faseSelect);
    faseSelect.addEventListener("change", () => applyFase(faseSelect.value || "girone"));
    applyFase(faseSelect.value || "girone");
  }

  if (coppaSelect) {
    coppaSelect.style.display = "none";
  }

  caricaMarcatori();
  prevMarcatoriBtn?.addEventListener("click", () => renderMarcatoriPagina(marcatoriPage - 1));
  nextMarcatoriBtn?.addEventListener("click", () => renderMarcatoriPagina(marcatoriPage + 1));

  document.querySelectorAll(".tab-button").forEach(btn => {
    btn.addEventListener("click", () => {
      if (btn.dataset.tab === "marcatori") {
        renderMarcatoriPagina(marcatoriPage);
      }
    });
  });
});

// ====================== GESTIONE TAB NAVIGAZIONE ======================
document.querySelectorAll(".tab-button").forEach(btn => {
  btn.addEventListener("click", () => {
    document.querySelectorAll(".tab-button").forEach(b => b.classList.remove("active"));
    document.querySelectorAll(".tab-section").forEach(s => s.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById(btn.dataset.tab).classList.add("active");
  });
});
