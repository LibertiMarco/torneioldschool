<?php
// === AVVIO SESSIONE ===
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// === CONNESSIONE AL DATABASE ===
require_once __DIR__ . '/includi/db.php'; // contiene $conn
require_once __DIR__ . '/includi/seo.php';

// === FUNZIONE PER FORMATTARE LE DATE ===
if (!function_exists('formattaData')) {
  function formattaData($data) {
    if (empty($data) || $data === '0000-00-00') return '';
    $ts = strtotime($data);
    if (!$ts) return '';

    // Usa IntlDateFormatter per evitare strftime (deprecato)
    $fmt = class_exists('IntlDateFormatter')
      ? new IntlDateFormatter('it_IT', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Europe/Rome', IntlDateFormatter::GREGORIAN, 'd MMMM')
      : null;

    return $fmt ? $fmt->format($ts) : date('d F', $ts);
  }
}

function formattaMeseAnno($data) {
  if (empty($data) || $data === '0000-00-00') return '';
  $ts = strtotime($data);
  if (!$ts) return '';

  $fmt = class_exists('IntlDateFormatter')
    ? new IntlDateFormatter('it_IT', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Europe/Rome', IntlDateFormatter::GREGORIAN, 'MM/yy')
    : null;

  return $fmt ? $fmt->format($ts) : date('m/y', $ts);
}

/**
 * Restituisce il percorso del file torneo forzando l'estensione .php e gestendo valori vuoti.
 */
function resolveTorneoLink(?string $filetorneo): string {
  $value = trim((string)$filetorneo);
  if ($value === '' || $value === '0') {
    return '#';
  }
  if (preg_match('#^https?://#i', $value)) {
    return $value;
  }
  $value = ltrim($value, '/');
  $slug = preg_replace('/\.(html?|php)$/i', '', $value);
  $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $slug);
  if ($slug === '') {
    return '#';
  }
  return 'tornei/' . $slug . '.php';
}

// === DATI UTENTE DALLA SESSIONE ===
$utente_loggato = isset($_SESSION['user_id']);
$ruolo = $_SESSION['ruolo'] ?? 'utente';
$nome_utente = $_SESSION['nome'] ?? '';
$cognome_utente = $_SESSION['cognome'] ?? '';
$nome_completo = trim($nome_utente . ' ' . $cognome_utente);

// === QUERY TORNEI ===
$sql = "SELECT nome, stato, data_inizio, data_fine, img, filetorneo, categoria FROM tornei";
$result = $conn->query($sql);

$tornei = [
  'in corso' => [],
  'programmato' => [],
  'terminato' => []
];

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $stato = strtolower(trim($row['stato']));
    if (isset($tornei[$stato])) {
      $tornei[$stato][] = $row;
    }
  }
}

// Ordina i tornei programmati per data di inizio piu vicina e i terminati per data fine piu recente
$parseDate = static function (?string $date, int $fallback) {
  if (empty($date) || $date === '0000-00-00') return $fallback;
  $ts = strtotime($date);
  return $ts ?: $fallback;
};

if (!empty($tornei['programmato'])) {
  usort($tornei['programmato'], static function ($a, $b) use ($parseDate) {
    $tsA = $parseDate($a['data_inizio'] ?? '', PHP_INT_MAX);
    $tsB = $parseDate($b['data_inizio'] ?? '', PHP_INT_MAX);
    return $tsA <=> $tsB; // piu vicina (piu piccola) prima
  });
}

if (!empty($tornei['terminato'])) {
  usort($tornei['terminato'], static function ($a, $b) use ($parseDate) {
    $tsA = $parseDate($a['data_fine'] ?? '', 0);
    $tsB = $parseDate($b['data_fine'] ?? '', 0);
    return $tsB <=> $tsA; // piu recente prima
  });
}

$baseUrl = seo_base_url();
$torneiSeo = [
  'title' => 'Tornei calcetto Napoli (5, 6, 8) - Calendari e risultati | Tornei Old School',
  'description' => 'Tornei di calcio a 5, calcio a 6 e calciotto (8) a Napoli: calendari, risultati, tabelloni e documenti per ogni torneo.',
  'url' => $baseUrl . '/tornei.php',
  'canonical' => $baseUrl . '/tornei.php',
];
$torneiBreadcrumbs = seo_breadcrumb_schema([
  ['name' => 'Home', 'url' => $baseUrl . '/'],
  ['name' => 'Tornei', 'url' => $baseUrl . '/tornei.php'],
]);
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php render_seo_tags($torneiSeo); ?>
  <?php render_jsonld($torneiBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <script data-cfasync="false" src="https://cmp.gatekeeperconsent.com/min.js"></script>
  <script data-cfasync="false" src="https://the.gatekeeperconsent.com/cmp.min.js"></script><script async src="//www.ezojs.com/ezoic/sa.min.js"></script>
  <script>
    window.ezstandalone = window.ezstandalone || {};
    ezstandalone.cmd = ezstandalone.cmd || [];
  </script>
  <style>
    .content {
      margin-top: 30px;
      padding-top: 10px;
    }

    /* Card tornei: layout invariato, look piu' pulito */
    .news-grid {
      display: grid;
      gap: 20px;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      width: 100%;
      margin: 0;
      justify-content: start;
    }
    .news-grid article {
      background: #fff;
      border-radius: 14px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      height: 100%;
      max-width: 280px;
      margin: 0 auto;
      border: 1px solid #e5e9f0;
      box-shadow: 0 12px 28px rgba(15, 31, 51, 0.1);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .news-grid article:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 36px rgba(15, 31, 51, 0.14);
    }
    .news-grid article img {
      width: 100%;
      height: 160px; /* grandezza immagine invariata */
      object-fit: cover;
      background: linear-gradient(135deg, #15293e, #1f3f63);
    }
    .news-grid article h3 {
      min-height: 45px;
      padding: 10px 12px 0;
      font-size: 18px;
      color: #15293e;
      margin: 0;
    }
    .news-grid article .categoria {
      font-size: 0.9rem;
      color: #5b6b82;
      font-weight: 600;
    }
    .news-grid article p {
      padding: 0 12px 14px;
      margin-top: auto;
      color: #4c5b71;
      font-weight: 600;
    }
    .tornei-switch {
      display: inline-flex;
      gap: 8px;
      margin: 20px 0 10px;
      padding: 6px;
      background: #e9edf4;
      border-radius: 999px;
      border: 1px solid #d7deea;
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
    }
    .tornei-switch button {
      border: none;
      background: transparent;
      padding: 10px 16px;
      border-radius: 999px;
      font-weight: 700;
      color: #15293e;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .tornei-switch button.active {
      background: linear-gradient(135deg, #15293e, #1f3f63);
      color: #fff;
      box-shadow: 0 8px 20px rgba(21, 41, 62, 0.18);
    }
    .tornei-section {
      display: none;
    }
    .tornei-section.active {
      display: block;
    }

    /* Popup accesso */
    .popup-accesso {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.6);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 3000;
    }
    .popup-accesso .box {
      background: #fff;
      color: #222;
      border-radius: 12px;
      padding: 30px;
      max-width: 300px;
      text-align: center;
      box-shadow: 0 0 15px rgba(0,0,0,0.3);
    }
    .popup-accesso button {
      background: #15293e;
      color: #fff;
      border: none;
      border-radius: 5px;
      padding: 8px 15px;
      margin-top: 10px;
      cursor: pointer;
    }
    .popup-accesso button:hover { background: #0e1d2e; }

    /* Mobile */
    @media (max-width: 768px) {
      .news-grid {
        grid-template-columns: 1fr;
        gap: 20px;
        justify-items: center;
      }
      .news-grid article {
        width: 95%;
        max-width: 500px;
        border-radius: 14px;
      }
      .news-grid article img {
        height: 200px; /* dimensione immagine mobile invariata */
      }
      .news-grid article h3 {
        font-size: 20px;
        padding: 12px 14px 6px;
      }
      .news-grid article p {
        font-size: 16px;
        padding: 0 14px 14px;
      }
    }
  </style>
</head>

<body>

  <!-- HEADER -->
  <div id="header-container"></div>

  <!-- CONTENUTO PRINCIPALE -->
  <div class="content">

    <div class="tornei-switch" aria-label="Filtra tornei">
      <button type="button" class="active" data-target="incorso">Tornei in corso</button>
      <button type="button" data-target="programmati">Tornei programmati</button>
      <button type="button" data-target="terminati">Tornei terminati</button>
    </div>
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

    <!-- TORNEI IN CORSO -->
    <section class="home-news tornei-section active" id="tornei-incorso" style="margin-top:20px;">
      <h2>Tornei in corso</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['in corso'])): ?>
          <?php foreach ($tornei['in corso'] as $t): ?>
            <?php $link = resolveTorneoLink($t['filetorneo'] ?? ''); ?>
            <?php
              $start = formattaMeseAnno($t['data_inizio']);
              $end = formattaMeseAnno($t['data_fine']);
              $range = ($start || $end) ? trim($start . ($end ? ' - ' . $end : '')) : '';
            ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if ($range): ?>
                  <p><?= htmlspecialchars($range) ?></p>
                <?php endif; ?>
              </a>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Nessun torneo in corso al momento.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- TORNEI PROGRAMMATI -->
    <section class="home-news tornei-section" id="tornei-programmati" style="margin-top:20px;">
      <h2>Tornei programmati</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['programmato'])): ?>
          <?php foreach ($tornei['programmato'] as $t): ?>
            <?php $link = resolveTorneoLink($t['filetorneo'] ?? ''); ?>
            <?php
              $start = formattaMeseAnno($t['data_inizio']);
              $end = formattaMeseAnno($t['data_fine']);
              $range = ($start || $end) ? trim($start . ($end ? ' - ' . $end : '')) : '';
            ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if ($range): ?>
                  <p><?= htmlspecialchars($range) ?></p>
                <?php endif; ?>
              </a>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Nessun torneo programmato al momento.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- TORNEI TERMINATI -->
    <section class="home-news tornei-section" id="tornei-terminati" style="margin-top:20px; margin-bottom:80px;">
      <h2>Tornei terminati</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['terminato'])): ?>
          <?php foreach ($tornei['terminato'] as $t): ?>
            <?php $link = resolveTorneoLink($t['filetorneo'] ?? ''); ?>
            <?php
              $start = formattaMeseAnno($t['data_inizio']);
              $end = formattaMeseAnno($t['data_fine']);
              $range = ($start || $end) ? trim($start . ($end ? ' - ' . $end : '')) : '';
            ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if ($range): ?>
                  <p><?= htmlspecialchars($range) ?></p>
                <?php endif; ?>
              </a>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Nessun torneo terminato al momento.</p>
        <?php endif; ?>
      </div>
    </section>

  </div>

  <!-- POPUP BLOCCO ACCESSO -->
  <?php if (!$utente_loggato): ?>
    <div class="popup-accesso">
      <div class="box">
        <h2>Accesso richiesto</h2>
        <p>Per visualizzare i tornei devi iscriverti o accedere al sito.</p>
        <button onclick="window.location.href='/login.php'">Accedi</button>
        <button onclick="window.location.href='/register.php'">Iscriviti</button>
      </div>
    </div>
  <?php endif; ?>

  <!-- FOOTER -->
  <div id="footer-container"></div>

  <!-- SCRIPTS FOOTER + HEADER -->
  <script src="/includi/app.min.js?v=20251220"></script>
  <script>
    fetch("/includi/footer.html")
      .then(r => r.text())
      .then(d => document.getElementById("footer-container").innerHTML = d);

    document.addEventListener("DOMContentLoaded", () => {
      fetch("/includi/header.php")
        .then(r => r.text())
        .then(d => {
          document.getElementById("header-container").innerHTML = d;
          if (typeof initHeaderInteractions === "function") {
            initHeaderInteractions();
          } else if (typeof window.initHeaderInteractions === "function") {
            window.initHeaderInteractions();
          } else {
            console.warn("initHeaderInteractions non disponibile, uso solo fallback inline");
          }

          // Fallback: se il toggle mobile non risponde, aggancia manualmente i listener
          const headerEl = document.querySelector(".site-header");
          if (headerEl) {
            const mobileBtn = headerEl.querySelector("#mobileMenuBtn");
            const mainNav = headerEl.querySelector("#mainNav");
            const userBtn = headerEl.querySelector("#userBtn");
            const userMenu = headerEl.querySelector("#userMenu");
            let fallbackBound = false;
            let forceBound = false;

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

            // Forza il toggle mobile anche se il test sopra sembra ok
            if (mobileBtn && mainNav && !forceBound) {
              forceBound = true;
              mobileBtn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") {
                  e.stopImmediatePropagation();
                }
                const isOpen = !mainNav.classList.contains("open");
                applyDisplay(isOpen);
                if (isOpen && userMenu) userMenu.classList.remove("open");
              }, { capture: true });
            }

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
        });

      // Switch tornei
      const tabs = document.querySelectorAll(".tornei-switch button");
      const sections = {
        "incorso": document.getElementById("tornei-incorso"),
        "programmati": document.getElementById("tornei-programmati"),
        "terminati": document.getElementById("tornei-terminati"),
      };

      tabs.forEach(btn => {
        btn.addEventListener("click", () => {
          const target = btn.dataset.target || "";
          tabs.forEach(b => b.classList.toggle("active", b === btn));
          Object.keys(sections).forEach(key => {
            if (sections[key]) {
              sections[key].classList.toggle("active", key === target);
            }
          });
        });
      });
    });
  </script>
</body>
</html>


