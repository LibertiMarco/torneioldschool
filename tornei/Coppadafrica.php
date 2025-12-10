<?php
require_once __DIR__ . '/../includi/require_login.php';
require_once __DIR__ . '/../includi/seo.php';

$assetVersion = '20251208';
$baseUrl = seo_base_url();
$torneoSeo = [
  'title' => "Coppa d'Africa - Tornei Old School",
  'description' => "Coppa d'Africa: gironi, semifinali e finale in una sola serata. Calendario, classifiche, marcatori e rose aggiornate.",
  'url' => $baseUrl . '/tornei/Coppadafrica.php',
  'canonical' => $baseUrl . '/tornei/Coppadafrica.php',
  'type' => 'article',
  'image' => $baseUrl . '/img/logo_old_school_1200.png',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php render_seo_tags($torneoSeo); ?>
  <link rel="stylesheet" href="../style.css?v=<?= $assetVersion ?>" />
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
    /* Classifiche doppi gironi */
    .gironi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 18px;
    }
    .girone-box {
      position: relative;
    }
    .girone-box h3 {
      margin: 0 0 8px;
      color: #15293e;
      position: sticky;
      top: 0;
      z-index: 8;
      padding: 6px 8px;
      background: linear-gradient(145deg, #f7f9fc, #eef2f7);
      border-radius: 10px;
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
    /* Header sticky */
    .gironi-grid table th {
      position: sticky;
      top: 0;
      z-index: 3;
    }
    .gironi-grid table th:nth-child(2),
    .gironi-grid table td:nth-child(2) {
      text-align: left;
      width: 38%;
    }
    .gironi-grid table th:nth-child(1),
    .gironi-grid table td:nth-child(1) {
      width: 12%;
    }
    .gironi-grid table th:nth-child(n+3),
    .gironi-grid table td:nth-child(n+3) {
      width: 10%;
    }
    /* Prime due colonne sticky come nelle classifiche generali */
    #tableClassificaA th:nth-child(1),
    #tableClassificaA td:nth-child(1),
    #tableClassificaB th:nth-child(1),
    #tableClassificaB td:nth-child(1) {
      position: sticky;
      left: 0;
      min-width: 40px;
      width: 40px;
      z-index: 6;
      background: #fff;
    }
    #tableClassificaA th:nth-child(2),
    #tableClassificaA td:nth-child(2),
    #tableClassificaB th:nth-child(2),
    #tableClassificaB td:nth-child(2) {
      position: sticky;
      left: 40px;
      min-width: 170px;
      width: 170px;
      z-index: 5;
      background: #fff;
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
    .gironi-grid tr.gold-row td {
      background: transparent;
    }
    .gironi-grid tr.gold-row td:first-child {
      font-weight: 800;
      background: #FFD700 !important;
      color: #15293e !important;
    }
    /* Nasconde la seconda picklist non usata */
    #coppaSelect {
      display: none !important;
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
            <option value="girone" selected>GIRONI</option>
            <option value="eliminazione">FASE FINALE</option>
          </select>

          <select id="coppaSelect" style="display: none;">
            <option value="gold" selected>FASE FINALE</option>
          </select>
        </div>
      </div>

      <!-- CLASSIFICA DOPPIO GIRONE -->
      <div class="table-wrapper" id="classificaWrapper">
        <div class="gironi-grid">
          <div class="girone-box">
            <h3>Girone A</h3>
            <table id="tableClassificaA">
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

          <div class="girone-box">
            <h3>Girone B</h3>
            <table id="tableClassificaB">
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
        </div>
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
            <option value="REGULAR" selected>GIRONI</option>
            <option value="FINALE">FASE FINALE</option>
          </select>
        </div>
        <div class="filtro-riga" id="wrapperGiornataSelect" style="display:none;">
          <label for="giornataSelect">Turno:</label>
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
        Il torneo prevede <strong style="color:#d9a441;">8 squadre</strong> suddivise in <strong style="color:#d9a441;">2 gironi da 4</strong>, tutto in una sola serata.
      </p>
    </div>

    <div class="regola">
      <h3>Gironi</h3>
      <p>
        Ogni squadra gioca <strong>3 partite</strong> (una contro ognuna delle altre del girone).
        Le gare di girone durano <strong>15 minuti</strong> a tempo unico.
      </p>
      <p>Si qualificano le <strong style="color:#d9a441;">prime 2</strong> di ciascun girone.</p>
    </div>

    <div class="regola">
      <h3>Semifinali e Finale</h3>
      <p>
        Semifinali incrociate: <strong>1° Girone A vs 2° Girone B</strong> e <strong>1° Girone B vs 2° Girone A</strong>.
        Semifinali e finale durano <strong style="color:#d9a441;">20 minuti</strong> (tempo unico).
      </p>
      <p>Vince la Coppa d'Africa la squadra che si impone in finale.</p>
    </div>

    <div class="regola">
      <h3>Premi</h3>
      <p>Premiazioni a fine serata per squadra vincitrice e <strong style="color:#d9a441;">migliori giocatori</strong>.</p>
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
        <li><strong style="color:#d9a441;">6 partite</strong> di girone (3 per squadra).</li>
        <li><strong style="color:#d9a441;">2 semifinali</strong>.</li>
        <li><strong style="color:#d9a441;">1 finale</strong>.</li>
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















