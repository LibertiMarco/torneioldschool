<?php
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/mail_helper.php';
require_once __DIR__ . '/includi/seo.php';

$baseUrl = seo_base_url();
$resendSeo = [
    'title' => 'Reinvia conferma email - Tornei Old School',
    'description' => 'Richiedi un nuovo link di conferma per il tuo account Tornei Old School.',
    'url' => $baseUrl . '/resend_verification.php',
    'canonical' => $baseUrl . '/resend_verification.php',
];
$resendBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Reinvia conferma email', 'url' => $baseUrl . '/resend_verification.php'],
]);

$error = "";
$success = "";
$emailField = trim($_POST['email'] ?? '');
$captchaQuestion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromPending = ($_POST['from_pending'] ?? '') === '1';
    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'resend_form')) {
        $error = "Sessione scaduta. Ricarica e riprova.";
    } elseif (honeypot_triggered()) {
        $error = "Richiesta non valida.";
    } elseif (!rate_limit_allow('resend_form', 3, 900)) {
        $wait = rate_limit_retry_after('resend_form', 900);
        $error = "Troppi tentativi ravvicinati. Riprova tra {$wait} secondi.";
    } elseif (!$fromPending && !captcha_is_valid('resend_form', $_POST['captcha_answer'] ?? null)) {
        $error = "Verifica anti-spam non valida.";
    }

    if (!$error) {
        $email = trim($_POST['email'] ?? '');
        $emailField = $email;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Inserisci un indirizzo email valido.";
        } else {
            [$deliverable, $emailError] = tos_email_is_deliverable($email);
            if (!$deliverable) {
                $error = $emailError;
            } else {
                $stmt = $conn->prepare("SELECT id, nome, cognome, email_verificata FROM utenti WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                if (!$user) {
                    $success = "Se esiste un account con questa email, riceverai a breve un nuovo link di conferma.";
                } elseif ((int)$user['email_verificata'] === 1) {
                    $success = "Questa email risulta gia verificata. Puoi accedere con le tue credenziali.";
                } else {
                    try {
                        $token = bin2hex(random_bytes(32));
                    } catch (Exception $e) {
                        $error = "Errore nella generazione del token. Riprova fra qualche minuto.";
                    }

                    if (!$error) {
                        $scadenza = (new DateTime('+1 day'))->format('Y-m-d H:i:s');
                        $update = $conn->prepare("UPDATE utenti SET token_verifica = ?, token_verifica_scadenza = ? WHERE id = ?");
                        $update->bind_param("ssi", $token, $scadenza, $user['id']);

                        if ($update->execute()) {
                            if (inviaEmailVerifica($email, $user['nome'], $token)) {
                                $success = "Email inviata! Controlla la tua casella per completare la verifica.";
                                $_POST = [];
                            } else {
                                $error = "Non e stato possibile inviare l'email. Riprova piu tardi.";
                            }
                        } else {
                            $error = "Errore durante l'aggiornamento del token. Riprova.";
                        }
                    }
                }
            }
        }
    }
}

$captchaQuestion = captcha_generate('resend_form');?>
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
  <?php render_seo_tags($resendSeo); ?>
  <?php render_jsonld($resendBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    body {
      background: #f4f6fb;
    }
    .resend-wrapper {
      min-height: calc(100vh - 200px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px 120px;
    }
    .resend-card {
      max-width: 480px;
      width: 100%;
      background: #fff;
      border-radius: 16px;
      padding: 40px 36px;
      box-shadow: 0 30px 60px rgba(15,23,42,0.1);
      border: 1px solid rgba(15,23,42,0.08);
      text-align: center;
    }
    .resend-card h1 {
      margin-bottom: 12px;
      color: #15293e;
    }
    .resend-card form {
      display: flex;
      flex-direction: column;
      gap: 16px;
      text-align: left;
      margin-top: 20px;
    }
    .resend-card input[type="email"] {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #c9d3e1;
      border-radius: 10px;
      font-size: 1rem;
    }
    .hp-field {
      position: absolute;
      left: -9999px;
      top: auto;
      width: 1px;
      height: 1px;
      overflow: hidden;
    }
    .resend-card button {
      border: none;
      border-radius: 10px;
      background: #15293e;
      color: #fff;
      padding: 12px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, background 0.2s ease;
    }
    .resend-card button:hover {
      background: #0e1d2e;
      transform: translateY(-1px);
    }
    .info-message, .error-message, .success-message {
      margin-top: 16px;
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 0.95rem;
      line-height: 1.5;
    }
    .error-message {
      color: #c01c28;
      background: #fdecea;
      border: 1px solid #facdcd;
    }
    .success-message {
      color: #0f8755;
      background: #e6f6ed;
      border: 1px solid #b8e4c7;
    }
    .resend-card .links {
      margin-top: 24px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .resend-card .links a {
      color: #15293e;
      font-weight: 600;
      text-decoration: underline;
      text-underline-offset: 4px;
    }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="resend-wrapper">
    <div class="resend-card">
      <h1>Reinvia email di conferma</h1>
      <p>Inserisci la tua email. Se l'account non ├¿ ancora stato verificato, ti invieremo un nuovo link.</p>

      <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrf_field('resend_form') ?>
        <div class="hp-field" aria-hidden="true">
          <label for="hp_field">Lascia vuoto</label>
          <input type="text" id="hp_field" name="hp_field" tabindex="-1" autocomplete="off">
        </div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($emailField) ?>">
        <label for="captcha_answer">Verifica: quanto fa <?= htmlspecialchars($captchaQuestion) ?>?</label>
        <input type="number" id="captcha_answer" name="captcha_answer" inputmode="numeric" required>
        <button type="submit">Invia nuovamente il link</button>
      </form>

      <div class="links">
        <a href="/login.php">Torna al login</a>
        <a href="/register.php">Crea un nuovo account</a>
      </div>
    </div>
  </main>

  <div id="footer-container"></div>
  <script src="/includi/app.min.js?v=20251220"></script>
  <script>
    fetch("/includi/footer.html")
      .then(r => r.text())
      .then(html => document.getElementById("footer-container").innerHTML = html);
    fetch("/includi/header.php")
      .then(r => r.text())
      .then(html => {
        document.getElementById("header-container").innerHTML = html;
        if (typeof initHeaderInteractions === 'function') {
          initHeaderInteractions();
        }
      });
  </script>
</body>
</html>






