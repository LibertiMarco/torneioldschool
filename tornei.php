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
  'title' => 'Tornei in corso - Tornei Old School',
  'description' => 'Calendari, risultati e documenti dei tornei Old School divisi per stato.',
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
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/png" href="img/logo_old_school.png">
  <style>
    .content {
      margin-top: 30px;
      padding-top: 10px;
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
    .popup-accesso h2 {
      margin-bottom: 10px;
      font-size: 1.2em;
    }
    .popup-accesso button {
      background: #15293e;
      color: #fff;
      border: none;
      border-radius: 5px;
      padding: 8px 15px;
      margin-top: 15px;
      cursor: pointer;
    }
    .popup-accesso button:hover {
      background: #0e1d2e;
    }
    /* --- Ripristino card tornei versione precedente --- */

/* Impedisce che le card diventino troppo larghe su desktop */
.news-grid {
    display: grid;
    gap: 20px;
    justify-content: center; /* non riempie tutta la riga */
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    max-width: 1100px; /* NON tutta la riga */
    margin: 0 auto;
}

/* Card sempre della stessa altezza */
.news-grid article {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 280px; /* dimensione massima card come era prima */
    margin: 0 auto;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

/* Immagine con altezza fissa */
.news-grid article img {
    width: 100%;
    height: 160px;
    object-fit: cover;
}

/* Titolo con altezza minima */
.news-grid article h3 {
    min-height: 45px;
    padding: 8px 12px 5px;
    font-size: 18px;
}

/* Testo */
.news-grid article p {
    padding: 0 12px 12px;
    margin-top: auto;
}
/* --- Ripristino card tornei versione precedente con allineamento a sinistra --- */

/* Contenitore griglia */
.news-grid {
    display: grid;
    gap: 20px;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    max-width: 1100px; /* limite per non farle diventare enormi */
    margin: 0; /* niente centratura */
    justify-content: start !important; /* allinea tutto a sinistra */
}

/* Card */
.news-grid article {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 280px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

/* Immagine card */
.news-grid article img {
    width: 100%;
    height: 160px;
    object-fit: cover;
}

/* Titolo card */
.news-grid article h3 {
    min-height: 45px;
    padding: 8px 12px 5px;
    font-size: 18px;
}

/* Date */
.news-grid article p {
    padding: 0 12px 12px;
    margin-top: auto;
}
/* === MOBILE: 1 card per riga, larghezza quasi totale === */
@media (max-width: 768px) {

    .news-grid {
        grid-template-columns: 1fr !important;   /* UNA PER RIGA */
        gap: 20px;                                /* Spazio tra le card */
        justify-items: center;                    /* Centra la card */
    }

    .news-grid article {
        width: 95%;                               /* Quasi tutta la larghezza */
        max-width: 500px;                         /* Evita che diventi troppo larga */
        border-radius: 12px;
    }

    .news-grid article img {
        width: 100%;
        height: 200px;                            /* Più grande come prima */
        object-fit: cover;
        border-radius: 12px 12px 0 0;
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

    <!-- HERO -->
    <section class="home-hero">
      <div class="hero-overlay">
        <h1>I Nostri Tornei</h1>
        <p>Scopri i tornei in corso, programmati e quelli già conclusi</p>
      </div>
    </section>

    <!-- TORNEI IN CORSO -->
    <section class="home-news" style="margin-top:50px;">
      <h2>⚽️ Tornei in corso</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['in corso'])): ?>
          <?php foreach ($tornei['in corso'] as $t): ?>
            <?php $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#'; ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($t['data_inizio']) || !empty($t['data_fine'])): ?>
                  <p>📅 
                    <?= htmlspecialchars(formattaData($t['data_inizio'])) ?>
                    <?php if (!empty($t['data_fine'])): ?> - <?= htmlspecialchars(formattaData($t['data_fine'])) ?><?php endif; ?>
                  </p>
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
      <h2>⏳ Tornei programmati</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['programmato'])): ?>
          <?php foreach ($tornei['programmato'] as $t): ?>
            <?php $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#'; ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($t['data_inizio']) || !empty($t['data_fine'])): ?>
                  <p>📅 
                    <?= htmlspecialchars(formattaData($t['data_inizio'])) ?>
                    <?php if (!empty($t['data_fine'])): ?> - <?= htmlspecialchars(formattaData($t['data_fine'])) ?><?php endif; ?>
                  </p>
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
      <h2>🏁 Tornei terminati</h2>
      <div class="news-grid">
        <?php if (!empty($tornei['terminato'])): ?>
          <?php foreach ($tornei['terminato'] as $t): ?>
            <?php $link = !empty($t['filetorneo']) ? 'tornei/' . htmlspecialchars($t['filetorneo']) : '#'; ?>
            <article>
              <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($t['img'] ?: 'img/default.jpg') ?>" alt="<?= htmlspecialchars($t['nome']) ?>">
                <h3>
                  <?= htmlspecialchars($t['nome']) ?>
                  <?php if (!empty($t['categoria'])): ?>
                    <span class="categoria">(<?= htmlspecialchars($t['categoria']) ?>)</span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($t['data_inizio']) || !empty($t['data_fine'])): ?>
                  <p>📅 
                    <?= htmlspecialchars(formattaData($t['data_inizio'])) ?>
                    <?php if (!empty($t['data_fine'])): ?> - <?= htmlspecialchars(formattaData($t['data_fine'])) ?><?php endif; ?>
                  </p>
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
  <script src="/includi/header-interactions.js"></script>
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
