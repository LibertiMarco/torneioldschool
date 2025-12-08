const TORNEO = "Coppadafrica"; // Nome base del torneo nel DB (fase girone)
const teamLogos = {};

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

// ====================== UTILS ======================
function formattaData(data) {
  if (!data) return "";
  const [anno, mese, giorno] = data.split("-");
  return `${giorno}/${mese}/${anno}`;
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
    document.querySelectorAll("#tableClassificaA tbody .team-cell, #tableClassificaB tbody .team-cell").forEach(cell => {
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
  const tbodyA = document.querySelector("#tableClassificaA tbody");
  const tbodyB = document.querySelector("#tableClassificaB tbody");
  if (tbodyA) tbodyA.innerHTML = "";
  if (tbodyB) tbodyB.innerHTML = "";

  classifica.sort((a, b) => b.punti - a.punti || b.differenza_reti - a.differenza_reti);

  const gironeA = classifica.slice(0, 4);
  const gironeB = classifica.slice(4, 8);

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
    const giornateDisponibili = Object.keys(dataFiltrata).sort((a, b) => a - b);

    // mostra la select solo in fase finale
    if (wrapperGiornata) {
      wrapperGiornata.style.display = isGironi ? "none" : "flex";
    }

    // Gestione select giornate: nascosta sui gironi, attiva solo per fase finale (semi/finale)
    if (giornataSelect) {
      if (isGironi) {
        giornataSelect.innerHTML = '<option value="">Tutte</option>';
      } else {
        giornataSelect.innerHTML = '<option value="">Tutte</option>';
        giornateDisponibili.forEach(g => {
          const opt = document.createElement("option");
          opt.value = g;
          opt.textContent = g === "1" ? "Finale" : g === "2" ? "Semifinali" : `Fase ${g}`;
          giornataSelect.appendChild(opt);
        });
      }
    }

    const giornateDaMostrare = isGironi
      ? giornateDisponibili
      : (giornataSelect && giornataSelect.value ? [giornataSelect.value] : giornateDisponibili);

    giornateDaMostrare.forEach(numGiornata => {
      const giornataDiv = document.createElement("div");
      giornataDiv.classList.add("giornata");

      if (!isGironi) {
        const titolo = document.createElement("h3");
        const labelRound = roundLabelByKey[String(numGiornata)] || "Fase eliminazione";
        titolo.textContent = labelRound;
        giornataDiv.appendChild(titolo);
      }

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
                        class="maps-link">📍</a>`
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
              <span class="team-name">${partita.squadra_ospite}</span>
              <img src="${logoOspite}" alt="${partita.squadra_ospite}" class="team-logo">
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
async function caricaPlayoff() {
  if (!window.__COPPA_AF_BRACKET_STYLE__) {
    window.__COPPA_AF_BRACKET_STYLE__ = true;
    const st = document.createElement("style");
    st.textContent = `
      .bracket-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
      .bracket-filters { display:flex; gap:8px; }
      .bracket-filter-btn { border:1px solid #d7deea; background:#f4f6fb; color:#15293e; padding:8px 12px; border-radius:10px; font-weight:700; cursor:pointer; transition:all 0.15s ease; }
      .bracket-filter-btn.active { background:#15293e; color:#fff; border-color:#15293e; box-shadow:0 8px 18px rgba(21,41,62,0.25); }
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
  `;

  try {
    const res = await fetch(`/api/get_partite.php?torneo=${encodeURIComponent(TORNEO)}&fase=${faseParam}`);
    const data = await res.json();

    if (data.error) {
      container.innerHTML += `<p>Errore nel caricamento delle partite playoff.</p>`;
      return;
    }

    const fasiMap = { 1: "Finale", 2: "Semifinali" };
    const fasiContainer = document.getElementById("fasiPlayoff");

    const giornateData = Object.keys(data)
      .map(g => parseInt(g, 10))
      .filter(g => g >= 1 && g <= 4);
    const giornate = Array.from(new Set([...giornateData, 2, 1])).sort((a, b) => a - b);

    const renderBracket = (stage = "semi") => {
      fasiContainer.innerHTML = "";
      const filtered = giornate.filter(g => {
        if (stage === "semi") return g === 2;
        if (stage === "finale") return g === 1;
        return false;
      });

      filtered.forEach(g => {
        const col = document.createElement("div");
        col.className = "bracket-col";

        let matchList = Array.isArray(data[g]) ? data[g] : [];

        if (!matchList.length) {
          if (g === 2) {
            matchList = [
              { squadra_casa: "Vincente Girone A", squadra_ospite: "Seconda Girone B", gol_casa: null, gol_ospite: null, giocata: 0, data_partita: "", ora_partita: "", campo: "Da definire", fase_leg: "" },
              { squadra_casa: "Vincente Girone B", squadra_ospite: "Seconda Girone A", gol_casa: null, gol_ospite: null, giocata: 0, data_partita: "", ora_partita: "", campo: "Da definire", fase_leg: "" }
            ];
          } else if (g === 1) {
            matchList = [
              { squadra_casa: "Vincente Semifinale 1", squadra_ospite: "Vincente Semifinale 2", gol_casa: null, gol_ospite: null, giocata: 0, data_partita: "", ora_partita: "", campo: "Da definire", fase_leg: "" }
            ];
          }
        }

        matchList.forEach(partita => {
          const hasScore = partita.gol_casa !== null && partita.gol_ospite !== null;
          const giocata = (Number(partita.giocata) === 1) || hasScore;
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
              <span class="team-score">${hasScore ? partita.gol_casa : "-"}</span>
            </div>
            <div class="bracket-team">
              <div class="team-side">
                <img class="team-logo" src="${logoOspite}" alt="${partita.squadra_ospite}">
                <span class="team-name">${partita.squadra_ospite}</span>
              </div>
              <span class="team-score">${hasScore ? partita.gol_ospite : "-"}</span>
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

    renderBracket("semi");

    const filterBtns = container.querySelectorAll(".bracket-filter-btn");
    filterBtns.forEach(btn => {
      btn.addEventListener("click", () => {
        filterBtns.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        const stage = btn.dataset.stage || "all";
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

card.innerHTML = `
  <div class="player-name-row">
    <h4 class="player-name">${giocatore.nome} ${giocatore.cognome}</h4>
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
  const faseSelect = document.getElementById("faseSelect");
  const coppaSelect = document.getElementById("coppaSelect");
  const classificaWrapper = document.getElementById("classificaWrapper");
  const playoffContainer = document.getElementById("playoffContainer");
  const heroImg = document.getElementById("torneoHeroImg");
  const torneoTitle = document.querySelector(".torneo-title .titolo");
  const loadClassifica = (slug) => caricaClassifica(slug || TORNEO);

  // carico subito la parte girone
  caricaClassifica();
  const faseCalendario = document.getElementById("faseCalendario");
  const giornataSelect = document.getElementById("giornataSelect");

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
    const legendaEsistente = document.querySelector(".legenda-coppe");
    if (legendaEsistente) legendaEsistente.remove();

    if (faseSelect.value === "eliminazione") {
      // mostra bracket playoff
      classificaWrapper.style.display = "none";
      playoffContainer.style.display = "block";
      caricaPlayoff();

    } else {
      // torna alla classifica
      playoffContainer.style.display = "none";
      classificaWrapper.style.display = "block";
      loadClassifica();
    }
  });

  // nasconde la seconda picklist (non usata in questa versione)
  if (coppaSelect) {
    coppaSelect.style.display = "none";
  }
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

