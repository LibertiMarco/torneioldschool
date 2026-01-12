<?php
require_once __DIR__ . '/includi/seo.php';
require_once __DIR__ . '/includi/db.php';

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

$staffCategories = [
  'arbitro' => [
    'label' => 'Arbitri',
    'description' => 'Direzione di gara affidata al nostro team di ufficiali.',
    'fallback_role' => 'Arbitro',
  ],
  'videomaker' => [
    'label' => 'Videomaker',
    'description' => 'Riprese, highlights e contenuti social dei nostri match.',
    'fallback_role' => 'Videomaker',
  ],
  'organizzazione' => [
    'label' => 'Organizzazione',
    'description' => 'Coordinamento e logistica degli eventi.',
    'fallback_role' => 'Organizzatore',
  ],
  'staff' => [
    'label' => 'Staff',
    'description' => 'Supporto in campo e fuori.',
    'fallback_role' => 'Staff',
  ],
];

$defaultStaff = [
  'arbitro' => [
    ['nome' => 'Arbitro 1', 'foto' => '/img/giocatori/unknown.jpg'],
    ['nome' => 'Arbitro 2', 'foto' => '/img/giocatori/unknown.jpg'],
    ['nome' => 'Arbitro 3', 'foto' => '/img/giocatori/unknown.jpg'],
  ],
  'videomaker' => [
    ['nome' => 'Videomaker 1', 'foto' => '/img/giocatori/unknown.jpg'],
    ['nome' => 'Videomaker 2', 'foto' => '/img/giocatori/unknown.jpg'],
  ],
];

function load_staff_from_db(mysqli $conn, array $defaults): array {
  $data = $defaults;
  $exists = $conn->query("SHOW TABLES LIKE 'staff'");
  if (!$exists || $exists->num_rows === 0) {
    return $data;
  }
  $res = $conn->query("SELECT nome, ruolo, categoria, foto, ordinamento FROM staff ORDER BY categoria, COALESCE(ordinamento, 9999), nome");
  if ($res instanceof mysqli_result) {
    $data = [];
    while ($row = $res->fetch_assoc()) {
      $cat = strtolower(trim($row['categoria'] ?? 'staff'));
      if (!isset($data[$cat])) {
        $data[$cat] = [];
      }
      $data[$cat][] = [
        'nome' => $row['nome'] ?: 'Staff',
        'ruolo' => $row['ruolo'] ?? '',
        'foto' => $row['foto'] ?: '/img/giocatori/unknown.jpg',
      ];
    }
    $res->free();
    foreach ($defaults as $cat => $list) {
      if (empty($data[$cat])) {
        $data[$cat] = $list;
      }
    }
  }
  return $data;
}

$staffData = $defaultStaff;
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
  $staffData = load_staff_from_db($conn, $defaultStaff);
}
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
      height: 160px; /* piu basso */
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

    /* Switch organizzatori/staff */
    .team-switch {
      display: inline-flex;
      gap: 12px;
      margin-top: 45px;
      background: #e6e9f1;
      padding: 8px;
      border-radius: 999px;
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
    }
    .team-tab {
      border: none;
      background: transparent;
      color: #15293e;
      font-weight: 700;
      padding: 10px 18px;
      border-radius: 999px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .team-tab.active {
      background: linear-gradient(135deg, #15293e, #1f3d5a);
      color: #fff;
      box-shadow: 0 8px 18px rgba(21, 41, 62, 0.25);
    }

    .team-panel {
      display: none;
      margin-top: 30px;
      animation: fadeIn 0.35s ease;
    }
    .team-panel.active {
      display: block;
    }

    /* Staff section */
    .staff-section {
      margin-top: 26px;
      text-align: left;
    }
    .staff-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      flex-wrap: wrap;
    }
    .staff-pill {
      background: #15293e;
      color: #fff;
      padding: 7px 14px;
      border-radius: 999px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      font-size: 0.9rem;
    }
    .staff-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 18px;
    }
    .staff-card {
      background: #fff;
      border-radius: 14px;
      padding: 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.08);
      border: 1px solid #e4e8f0;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }
    .staff-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 14px 30px rgba(0,0,0,0.18);
      border-color: rgba(21,41,62,0.18);
    }
    .staff-card img {
      width: 58px;
      height: 58px;
      border-radius: 12px;
      object-fit: cover;
      background: #f3f5f8;
      border: 1px solid #e5e9f2;
      flex-shrink: 0;
    }
    .staff-name {
      font-weight: 800;
      color: #15293e;
      margin: 0;
      font-size: 1rem;
    }
    .staff-role {
      margin: 2px 0 0;
      color: #4c5b71;
      font-size: 0.95rem;
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
      .team-switch {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
      }
    }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <div class="page-wrapper">
    <div class="banner">Chi Siamo</div>

    <section class="about-container">
      <h2 class="about-subtitle">Passione, amicizia e sport - lo spirito Old School</h2>

      <p class="about-text">
        Siamo <strong>Frank</strong> ed <strong>Emanuele</strong>, due amici con la stessa visione:  
        trasformare ogni partita in un momento di aggregazione, risate e vera competizione sana.
      </p>

      <p class="about-text">
        Organizziamo <strong>tornei amatoriali di calcio a 5 e calcio a 8</strong> - e, qualche volta, anche di altri sport -  
        nella zona di <strong>Napoli Nord</strong>.  
        I nostri eventi non hanno premi in denaro, ma offrono qualcosa di molto più importante:  
        <strong>unione, amicizia e divertimento puro</strong>.
      </p>

      <p class="about-text">
        Ogni torneo è pensato per essere un'esperienza completa:  
        arbitri qualificati, sistema <strong>VAR</strong>, <strong>highlights</strong>, completini personalizzati e anche <strong>contenuti TikTok</strong> per far rivivere i momenti più belli di ogni partita.
      </p>

      <p class="about-highlight">Tornei Old School - il calcio come una volta, con lo spirito di oggi.</p>

      <div class="team-switch">
        <button class="team-tab active" data-target="organizzatori">Organizzatori</button>
        <button class="team-tab" data-target="staff">Staff</button>
      </div>

      <div class="team-panel active" id="panel-organizzatori">
        <div class="about-team">
          <div class="member">
            <h3>Frank</h3>
            <p>Spirito organizzativo del gruppo, gestisce logistica e contatti con squadre e arbitri.  
            Sempre pronto a dare energia e motivazione al campo.</p>
          </div>
          <div class="member">
            <h3>Emanuele</h3>
            <p>Creativo e appassionato di comunicazione, cura i social, i video e l'esperienza digitale dei nostri tornei.</p>
          </div>
        </div>
      </div>

      <div class="team-panel" id="panel-staff">
        <?php
        $categoryOrder = ['arbitro', 'videomaker', 'organizzazione', 'staff'];
        $rendered = [];
        foreach ($categoryOrder as $catKey):
          $members = $staffData[$catKey] ?? [];
          if (empty($members)) continue;
          $rendered[$catKey] = true;
          $label = $staffCategories[$catKey]['label'] ?? ucfirst($catKey);
          $desc = $staffCategories[$catKey]['description'] ?? 'Staff';
          $fallbackRole = $staffCategories[$catKey]['fallback_role'] ?? ucfirst($catKey);
        ?>
          <div class="staff-section">
            <div class="staff-header">
              <span class="staff-pill"><?= htmlspecialchars($label) ?></span>
              <p class="staff-role"><?= htmlspecialchars($desc) ?></p>
            </div>
            <div class="staff-grid">
              <?php foreach ($members as $member): ?>
                <?php
                  $role = trim($member['ruolo'] ?? '');
                  $roleToShow = $role !== '' ? $role : $fallbackRole;
                ?>
                <div class="staff-card">
                  <img src="<?= htmlspecialchars($member['foto']) ?>" alt="Foto <?= htmlspecialchars($member['nome']) ?>" onerror="this.src='/img/giocatori/unknown.jpg';">
                  <div>
                    <p class="staff-name"><?= htmlspecialchars($member['nome']) ?></p>
                    <p class="staff-role"><?= htmlspecialchars($roleToShow) ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <?php foreach ($staffData as $catKey => $members):
          if (isset($rendered[$catKey]) || empty($members)) continue;
          $label = $staffCategories[$catKey]['label'] ?? ucwords(str_replace('_', ' ', $catKey));
          $desc = $staffCategories[$catKey]['description'] ?? 'Staff';
          $fallbackRole = $staffCategories[$catKey]['fallback_role'] ?? ucfirst($catKey);
        ?>
          <div class="staff-section">
            <div class="staff-header">
              <span class="staff-pill"><?= htmlspecialchars($label) ?></span>
              <p class="staff-role"><?= htmlspecialchars($desc) ?></p>
            </div>
            <div class="staff-grid">
              <?php foreach ($members as $member): ?>
                <?php
                  $role = trim($member['ruolo'] ?? '');
                  $roleToShow = $role !== '' ? $role : $fallbackRole;
                ?>
                <div class="staff-card">
                  <img src="<?= htmlspecialchars($member['foto']) ?>" alt="Foto <?= htmlspecialchars($member['nome']) ?>" onerror="this.src='/img/giocatori/unknown.jpg';">
                  <div>
                    <p class="staff-name"><?= htmlspecialchars($member['nome']) ?></p>
                    <p class="staff-role"><?= htmlspecialchars($roleToShow) ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </section>
  </div>

  <div id="footer-container"></div>

  <script src="/includi/app.min.js?v=20251220"></script>
  <script>
    function bindBasicHeaderToggle(root) {
      const header = root.querySelector(".site-header");
      if (!header) return;
      const mobileBtn = header.querySelector("#mobileMenuBtn");
      const mainNav = header.querySelector("#mainNav");
      const userBtn = header.querySelector("#userBtn");
      const userMenu = header.querySelector("#userMenu");
      if (mobileBtn && mainNav) {
        mobileBtn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          const open = mainNav.classList.toggle("open");
          if (open && userMenu) userMenu.classList.remove("open");
        });
      }
      if (userBtn && userMenu) {
        userBtn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          const open = userMenu.classList.toggle("open");
          if (open && mainNav) mainNav.classList.remove("open");
        });
      }
      document.addEventListener("click", (e) => {
        if (!header.contains(e.target)) {
          mainNav?.classList.remove("open");
          userMenu?.classList.remove("open");
        }
      });
    }

    // FOOTER
    fetch("/includi/footer.html")
      .then(r => r.text())
      .then(html => document.getElementById("footer-container").innerHTML = html);

    // HEADER
    fetch("/includi/header.php")
      .then(r => r.text())
      .then(html => {
        document.getElementById("header-container").innerHTML = html;
        if (typeof initHeaderInteractions === "function") {
          initHeaderInteractions();
        }
        bindBasicHeaderToggle(document);
        const header = document.querySelector(".site-header");
        window.addEventListener("scroll", () => {
          if (header) {
            header.classList.toggle("scrolled", window.scrollY > 50);
          }
        });
      });

    // Switch tra organizzatori e staff
    document.addEventListener("click", (e) => {
      const btn = e.target.closest(".team-tab");
      if (!btn) return;
      const target = btn.dataset.target || "";
      document.querySelectorAll(".team-tab").forEach(el => el.classList.toggle("active", el === btn));
      document.querySelectorAll(".team-panel").forEach(panel => {
        panel.classList.toggle("active", panel.id === `panel-${target}`);
      });
    });
  </script>
</body>
</html>









