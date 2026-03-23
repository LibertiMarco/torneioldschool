<?php
require_once __DIR__ . '/../includi/security.php';
require_once __DIR__ . '/../includi/db.php';

$normalizeTorneoText = static function ($value): string {
    if (!is_string($value)) {
        $value = (string)$value;
    }
    $value = str_replace("\r\n", "\n", $value);
    $value = @iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: $value;
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    return trim($value);
};

$torneoSlug = isset($torneoSlug) ? $normalizeTorneoText((string)$torneoSlug) : 'TEMPLATE_SLUG';
$torneoName = isset($torneoName) ? $normalizeTorneoText((string)$torneoName) : 'Torneo Template';
$assetVersion = isset($assetVersion) ? (string)$assetVersion : '20260323a';
$torneoConfigFallback = (isset($torneoConfigFallback) && is_array($torneoConfigFallback)) ? $torneoConfigFallback : [];
$torneoRulesMarkup = isset($torneoRulesMarkup) ? (string)$torneoRulesMarkup : '';

$torneoConfig = $torneoConfigFallback;

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
                        $torneoConfig = array_replace($torneoConfigFallback, $decoded);
                    }
                }
            }
            $stmt->close();
        }
    }
} catch (Throwable $e) {
    // ignora errori di lettura config: il fallback locale resta valido
}

if (!function_exists('renderRegoleMarkupFromText')) {
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
            $bodyLines = [];

            if ($firstLine !== '' && strpos($firstLine, ':') !== false) {
                [$rawTitle, $rest] = explode(':', $firstLine, 2);
                $rawTitle = trim($rawTitle);
                if ($rawTitle !== '') {
                    $title = htmlspecialchars($rawTitle, ENT_QUOTES, 'UTF-8');
                    $rest = trim($rest);
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
            if (!empty($bodyLines)) {
                $markup .= '<p>' . nl2br(htmlspecialchars(implode("\n", $bodyLines), ENT_QUOTES, 'UTF-8')) . '</p>';
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

$regoleMarkup = '';
if (!empty($torneoConfig['regole_html'])) {
    $regoleMarkup = renderRegoleMarkupFromText((string)$torneoConfig['regole_html']);
} elseif ($torneoRulesMarkup !== '') {
    $regoleMarkup = $torneoRulesMarkup;
} else {
    $regoleMarkup = '<div class="regola"><p>Le regole saranno pubblicate a breve.</p></div>';
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

  <div id="header-container"></div>

  <main class="content">
    <div class="torneo-hero">
      <img id="torneoHeroImg" src="/img/tornei/pallone.png" alt="Logo <?= htmlspecialchars($torneoName) ?>">
      <div class="torneo-title">
        <h1 class="titolo"><?= htmlspecialchars($torneoName) ?></h1>
        <button type="button" class="fav-toggle" id="favTournamentBtn">&#9734; Segui torneo</button>
      </div>
    </div>

    <nav class="tabs">
      <button class="tab-button active" data-tab="classifica">Classifica</button>
      <button class="tab-button" data-tab="marcatori">Classifica Marcatori</button>
      <button class="tab-button" data-tab="calendario">Calendario</button>
      <button class="tab-button" data-tab="rose">Rose Squadre</button>
      <button class="tab-button" data-tab="regole">Regole</button>
    </nav>

    <section id="classifica" class="tab-section active">
      <h2>Classifica</h2>

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

      <div id="playoffContainer" style="display:none;">
        <!-- popolato via JS -->
      </div>
    </section>

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

  <section id="regole" class="tab-section">
    <h2 class="titolo-sezione">Regole del Torneo</h2>
    <div class="regole-box" id="regoleBox">
      <?= $regoleMarkup ?>
    </div>
    <br><br><br><br>
  </section>

  <section id="marcatori" class="tab-section">
    <h2>Classifica Marcatori</h2>
    <div class="marcatori-list" id="marcatoriList"></div>
    <div class="marcatori-pagination">
      <button class="pill-btn pill-btn--ghost" id="prevMarcatori" disabled>Precedente</button>
      <span id="marcatoriPageInfo"></span>
      <button class="pill-btn" id="nextMarcatori" disabled>Successiva</button>
    </div>
  </section>

  <div id="footer-container"></div>

  <script src="/includi/header-interactions.js?v=<?= $assetVersion ?>"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
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

      fetch("/includi/header.php?v=<?= $assetVersion ?>")
        .then(response => response.text())
        .then(data => {
          document.getElementById("header-container").innerHTML = data;
          initHeaderInteractions();

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

  <script>
    fetch("/includi/footer.html?v=<?= $assetVersion ?>")
      .then(response => response.text())
      .then(data => {
        document.getElementById("footer-container").innerHTML = data;
      })
      .catch(error => console.error("Errore nel caricamento del footer:", error));
  </script>

  <script>
    window.__TEMPLATE_TORNEO_SLUG__ = <?= json_encode($torneoSlug) ?>;
    window.__TORNEO_CONFIG__ = <?= json_encode($torneoConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="script-TorneoTemplate.js?v=<?= $assetVersion ?>"></script>

</body>
</html>
