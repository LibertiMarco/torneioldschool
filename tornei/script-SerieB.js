const TORNEO = "SerieB"; // Nome base del torneo nel DB (fase girone)
const FALLBACK_AVATAR = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Crect width='120' height='120' rx='16' fill='%2315293e'/%3E%3Ctext x='50%25' y='55%25' dominant-baseline='middle' text-anchor='middle' font-size='48' fill='%23fff'%3E%3F%3C/text%3E%3C/svg%3E";
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
  if (!data) return "";
  const [anno, mese, giorno] = data.split("-");
  return `${giorno}/${mese}`;
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

    if (i + 1 <= 16) {
      tr.classList.add("gold-row");
    } else {
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

  // rimuove eventuale legenda già presente
  if (legendaEsistente) legendaEsistente.remove();

  // crea legenda solo se siamo in fase girone
  if (!faseSelect || faseSelect.value === "girone") {
    const legenda = document.createElement("div");
    legenda.classList.add("legenda-coppe");
    legenda.innerHTML = `
      <div class="box gold-box">🏆 COPPA GOLD</div>
      <div class="box silver-box">🥈 COPPA SILVER</div>
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
    const key = `${gol}`; // ranking per ex aequo basato solo sui gol

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

      (dataFiltrata[numGiornata] || []).forEach(partita => {
        const partitaDiv = document.createElement("div");
        partitaDiv.classList.add("match-card");
      
        // ✅ Rende cliccabile la match-card solo se giocata
        if (String(partita.giocata) === "1") {
          partitaDiv.style.cursor = "pointer";
          partitaDiv.onclick = () => {
            window.location.href = `partita_eventi.php?id=${partita.id}&torneo=${TORNEO}`;
          };
        } else {
          partitaDiv.style.cursor = "default";
        }
      
        const dataStr = formattaData(partita.data_partita);
        const stadio = partita.campo || "Campo da definire";
        const golCasa = partita.giocata == 1 ? partita.gol_casa : null;
        const golOspite = partita.giocata == 1 ? partita.gol_ospite : null;
        const logoCasa = resolveLogoPath(partita.squadra_casa, partita.logo_casa);
        const logoOspite = resolveLogoPath(partita.squadra_ospite, partita.logo_ospite);

        partitaDiv.innerHTML = `
          <div class="match-header">
            <span>
              ${stadio}
              ${
                stadio && stadio !== "Campo da definire"
                  ? `<a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(stadio)}"
                        target="_blank"
                        class="maps-link"><span class="maps-icon" aria-hidden="true"></span></a>`
                  : ""
              }
            </span>
            <span>${dataStr}${partita.ora_partita ? " - " + partita.ora_partita.slice(0,5) : ""}</span>
          </div>
      
          <div class="match-body">
            <div class="team home">
              <img src="${logoCasa}" alt="${partita.squadra_casa}" class="team-logo">
              <span class="team-name">${partita.squadra_casa}</span>
            </div>
      
            <div class="match-center">
              ${
                (partita.gol_casa == 0 && partita.gol_ospite == 0)
                  ? `<span class="vs">VS</span>`
                  : (partita.gol_casa != null && partita.gol_ospite != null
                      ? `<span class="score">${partita.gol_casa}</span><span class="dash-cal">-</span><span class="score">${partita.gol_ospite}</span>`
                      : `<span class="vs">VS</span>`)
              }
            </div>
                  
            <div class="team away">
              <img src="${logoOspite}" alt="${partita.squadra_ospite}" class="team-logo">
              <span class="team-name">${partita.squadra_ospite}</span>
            </div>
          </div>
        `;
                  
        giornataDiv.appendChild(partitaDiv);
      });

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

  container.innerHTML = `
    <h3 class="bracket-titolo">Playoff ${tipoCoppa === "gold" ? "COPPA GOLD" : "COPPA SILVER"}</h3>
    <div id="fasiPlayoff"></div>
  `;

  try {
    const res = await fetch(`/api/get_partite.php?torneo=${encodeURIComponent(TORNEO)}&fase=${faseParam}`);
    const data = await res.json();

    if (data.error) {
      container.innerHTML += `<p>Errore nel caricamento delle partite playoff.</p>`;
      return;
    }

    const fasiMap = {
      1: "Finale",
      2: "Semifinali",
      3: "Quarti di finale",
      4: "Ottavi di finale"
    };

    const fasiContainer = document.getElementById("fasiPlayoff");
    fasiContainer.innerHTML = "";

    // ordina e mostra solo giornate 1–4
    const giornate = Object.keys(data)
      .map(g => parseInt(g))
      .filter(g => g >= 1 && g <= 4)
      .sort((a, b) => a - b);

    giornate.forEach(g => {
      const nomeFase = fasiMap[g] || `Fase ${g}`;
      const faseDiv = document.createElement("div");
      faseDiv.classList.add("fase-playoff");

      const titolo = document.createElement("h3");
      titolo.textContent = nomeFase;
      faseDiv.appendChild(titolo);

      data[g].forEach(partita => {
        const partitaDiv = document.createElement("div");
        partitaDiv.classList.add("match-card");

        const dataStr = formattaData(partita.data_partita);
        const stadio = partita.campo || "Campo da definire";
        const giocata = partita.giocata == 1;
        const golCasa = giocata ? partita.gol_casa : null;
        const golOspite = giocata ? partita.gol_ospite : null;

        const logoCasa = resolveLogoPath(partita.squadra_casa, partita.logo_casa);
        const logoOspite = resolveLogoPath(partita.squadra_ospite, partita.logo_ospite);

        partitaDiv.innerHTML = `
          <div class="match-header">
            <span>
              ${stadio}
              ${
                stadio && stadio !== "Campo da definire"
                  ? `<a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(stadio)}"
                        target="_blank"
                        class="maps-link"><span class="maps-icon" aria-hidden="true"></span></a>`
                  : ""
              }
            </span>
            <span>
              ${dataStr}${partita.ora_partita ? " - " + partita.ora_partita.slice(0,5) : ""}
            </span>
          </div>

          <div class="match-body">
            <div class="team home">
              <img src="${logoCasa}" alt="${partita.squadra_casa}" class="team-logo">
              <span class="team-name">${partita.squadra_casa}</span>
            </div>

            <div class="match-center">
              ${
                giocata
                  ? `<span class="score">${golCasa}</span>
                     <span class="dash">-</span>
                     <span class="score">${golOspite}</span>`
                  : `<span class="vs">VS</span>`
              }
            </div>

                        <div class="team away">
              <img src="${logoOspite}" alt="${partita.squadra_ospite}" class="team-logo">
              <span class="team-name">${partita.squadra_ospite}</span>
            </div>
          </div>
        `;

        faseDiv.appendChild(partitaDiv);
      });

      fasiContainer.appendChild(faseDiv);
    });

  } catch (err) {
    console.error("Errore nel caricamento playoff:", err);
    container.innerHTML += `<p>Errore nel caricamento playoff.</p>`;
  }
}

// override con filtro andata/ritorno semifinali in stile bracket
async function caricaPlayoff(tipoCoppa) {
  const faseParam = (tipoCoppa || "gold").toUpperCase(); // GOLD / SILVER
  const container = document.getElementById("playoffContainer");

  container.innerHTML = `
    <h3 class="bracket-titolo">Playoff ${tipoCoppa === "gold" ? "COPPA GOLD" : "COPPA SILVER"}</h3>
    <div class="leg-filter" id="playoffLegFilterWrap" style="display:none; gap: 8px; align-items: center; margin: 10px 0;">
      <label for="playoffLegFilter">Semifinali:</label>
      <select id="playoffLegFilter">
        <option value="">Tutte</option>
        <option value="ANDATA">Andata</option>
        <option value="RITORNO">Ritorno</option>
      </select>
    </div>
    <div class="phase-filter" id="playoffPhaseFilters"></div>
    <div class="bracket-wrapper" id="fasiPlayoff"></div>
  `;

  try {
    const res = await fetch(`/api/get_partite.php?torneo=${encodeURIComponent(TORNEO)}&fase=${faseParam}`);
    const data = await res.json();

    if (data.error) {
      container.innerHTML += `<p>Errore nel caricamento delle partite playoff.</p>`;
      return;
    }

    const fasiMap = {
      1: "Finale",
      2: "Semifinali",
      3: "Quarti di finale",
      4: "Ottavi di finale"
    };

    const fasiContainer = document.getElementById("fasiPlayoff");
    const legWrap = document.getElementById("playoffLegFilterWrap");
    const legSelect = document.getElementById("playoffLegFilter");
    const phaseFilter = document.getElementById("playoffPhaseFilters");
    let currentLeg = "";
    let currentPhase = "";

    const hasSemiLegs = Object.values(data || {}).some(arr =>
      (arr || []).some(p => (p.fase_round || "").toUpperCase() === "SEMIFINALE" && p.fase_leg)
    );
    if (legWrap && hasSemiLegs) {
      legWrap.style.display = "flex";
    } else if (legWrap) {
      legWrap.style.display = "none";
    }

    if (phaseFilter) {
      phaseFilter.innerHTML = "";
      const phases =
        tipoCoppa.toLowerCase() === "gold"
          ? [
              { label: "Tutte", val: "" },
              { label: "Ottavi", val: "4" },
              { label: "Quarti", val: "3" },
              { label: "Semifinali", val: "2" },
              { label: "Finale", val: "1" },
            ]
          : [
              { label: "Tutte", val: "" },
              { label: "Semifinali", val: "2" },
              { label: "Finale", val: "1" },
            ];
      phases.forEach(ph => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "phase-btn" + (ph.val === "" ? " active" : "");
        btn.dataset.phase = ph.val;
        btn.textContent = ph.label;
        btn.onclick = () => {
          phaseFilter.querySelectorAll(".phase-btn").forEach(b => b.classList.remove("active"));
          btn.classList.add("active");
          currentPhase = ph.val;
          renderPlayoff();
        };
        phaseFilter.appendChild(btn);
      });
    }

    const renderPlayoff = (newLeg) => {
      if (typeof newLeg !== "undefined") currentLeg = newLeg;
      if (!fasiContainer) return;
      fasiContainer.innerHTML = "";
      const ordineGiornate = [4, 3, 2, 1]; // Ottavi -> Quarti -> Semi -> Finale

      ordineGiornate.forEach(g => {
        if (currentPhase && String(g) !== currentPhase) return;
        const matchList = (data[g] || []).filter(p => {
          const leg = (p.fase_leg || "").toUpperCase();
          const isSemi = (p.fase_round || "").toUpperCase() === "SEMIFINALE";
          if (currentLeg && isSemi && leg) {
            return leg === currentLeg;
          }
          return true;
        });
        if (!matchList.length) return;

        const col = document.createElement("div");
        col.className = "bracket-col";
        const titolo = document.createElement("div");
        titolo.className = "bracket-col-title";
        titolo.textContent = fasiMap[g] || `Fase ${g}`;
        col.appendChild(titolo);

        matchList.forEach((partita, idx) => {
          const giocata = partita.giocata == 1 && partita.gol_casa !== null && partita.gol_ospite !== null;
          const logoCasa = resolveLogoPath(partita.squadra_casa, partita.logo_casa);
          const logoOspite = resolveLogoPath(partita.squadra_ospite, partita.logo_ospite);
          const dataStr = formattaData(partita.data_partita);
          const legLabel = (partita.fase_leg || "").trim();
          const match = document.createElement("div");
          match.className = "bracket-match" + (giocata ? " is-played" : "");
          match.style.cursor = giocata ? "pointer" : "default";
          match.innerHTML = `
            <div class="bracket-head">
              ${legLabel ? `<span class="bracket-leg">${legLabel}</span>` : ""}
            </div>
            <div class="bracket-team">
              <div class="team-side">
                <img class="team-logo" src="${logoCasa}" alt="${partita.squadra_casa}">
                <span class="team-name">${partita.squadra_casa}</span>
              </div>
              <span class="team-score">${giocata ? partita.gol_casa : "-"}</span>
            </div>
            <div class="bracket-team">
              <div class="team-side">
                <img class="team-logo" src="${logoOspite}" alt="${partita.squadra_ospite}">
                <span class="team-name">${partita.squadra_ospite}</span>
              </div>
              <span class="team-score">${giocata ? partita.gol_ospite : "-"}</span>
            </div>
            <div class="bracket-meta">
              <span>${dataStr}${partita.ora_partita ? ' - ' + partita.ora_partita.slice(0,5) : ''}${legLabel ? ' - ' + legLabel : ''}</span>
              <span>${partita.campo || 'Campo da definire'}</span>
            </div>
          `;
          if (giocata) {
            match.addEventListener("click", () => {
              window.location.href = `partita_eventi.php?id=${partita.id}&torneo=${encodeURIComponent(TORNEO)}`;
            });
          }
          col.appendChild(match);
        });

        fasiContainer.appendChild(col);
      });
    };

    renderPlayoff("");
    if (legSelect) {
      legSelect.onchange = () => renderPlayoff((legSelect.value || "").toUpperCase());
    }
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

    // 1️⃣ Ordina le squadre in ordine alfabetico (A → Z)
    squadre.sort((a, b) => a.nome.localeCompare(b.nome, 'it', { sensitivity: 'base' }));

    // 2️⃣ Popola la select e imposta la prima come selezionata
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

    // 3️⃣ Mostra subito la rosa della prima squadra
    if (squadre.length > 0) {
      caricaRosaSquadra(squadre[0].nome);
    }

    // 4️⃣ Evento cambio squadra
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
      <button type="button" class="fav-toggle fav-toggle--small fav-team-btn" aria-label="Segui la squadra">☆</button>
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

    // ordina per cognome
    data.sort((a, b) => (a.cognome || "").localeCompare(b.cognome || "", "it", { sensitivity: "base" }));

    data.forEach(giocatore => {
      const card = document.createElement("div");
      card.classList.add("player-card");

      const nome = giocatore.nome || "";
      const cognome = giocatore.cognome || "";
      const nomeCompleto = `${nome} ${cognome}`.trim() || "Giocatore";
      const ruolo = (giocatore.ruolo_squadra || giocatore.ruolo || "").toLowerCase().trim();
      const isPortiere = ruolo === "portiere";
      const isCaptain = String(giocatore.is_captain || giocatore.captain) === "1";
      const ruoloBadge = isPortiere ? ' <span class="role-badge gk-badge">GK</span>' : "";
      const captainBadge = isCaptain ? ' <span class="role-badge captain-badge">C</span>' : "";
      const foto = giocatore.foto || FALLBACK_AVATAR;

      card.innerHTML = `
        <div class="player-name-row">
          <h4 class="player-name">${nomeCompleto}${ruoloBadge}${captainBadge}</h4>
        </div>

        <div class="player-team-row">
          <img src="${squadraLogo}" alt="${squadra}" class="player-team-logo">
          <span class="player-team-name">${squadra}</span>
        </div>

        <div class="player-bottom">
          <div class="player-photo">
            <img src="${foto}" 
                 alt="${nomeCompleto}"
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
  const prevMarcatori = document.getElementById("prevMarcatori");
  const nextMarcatori = document.getElementById("nextMarcatori");
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
  }

  // cambio fase girone/eliminazione
  faseSelect.addEventListener("change", () => {
    // rimuovi eventuale legenda se si passa a eliminazione diretta
    const legendaEsistente = document.querySelector(".legenda-coppe");
    if (faseSelect.value === "eliminazione" && legendaEsistente) {
      legendaEsistente.remove();
    }

    if (faseSelect.value === "eliminazione") {
      // mostra bracket playoff
      classificaWrapper.style.display = "none";
      playoffContainer.style.display = "block";

      // se non è selezionata nessuna coppa ancora, default gold
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

  // toggle pill per fase/coppa
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
      caricaMarcatori();
    }
  });

  if (prevMarcatori) {
    prevMarcatori.addEventListener("click", () => {
      renderMarcatoriPagina(marcatoriPage - 1);
    });
  }
  if (nextMarcatori) {
    nextMarcatori.addEventListener("click", () => {
      renderMarcatoriPagina(marcatoriPage + 1);
    });
  }
});







