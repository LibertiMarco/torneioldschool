<?php
// === AVVIO SESSIONE E SICUREZZA ===
require_once __DIR__ . '/includi/require_login.php';

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
  $stato = strtolower(trim((string)($torneo['stato'] ?? '')));
  $statoMap = [
    'in corso' => ['label' => 'In corso', 'class' => 'incorso'],
    'programmato' => ['label' => 'Programmato', 'class' => 'programmato'],
    'terminato' => ['label' => 'Terminato', 'class' => 'terminato'],
  ];
  $statoInfo = $statoMap[$stato] ?? ['label' => 'Torneo', 'class' => 'default'];
  $searchText = trim($nome . ' ' . $categoria . ' ' . $range . ' ' . $statoInfo['label']);

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
      <div class="torneo-media">
        <img
          src="<?= escapeHtml($img) ?>"
          alt="<?= escapeHtml($nome) ?>"
          loading="lazy"
          decoding="async"
          onerror="this.src='/img/tornei/pallone.png';">
        <span class="torneo-state-badge torneo-state-badge--<?= escapeHtml($statoInfo['class']) ?>">
          <?= escapeHtml($statoInfo['label']) ?>
        </span>
      </div>
      <div class="torneo-card-body">
        <?php if ($categoria !== '' || $range !== ''): ?>
          <div class="torneo-meta-row">
            <?php if ($categoria !== ''): ?>
              <span class="torneo-chip"><?= escapeHtml($categoria) ?></span>
            <?php endif; ?>
            <?php if ($range !== ''): ?>
              <span class="torneo-period"><?= escapeHtml($range) ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <h3><?= escapeHtml($nome) ?></h3>
        <div class="torneo-footer">
          <span class="torneo-footer-copy">Scheda torneo, risultati e dettagli</span>
          <span class="torneo-link-cta">Apri</span>
        </div>
      </div>
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
$totaleTornei = array_sum($totaliTornei);
$totaleCategorie = count($categorieTornei);

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
      max-width: 1180px;
      margin: 22px auto 0;
      padding: 14px 16px 0;
      position: relative;
    }

    .tornei-hero {
      position: relative;
      overflow: hidden;
      margin-bottom: 26px;
      padding: clamp(24px, 4vw, 38px);
      border-radius: 30px;
      background:
        radial-gradient(circle at top right, rgba(244, 188, 92, 0.22), transparent 32%),
        linear-gradient(135deg, #102236 0%, #16314d 52%, #22547d 100%);
      color: #fff;
      box-shadow: 0 24px 60px rgba(16, 34, 54, 0.24);
    }
    .tornei-hero::before,
    .tornei-hero::after {
      content: "";
      position: absolute;
      border-radius: 999px;
      pointer-events: none;
      opacity: 0.55;
    }
    .tornei-hero::before {
      width: 280px;
      height: 280px;
      top: -120px;
      right: -80px;
      background: radial-gradient(circle, rgba(255,255,255,0.22), transparent 70%);
    }
    .tornei-hero::after {
      width: 220px;
      height: 220px;
      bottom: -90px;
      left: -60px;
      background: radial-gradient(circle, rgba(244,188,92,0.24), transparent 68%);
    }
    .tornei-hero-inner {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.95fr);
      gap: 26px;
      align-items: end;
    }
    .tornei-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.18);
      background: rgba(255,255,255,0.1);
      font-size: 0.8rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .tornei-hero h1 {
      margin: 16px 0 12px;
      max-width: 12ch;
      font-size: clamp(2rem, 4vw, 3.4rem);
      line-height: 0.98;
      letter-spacing: -0.04em;
      color: #fff;
    }
    .tornei-hero p {
      max-width: 58ch;
      margin: 0;
      color: rgba(255,255,255,0.84);
      font-size: 1rem;
      line-height: 1.65;
    }
    .tornei-hero-stats {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }
    .tornei-stat {
      padding: 16px 18px;
      border-radius: 20px;
      border: 1px solid rgba(255,255,255,0.14);
      background: rgba(255,255,255,0.1);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
      backdrop-filter: blur(10px);
    }
    .tornei-stat-value {
      display: block;
      margin-bottom: 4px;
      font-size: clamp(1.35rem, 2vw, 2rem);
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #fff;
    }
    .tornei-stat-label {
      display: block;
      color: rgba(255,255,255,0.76);
      font-size: 0.88rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .tornei-controls {
      position: relative;
      z-index: 2;
      margin: 0 0 22px;
    }
    .tornei-toolbar {
      display: grid;
      grid-template-columns: minmax(0, 1.35fr) minmax(220px, 0.8fr) auto;
      gap: 14px;
      align-items: end;
      padding: 18px;
      background: rgba(255,255,255,0.94);
      border: 1px solid #dce4f2;
      border-radius: 24px;
      box-shadow: 0 18px 44px rgba(21, 41, 62, 0.12);
      backdrop-filter: blur(10px);
    }
    .tornei-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 0;
    }
    .tornei-field label {
      font-size: 0.9rem;
      font-weight: 800;
      color: #15293e;
    }
    .tornei-search,
    .tornei-select {
      width: 100%;
      min-height: 50px;
      padding: 0 16px;
      border: 1px solid #cfd8e8;
      border-radius: 14px;
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      color: #15293e;
      font-size: 0.98rem;
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
      transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }
    .tornei-search::placeholder {
      color: #7f91aa;
    }
    .tornei-search:focus,
    .tornei-select:focus {
      outline: none;
      border-color: #4f6fbf;
      box-shadow: 0 0 0 4px rgba(79, 111, 191, 0.14);
      transform: translateY(-1px);
    }
    .tornei-toolbar-actions {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }
    .tornei-results {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 50px;
      padding: 0 16px;
      border-radius: 999px;
      border: 1px solid #dbe4f1;
      background: #eef4fb;
      color: #41526a;
      font-weight: 800;
      white-space: nowrap;
    }
    .tornei-reset {
      min-height: 50px;
      padding: 0 18px;
      border: 1px solid #d0daeb;
      border-radius: 14px;
      background: #fff;
      color: #15293e;
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .tornei-reset:hover:not(:disabled) {
      transform: translateY(-1px);
      border-color: #9fb2d4;
      box-shadow: 0 10px 22px rgba(21, 41, 62, 0.08);
    }
    .tornei-reset:disabled {
      opacity: 0.5;
      cursor: default;
      box-shadow: none;
    }

    .tornei-switch {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      width: 100%;
      margin: 14px 0 0;
      padding: 0;
      background: transparent;
      border: none;
      box-shadow: none;
    }
    .tornei-switch button {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      min-height: 76px;
      padding: 16px 18px;
      border: 1px solid #dbe3f0;
      border-radius: 22px;
      background: rgba(255,255,255,0.96);
      color: #15293e;
      cursor: pointer;
      box-shadow: 0 14px 28px rgba(21, 41, 62, 0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
    }
    .tornei-switch button:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 32px rgba(21, 41, 62, 0.1);
    }
    .tornei-switch button.active {
      border-color: transparent;
      background: linear-gradient(135deg, #15293e, #1f4a71);
      color: #fff;
      box-shadow: 0 20px 40px rgba(21, 41, 62, 0.2);
    }
    .tornei-tab-label {
      display: block;
      text-align: left;
      font-size: 0.98rem;
      font-weight: 800;
      line-height: 1.2;
    }
    .tornei-count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 32px;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(21, 41, 62, 0.08);
      color: inherit;
      font-size: 0.82rem;
      font-weight: 800;
      line-height: 1.2;
    }
    .tornei-switch button.active .tornei-count {
      background: rgba(255, 255, 255, 0.16);
    }

    .tornei-section {
      display: none;
      padding: clamp(18px, 2.5vw, 28px);
      border-radius: 28px;
      border: 1px solid #e1e8f3;
      background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
      box-shadow: 0 20px 44px rgba(21, 41, 62, 0.08);
    }
    .tornei-section.active {
      display: block;
      animation: torneiSectionReveal 0.28s ease;
    }
    @keyframes torneiSectionReveal {
      from {
        opacity: 0;
        transform: translateY(8px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .tornei-section-head {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }
    .tornei-section h2 {
      margin: 0;
      color: #15293e;
      font-size: clamp(1.45rem, 2.2vw, 2rem);
      letter-spacing: -0.03em;
    }
    .tornei-section-meta {
      margin: 0;
      padding: 8px 12px;
      border-radius: 999px;
      border: 1px solid #dae3f1;
      background: #eef4fb;
      color: #5b6b82;
      font-weight: 800;
      font-size: 0.92rem;
    }

    .news-grid {
      display: grid;
      gap: 18px;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      width: 100%;
      margin: 0;
    }
    .news-grid article {
      width: 100%;
      max-width: none;
      border-radius: 24px;
      overflow: hidden;
      border: 1px solid #e2eaf4;
      background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
      box-shadow: 0 16px 34px rgba(15, 31, 51, 0.08);
      transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    }
    .news-grid article[hidden] {
      display: none !important;
    }
    .news-grid article:hover {
      transform: translateY(-4px);
      border-color: #c8d5e7;
      box-shadow: 0 22px 40px rgba(15, 31, 51, 0.12);
    }
    .torneo-link {
      display: flex;
      flex-direction: column;
      height: 100%;
      text-decoration: none;
      color: inherit;
    }
    .torneo-media {
      position: relative;
      aspect-ratio: 16 / 10;
      overflow: hidden;
      background: linear-gradient(135deg, #16314d, #22547d);
    }
    .torneo-media::after {
      content: "";
      position: absolute;
      inset: auto 0 0;
      height: 60%;
      background: linear-gradient(180deg, rgba(0,0,0,0), rgba(7, 21, 34, 0.55));
      pointer-events: none;
    }
    .torneo-media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.38s ease;
    }
    .news-grid article:hover .torneo-media img {
      transform: scale(1.04);
    }
    .torneo-state-badge {
      position: absolute;
      top: 14px;
      left: 14px;
      z-index: 1;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 12px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.18);
      color: #fff;
      font-size: 0.76rem;
      font-weight: 800;
      letter-spacing: 0.02em;
      backdrop-filter: blur(10px);
      background: rgba(7, 21, 34, 0.28);
      box-shadow: 0 6px 18px rgba(0,0,0,0.14);
    }
    .torneo-state-badge--incorso {
      background: rgba(20, 128, 96, 0.72);
    }
    .torneo-state-badge--programmato {
      background: rgba(31, 74, 113, 0.72);
    }
    .torneo-state-badge--terminato {
      background: rgba(45, 56, 72, 0.72);
    }
    .torneo-card-body {
      display: flex;
      flex-direction: column;
      gap: 14px;
      min-height: 170px;
      padding: 16px 18px 18px;
    }
    .torneo-meta-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }
    .torneo-chip {
      display: inline-flex;
      align-items: center;
      padding: 6px 10px;
      border-radius: 999px;
      background: #edf4ff;
      color: #1f4f86;
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.01em;
    }
    .torneo-period {
      color: #5e7087;
      font-size: 0.84rem;
      font-weight: 800;
      white-space: nowrap;
    }
    .news-grid article h3 {
      min-height: 0;
      margin: 0;
      padding: 0;
      color: #15293e;
      font-size: 1.14rem;
      line-height: 1.22;
      letter-spacing: -0.02em;
    }
    .torneo-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: auto;
    }
    .torneo-footer-copy {
      color: #5a6c84;
      font-size: 0.92rem;
      font-weight: 700;
      line-height: 1.4;
    }
    .torneo-link-cta {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #15293e;
      font-size: 0.92rem;
      font-weight: 800;
      white-space: nowrap;
    }
    .torneo-link-cta::after {
      content: "->";
      font-size: 0.88rem;
    }
    .tornei-empty {
      margin: 22px 0 0;
      padding: 18px 20px;
      border: 1px dashed #cdd9ea;
      border-radius: 18px;
      background: #f7f9fc;
      color: #5b6b82;
      font-weight: 800;
    }
    .tornei-actions {
      display: flex;
      justify-content: center;
      margin-top: 22px;
    }
    .tornei-more {
      min-height: 48px;
      padding: 0 20px;
      border: 1px solid #d0d9e8;
      border-radius: 999px;
      background: #fff;
      color: #15293e;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 12px 24px rgba(21, 41, 62, 0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .tornei-more:hover {
      transform: translateY(-1px);
      box-shadow: 0 16px 28px rgba(21, 41, 62, 0.1);
      background: #f7f9fc;
    }
    .tornei-more[hidden] {
      display: none;
    }

    .cta-accesso {
      margin: 34px auto 70px;
      padding: 0 4px;
    }
    .cta-accesso .box {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 24px 26px;
      border-radius: 26px;
      border: 1px solid #dfe7f4;
      background: linear-gradient(135deg, #ffffff 0%, #f4f8fd 100%);
      box-shadow: 0 18px 40px rgba(21,41,62,0.08);
    }
    .cta-accesso h2 {
      margin: 0;
      color: #15293e;
      font-size: 1.38rem;
      letter-spacing: -0.02em;
    }
    .cta-accesso p {
      margin: 6px 0 0;
      color: #41526a;
      font-weight: 700;
      line-height: 1.55;
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
      min-height: 48px;
      padding: 0 18px;
      border-radius: 14px;
      text-decoration: none;
      border: 1px solid #15293e;
      color: #fff;
      background: linear-gradient(135deg, #15293e, #1f4a71);
      font-weight: 800;
      transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .cta-accesso .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 22px rgba(21,41,62,0.16);
    }
    .cta-accesso .btn.ghost {
      background: transparent;
      color: #15293e;
    }

    @media (max-width: 960px) {
      .tornei-hero-inner {
        grid-template-columns: 1fr;
      }
      .tornei-controls {
        margin-top: 0;
      }
      .tornei-toolbar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .tornei-toolbar-actions {
        grid-column: 1 / -1;
        justify-content: space-between;
      }
    }

    @media (max-width: 720px) {
      .content {
        margin-top: 16px;
        padding: 12px 12px 0;
      }
      .tornei-hero {
        border-radius: 24px;
        padding: 22px 18px;
      }
      .tornei-hero h1 {
        max-width: none;
      }
      .tornei-hero-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .tornei-toolbar {
        grid-template-columns: 1fr;
        padding: 16px;
        border-radius: 22px;
      }
      .tornei-toolbar-actions {
        justify-content: flex-start;
      }
      .tornei-results {
        order: -1;
        width: 100%;
        white-space: normal;
      }
      .tornei-reset {
        width: 100%;
      }
      .tornei-switch {
        grid-template-columns: 1fr;
      }
      .tornei-switch button {
        min-height: 0;
        padding: 14px 16px;
      }
      .tornei-section {
        padding: 18px;
        border-radius: 24px;
      }
      .news-grid {
        grid-template-columns: 1fr;
        gap: 14px;
      }
      .news-grid article {
        border-radius: 20px;
      }
      .torneo-link {
        display: grid;
        grid-template-columns: 108px minmax(0, 1fr);
        column-gap: 14px;
        min-height: 144px;
      }
      .torneo-media {
        aspect-ratio: auto;
        height: 100%;
      }
      .torneo-state-badge {
        top: 10px;
        left: 10px;
        font-size: 0.72rem;
        padding: 6px 10px;
      }
      .torneo-card-body {
        min-height: 0;
        gap: 10px;
        padding: 14px 14px 14px 0;
      }
      .torneo-meta-row {
        align-items: flex-start;
      }
      .torneo-period {
        white-space: normal;
      }
      .news-grid article h3 {
        font-size: 1rem;
      }
      .torneo-footer {
        flex-wrap: wrap;
        align-items: flex-start;
      }
      .torneo-footer-copy {
        width: 100%;
        font-size: 0.86rem;
      }
      .cta-accesso .box {
        padding: 20px;
        border-radius: 22px;
      }
      .cta-accesso .cta-actions {
        width: 100%;
      }
      .cta-accesso .btn {
        flex: 1 1 100%;
      }
    }

    @media (max-width: 420px) {
      .tornei-hero-stats {
        grid-template-columns: 1fr 1fr;
      }
      .tornei-stat {
        padding: 14px;
      }
      .torneo-link {
        grid-template-columns: 96px minmax(0, 1fr);
        column-gap: 12px;
      }
    }
  </style>
</head>

<body>

  <!-- HEADER -->
  <div id="header-container"></div>

  <!-- CONTENUTO PRINCIPALE -->
  <div class="content">

    <section class="tornei-hero" aria-label="Panoramica tornei">
      <div class="tornei-hero-inner">
        <div>
          <span class="tornei-kicker">Archivio tornei Old School</span>
          <h1>Tutti i tornei</h1>
          <p>Cerca in tempo reale, filtra per categoria e passa da in corso, programmati e terminati.</p>
        </div>
        <div class="tornei-hero-stats">
          <div class="tornei-stat">
            <span class="tornei-stat-value"><?= $totaleTornei ?></span>
            <span class="tornei-stat-label">Tornei totali</span>
          </div>
          <div class="tornei-stat">
            <span class="tornei-stat-value"><?= $totaliTornei['incorso'] ?></span>
            <span class="tornei-stat-label">Attivi in questo momento</span>
          </div>
          <div class="tornei-stat">
            <span class="tornei-stat-value"><?= $totaliTornei['programmati'] ?></span>
            <span class="tornei-stat-label">Gia programmati</span>
          </div>
          <div class="tornei-stat">
            <span class="tornei-stat-value"><?= $totaleCategorie ?></span>
            <span class="tornei-stat-label">Categorie disponibili</span>
          </div>
        </div>
      </div>
    </section>

    <div class="tornei-controls">
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
          <span class="tornei-results" id="torneiResults" role="status" aria-live="polite">Totale tornei: <?= $totaleTornei ?></span>
        </div>
      </div>

      <div class="tornei-switch" aria-label="Filtra tornei">
        <button type="button" class="active" data-target="incorso">
          <span class="tornei-tab-label">Tornei in corso</span>
          <span class="tornei-count" data-tab-count-for="incorso"><?= $totaliTornei['incorso'] ?></span>
        </button>
        <button type="button" data-target="programmati">
          <span class="tornei-tab-label">Tornei programmati</span>
          <span class="tornei-count" data-tab-count-for="programmati"><?= $totaliTornei['programmati'] ?></span>
        </button>
        <button type="button" data-target="terminati">
          <span class="tornei-tab-label">Tornei terminati</span>
          <span class="tornei-count" data-tab-count-for="terminati"><?= $totaliTornei['terminati'] ?></span>
        </button>
      </div>
    </div>
    <!-- TORNEI IN CORSO -->
    <section class="home-news tornei-section active" id="tornei-incorso" data-section-key="incorso" data-page-size="0" style="margin-top:14px;">
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
    <section class="home-news tornei-section" id="tornei-programmati" data-section-key="programmati" data-page-size="0" style="margin-top:14px;">
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
    <section class="home-news tornei-section" id="tornei-terminati" data-section-key="terminati" data-page-size="12" style="margin-top:14px; margin-bottom:80px;">
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


