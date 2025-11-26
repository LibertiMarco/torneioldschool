<?php
require_once __DIR__ . '/includi/seo.php';
$baseUrl = seo_base_url();
$aboutSeo = [
  'title' => 'Chi siamo - Tornei Old School',
  'description' => 'La storia, la squadra e i valori che guidano i tornei Old School.',
  'url' => $baseUrl . '/chisiamo.php',
  'canonical' => $baseUrl . '/chisiamo.php',
];
$aboutBreadcrumbs = seo_breadcrumb_schema([
  ['name' => 'Home', 'url' => $baseUrl . '/'],
  ['name' => 'Chi siamo', 'url' => $baseUrl . '/chisiamo.php'],
]);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($aboutSeo); ?>
  <?php render_jsonld($aboutBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@500;600;700&family=Poppins:wght@400;600&display=swap');

    body {
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(180deg, #f6f7fb 0%, #e9edf2 100%);
      color: #15293e;
      overflow-x: hidden;
    }

    /* Spazio per header */
    .page-wrapper {
      padding-top: 30px;
    }

    .about-container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 15px 20px 100px; /* meno spazio sopra */
      text-align: center;
      position: relative;
      z-index: 1;
    }

    /* Banner compatto */
    .banner {
      position: relative;
      width: 100%;
      height: 160px; /* più basso */
      background: #f4f6fb;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #15293e;
      font-family: 'Kanit', sans-serif;
      font-weight: 800;
      font-size: 3.2rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 5px; /* meno spazio sotto */
    }

    .about-subtitle {
      font-size: 1.2rem;
      font-weight: 600;
      color: #3b4a61;
      margin-bottom: 40px;
    }

    .about-text {
      font-size: 1.1rem;
      color: #2e2e2e;
      line-height: 1.8;
      margin-bottom: 25px;
      animation: fadeIn 0.6s ease;
    }

    .about-highlight {
      background: linear-gradient(90deg, #15293e 0%, #1e3c60 100%);
      color: #fff;
      display: inline-block;
      padding: 14px 28px;
      border-radius: 50px;
      font-weight: 600;
      margin-top: 35px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    .about-team {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 30px;
      margin-top: 60px;
    }

    .member {
      background-color: #fff;
      border-radius: 16px;
      padding: 30px;
      width: 280px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }

    .member:hover {
      transform: translateY(-8px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }

    .member h3 {
      font-family: 'Kanit', sans-serif;
      font-size: 1.4rem;
      color: #15293e;
      margin-bottom: 10px;
    }

    .member p {
      color: #555;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    .cta-button {
      display: inline-block;
      margin-top: 70px;
      background-color: #15293e;
      color: #fff;
      padding: 15px 40px;
      border-radius: 50px;
      text-decoration: none;
      font-family: 'Kanit', sans-serif;
      font-weight: 600;
      letter-spacing: 0.5px;
      font-size: 1.05rem;
      transition: 0.3s ease;
      box-shadow: 0 6px 20px rgba(21,41,62,0.35);
    }

    .cta-button:hover {
      background-color: #0e1d2e;
      transform: scale(1.05);
      box-shadow: 0 10px 25px rgba(21,41,62,0.45);
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 700px) {
      .banner {
        font-size: 2.2rem;
        height: 130px;
      }
      .about-container {
        padding: 10px 15px 70px;
      }
      .member {
        width: 90%;
      }
    }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <div class="page-wrapper">
    <div class="banner">Chi Siamo</div>

    <section class="about-container">
      <h2 class="about-subtitle">Passione, amicizia e sport — lo spirito Old School</h2>

      <p class="about-text">
        Siamo <strong>Frank</strong> ed <strong>Emanuele</strong>, due amici con la stessa visione:  
        trasformare ogni partita in un momento di aggregazione, risate e vera competizione sana.
      </p>

      <p class="about-text">
        Organizziamo <strong>tornei amatoriali di calcio a 5 e calcio a 8</strong> — e, qualche volta, anche di altri sport —  
        nella zona di <strong>Napoli Nord</strong>.  
        I nostri eventi non hanno premi in denaro, ma offrono qualcosa di molto più importante:  
        <strong>unione, amicizia e divertimento puro</strong>.
      </p>

      <p class="about-text">
        Ogni torneo è pensato per essere un’esperienza completa:  
        arbitri qualificati, sistema <strong>VAR</strong>, <strong>highlights</strong>, completini personalizzati e anche <strong>contenuti TikTok</strong> per far rivivere i momenti più belli di ogni partita.
      </p>

      <p class="about-highlight">Tornei Old School — il calcio come una volta, con lo spirito di oggi.</p>

      <div class="about-team">
        <div class="member">
          <h3>Frank</h3>
          <p>Spirito organizzativo del gruppo, gestisce logistica e contatti con squadre e arbitri.  
          Sempre pronto a dare energia e motivazione al campo.</p>
        </div>
        <div class="member">
          <h3>Emanuele</h3>
          <p>Creativo e appassionato di comunicazione, cura i social, i video e l’esperienza digitale dei nostri tornei.</p>
        </div>
      </div>

      <a href="contatti.php" class="cta-button">Contatti</a>
    </section>
  </div>

  <div id="footer-container"></div>

  <script src="/includi/app.min.js?v=20251201"></script>
  <script>
    // FOOTER
    fetch("/includi/footer.html")
      .then(r => r.text())
      .then(html => document.getElementById("footer-container").innerHTML = html);

    // HEADER
    fetch("/includi/header.php")
      .then(r => r.text())
      .then(html => {
        document.getElementById("header-container").innerHTML = html;
        initHeaderInteractions();
        const header = document.querySelector(".site-header");
        window.addEventListener("scroll", () => {
          header?.classList.toggle("scrolled", window.scrollY > 50);
        });
      });
  </script>
</body>
</html>
