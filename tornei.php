<?php
// === AVVIO SESSIONE E SICUREZZA ===
require_once __DIR__ . '/includi/security.php';

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

function escapeHtml(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

function renderTorneoCard(array $torneo): void {
  $link = resolveTorneoLink($torneo['filetorneo'] ?? '');
  $start = formattaMeseAnno($torneo['data_inizio'] ?? '');
  $end = formattaMeseAnno($torneo['data_fine'] ?? '');
  $range = ($start || $end) ? trim($start . ($end ? ' - ' . $end : '')) : '';
  $nome = trim((string)($torneo['nome'] ?? ''));
  $categoria = trim((string)($torneo['categoria'] ?? ''));
  $img = trim((string)($torneo['img'] ?? ''));
  $searchText = trim($nome . ' ' . $categoria . ' ' . $range);

  if ($img === '') {
    $img = '/img/tornei/pallone.png';
  }
  ?>
  <article
    class="torneo-card"
    data-name="<?= escapeHtml($nome) ?>"
    data-category="<?= escapeHtml($categoria) ?>"
    data-search="<?= escapeHtml($searchText) ?>">
    <a class="torneo-link" href="<?= escapeHtml($link) ?>">
      <img
        src="<?= escapeHtml($img) ?>"
        alt="<?= escapeHtml($nome) ?>"
        loading="lazy"
        decoding="async"
        onerror="this.src='/img/tornei/pallone.png';">
      <h3>
        <?= escapeHtml($nome) ?>
        <?php if ($categoria !== ''): ?>
          <span class="categoria">(<?= escapeHtml($categoria) ?>)</span>
        <?php endif; ?>
      </h3>
      <?php if ($range !== ''): ?>
        <p><?= escapeHtml($range) ?></p>
      <?php endif; ?>
    </a>
  </article>
  <?php
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

// Ordina i tornei in corso e programmati per data piu vicina, i terminati per data fine piu recente
$parseDate = static function (?string $date, int $fallback) {
  if (empty($date) || $date === '0000-00-00') return $fallback;
  $ts = strtotime($date);
  return $ts ?: $fallback;
};

if (!empty($tornei['in corso'])) {
  usort($tornei['in corso'], static function ($a, $b) use ($parseDate) {
    $tsA = $parseDate($a['data_fine'] ?? '', PHP_INT_MAX);
    $tsB = $parseDate($b['data_fine'] ?? '', PHP_INT_MAX);
    if ($tsA !== $tsB) {
      return $tsA <=> $tsB;
    }
    $startA = $parseDate($a['data_inizio'] ?? '', 0);
    $startB = $parseDate($b['data_inizio'] ?? '', 0);
    return $startB <=> $startA;
  });
}

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

$categorieTornei = [];
foreach ($tornei as $items) {
  foreach ($items as $torneo) {
    $categoria = trim((string)($torneo['categoria'] ?? ''));
    if ($categoria !== '') {
      $categorieTornei[$categoria] = $categoria;
    }
  }
}
natcasesort($categorieTornei);
$categorieTornei = array_values($categorieTornei);

$totaliTornei = [
  'incorso' => count($tornei['in corso']),
  'programmati' => count($tornei['programmato']),
  'terminati' => count($tornei['terminato']),
];

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
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php render_seo_tags($torneiSeo); ?>
  <?php render_jsonld($torneiBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
<style>
    .content {
      margin-top: 30px;
      padding-top: 10px;
    }

    .tornei-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      align-items: end;
      margin: 18px 0 8px;
      padding: 18px;
      background: #f5f8fc;
      border: 1px solid #dce4f2;
      border-radius: 18px;
      box-shadow: 0 12px 28px rgba(21, 41, 62, 0.08);
    }
    .tornei-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1 1 240px;
      min-width: 220px;
    }
    .tornei-field label {
      font-size: 0.92rem;
      font-weight: 700;
      color: #15293e;
    }
    .tornei-search,
    .tornei-select {
      width: 100%;
      min-height: 46px;
      padding: 0 14px;
      border: 1px solid #cdd8e8;
      border-radius: 12px;
      background: #fff;
      color: #15293e;
      font-size: 0.98rem;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }
    .tornei-search:focus,
    .tornei-select:focus {
      outline: none;
      border-color: #4f6fbf;
      box-shadow: 0 0 0 3px rgba(79, 111, 191, 0.18);
    }
    .tornei-toolbar-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .tornei-results {
      font-weight: 700;
      color: #41526a;
      white-space: nowrap;
    }
    .tornei-reset {
      min-height: 46px;
      padding: 0 16px;
      border: 1px solid #cdd8e8;
      border-radius: 12px;
      background: #fff;
      color: #15293e;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .tornei-reset:hover:not(:disabled) {
      transform: translateY(-1px);
      border-color: #9fb2d4;
      box-shadow: 0 10px 20px rgba(21, 41, 62, 0.08);
    }
    .tornei-reset:disabled {
      opacity: 0.5;
      cursor: default;
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
    .news-grid article[hidden] {
      display: none !important;
    }
    .news-grid article:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 36px rgba(15, 31, 51, 0.14);
    }
    .torneo-link {
      display: flex;
      flex-direction: column;
      height: 100%;
      text-decoration: none;
      color: inherit;
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
      flex-wrap: wrap;
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
    .tornei-count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 28px;
      padding: 2px 8px;
      margin-left: 8px;
      border-radius: 999px;
      background: rgba(21, 41, 62, 0.08);
      color: inherit;
      font-size: 0.86rem;
      line-height: 1.2;
    }
    .tornei-switch button.active .tornei-count {
      background: rgba(255, 255, 255, 0.2);
    }
    .tornei-section {
      display: none;
    }
    .tornei-section.active {
      display: block;
    }
    .tornei-section-head {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .tornei-section-meta {
      margin: 0;
      color: #5b6b82;
      font-weight: 700;
      font-size: 0.96rem;
    }
    .tornei-empty {
      margin: 18px 0 0;
      color: #5b6b82;
      font-weight: 700;
    }
    .tornei-actions {
      display: flex;
      justify-content: center;
      margin-top: 18px;
    }
    .tornei-more {
      min-height: 44px;
      padding: 0 18px;
      border: 1px solid #15293e;
      border-radius: 999px;
      background: #fff;
      color: #15293e;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .tornei-more:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 20px rgba(21, 41, 62, 0.1);
      background: #f7f9fc;
    }
    .tornei-more[hidden] {
      display: none;
    }

    /* CTA accesso non bloccante */
    .cta-accesso {
      margin: 32px auto 60px;
      max-width: 860px;
      padding: 0 12px;
    }
    .cta-accesso .box {
      background: #f5f8fc;
      border: 1px solid #dce4f2;
      border-radius: 16px;
      padding: 22px 24px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      box-shadow: 0 10px 28px rgba(21,41,62,0.08);
    }
    .cta-accesso h2 {
      margin: 0;
      color: #15293e;
      font-size: 1.35rem;
    }
    .cta-accesso p {
      margin: 6px 0 0;
      color: #41526a;
      font-weight: 600;
    }
    .cta-accesso .cta-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .cta-accesso .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 16px;
      border-radius: 12px;
      font-weight: 700;
      text-decoration: none;
      border: 1px solid #15293e;
      color: #fff;
      background: linear-gradient(135deg, #15293e, #1f3f63);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .cta-accesso .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 20px rgba(21,41,62,0.16);
    }
    .cta-accesso .btn.ghost {
      background: transparent;
      color: #15293e;
    }

    /* Mobile */
    @media (max-width: 768px) {
      .tornei-toolbar {
        padding: 16px;
      }
      .tornei-field,
      .tornei-toolbar-actions {
        flex-basis: 100%;
      }
      .tornei-toolbar-actions {
        justify-content: space-between;
      }
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

    <div class="tornei-toolbar" aria-label="Strumenti di ricerca tornei">
      <div class="tornei-field">
        <label for="torneiSearch">Cerca torneo</label>
        <input
          id="torneiSearch"
          class="tornei-search"
          type="search"
          placeholder="Nome torneo o categoria"
          autocomplete="off">
      </div>
      <div class="tornei-field">
        <label for="torneiCategoria">Categoria</label>
        <select id="torneiCategoria" class="tornei-select">
          <option value="">Tutte le categorie</option>
          <?php foreach ($categorieTornei as $categoria): ?>
            <option value="<?= escapeHtml($categoria) ?>"><?= escapeHtml($categoria) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="tornei-toolbar-actions">
        <button type="button" class="tornei-reset" id="torneiReset">Azzera filtri</button>
        <span class="tornei-results" id="torneiResults">Totale tornei: <?= $totaliTornei['incorso'] + $totaliTornei['programmati'] + $totaliTornei['terminati'] ?></span>
      </div>
    </div>

    <div class="tornei-switch" aria-label="Filtra tornei">
      <button type="button" class="active" data-target="incorso">
        Tornei in corso
        <span class="tornei-count" data-tab-count-for="incorso"><?= $totaliTornei['incorso'] ?></span>
      </button>
      <button type="button" data-target="programmati">
        Tornei programmati
        <span class="tornei-count" data-tab-count-for="programmati"><?= $totaliTornei['programmati'] ?></span>
      </button>
      <button type="button" data-target="terminati">
        Tornei terminati
        <span class="tornei-count" data-tab-count-for="terminati"><?= $totaliTornei['terminati'] ?></span>
      </button>
    </div>
    <!-- TORNEI IN CORSO -->
    <section class="home-news tornei-section active" id="tornei-incorso" data-section-key="incorso" data-page-size="0" style="margin-top:20px;">
      <div class="tornei-section-head">
        <h2>Tornei in corso</h2>
        <p class="tornei-section-meta"><span data-section-count-for="incorso"><?= $totaliTornei['incorso'] ?></span> visibili</p>
      </div>
      <div class="news-grid">
        <?php if (!empty($tornei['in corso'])): ?>
          <?php foreach ($tornei['in corso'] as $t): ?>
            <?php renderTorneoCard($t); ?>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Nessun torneo in corso al momento.</p>
        <?php endif; ?>
      </div>
      <?php if (!empty($tornei['in corso'])): ?>
        <p class="tornei-empty" hidden>Nessun torneo in corso corrisponde ai filtri attuali.</p>
      <?php endif; ?>
    </section>

    <!-- TORNEI PROGRAMMATI -->
    <section class="home-news tornei-section" id="tornei-programmati" data-section-key="programmati" data-page-size="0" style="margin-top:20px;">
      <div class="tornei-section-head">
        <h2>Tornei programmati</h2>
        <p class="tornei-section-meta"><span data-section-count-for="programmati"><?= $totaliTornei['programmati'] ?></span> visibili</p>
      </div>
      <div class="news-grid">
        <?php if (!empty($tornei['programmato'])): ?>
          <?php foreach ($tornei['programmato'] as $t): ?>
            <?php renderTorneoCard($t); ?>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Nessun torneo programmato al momento.</p>
        <?php endif; ?>
      </div>
      <?php if (!empty($tornei['programmato'])): ?>
        <p class="tornei-empty" hidden>Nessun torneo programmato corrisponde ai filtri attuali.</p>
      <?php endif; ?>
    </section>

    <!-- TORNEI TERMINATI -->
    <section class="home-news tornei-section" id="tornei-terminati" data-section-key="terminati" data-page-size="12" style="margin-top:20px; margin-bottom:80px;">
      <div class="tornei-section-head">
        <h2>Tornei terminati</h2>
        <p class="tornei-section-meta"><span data-section-count-for="terminati"><?= $totaliTornei['terminati'] ?></span> visibili</p>
      </div>
      <div class="news-grid">
        <?php if (!empty($tornei['terminato'])): ?>
          <?php foreach ($tornei['terminato'] as $t): ?>
            <?php renderTorneoCard($t); ?>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Nessun torneo terminato al momento.</p>
        <?php endif; ?>
      </div>
      <?php if (!empty($tornei['terminato'])): ?>
        <p class="tornei-empty" hidden>Nessun torneo terminato corrisponde ai filtri attuali.</p>
        <div class="tornei-actions">
          <button type="button" class="tornei-more" data-more-for="terminati" hidden>Carica altri</button>
        </div>
      <?php endif; ?>
    </section>

  </div>

  <!-- CTA ACCESSO NON BLOCCANTE -->
  <?php if (!$utente_loggato): ?>
    <div class="cta-accesso">
      <div class="box">
        <div>
          <h2>Vuoi seguire i tornei?</h2>
          <p>Crea un account o accedi per salvare preferiti, ricevere notifiche e seguire squadre e tornei.</p>
        </div>
        <div class="cta-actions">
          <a class="btn" href="/register.php">Iscriviti</a>
          <a class="btn ghost" href="/login.php">Accedi</a>
        </div>
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

      const searchInput = document.getElementById("torneiSearch");
      const categorySelect = document.getElementById("torneiCategoria");
      const resetButton = document.getElementById("torneiReset");
      const resultsCounter = document.getElementById("torneiResults");
      const tabs = Array.from(document.querySelectorAll(".tornei-switch button"));
      const sections = {
        "incorso": document.getElementById("tornei-incorso"),
        "programmati": document.getElementById("tornei-programmati"),
        "terminati": document.getElementById("tornei-terminati"),
      };
      const sectionOrder = Object.keys(sections);
      const countElements = Object.fromEntries(sectionOrder.map((key) => [
        key,
        document.querySelector(`[data-tab-count-for="${key}"]`)
      ]));
      const sectionCountElements = Object.fromEntries(sectionOrder.map((key) => [
        key,
        document.querySelector(`[data-section-count-for="${key}"]`)
      ]));
      const visibleState = {};
      let activeTab = "incorso";

      function normalizeSearchValue(value) {
        const raw = String(value || "").toLowerCase().trim();
        if (typeof raw.normalize !== "function") {
          return raw;
        }
        return raw.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
      }

      sectionOrder.forEach((key) => {
        const section = sections[key];
        if (!section) return;

        const pageSize = Math.max(0, Number(section.dataset.pageSize || 0));
        visibleState[key] = pageSize;

        section.querySelectorAll(".torneo-card").forEach((card) => {
          card.dataset.searchNormalized = normalizeSearchValue(card.dataset.search || "");
          card.dataset.categoryNormalized = normalizeSearchValue(card.dataset.category || "");
        });
      });

      function syncUrl() {
        const params = new URLSearchParams(window.location.search);
        const query = searchInput ? searchInput.value.trim() : "";
        const category = categorySelect ? categorySelect.value.trim() : "";

        if (query) params.set("q", query);
        else params.delete("q");

        if (category) params.set("categoria", category);
        else params.delete("categoria");

        if (activeTab && activeTab !== "incorso") params.set("tab", activeTab);
        else params.delete("tab");

        const next = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ""}${window.location.hash}`;
        window.history.replaceState({}, "", next);
      }

      function setActiveTab(target, options = {}) {
        if (!sections[target]) {
          return;
        }

        activeTab = target;
        tabs.forEach((button) => {
          button.classList.toggle("active", button.dataset.target === target);
        });

        sectionOrder.forEach((key) => {
          if (sections[key]) {
            sections[key].classList.toggle("active", key === target);
          }
        });

        if (options.syncUrl !== false) {
          syncUrl();
        }
      }

      function updateResultsSummary(totalVisible, totalAll) {
        if (!resultsCounter) {
          return;
        }

        resultsCounter.textContent = totalVisible === totalAll
          ? `Totale tornei: ${totalAll}`
          : `Risultati: ${totalVisible} su ${totalAll}`;
      }

      function updateResetButtonState() {
        if (!resetButton) {
          return;
        }

        const hasFilters = Boolean(
          (searchInput && searchInput.value.trim()) ||
          (categorySelect && categorySelect.value.trim())
        );
        resetButton.disabled = !hasFilters;
      }

      function renderSection(sectionKey) {
        const section = sections[sectionKey];
        if (!section) {
          return { total: 0, filtered: 0 };
        }

        const cards = Array.from(section.querySelectorAll(".torneo-card"));
        const emptyState = section.querySelector(".tornei-empty");
        const moreButton = section.querySelector(".tornei-more");
        const query = normalizeSearchValue(searchInput ? searchInput.value : "");
        const category = normalizeSearchValue(categorySelect ? categorySelect.value : "");
        const hasFilters = Boolean(query || category);
        const pageSize = Math.max(0, Number(section.dataset.pageSize || 0));

        const filteredCards = cards.filter((card) => {
          const matchesQuery = !query || (card.dataset.searchNormalized || "").includes(query);
          const matchesCategory = !category || (card.dataset.categoryNormalized || "") === category;
          return matchesQuery && matchesCategory;
        });

        const visibleLimit = pageSize > 0 && !hasFilters
          ? Math.max(pageSize, Number(visibleState[sectionKey] || pageSize))
          : filteredCards.length;

        cards.forEach((card) => {
          card.hidden = true;
        });

        filteredCards.forEach((card, index) => {
          card.hidden = index >= visibleLimit;
        });

        if (emptyState) {
          emptyState.hidden = filteredCards.length > 0;
        }

        if (moreButton) {
          const remaining = filteredCards.length - visibleLimit;
          moreButton.hidden = remaining <= 0;
          if (remaining > 0) {
            moreButton.textContent = `Carica altri ${Math.min(pageSize, remaining)}`;
          }
        }

        const total = cards.length;
        const filtered = filteredCards.length;
        const shown = filteredCards.filter((card) => !card.hidden).length;
        const countText = filtered === total ? `${total}` : `${filtered}/${total}`;

        if (countElements[sectionKey]) {
          countElements[sectionKey].textContent = countText;
        }
        if (sectionCountElements[sectionKey]) {
          sectionCountElements[sectionKey].textContent = shown;
        }

        return { total, filtered, shown };
      }

      function applyFilters(options = {}) {
        if (options.resetPagination !== false) {
          sectionOrder.forEach((key) => {
            const section = sections[key];
            if (!section) return;
            visibleState[key] = Math.max(0, Number(section.dataset.pageSize || 0));
          });
        }

        let totalVisible = 0;
        let totalAll = 0;

        sectionOrder.forEach((key) => {
          const summary = renderSection(key);
          totalVisible += summary.filtered;
          totalAll += summary.total;
        });

        updateResultsSummary(totalVisible, totalAll);
        updateResetButtonState();

        if (options.syncUrl !== false) {
          syncUrl();
        }
      }

      const params = new URLSearchParams(window.location.search);
      const initialQuery = params.get("q") || "";
      const initialCategory = params.get("categoria") || "";
      const initialTab = params.get("tab") || "incorso";

      if (searchInput) {
        searchInput.value = initialQuery;
      }
      if (categorySelect && Array.from(categorySelect.options).some((option) => option.value === initialCategory)) {
        categorySelect.value = initialCategory;
      }

      setActiveTab(initialTab, { syncUrl: false });
      applyFilters({ syncUrl: false });

      tabs.forEach(btn => {
        btn.addEventListener("click", () => {
          setActiveTab(btn.dataset.target || "");
        });
      });

      searchInput?.addEventListener("input", () => {
        applyFilters();
      });

      categorySelect?.addEventListener("change", () => {
        applyFilters();
      });

      resetButton?.addEventListener("click", () => {
        if (searchInput) searchInput.value = "";
        if (categorySelect) categorySelect.value = "";
        applyFilters();
      });

      document.querySelectorAll(".tornei-more").forEach((button) => {
        button.addEventListener("click", () => {
          const sectionKey = button.dataset.moreFor || "";
          const section = sections[sectionKey];
          if (!section) return;

          const pageSize = Math.max(0, Number(section.dataset.pageSize || 0));
          if (!pageSize) return;

          visibleState[sectionKey] = Math.max(pageSize, Number(visibleState[sectionKey] || pageSize)) + pageSize;
          renderSection(sectionKey);
        });
      });
    });
  </script>
</body>
</html>


