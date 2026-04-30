(() => {
  const infoCache = new Map();
  const STYLE_ID = "tornei-gironi-helper-style";

  function toNumber(value, fallback = 0) {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
  }

  function normalizeGironeValue(value = "") {
    return String(value || "")
      .trim()
      .toUpperCase()
      .replace(/^GIRONE\s+/u, "")
      .replace(/^GRUPPO\s+/u, "");
  }

  function normalizeTeamLookupName(value = "") {
    return String(value || "").trim().toLowerCase();
  }

  function indexToLetters(index) {
    let n = Number(index);
    if (!Number.isFinite(n) || n < 0) return "";
    let label = "";
    do {
      label = String.fromCharCode(65 + (n % 26)) + label;
      n = Math.floor(n / 26) - 1;
    } while (n >= 0);
    return label;
  }

  function buildGroupLabels(count) {
    return Array.from({ length: Math.max(0, count) }, (_, idx) => indexToLetters(idx));
  }

  function getConfig(info) {
    return info && typeof info.config === "object" && info.config ? info.config : {};
  }

  function loadTorneoInfo(slug) {
    const key = String(slug || "").trim();
    if (!key) return Promise.resolve({});

    if (!infoCache.has(key)) {
      const promise = fetch(`/api/get_torneo_by_slug.php?slug=${encodeURIComponent(key)}`)
        .then(res => {
          if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
          }
          return res.json();
        })
        .catch(err => {
          console.error("Errore nel caricamento info torneo", err);
          return {};
        });
      infoCache.set(key, promise);
    }

    return infoCache.get(key);
  }

  async function loadSquadreGironi(slug) {
    const key = String(slug || "").trim();
    if (!key) return [];

    try {
      const res = await fetch(`/api/get_squadre_torneo.php?torneo=${encodeURIComponent(key)}`);
      const data = await res.json();
      return Array.isArray(data) ? data : [];
    } catch (err) {
      console.error("Errore nel caricamento gironi squadre", err);
      return [];
    }
  }

  function mergeGironiIntoClassifica(classifica = [], squadre = []) {
    const byId = new Map();
    const byName = new Map();

    squadre.forEach(team => {
      const normalizedGirone = normalizeGironeValue(team?.girone);
      const id = Number(team?.id);
      if (Number.isFinite(id) && id > 0) {
        byId.set(id, normalizedGirone);
      }

      const key = normalizeTeamLookupName(team?.nome);
      if (key) {
        byName.set(key, normalizedGirone);
      }
    });

    return classifica.map(team => {
      const id = Number(team?.id);
      const nameKey = normalizeTeamLookupName(team?.nome);
      let girone = normalizeGironeValue(team?.girone);

      if (Number.isFinite(id) && id > 0 && byId.has(id)) {
        girone = byId.get(id);
      } else if (nameKey && byName.has(nameKey)) {
        girone = byName.get(nameKey);
      }

      return { ...team, girone };
    });
  }

  function getConfiguredTotalTeams(config = {}, actualCount = 0) {
    const tot = toNumber(config.totale_squadre, 0);
    if (tot > 0) return tot;
    const camp = toNumber(config.campionato_squadre, 0);
    if (camp > 0) return camp;
    const gironi = toNumber(config.numero_gironi, 0);
    const perGirone = toNumber(config.squadre_per_girone, 0);
    if (gironi > 0 && perGirone > 0) return gironi * perGirone;
    return Math.max(0, actualCount);
  }

  function resolveGroupSetup(classifica = [], info = {}, options = {}) {
    const config = getConfig(info);
    const formato = String(config.formato || config.formula_torneo || "").trim().toLowerCase();
    if (formato !== "girone") {
      return null;
    }

    const configuredGroupCount = Math.max(0, toNumber(config.numero_gironi, 0));
    const teamLabels = [...new Set(
      (classifica || [])
        .map(team => normalizeGironeValue(team?.girone))
        .filter(Boolean)
    )].sort((a, b) => a.localeCompare(b, "it", { sensitivity: "base" }));

    let labels = [];
    if (configuredGroupCount > 1) {
      labels = buildGroupLabels(configuredGroupCount);
    } else if (teamLabels.length > 1) {
      labels = teamLabels;
    }

    if (labels.length <= 1) {
      return null;
    }

    const actualCount = Array.isArray(classifica) ? classifica.length : 0;
    const totalTeams = Math.max(actualCount, getConfiguredTotalTeams(config, actualCount));
    const configuredPerGroup = Math.max(0, toNumber(config.squadre_per_girone, 0));
    const teamsPerGroup = Math.max(1, configuredPerGroup || Math.ceil(totalTeams / labels.length));

    const hasGold = Object.prototype.hasOwnProperty.call(config, "qualificati_gold");
    const hasSilver = Object.prototype.hasOwnProperty.call(config, "qualificati_silver");
    const goldTotal = hasGold
      ? Math.max(0, toNumber(config.qualificati_gold, 0))
      : Math.max(0, toNumber(options.fallbackGoldSpots, 0));
    const silverFallback = options.fallbackSilverSpots == null
      ? Math.max(totalTeams - goldTotal, 0)
      : Math.max(0, toNumber(options.fallbackSilverSpots, 0));
    const silverTotal = hasSilver
      ? Math.max(0, toNumber(config.qualificati_silver, 0))
      : silverFallback;
    const requestedGoldPerGroup = toNumber(options.goldPerGroupOverride, NaN);
    const goldPerGroup = Number.isFinite(requestedGoldPerGroup)
      ? Math.min(teamsPerGroup, Math.max(0, requestedGoldPerGroup))
      : (labels.length ? Math.floor(goldTotal / labels.length) : 0);

    return {
      labels,
      teamsPerGroup,
      goldTotal,
      silverTotal,
      goldPerGroup,
      silverPerGroup: labels.length ? Math.floor(silverTotal / labels.length) : 0
    };
  }

  function ensureStyles() {
    if (document.getElementById(STYLE_ID)) return;

    const style = document.createElement("style");
    style.id = STYLE_ID;
    style.textContent = `
      .gironi-grid {
        display: none;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 18px;
      }
      .gironi-grid.is-active {
        display: grid;
      }
      .girone-box {
        position: relative;
      }
      .girone-box h3 {
        margin: 0 0 8px;
        color: #15293e;
        position: sticky;
        top: 0;
        z-index: 9;
        padding: 10px 10px 8px;
        background: linear-gradient(145deg, #f7f9fc, #eef2f7);
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(21, 41, 62, 0.08);
      }
      .girone-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      .gironi-grid table {
        width: 100%;
        table-layout: fixed;
        min-width: 720px;
      }
      .gironi-grid table th,
      .gironi-grid table td {
        text-align: center;
        padding: 10px 8px;
        vertical-align: middle;
        background: #fff;
        white-space: nowrap;
      }
      .gironi-grid table th {
        position: sticky;
        top: 0;
        z-index: 3;
      }
      .gironi-grid table th:nth-child(1),
      .gironi-grid table td:nth-child(1) {
        position: sticky;
        left: 0;
        min-width: 40px;
        width: 40px;
        z-index: 6;
        background: #fff;
      }
      .gironi-grid table th:nth-child(2),
      .gironi-grid table td:nth-child(2) {
        position: sticky;
        left: 40px;
        min-width: 170px;
        width: 170px;
        z-index: 5;
        background: #fff;
        text-align: left;
      }
      .gironi-grid table th:nth-child(n+3),
      .gironi-grid table td:nth-child(n+3) {
        width: 10%;
      }
      .gironi-grid .team-cell .team-info {
        display: inline-flex;
        justify-content: flex-start;
        align-items: center;
        gap: 6px;
      }
      .gironi-grid .team-cell {
        text-align: left;
      }
      .gironi-grid .team-logo {
        width: 28px;
        height: 28px;
        object-fit: contain;
        display: block;
      }
      .gironi-grid tr.gold-row td:first-child {
        font-weight: 800;
        background: #ffd700 !important;
        color: #15293e !important;
      }
      .gironi-grid tr.silver-row td:first-child {
        font-weight: 800;
        background: #d9dee8 !important;
        color: #15293e !important;
      }
      .gironi-grid tr.placeholder-row td {
        background: #f8fafc;
        color: #7b8798;
      }
      .gironi-grid .team-cell--placeholder {
        font-style: italic;
      }
    `;
    document.head.appendChild(style);
  }

  function ensureGridElements() {
    const wrapper = document.getElementById("classificaWrapper");
    const table = document.getElementById("tableClassifica");
    if (!wrapper || !table) {
      return null;
    }

    let grid = document.getElementById("gironiGrid");
    if (!grid) {
      grid = document.createElement("div");
      grid.id = "gironiGrid";
      grid.className = "gironi-grid";
      wrapper.appendChild(grid);
    }

    return { wrapper, table, grid };
  }

  function createClassificaRow(team, posizione, resolveLogoPath, goldThreshold = 0, silverThreshold = null) {
    const tr = document.createElement("tr");
    if (goldThreshold > 0 && posizione <= goldThreshold) {
      tr.classList.add("gold-row");
    } else if (silverThreshold !== null && silverThreshold > 0 && posizione <= silverThreshold) {
      tr.classList.add("silver-row");
    }

    const logoPath = typeof resolveLogoPath === "function"
      ? resolveLogoPath(team.nome, team.logo)
      : (team.logo || "/img/scudetti/default.png");

    tr.innerHTML = `
      <td>${posizione}</td>
      <td class="team-cell" data-team-name="${team.nome}">
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
    return tr;
  }

  function createPlaceholderRow(posizione) {
    const tr = document.createElement("tr");
    tr.className = "placeholder-row";
    tr.innerHTML = `
      <td>${posizione}</td>
      <td class="team-cell team-cell--placeholder">Da definire</td>
      <td>-</td>
      <td>-</td>
      <td>-</td>
      <td>-</td>
      <td>-</td>
      <td>-</td>
      <td>-</td>
      <td>-</td>
    `;
    return tr;
  }

  function resetGroupedClassifica() {
    const elements = ensureGridElements();
    if (!elements) return;

    const { table, grid } = elements;
    table.style.display = "";
    grid.innerHTML = "";
    grid.classList.remove("is-active");
  }

  function renderGroupedClassifica(options = {}) {
    const elements = ensureGridElements();
    if (!elements) return null;

    const setup = resolveGroupSetup(options.classifica || [], options.torneoInfo || {}, options);
    if (!setup) {
      resetGroupedClassifica();
      return null;
    }

    ensureStyles();

    const { table, grid } = elements;
    const labels = setup.labels;
    const groups = new Map(labels.map(label => [label, []]));
    const leftovers = [];
    const seededTeams = (options.classifica || []).slice().sort((a, b) => {
      const idA = Number(a.id) || 0;
      const idB = Number(b.id) || 0;
      if (idA !== idB) return idA - idB;
      return String(a.nome || "").localeCompare(String(b.nome || ""), "it", { sensitivity: "base" });
    });

    seededTeams.forEach(team => {
      const label = normalizeGironeValue(team.girone);
      if (groups.has(label)) {
        groups.get(label).push(team);
      } else {
        leftovers.push(team);
      }
    });

    labels.forEach(label => {
      const rows = groups.get(label);
      while (rows.length < setup.teamsPerGroup && leftovers.length) {
        rows.push(leftovers.shift());
      }
    });

    while (leftovers.length && labels.length) {
      const targetLabel = labels.reduce((best, current) => {
        return groups.get(current).length < groups.get(best).length ? current : best;
      }, labels[0]);
      groups.get(targetLabel).push(leftovers.shift());
    }

    table.style.display = "none";
    grid.innerHTML = "";
    grid.classList.add("is-active");

    labels.forEach(label => {
      const orderedTeams = typeof options.orderRows === "function"
        ? options.orderRows(groups.get(label) || [], options.partiteGiocate || [])
        : (groups.get(label) || []);

      const box = document.createElement("div");
      box.className = "girone-box";

      const heading = document.createElement("h3");
      heading.textContent = `Girone ${label}`;
      box.appendChild(heading);

      const wrap = document.createElement("div");
      wrap.className = "girone-table-wrap";
      wrap.innerHTML = `
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Squadra</th>
              <th>Pti</th>
              <th>G</th>
              <th>V</th>
              <th>N</th>
              <th>P</th>
              <th>GF</th>
              <th>GS</th>
              <th>DR</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      `;

      const tbody = wrap.querySelector("tbody");
      orderedTeams.forEach((team, idx) => {
        const goldThreshold = setup.goldPerGroup > 0 ? setup.goldPerGroup : 0;
        const silverThreshold = setup.silverPerGroup > 0 ? setup.goldPerGroup + setup.silverPerGroup : null;
        tbody.appendChild(createClassificaRow(team, idx + 1, options.resolveLogoPath, goldThreshold, silverThreshold));
      });

      for (let idx = orderedTeams.length; idx < setup.teamsPerGroup; idx++) {
        tbody.appendChild(createPlaceholderRow(idx + 1));
      }

      box.appendChild(wrap);
      grid.appendChild(box);
    });

    return setup;
  }

  function bindClassificaTeamCells(scope, onOpenMatches) {
    if (typeof onOpenMatches !== "function") return;

    const root = scope || document;
    root.querySelectorAll(".team-cell").forEach(cell => {
      if (cell.dataset.bound === "1") return;

      const squadra = cell.dataset.teamName || cell.querySelector(".team-name")?.textContent.trim();
      if (!squadra) return;

      const apriPartite = (e) => {
        if (e) {
          e.preventDefault();
          e.stopPropagation();
        }
        onOpenMatches(squadra);
      };

      cell.style.cursor = "pointer";
      cell.setAttribute("role", "button");
      cell.setAttribute("tabindex", "0");
      cell.dataset.bound = "1";
      cell.addEventListener("click", apriPartite);
      cell.addEventListener("touchend", apriPartite, { passive: false });
      cell.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") apriPartite(e);
      });
    });
  }

  window.TorneoGironiHelper = {
    loadSquadreGironi,
    loadTorneoInfo,
    mergeGironiIntoClassifica,
    renderGroupedClassifica,
    resetGroupedClassifica,
    bindClassificaTeamCells
  };
})();
