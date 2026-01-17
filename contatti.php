<?php
session_start();
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/env_loader.php';
require_once __DIR__ . '/includi/seo.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'contact_form')) {
        $error = "Sessione scaduta. Ricarica la pagina e riprova.";
    } elseif (honeypot_triggered()) {
        $error = "Richiesta non valida.";
    } elseif (!rate_limit_allow('contact_form', 3, 300)) {
        $wait = rate_limit_retry_after('contact_form', 300);
        $error = "Troppi tentativi ravvicinati. Riprova tra {$wait} secondi.";
    } else {
        $nome = trim($_POST["nome"] ?? '');
        $messaggio = trim($_POST["messaggio"] ?? '');

        if ($nome === '' || $messaggio === '') {
            $error = "Compila tutti i campi.";
        } else {
            $mailto = 'mailto:info@torneioldschool.it'
                . '?subject=' . rawurlencode('Messaggio dal sito Tornei Old School')
                . '&body=' . rawurlencode($messaggio . "\n\n" . $nome);
            header("Location: {$mailto}");
            exit;
        }
    }
}
$baseUrl = seo_base_url();
$contattiSeo = [
  'title' => 'Contatti - Tornei Old School',
  'description' => 'Scrivici per collaborazioni, iscrizioni o domande sui tornei Old School.',
  'url' => $baseUrl . '/contatti.php',
  'canonical' => $baseUrl . '/contatti.php',
];
$contattiBreadcrumbs = seo_breadcrumb_schema([
  ['name' => 'Home', 'url' => $baseUrl . '/'],
  ['name' => 'Contatti', 'url' => $baseUrl . '/contatti.php'],
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($contattiSeo); ?>
  <?php render_jsonld($contattiBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Poppins', 'Segoe UI', Tahoma, sans-serif;
      background: linear-gradient(180deg, #f6f7fb 0%, #e9edf2 100%);
      color: #15293e;
    }

    .page-wrapper {
      padding-top: 30px;
    }

    .banner {
      width: 100%;
      height: 160px;
      background: #f4f6fb;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #15293e;
      font-family: 'Kanit', sans-serif;
      font-weight: 800;
      font-size: 3rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 20px;
    }

    .contact-container {
      max-width: 950px;
      margin: 0 auto;
      padding: 30px 20px 100px;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 40px;
      text-align: center;
    }

    .contact-form {
      background-color: #fff;
      border-radius: 16px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
      padding: 30px;
      flex: 1 1 400px;
      min-width: 340px;
    }

    .contact-form h1 {
      font-family: 'Kanit', sans-serif;
      font-size: 2rem;
      color: #15293e;
      margin-bottom: 25px;
    }

    .contact-form form {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .contact-form label {
      text-align: left;
      font-weight: 600;
      color: #15293e;
      font-size: 0.95rem;
    }

    .contact-form input,
    .contact-form textarea {
      padding: 12px 15px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      font-family: 'Poppins', sans-serif;
      resize: none;
      transition: border-color 0.2s;
    }

    .contact-form input:focus,
    .contact-form textarea:focus {
      border-color: #15293e;
      outline: none;
    }

    .contact-form button {
      background-color: #15293e;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 12px;
      font-size: 1.05rem;
      font-weight: 700;
      cursor: pointer;
      transition: 0.3s ease;
    }

    .contact-form button:hover {
      background-color: #0e1d2e;
      transform: scale(1.03);
    }

    .success-message {
      color: green;
      margin-top: 15px;
      font-weight: 600;
    }

    .error-message {
      color: red;
      margin-top: 15px;
      font-weight: 600;
    }
    .contact-ads {
      margin: 16px 0;
    }

    .footer-socials {
      margin-top: 30px;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .footer-socials a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .footer-socials img {
      width: 40px;
      height: 40px;
      transition: transform 0.3s ease;
    }

    .footer-socials img:hover {
      transform: scale(1.15);
    }

    .hp-field {
      position: absolute;
      left: -9999px;
      top: auto;
      width: 1px;
      height: 1px;
      overflow: hidden;
    }
    @media (max-width: 640px) {
    }

    @media (max-width: 700px) {
      .banner {
        font-size: 2.2rem;
        height: 130px;
      }

      .contact-container {
        padding: 20px 15px 80px;
      }

      .footer-socials a img {
        width: 35px;
        height: 35px;
      }
    }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <div class="page-wrapper">
    <div class="contact-ads" style="text-align:center;">
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

    <section class="contact-container">
      <div class="contact-form">
        <h1>Contattaci</h1>

        <form method="POST" action="" id="contactForm">
          <?= csrf_field('contact_form') ?> 
          <div class="hp-field" aria-hidden="true">
            <label for="hp_field">Lascia vuoto</label>
            <input type="text" id="hp_field" name="hp_field" tabindex="-1" autocomplete="off">
          </div>
          <input type="text" name="nome" placeholder="Il tuo nome" required>
          <textarea name="messaggio" rows="5" placeholder="Il tuo messaggio..." required></textarea>
          <button type="submit">Invia Messaggio</button>
        </form>

        <?php if ($success): ?>
          <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php elseif ($error): ?>
          <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="footer-socials">
          <a href="https://www.instagram.com/tornei_old_school/" target="_blank" aria-label="Instagram">
            <img src="/img/icone/instagram.png" alt="Instagram">
          </a>
          <a href="https://www.facebook.com/TotorABullet" target="_blank" aria-label="Facebook">
            <img src="/img/icone/facebook.png" alt="Facebook">
          </a>
          <a href="https://www.youtube.com/@TORNEIOLDSCHOOL-e8f" target="_blank" aria-label="YouTube">
            <img src="/img/icone/youtube.png" alt="YouTube">
          </a>
          <a href="https://www.tiktok.com/@tornei_oldschool" target="_blank" aria-label="TikTok">
            <img src="/img/icone/tiktok.png" alt="TikTok">
          </a>
          <a href="https://wa.me/393383213272" target="_blank" aria-label="Whatsapp">
            <img src="/img/icone/whatsapp.png" alt="Whatsapp">
          </a>
        </div>
      </div>
    </section>
  </div>

  <div class="contact-ads" style="text-align:center; margin: 10px auto 20px;">
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
      .then(html => {
        document.getElementById("footer-container").innerHTML = html;
      });

    // HEADER
    fetch("/includi/header.php")
      .then(r => r.text())
      .then(html => {
        document.getElementById("header-container").innerHTML = html;
        const header = document.querySelector(".site-header");
        const hasAdvancedHeader = typeof initHeaderInteractions === "function";
        if (hasAdvancedHeader) {
          initHeaderInteractions();
        } else {
          bindBasicHeaderToggle(document);
        }
        window.addEventListener("scroll", () => {
          if (header) {
            header.classList.toggle("scrolled", window.scrollY > 50);
          }
        });
      });

    // Mailto client-side: apre il client email con il messaggio e il nome in coda
    (function () {
      const form = document.getElementById("contactForm");
      if (!form) return;
      form.addEventListener("submit", (event) => {
        event.preventDefault();
        const nome = (form.nome?.value || "").trim();
        const messaggio = (form.messaggio?.value || "").trim();
        if (!nome || !messaggio) {
          return;
        }
        const mailto = "mailto:info@torneioldschool.it"
          + "?subject=" + encodeURIComponent("Messaggio dal sito Tornei Old School")
          + "&body=" + encodeURIComponent(messaggio + "\\n\\n" + nome);
        window.location.href = mailto;
      });
    })();
  </script>
</body>
</html>


