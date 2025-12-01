<?php
// === AVVIO SESSIONE ===
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// === CONNESSIONE AL DATABASE ===
require_once __DIR__ . '/includi/db.php'; // contiene $conn

// === FUNZIONE PER FORMATTARE LE DATE ===
if (!function_exists('formattaData')) {
  function formattaData($data) {
    if (empty($data) || $data === '0000-00-00') return '';
    $timestamp = strtotime($data);
    setlocale(LC_TIME, 'it_IT.UTF-8', 'it_IT', 'Italian_Italy.1252');
    return strftime('%d %B', $timestamp);
  }
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
require_once __DIR__ . '/includi/seo.php';
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
    .tournaments-page {
      max-width: 1200px;
      margin: 0 auto;
      padding: 50px 20px 80px;
    }
    .page-hero {
      margin-bottom: 30px;
    }
    .page-eyebrow {
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-weight: 800;
      color: #5b6b82;
      margin: 0;
      font-size: 0.9rem;
    }
    .page-hero h1 {
      margin: 6px 0;
      color: #15293e;
      font-size: 2rem;
    }
    .page-hero p {
      margin: 0;
      color: #4c5b71;
    }
    .section-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 14px;
    }
    .section-title h2 {
      margin: 0;
      color: #15293e;
    }
    .status-pill {
      padding: 6px 10px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 0.9rem;
      background: #e8edf5;
      color: #1a2d44;
    }
    .status-pill.live { background: #e8f7f0; color: #0f5132; }
    .status-pill.next { background: #f6f1ff; color: #5b3ba3; }
    .status-pill.done { background: #f4f6f8; color: #475569; }

    .tournament-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 18px;
    }
    .tournament-card {
      display: block;
      text-decoration: none;
      color: inherit;
      background: #fff;
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid #e5e9f0;
      box-shadow: 0 14px 35px rgba(15, 31, 51, 0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .tournament-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 18px 45px rgba(15, 31, 51, 0.12);
    }
    .tournament-cover {
      position: relative;
      height: 170px;
      background: linear-gradient(135deg, #15293e, #1f3f63);
    }
    .tournament-cover img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .category-pill {
      position: absolute;
      top: 12px;
      left: 12px;
      background: rgba(0, 0, 0, 0.7);
      color: #fff;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 0.9rem;
    }
    .tournament-body {
      padding: 16px;
      display: grid;
      gap: 8px;
    }
    .tournament-name {
      font-size: 1.1rem;
      font-weight: 800;
      color: #15293e;
      margin: 0;
    }
    .tournament-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      color: #4c5b71;
      font-weight: 600;
      flex-wrap: wrap;
    }
    .muted { color: #4c5b71; }
    .tournament-dates {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .tournament-status {
      padding: 6px 10px;
      border-radius: 10px;
      font-size: 0.85rem;
      font-weight: 800;
      background: #f2f6ff;
      color: #1a2d44;
    }
    .tournament-status.live { background: #e8f7f0; color: #0f5132; }
    .tournament-status.next { background: #f6f1ff; color: #5b3ba3; }
    .tournament-status.done { background: #f4f6f8; color: #475569; }
    .empty-state {
      color: #5f6b7b;
      margin: 0;
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

    @media (max-width: 640px) {
      .tournaments-page { padding: 40px 16px 70px; }
      .tournament-cover { height: 150px; }
    }
  </style>
</head>

<body>

  <!-- HEADER -->
  <div id="header-container"></div>

  <!-- CONTENUTO PRINCIPALE -->
  <div class="content tournaments-page">
    <div class="page-hero">
      <p class="page-eyebrow">Calendari & risultati</p>
      <h1>I tornei Old School</h1>
      <p>Scopri i tornei in corso, quelli in programma e gli archivi terminati con calendari, risultati e tabelloni.</p>
    </div>

    <!-- TORNEI IN CORSO -->
    <section class="tournament-section" style="margin-top:30px;">
      <div class="section-title">
        <h2>Tornei in corso</h2>
        <span class="status-pill live">In corso</span>
      </div>
      <div class="tournament-grid">
        <?php if (!empty($tornei['in corso'])): ?>
          <?php foreach ($tornei['in corso'] as $t): ?>
            <?php
              $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#';
              $img = !empty($t['img']) ? $t['img'] : '/img/tornei/pallone.png';
              $dates = (!empty($t['data_inizio']) || !empty($t['data_fine']))
                ? 'Dal ' . htmlspecialchars(formattaData($t['data_inizio'])) . (!empty($t['data_fine']) ? ' - ' . htmlspecialchars(formattaData($t['data_fine'])) : '')
                : '';
            ?>
            <a class="tournament-card" href="<?= htmlspecialchars($link) ?>">
              <div class="tournament-cover">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <?php if (!empty($t['categoria'])): ?>
                  <span class="category-pill"><?= htmlspecialchars($t['categoria']) ?></span>
                <?php endif; ?>
              </div>
              <div class="tournament-body">
                <div class="tournament-name"><?= htmlspecialchars($t['nome']) ?></div>
                <?php if ($dates): ?>
                  <div class="tournament-dates"><?= $dates ?></div>
                <?php endif; ?>
                <div class="tournament-meta">
                  <span class="tournament-status live">Live</span>
                  <span class="muted">Calendario e risultati</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty-state">Nessun torneo in corso al momento.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- TORNEI PROGRAMMATI -->
    <section class="tournament-section" style="margin-top:40px;">
      <div class="section-title">
        <h2>Tornei programmati</h2>
        <span class="status-pill next">In arrivo</span>
      </div>
      <div class="tournament-grid">
        <?php if (!empty($tornei['programmato'])): ?>
          <?php foreach ($tornei['programmato'] as $t): ?>
            <?php
              $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#';
              $img = !empty($t['img']) ? $t['img'] : '/img/tornei/pallone.png';
              $dates = (!empty($t['data_inizio']) || !empty($t['data_fine']))
                ? 'Dal ' . htmlspecialchars(formattaData($t['data_inizio'])) . (!empty($t['data_fine']) ? ' - ' . htmlspecialchars(formattaData($t['data_fine'])) : '')
                : '';
            ?>
            <a class="tournament-card" href="<?= htmlspecialchars($link) ?>">
              <div class="tournament-cover">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <?php if (!empty($t['categoria'])): ?>
                  <span class="category-pill"><?= htmlspecialchars($t['categoria']) ?></span>
                <?php endif; ?>
              </div>
              <div class="tournament-body">
                <div class="tournament-name"><?= htmlspecialchars($t['nome']) ?></div>
                <?php if ($dates): ?>
                  <div class="tournament-dates"><?= $dates ?></div>
                <?php endif; ?>
                <div class="tournament-meta">
                  <span class="tournament-status next">Prossimo</span>
                  <span class="muted">Calendario e risultati</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty-state">Nessun torneo programmato al momento.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- TORNEI TERMINATI -->
    <section class="tournament-section" style="margin-top:40px; margin-bottom:80px;">
      <div class="section-title">
        <h2>Tornei terminati</h2>
        <span class="status-pill done">Archivio</span>
      </div>
      <div class="tournament-grid">
        <?php if (!empty($tornei['terminato'])): ?>
          <?php foreach ($tornei['terminato'] as $t): ?>
            <?php
              $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#';
              $img = !empty($t['img']) ? $t['img'] : '/img/tornei/pallone.png';
              $dates = (!empty($t['data_inizio']) || !empty($t['data_fine']))
                ? 'Dal ' . htmlspecialchars(formattaData($t['data_inizio'])) . (!empty($t['data_fine']) ? ' - ' . htmlspecialchars(formattaData($t['data_fine'])) : '')
                : '';
            ?>
            <a class="tournament-card" href="<?= htmlspecialchars($link) ?>">
              <div class="tournament-cover">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <?php if (!empty($t['categoria'])): ?>
                  <span class="category-pill"><?= htmlspecialchars($t['categoria']) ?></span>
                <?php endif; ?>
              </div>
              <div class="tournament-body">
                <div class="tournament-name"><?= htmlspecialchars($t['nome']) ?></div>
                <?php if ($dates): ?>
                  <div class="tournament-dates"><?= $dates ?></div>
                <?php endif; ?>
                <div class="tournament-meta">
                  <span class="tournament-status done">Terminato</span>
                  <span class="muted">Calendario e risultati</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty-state">Nessun torneo terminato al momento.</p>
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
  <script src="/includi/app.min.js?v=20251204"></script>
  <script>
    fetch("/includi/footer.html")
      .then(r => r.text())
      .then(d => document.getElementById("footer-container").innerHTML = d);

    document.addEventListener("DOMContentLoaded", () => {
      fetch("/includi/header.php")
        .then(r => r.text())
        .then(d => {
          document.getElementById("header-container").innerHTML = d;
          initHeaderInteractions();
        });
    });
  </script>
</body>
</html>

