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

    /* Card tornei: layout invariato, look piu' pulito */
    .news-grid {
      display: grid;
      gap: 20px;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      max-width: 1100px;
      margin: 0 auto;
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

    <!-- TORNEI IN CORSO -->
    <section class="home-news" style="margin-top:50px;">
      <h2>Tornei in corso</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['in corso'])): ?>
          <?php foreach ($tornei['in corso'] as $t): ?>
            <?php $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#'; ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($t['data_inizio']) || !empty($t['data_fine'])): ?>
                  <p>Dal <?= htmlspecialchars(formattaData($t['data_inizio'])) ?><?php if (!empty($t['data_fine'])): ?> - <?= htmlspecialchars(formattaData($t['data_fine'])) ?><?php endif; ?></p>
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
    <section class="home-news" style="margin-top:50px;">
      <h2>Tornei programmati</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['programmato'])): ?>
          <?php foreach ($tornei['programmato'] as $t): ?>
            <?php $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#'; ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($t['data_inizio']) || !empty($t['data_fine'])): ?>
                  <p>Dal <?= htmlspecialchars(formattaData($t['data_inizio'])) ?><?php if (!empty($t['data_fine'])): ?> - <?= htmlspecialchars(formattaData($t['data_fine'])) ?><?php endif; ?></p>
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
    <section class="home-news" style="margin-top:50px; margin-bottom:80px;">
      <h2>Tornei terminati</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['terminato'])): ?>
          <?php foreach ($tornei['terminato'] as $t): ?>
            <?php $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#'; ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>" onerror="this.src='/img/tornei/pallone.png';">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($t['data_inizio']) || !empty($t['data_fine'])): ?>
                  <p>Dal <?= htmlspecialchars(formattaData($t['data_inizio'])) ?><?php if (!empty($t['data_fine'])): ?> - <?= htmlspecialchars(formattaData($t['data_fine'])) ?><?php endif; ?></p>
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
