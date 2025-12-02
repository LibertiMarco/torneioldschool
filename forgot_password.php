<?php
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/mail_helper.php';
require_once __DIR__ . '/includi/seo.php';

$recaptchaSiteKey = getenv('RECAPTCHA_SITE_KEY') ?: '';
$recaptchaSecretKey = getenv('RECAPTCHA_SECRET_KEY') ?: '';

$baseUrl = seo_base_url();
$seo = [
    'title' => 'Recupera password - Tornei Old School',
    'description' => 'Richiedi il link per reimpostare la tua password.',
    'url' => $baseUrl . '/forgot_password.php',
    'canonical' => $baseUrl . '/forgot_password.php',
];
$breadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Accedi', 'url' => $baseUrl . '/login.php'],
    ['name' => 'Recupera password', 'url' => $baseUrl . '/forgot_password.php'],
]);

$error = '';
$success = '';
$csrf = csrf_get_token('forgot_password');

function verify_recaptcha(string $secret, string $token, string $ip = '', string $expectedAction = '', float $minScore = 0.0): bool
{
    if (trim($secret) === '' || trim($token) === '') {
        return false;
    }
    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ip,
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 5,
        ]
    ]);
    $result = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    if ($result === false) {
        return false;
    }
    $data = json_decode($result, true);
    if (!is_array($data) || empty($data['success'])) {
        return false;
    }
    if ($expectedAction !== '' && isset($data['action']) && $data['action'] !== $expectedAction) {
        return false;
    }
    if ($minScore > 0 && isset($data['score']) && $data['score'] < $minScore) {
        return false;
    }
    return true;
}

function create_reset_token(mysqli $conn, int $userId, string $token, DateTime $expires): bool
{
    // annulla token precedenti
    $conn->query("DELETE FROM password_reset_tokens WHERE user_id = " . (int)$userId);
    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $expStr = $expires->format('Y-m-d H:i:s');
    $stmt->bind_param("iss", $userId, $token, $expStr);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'forgot_password')) {
        $error = "Sessione scaduta. Ricarica la pagina e riprova.";
    } elseif (honeypot_triggered()) {
        $error = "Richiesta non valida.";
    } elseif (!rate_limit_allow('forgot_password', 3, 60)) {
        $wait = rate_limit_retry_after('forgot_password', 60);
        $error = "Troppi tentativi ravvicinati. Riprova tra {$wait} secondi.";
    } elseif ($recaptchaSecretKey === '' || $recaptchaSiteKey === '') {
        $error = "Servizio non disponibile: reCAPTCHA non configurato.";
    } elseif (!verify_recaptcha($recaptchaSecretKey, $_POST['g-recaptcha-response'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '')) {
        $error = "Verifica reCAPTCHA non valida. Riprova.";
    }

    if (!$error) {
        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Non rivelare se l'account esiste: messaggio generico
            $success = "Se l'email è registrata, riceverai un link per reimpostare la password.";
        } else {
            $stmt = $conn->prepare("SELECT id, nome FROM utenti WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            $success = "Se l'email è registrata, riceverai un link per reimpostare la password.";
            if ($user) {
                try {
                    $token = bin2hex(random_bytes(32));
                } catch (Exception $e) {
                    $error = "Errore tecnico. Riprova più tardi.";
                }
                if (!$error) {
                    $expires = new DateTime('+1 hour');
                    if (create_reset_token($conn, (int)$user['id'], $token, $expires)) {
                        $mailOk = inviaEmailResetPassword($email, $user['nome'] ?? '', $token);
                        if (!$mailOk) {
                            error_log('forgot_password: invio email fallito per ' . $email);
                            $error = "Invio email non riuscito. Riprova tra poco o contattaci.";
                            $success = "";
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($seo); ?>
  <?php render_jsonld($breadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    body { background: #f4f4f4; }
    .fp-container {
      min-height: calc(100vh - 180px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px 120px;
    }
    .fp-card {
      width: 100%;
      max-width: 460px;
      background: #fff;
      border-radius: 16px;
      padding: 30px 28px;
      box-shadow: 0 26px 48px rgba(15,23,42,0.12);
      border: 1px solid rgba(15,23,42,0.08);
      text-align: left;
    }
    .fp-card h1 { margin: 0 0 12px; color: #0f172a; font-size: 1.8rem; }
    .fp-card p { color: #475569; line-height: 1.6; margin: 0 0 10px; }
    .form-field { display: flex; flex-direction: column; gap: 6px; margin-top: 12px; }
    .form-field label { font-weight: 700; color: #0f172a; }
    .form-field input { padding: 11px 12px; border-radius: 10px; border: 1px solid #d0d7e1; font-size: 1rem; }
    .submit-btn {
      margin-top: 14px;
      width: 100%;
      background: linear-gradient(120deg, #15293e, #1f3d60);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 12px;
      font-size: 1.05rem;
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      box-shadow: 0 14px 28px rgba(15,23,42,0.18);
    }
    .submit-btn:hover { transform: translateY(-1px); }
    .hp-field { position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden; }
    .recaptcha-box {
      margin: 12px 0 6px;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      transform-origin: left top;
    }
    .recaptcha-box .g-recaptcha { transform: scale(1); transform-origin: left top; }
    @media (max-width: 640px) {
      .recaptcha-box .g-recaptcha { transform: scale(0.9); }
    }
    .message { margin-top: 10px; font-weight: 700; }
    .message.error { color: #b91c1c; }
    .message.success { color: #0f8755; }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="fp-container">
    <div class="fp-card">
      <h1>Recupera password</h1>
      <p>Inserisci l'email del tuo account. Ti invieremo un link per reimpostare la password.</p>
      <form method="POST" action="">
        <?= csrf_field('forgot_password') ?>
        <div class="hp-field" aria-hidden="true">
          <input type="text" name="hp_field" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
        </div>
        <div class="recaptcha-box">
          <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($recaptchaSiteKey) ?>"></div>
        </div>
        <button type="submit" class="submit-btn">Invia link</button>
      </form>
      <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <p style="margin-top:14px;"><a href="/login.php">Torna al login</a></p>
    </div>
  </main>

  <div id="footer-container"></div>

  <script src="/includi/app.min.js?v=20251204"></script>
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

    // Lazy-load reCAPTCHA al primo tocco/focus sul form
    (function () {
      const form = document.querySelector(".fp-card form");
      if (!form) return;
      const loadRecaptcha = () => {
        if (window.__tosRecaptchaLoaded) return;
        window.__tosRecaptchaLoaded = true;
        const s = document.createElement("script");
        s.src = "https://www.google.com/recaptcha/api.js";
        s.async = true;
        s.defer = true;
        document.head.appendChild(s);
      };
      ["pointerdown", "focusin", "keydown"].forEach(evt => {
        form.addEventListener(evt, loadRecaptcha, { once: true });
      });
    })();
  </script>
</body>
</html>
