const TORNEO = "SerieB"; // Nome base del torneo nel DB (fase girone)

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
async function caricaClassifica() {
  try {
    const response = await fetch(`/torneioldschool/api/leggiClassifica.php?torneo=${TORNEO}`);
    const data = await response.json();

    if (data.error) {
      console.error("Errore dal server:", data.error);
      return;
    }

    mostraClassifica(data);
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

    tr.innerHTML = `
      <td>${i + 1}</td>
      <td class="team-cell">
        <div class="team-info">
          <img src="/torneioldschool/img/scudetti/${team.nome}.png" alt="${team.nome}" class="team-logo">
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


// ====================== CALENDARIO (GIRONE) ======================
async function caricaCalendario(giornataSelezionata = "") {
  try {
    const res = await fetch(`/torneioldschool/api/get_partite.php?torneo=${TORNEO}`);
    const data = await res.json();

    if (data.error) {
      console.error("Errore:", data.error);
      return;
    }

    const calendarioSection = document.getElementById("contenitoreGiornate");
    calendarioSection.innerHTML = "";

    const giornataSelect = document.getElementById("giornataSelect");
    const giornateDisponibili = Object.keys(data).sort((a, b) => a - b);

    // Popola la picklist solo una volta
    if (giornataSelect.options.length <= 1) {
      giornateDisponibili.forEach(g => {
        const opt = document.createElement("option");
        opt.value = g;
        opt.textContent = `Giornata ${g}`;
        giornataSelect.appendChild(opt);
      });
    }

    // Filtra le giornate mostrate
    const giornateDaMostrare = giornataSelezionata ? [giornataSelezionata] : giornateDisponibili;

    giornateDaMostrare.forEach(numGiornata => {
      const giornataDiv = document.createElement("div");
      giornataDiv.classList.add("giornata");

      const titolo = document.createElement("h3");
      titolo.textContent = `Giornata ${numGiornata}`;
      giornataDiv.appendChild(titolo);

      data[numGiornata].forEach(partita => {
        const partitaDiv = document.createElement("div");
        partitaDiv.classList.add("match-card");

        const dataStr = formattaData(partita.data_partita);
        const stadio = partita.campo || "Campo da definire";
        const golCasa = partita.giocata == 1 ? partita.gol_casa : null;
        const golOspite = partita.giocata == 1 ? partita.gol_ospite : null;

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
              <img src="/torneioldschool/img/scudetti/${partita.squadra_casa}.png" alt="${partita.squadra_casa}" class="team-logo">
              <span class="team-name">${partita.squadra_casa}</span>
            </div>
            <div class="match-center">
              ${
                (partita.gol_casa == 0 && partita.gol_ospite == 0)
                  ? `<span class="vs">VS</span>`
                  : (partita.gol_casa != null && partita.gol_ospite != null
                      ? `<span class="score">${partita.gol_casa}</span><span class="dash">-</span><span class="score">${partita.gol_ospite}</span>`
                      : `<span class="vs">VS</span>`)
              }
            </div>

            <div class="team away">
              <span class="team-name">${partita.squadra_ospite}</span>
              <img src="/torneioldschool/img/scudetti/${partita.squadra_ospite}.png" alt="${partita.squadra_ospite}" class="team-logo">
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
  const torneoPlayoff = `${TORNEO}_${tipoCoppa.toUpperCase()}`;
  const container = document.getElementById("playoffContainer");

  container.innerHTML = `
    <h3 class="bracket-titolo">Playoff ${tipoCoppa === "gold" ? "COPPA GOLD" : "COPPA SILVER"}</h3>
    <div id="fasiPlayoff"></div>
  `;

  try {
    const res = await fetch(`/torneioldschool/api/get_partite.php?torneo=${torneoPlayoff}`);
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
            <span>
              ${dataStr}${partita.ora_partita ? " - " + partita.ora_partita.slice(0,5) : ""}
            </span>
          </div>

          <div class="match-body">
            <div class="team home">
              <img src="/torneioldschool/img/scudetti/${partita.squadra_casa}.png" alt="${partita.squadra_casa}" class="team-logo">
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
              <span class="team-name">${partita.squadra_ospite}</span>
              <img src="/torneioldschool/img/scudetti/${partita.squadra_ospite}.png" alt="${partita.squadra_ospite}" class="team-logo">
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




// ====================== ROSE SQUADRE ======================
async function caricaSquadrePerRosa() {
  try {
    const res = await fetch(`/torneioldschool/api/leggiClassifica.php?torneo=${TORNEO}`);
    const squadre = await res.json();

    const select = document.getElementById("selectSquadra");
    squadre.forEach(sq => {
      const opt = document.createElement("option");
      opt.value = sq.nome;
      opt.textContent = sq.nome;
      select.appendChild(opt);
    });

    // Evento cambio squadra
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
    const res = await fetch(`/torneioldschool/api/get_rosa.php?torneo=${TORNEO}&squadra=${encodeURIComponent(squadra)}`);
    const data = await res.json();

    const container = document.getElementById("rosaContainer");
    container.innerHTML = "";

    // intestazione con logo + nome squadra
    const header = document.createElement("div");
    header.classList.add("rosa-header");
    header.innerHTML = `
      <img src="/torneioldschool/img/scudetti/${squadra}.png" alt="${squadra}" class="team-logo-large">
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
        <img src="${giocatore.foto}" 
             alt="${giocatore.nome} ${giocatore.cognome}" 
             class="player-photo">
        <div class="player-info">
          <h4>${giocatore.nome} ${giocatore.cognome}</h4>
          <p class="player-meta">
            Presenze: ${giocatore.presenze || '-'} | Goal: ${giocatore.reti || '0'}
          </p>
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

  // carico subito la parte girone
  caricaClassifica();
  caricaCalendario();
  caricaSquadrePerRosa();

  // filtro calendario giornate
  const giornataSelect = document.getElementById("giornataSelect");
  giornataSelect.addEventListener("change", () => {
    caricaCalendario(giornataSelect.value);
  });

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
      caricaClassifica();
    }
  });

  // cambio coppa (gold/silver)
  coppaSelect.addEventListener("change", () => {
    if (faseSelect.value === "eliminazione") {
      caricaPlayoff(coppaSelect.value);
    }
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
