<?php
require_once __DIR__ . '/../includi/require_login.php';
$assetVersion = '20260122';
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Christmas's cup - Tornei Old School</title>
  <link rel="stylesheet" href="../style.css?v=<?= $assetVersion ?>" />
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Oswald:wght@500&display=swap" rel="stylesheet">
  <script data-cfasync="false" src="https://cmp.gatekeeperconsent.com/min.js"></script>
  <script data-cfasync="false" src="https://the.gatekeeperconsent.com/cmp.min.js"></script><script async src="//www.ezojs.com/ezoic/sa.min.js"></script>
  <script>
    window.ezstandalone = window.ezstandalone || {};
    ezstandalone.cmd = ezstandalone.cmd || [];
  </script>
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
    }
    .torneo-title .fav-toggle {
      align-self: center;
      margin-top: 0;
    }
    .torneo-title h1 {
      margin: 0;
      text-align: center;
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
    }
  </style>
</head>

<body>

  <!-- HEADER -->
  <div id="header-container"></div>

  <!-- CONTENUTO PRINCIPALE -->
  <main class="content">
    <div class="torneo-hero">
      <img id="torneoHeroImg" src="/img/tornei/pallone.png" alt="Logo Christmas's cup">
      <div class="torneo-title">
        <h1 class="titolo">Christmas's cup</h1>
        <button type="button" class="fav-toggle" id="favTournamentBtn">☆ Segui torneo</button>
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

    <div class="albo-ad" style="margin: 10px 0 18px;">
      <!-- Pub orizz -->
      <ins class="adsbygoogle"
           style="display:block"
           data-ad-client="ca-pub-8390787841690316"
           data-ad-slot="3707275285"
           data-ad-format="auto"
           data-full-width-responsive="true"></ins>
      <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
      </script>
    </div>

    <!-- CLASSIFICA -->
    <section id="classifica" class="tab-section active">
      <h2>Classifica</h2>

      <!-- FILTRI FASE -->
      <div class="filtro-fase">
        <label for="faseSelect">Seleziona fase:</label>
        <div class="filtro-fase-selects">
          <select id="faseSelect">
            <option value="eliminazione" selected>ELIMINAZIONE DIRETTA</option>
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
        <!-- verrÃ  popolato via JS -->
      </div>

    </section>

    <!-- CALENDARIO -->
    <section id="calendario" class="tab-section">
      <h2>Calendario</h2>
      <div class="filtro-giornata">
        <div class="filtro-riga" id="wrapperGiornataSelect">
          <label for="giornataSelect">Turno:</label>
          <select id="giornataSelect">
            <option value="">Tutti</option>
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
      <h3>Struttura</h3>
      <p>
        Christmas Cup è <strong>solo eliminazione diretta</strong> a partire dagli <strong>ottavi di finale</strong>.
        Ogni turno è gara secca: in caso di pareggio si va direttamente ai <strong>rigori</strong>.
      </p>
    </div>

    <div class="regola">
      <h3>Regole di Gioco</h3>
      <ul>
        <li>Partita da <strong>2 tempi da 25 minuti</strong>.</li>
        <li><strong>1 VAR</strong> disponibile per squadra, con <strong>arbitro video</strong>.</li>
        <li>Previsti <strong>highlights</strong> e contenuti <strong>TikTok</strong> di ogni gara.</li>
      </ul>
    </div>

    <div class="regola">
      <h3>Premi</h3>
      <p>Premi confermati come di consueto:</p>
      <div class="premi-grid">
        <span>Miglior Giocatore</span>
        <span>Miglior Portiere</span>
        <span>Miglior Difensore</span>
        <span>Miglior Attaccante</span>
      </div>
    </div>
  </div>
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
  <script src="script-Christmasscup.js?v=<?= $assetVersion ?>"></script>

</body>
</html>
