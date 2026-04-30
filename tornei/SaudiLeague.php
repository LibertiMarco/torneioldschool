<?php
require_once __DIR__ . '/../includi/require_login.php';
$assetVersion = '20260430c';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Saudi League 3.0 - Tornei Old School</title>
  <link rel="stylesheet" href="../style.css?v=<?= $assetVersion ?>" />
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Oswald:wght@500&display=swap" rel="stylesheet">
<style>
    main.content {
      margin-top: 30px;
      padding-top: 10px;
    }
    .torneo-hero {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 20px;
      margin-bottom: 25px;
      text-align: center;
      flex-wrap: wrap;
    }
    .torneo-hero img {
      width: 80px;
      height: 80px;
      border-radius: 18px;
      object-fit: cover;
      box-shadow: 0 12px 30px rgba(21, 41, 62, 0.22);
      border: 3px solid #fff;
    }
    .torneo-title {
      text-align: center;
      min-width: 220px;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 80px;
      gap: 12px;
      flex: 1 1 240px;
      max-width: 560px;
    }
    .torneo-title .fav-toggle {
      align-self: center;
      margin-top: 0;
    }
    .torneo-title h1 {
      margin: 0;
      text-align: center;
      font-size: clamp(20px, 5vw, 30px);
      line-height: 1.15;
      word-break: break-word;
      hyphens: auto;
      min-width: 0;
      flex: 1 1 auto;
    }
    .fav-toggle {
      border: 1px solid #15293e;
      background: #15293e;
      color: #fff;
      padding: 8px 14px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      line-height: 1;
      min-height: 36px;
      border-radius: 12px;
      font-weight: 700;
      letter-spacing: 0.3px;
      cursor: pointer;
      transition: transform 0.15s ease, background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .fav-toggle:hover {
      background: #0f1f33;
      border-color: #0f1f33;
      transform: translateY(-1px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.18);
    }
    .fav-toggle.is-fav {
      background: #d9a441;
      color: #0f1f33;
      border-color: #d9a441;
      box-shadow: 0 10px 18px rgba(217,164,65,0.25);
    }
    .fav-toggle.fav-toggle--small {
      padding: 7px 12px;
      font-size: 13px;
      letter-spacing: 0.2px;
    }
    #marcatori {
      margin-bottom: 48px;
    }
    #marcatori .table-wrapper {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    #marcatori .marcatori-table {
      min-width: 520px;
    }
    @media (max-width: 600px) {
      #marcatori .marcatori-table {
        min-width: 520px;
      }
    }
    @media (max-width: 600px) {
      .torneo-hero {
        flex-wrap: wrap;
      }
      .torneo-title {
        min-width: 0;
        width: 100%;
        max-width: 100%;
        height: auto;
        flex: 1 1 100%;
        flex-direction: column;
      }
      .torneo-title h1 {
        width: 100%;
      }
      .torneo-title .fav-toggle {
        width: 100%;
        max-width: 260px;
      }
    }
  </style>
</head>

<body>

  <!-- HEADER -->
  <div id="header-container"></div>

  <!-- CONTENUTO PRINCIPALE -->
  <main class="content">
    <div class="torneo-hero">
      <img id="torneoHeroImg" src="/img/tornei/pallone.png" alt="Logo Saudi League 3.0">
      <div class="torneo-title">
        <h1 class="titolo">Saudi League 3.0</h1>
        <button type="button" class="fav-toggle" id="favTournamentBtn">&#9734; Segui torneo</button>
      </div>
    </div>

    <!-- Sezioni navigazione -->
    <nav class="tabs">
      <button class="tab-button active" data-tab="classifica">Classifica</button>
      <button class="tab-button" data-tab="marcatori">Classifica Marcatori</button>
      <button class="tab-button" data-tab="calendario">Calendario</button>
      <button class="tab-button" data-tab="rose">Rose Squadre</button>
      <button class="tab-button" data-tab="regole">Regole</button>
    </nav>
<!-- CLASSIFICA -->
    <section id="classifica" class="tab-section active">
      <h2>Classifica</h2>

      <!-- FILTRI FASE -->
      <div class="filtro-fase">
        <label for="faseSelect">Seleziona fase:</label>
        <div class="filtro-fase-selects">
          <select id="faseSelect">
            <option value="girone" selected>REGULAR SEASON</option>
            <option value="eliminazione">ELIMINAZIONE DIRETTA</option>
          </select>

          <select id="coppaSelect" style="display: none;">
            <option value="gold">COPPA GOLD</option>
            <option value="silver">COPPA SILVER</option>
          </select>
        </div>
      </div>

      <!-- CLASSIFICA NORMALE -->
      <div class="table-wrapper" id="classificaWrapper">
        <table id="tableClassifica">
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
      </div>

      <!-- PLAYOFF / BRACKET -->
      <div id="playoffContainer" style="display:none;">
        <!-- verr&agrave; popolato via JS -->
      </div>

    </section>

    <!-- CALENDARIO -->
    <section id="calendario" class="tab-section">
      <h2>Calendario</h2>
      <div class="filtro-giornata">
        <div class="filtro-riga">
          <label for="faseCalendario">Fase:</label>
          <select id="faseCalendario">
            <option value="REGULAR" selected>Regular</option>
            <option value="GOLD">Gold</option>
            <option value="SILVER">Silver</option>
          </select>
        </div>
        <div class="filtro-riga" id="wrapperGiornataSelect">
          <label for="giornataSelect">Giornata:</label>
          <select id="giornataSelect">
            <option value="">Tutte</option>
          </select>
        </div>
      </div>
      <div id="contenitoreGiornate"></div>
    </section>

    <!-- ROSE -->
    <section id="rose" class="tab-section">
      <h2>Rose Squadre</h2>
      <div class="filtro-rosa">
        <label for="selectSquadra">Seleziona squadra:</label>
        <select id="selectSquadra">
          <option value="">-- Scegli una squadra --</option>
        </select>
      </div>
      <div id="rosaContainer"></div><br><br>
    </section>
  </main>

<!-- REGOLE -->
<section id="regole" class="tab-section">
  <h2 class="titolo-sezione">Regole del Torneo</h2>

  <div class="regole-box">
    <div class="regola">
      <h3>Struttura del campionato</h3>
      <p>
        Il torneo &egrave; composto da <strong>18 squadre</strong> e si sviluppa in <strong>due fasi principali</strong>.
      </p>
    </div>

    <div class="regola">
      <h3>Fase 1 &mdash; Regular Season</h3>
      <p>
        Tutte le squadre partecipano a una <strong>Regular Season</strong> in stile Champions League.
        Ogni squadra disputa <strong>8 partite</strong> totali.
      </p>
      <p>
        La squadra prima in classifica al termine del girone riceve il
        <span class="highlight">Trofeo Regular Season</span>.
      </p>
    </div>

    <div class="regola">
      <h3>Fase 2 &mdash; Coppe</h3>
      <ul>
        <li>Le <strong>prime 2</strong> classificate accedono ai <span class="gold">quarti della Coppa Gold</span>.</li>
        <li>Le squadre classificate dal <strong>3&deg; al 14&deg; posto</strong> accedono agli <span class="gold">ottavi della Coppa Gold</span>.</li>
        <li>Le <strong>ultime 4</strong> accedono alla <span class="silver">Coppa Silver</span>.</li>
        <li>Entrambe le coppe prevedono una <strong>premiazione con trofeo</strong> per la vincitrice.</li>
      </ul>
    </div>

    <div class="regola">
      <h3>Premi finali</h3>
      <p>Dopo la finale di <span class="gold">Coppa Gold</span> verranno assegnati i seguenti riconoscimenti:</p>
      <div class="premi-grid">
        <span>Miglior Giocatore</span>
        <span>Miglior Portiere</span>
        <span>Miglior Difensore</span>
        <span>Miglior Attaccante</span>
      </div>
      <p>
        Il <strong>Miglior Giocatore</strong> ricevera un
        <span class="highlight">buono tatuaggio da 500 euro</span>.
      </p>
    </div>

    <div class="regola">
      <h3>Regole di gioco</h3>
      <ul>
        <li>Ogni partita dura <strong>2 tempi da 25 minuti</strong>.</li>
        <li>I falli sono cumulativi, con un massimo di 5 per tempo. Dal sesto fallo in poi, per ogni ulteriore infrazione la squadra che lo subisce ha diritto a uno <strong>shootout</strong>. Al termine del primo tempo, il conteggio dei falli viene azzerato.</li>
        <li>Ogni squadra ha <strong>1 chiamata VAR</strong> disponibile per partita.</li>
      </ul>
    </div>

    <div class="regola">
      <h3>Calendario</h3>
      <ul>
        <li>Le partite si disputano principalmente <strong>il martedi, il mercoledi e il giovedi</strong>.</li>
        <li>Il <strong>calendario della settimana successiva</strong> viene pubblicato ogni <strong>giovedi o venerdi</strong>.</li>
      </ul>
    </div>
  </div>
  <br><br><br><br>
</section>

    <!-- MARCATORI -->
    <section id="marcatori" class="tab-section">
      <h2>Classifica Marcatori</h2>
      <div class="marcatori-list" id="marcatoriList"></div>
      <div class="marcatori-pagination">
        <button class="pill-btn pill-btn--ghost" id="prevMarcatori" disabled>Precedente</button>
        <span id="marcatoriPageInfo"></span>
        <button class="pill-btn" id="nextMarcatori" disabled>Successiva</button>
      </div>
    </section>

  <!-- FOOTER -->
  <div id="footer-container"></div>

  <!-- SCRIPT: HEADER -->
  <script src="/includi/header-interactions.js?v=<?= $assetVersion ?>"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {

      // ====== GESTIONE SCELTA FASE CLASSIFICA (UI base) ======
      const faseSelect = document.getElementById("faseSelect");
      const coppaSelect = document.getElementById("coppaSelect");

      if (faseSelect && coppaSelect) {
        faseSelect.value = "girone";
        coppaSelect.style.display = "none";

        faseSelect.addEventListener("change", () => {
          if (faseSelect.value === "eliminazione") {
            coppaSelect.style.display = "inline-block";
          } else {
            coppaSelect.style.display = "none";
            coppaSelect.value = "gold";
          }
        });
      }

      // ====== HEADER DINAMICO ======
      fetch("/includi/header.php?v=<?= $assetVersion ?>")
        .then(response => response.text())
        .then(data => {
          document.getElementById("header-container").innerHTML = data;
          initHeaderInteractions();

          const header = document.querySelector(".site-header");
          window.addEventListener("scroll", () => {
            if (window.scrollY > 50) header.classList.add("scrolled");
            else header.classList.remove("scrolled");
          });

          const dropdown = document.querySelector(".dropdown");
          if (dropdown) {
            const btn = dropdown.querySelector(".dropbtn");
            const menu = dropdown.querySelector(".dropdown-content");

            if (btn && menu) {
              btn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropdown.classList.toggle("open");
                menu.style.display = dropdown.classList.contains("open") ? "block" : "none";
              });

              document.addEventListener("click", (e) => {
                if (!dropdown.contains(e.target)) {
                  dropdown.classList.remove("open");
                  menu.style.display = "none";
                }
              });
            }
          }
        })
        .catch(error => console.error("Errore nel caricamento dell'header:", error));
    });
  </script>

  <!-- SCRIPT: FOOTER -->
  <script>
    fetch("/includi/footer.html?v=<?= $assetVersion ?>")
      .then(response => response.text())
      .then(data => {
        document.getElementById("footer-container").innerHTML = data;
      })
      .catch(error => console.error("Errore nel caricamento del footer:", error));
  </script>

  <!-- SCRIPT: SERIE A -->
  <script src="script-SaudiLeague.js?v=<?= $assetVersion ?>"></script>

</body>
</html>
