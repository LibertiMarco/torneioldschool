<?php
require_once __DIR__ . '/../includi/require_login.php';
$assetVersion = '20251208';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Coppa d’Africa - Tornei Old School</title>
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
      <img id="torneoHeroImg" src="/img/tornei/pallone.png" alt="Logo Coppa d’Africa">
      <div class="torneo-title">
        <h1 class="titolo">Coppa d’Africa</h1>
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
        <!-- verrà popolato via JS -->
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
      <h3>Struttura del Torneo</h3>
      <p>
        Il torneo prevede <strong>8 squadre</strong> suddivise in <strong>2 gironi da 4</strong>, tutto in una sola serata.
      </p>
    </div>

    <div class="regola">
      <h3>Gironi</h3>
      <p>
        Ogni squadra gioca <strong>3 partite</strong> (una contro ognuna delle altre del girone).
        Le gare di girone durano <strong>15 minuti</strong> a tempo unico.
      </p>
      <p>Si qualificano le <strong>prime 2</strong> di ciascun girone.</p>
    </div>

    <div class="regola">
      <h3>Semifinali e Finale</h3>
      <p>
        Semifinali incrociate: <strong>1ª Girone A vs 2ª Girone B</strong> e <strong>1ª Girone B vs 2ª Girone A</strong>.
        Semifinali e finale durano <strong>20 minuti</strong> (tempo unico).
      </p>
      <p>Vince la Coppa d'Africa la squadra che si impone in finale.</p>
    </div>

    <div class="regola">
      <h3>Premi</h3>
      <p>Premiazioni a fine serata per squadra vincitrice e migliori giocatori.</p>
    </div>

    <div class="regola">
      <h3>Regole di Gioco</h3>
      <ul>
        <li>Gironi: <strong>15 minuti</strong> tempo unico.</li>
        <li>Semifinali e finale: <strong>20 minuti</strong> tempo unico.</li>
        <li>Si gioca tutto in una notte: rispetto degli orari tra i match.</li>
      </ul>
    </div>

    <div class="regola">
      <h3>Formula</h3>
      <ul>
        <li>6 partite di girone (3 per squadra).</li>
        <li>2 semifinali.</li>
        <li>1 finale.</li>
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
  <script src="script-Coppadafrica.js?v=<?= $assetVersion ?>"></script>

</body>
</html>


