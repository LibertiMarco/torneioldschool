<?php
require_once __DIR__ . '/../includi/require_login.php';

// Template base per creare un nuovo torneo:
// 1) Duplica questo file e rinominalo (es. NuovoTorneo.php)
// 2) Imposta $torneoSlug e $torneoName qui sotto
// 3) Duplica anche script-McLeague.js rinominandolo in script-NuovoTorneo.js
// 4) Nel nuovo JS sostituisci TORNEO con lo stesso slug usato nel DB/API
// 5) (Opzionale) Aggiorna assetVersion per forzare la cache
$torneoSlug = 'McLeague';
$torneoName = 'Mc League';
$assetVersion = '20260429a';

require_once __DIR__ . '/../includi/db.php';
$torneoConfig = [];
$regoleMarkup = '';
try {
    if (isset($conn) && $conn instanceof mysqli) {
        $filetorneo = $torneoSlug . '.php';
        if ($stmt = $conn->prepare("SELECT config FROM tornei WHERE filetorneo = ? LIMIT 1")) {
            $stmt->bind_param('s', $filetorneo);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                if ($row && !empty($row['config'])) {
                    $decoded = json_decode($row['config'], true);
                    if (is_array($decoded)) {
                        $torneoConfig = $decoded;
                    }
                }
            }
            $stmt->close();
        }
    }
} catch (Throwable $e) {
    // ignora eventuali errori di lettura config
}

if (!function_exists('renderRegoleMarkupFromText')) {
    function decorateRegoleInline(string $text): string {
        $placeholders = [
            '__TROFEO_RS_START__' => '<span class="highlight">',
            '__TROFEO_RS_END__' => '</span>',
            '__COPPA_GOLD_START__' => '<span class="gold">',
            '__COPPA_GOLD_END__' => '</span>',
            '__COPPA_SILVER_START__' => '<span class="silver">',
            '__COPPA_SILVER_END__' => '</span>',
        ];

        $text = str_replace('Trofeo Regular Season', '__TROFEO_RS_START__Trofeo Regular Season__TROFEO_RS_END__', $text);
        $text = str_replace('Coppa Gold', '__COPPA_GOLD_START__Coppa Gold__COPPA_GOLD_END__', $text);
        $text = str_replace('Coppa Silver', '__COPPA_SILVER_START__Coppa Silver__COPPA_SILVER_END__', $text);

        return strtr(htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8'), $placeholders);
    }

    function renderRegoleMarkupFromText(string $text): string {
        $blocks = preg_split("/\n\s*\n/u", trim($text));
        $markup = '';

        foreach ($blocks as $block) {
            $block = trim((string)$block);
            if ($block === '') {
                continue;
            }

            $lines = preg_split("/\n/u", $block);
            $firstLine = trim((string)array_shift($lines));
            $title = '';
            $titleSource = '';
            $bodyLines = [];

            if ($firstLine !== '' && strpos($firstLine, ':') !== false) {
                [$rawTitle, $rest] = explode(':', $firstLine, 2);
                $rawTitle = trim($rawTitle);
                if ($rawTitle !== '') {
                    $title = htmlspecialchars(str_replace(' - ', ' — ', $rawTitle), ENT_QUOTES, 'UTF-8');
                    $rest = trim($rest);
                    $titleSource = $rawTitle;
                    $title = str_replace(' - ', ' &mdash; ', htmlspecialchars($rawTitle, ENT_QUOTES, 'UTF-8'));
                    if ($rest !== '') {
                        $bodyLines[] = $rest;
                    }
                } else {
                    $bodyLines[] = $firstLine;
                }
            } elseif ($firstLine !== '') {
                $bodyLines[] = $firstLine;
            }

            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line !== '') {
                    $bodyLines[] = $line;
                }
            }

            $markup .= '<div class="regola">';
            if ($title !== '') {
                $markup .= '<h3>' . $title . '</h3>';
            }

            $normalizedTitle = function_exists('mb_strtolower')
                ? mb_strtolower(strip_tags($title), 'UTF-8')
                : strtolower(strip_tags($title));
            $normalizedTitle = function_exists('mb_strtolower')
                ? mb_strtolower($titleSource, 'UTF-8')
                : strtolower($titleSource);
            $trimmedLines = array_values(array_filter(array_map(static function ($line) {
                return trim((string)$line);
            }, $bodyLines), static function ($line) {
                return $line !== '';
            }));

            $allBullets = !empty($trimmedLines);
            foreach ($trimmedLines as $line) {
                if (!preg_match('/^[-*•]\s+/u', $line)) {
                    $allBullets = false;
                    break;
                }
            }

            $useList = $allBullets || in_array($normalizedTitle, ['fase 2 — coppe', 'regole di gioco', 'calendario'], true);
            $allBullets = !empty($trimmedLines);
            foreach ($trimmedLines as $line) {
                if (!preg_match('/^(?:[-*]|\x{2022})\s+/u', $line)) {
                    $allBullets = false;
                    break;
                }
            }
            $useList = $allBullets || in_array($normalizedTitle, ['fase 2 - coppe', 'regole di gioco', 'calendario'], true);
            $usePremiGrid = $normalizedTitle === 'premi finali' && count($trimmedLines) > 1;

            if ($usePremiGrid) {
                $intro = array_shift($trimmedLines);
                if ($intro !== null && $intro !== '') {
                    $markup .= '<p>' . decorateRegoleInline($intro) . '</p>';
                }
                if (!empty($trimmedLines)) {
                    $markup .= '<div class="premi-grid">';
                    foreach ($trimmedLines as $line) {
                        $line = preg_replace('/^[-*•]\s+/u', '', $line);
                        $line = preg_replace('/^(?:[-*]|\x{2022})\s+/u', '', $line);
                        $markup .= '<span>' . decorateRegoleInline($line) . '</span>';
                    }
                    $markup .= '</div>';
                }
            } elseif ($useList) {
                $markup .= '<ul>';
                foreach ($trimmedLines as $line) {
                    $line = preg_replace('/^[-*•]\s+/u', '', $line);
                    $line = preg_replace('/^(?:[-*]|\x{2022})\s+/u', '', $line);
                    $markup .= '<li>' . decorateRegoleInline($line) . '</li>';
                }
                $markup .= '</ul>';
            } elseif (!empty($trimmedLines)) {
                foreach ($trimmedLines as $line) {
                    $markup .= '<p>' . decorateRegoleInline($line) . '</p>';
                }
            }
            $markup .= '</div>';
        }

        return $markup;
    }
}

$qualificatiGold = array_key_exists('qualificati_gold', $torneoConfig)
    ? max(0, (int)$torneoConfig['qualificati_gold'])
    : null;
$qualificatiSilver = array_key_exists('qualificati_silver', $torneoConfig)
    ? max(0, (int)$torneoConfig['qualificati_silver'])
    : null;
$showGoldCup = $qualificatiGold === null ? true : $qualificatiGold > 0;
$showSilverCup = $qualificatiSilver === null ? true : $qualificatiSilver > 0;

if (!empty($torneoConfig['regole_html'])) {
    $regoleMarkup = renderRegoleMarkupFromText((string)$torneoConfig['regole_html']);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($torneoName) ?> - Tornei Old School</title>
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
    .gironi-grid tr.eliminated-row td:first-child,
    #tableClassifica tr.eliminated-row td:first-child {
      font-weight: 800;
      background: #f8d7da !important;
      color: #8b1e2d !important;
    }
    .legenda-coppe .box.elim-box {
      background: #f8d7da;
      color: #8b1e2d;
      border: 1px solid #ef9aa5;
    }
    .gironi-grid tr.placeholder-row td {
      background: #f8fafc;
      color: #7b8798;
    }
    .gironi-grid .team-cell--placeholder {
      font-style: italic;
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
      <img id="torneoHeroImg" src="/img/tornei/pallone.png" alt="Logo <?= htmlspecialchars($torneoName) ?>">
      <div class="torneo-title">
        <h1 class="titolo"><?= htmlspecialchars($torneoName) ?></h1>
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
            <?php if ($showGoldCup): ?>
              <option value="gold">COPPA GOLD</option>
            <?php endif; ?>
            <?php if ($showSilverCup): ?>
              <option value="silver">COPPA SILVER</option>
            <?php endif; ?>
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
        <div id="gironiGrid" class="gironi-grid"></div>
      </div>

      <!-- PLAYOFF / BRACKET -->
      <div id="playoffContainer" style="display:none;">
        <!-- popolato via JS -->
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
            <?php if ($showGoldCup): ?>
              <option value="GOLD">Gold</option>
            <?php endif; ?>
            <?php if ($showSilverCup): ?>
              <option value="SILVER">Silver</option>
            <?php endif; ?>
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
    <div class="regole-box" id="regoleBox">
      <?php if ($regoleMarkup !== ''): ?>
        <?= $regoleMarkup ?>
      <?php else: ?>
        <div class="regola">
          <p>Le regole saranno pubblicate a breve.</p>
        </div>
      <?php endif; ?>
    </div>
    <br><br><br><br>
  </section>

  <!-- MARCATORI --><!-- MARCATORI -->
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

      // Gestione scelta fase classifica (UI base)
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

      // HEADER dinamico
      fetch("/includi/header.php?v=<?= $assetVersion ?>")
        .then(response => response.text())
        .then(data => {
          document.getElementById("header-container").innerHTML = data;
          initHeaderInteractions();

          // Fallback mobile toggle
          const headerEl = document.querySelector(".site-header");
          if (headerEl) {
            const mobileBtn = headerEl.querySelector("#mobileMenuBtn");
            const mainNav = headerEl.querySelector("#mainNav");
            const userBtn = headerEl.querySelector("#userBtn");
            const userMenu = headerEl.querySelector("#userMenu");
            let fallbackBound = false;

            const applyDisplay = (open) => {
              if (!mainNav) return;
              mainNav.classList.toggle("open", open);
              if (window.matchMedia("(max-width: 768px)").matches) {
                mainNav.style.display = open ? "flex" : "none";
              } else {
                mainNav.style.display = "";
              }
            };

            const toggleWorks = (() => {
              if (!mobileBtn || !mainNav) return false;
              const wasOpen = mainNav.classList.contains("open");
              mobileBtn.click();
              const changed = mainNav.classList.contains("open");
              if (changed !== wasOpen) {
                mainNav.classList.toggle("open");
                return true;
              }
              return false;
            })();

            if (!toggleWorks && mobileBtn && mainNav && !fallbackBound) {
              fallbackBound = true;

              const closeMenus = () => {
                applyDisplay(false);
                if (userMenu) userMenu.classList.remove("open");
              };

              mobileBtn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = !mainNav.classList.contains("open");
                applyDisplay(isOpen);
                if (isOpen && userMenu) userMenu.classList.remove("open");
              });

              if (userBtn && userMenu) {
                userBtn.addEventListener("click", (e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  const isOpen = userMenu.classList.toggle("open");
                  if (isOpen) applyDisplay(false);
                });
              }

              document.addEventListener("click", (e) => {
                if (!headerEl.contains(e.target)) closeMenus();
              });

              window.addEventListener("resize", () => {
                if (window.innerWidth > 768) closeMenus();
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

  <!-- SCRIPT: TEMPLATE -->
  <script>
    // espone lo slug al JS template
    window.__TEMPLATE_TORNEO_SLUG__ = <?= json_encode($torneoSlug) ?>;
    window.__TORNEO_CONFIG__ = <?= json_encode($torneoConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="script-McLeague.js?v=<?= $assetVersion ?>"></script>

</body>
</html>
