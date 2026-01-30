<?php
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/seo.php';

$baseUrl = seo_base_url();
$pendingSeo = [
    'title' => 'Conferma la tua email - Tornei Old School',
    'description' => 'Completa la verifica email per accedere al tuo account.',
    'url' => $baseUrl . '/verify_pending.php',
    'canonical' => $baseUrl . '/verify_pending.php',
];
$pendingBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Login', 'url' => $baseUrl . '/login.php'],
    ['name' => 'Conferma email', 'url' => $baseUrl . '/verify_pending.php'],
]);

$emailParam = trim($_GET['email'] ?? '');
$emailPrefill = filter_var($emailParam, FILTER_VALIDATE_EMAIL) ? $emailParam : '';
$resendCsrf = csrf_get_token('resend_verification');
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($pendingSeo); ?>
  <?php render_jsonld($pendingBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    body { background: #f4f6fb; }
    .pending-wrapper {
      min-height: calc(100vh - 200px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px 120px;
    }
    .pending-card {
      max-width: 520px;
      width: 100%;
      background: #fff;
      border-radius: 16px;
      padding: 36px 32px;
      box-shadow: 0 30px 60px rgba(15,23,42,0.1);
      border: 1px solid rgba(15,23,42,0.08);
      text-align: center;
    }
    .pending-card h1 {
      margin-bottom: 12px;
      color: #15293e;
    }
    .pending-card p {
      color: #334155;
      margin: 8px 0;
    }
    .pending-card form {
      margin-top: 20px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      align-items: stretch;
    }
    .pending-card input[type="email"] {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #c9d3e1;
      border-radius: 10px;
      font-size: 1rem;
    }
    .pending-card button {
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
    .pending-card button:hover { background: #0e1d2e; transform: translateY(-1px); }
    .pending-links { margin-top: 16px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .pending-links a { color: #15293e; font-weight: 600; text-decoration: underline; text-underline-offset: 4px; }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="pending-wrapper">
    <div class="pending-card">
      <h1>Conferma il tuo indirizzo email</h1>
      <p>Per completare l’accesso, apri l’email di conferma che ti abbiamo inviato.</p>
      <p>Non la trovi? Inviati subito un nuovo link:</p>

      <form method="POST" action="/resend_verification.php">
        <?= csrf_field('resend_form') ?>
        <input type="email" name="email" required placeholder="Inserisci la tua email" value="<?= htmlspecialchars($emailPrefill) ?>">
        <button type="submit">Reinvia email di conferma</button>
      </form>

      <div class="pending-links">
        <a href="/login.php">Torna al login</a>
        <a href="/register.php">Registrati</a>
      </div>
    </div>
  </main>

  <div id="footer-container"></div>

  <script src="/includi/app.min.js?v=20251220"></script>
  <script>
    // Footer
    fetch("/includi/footer.html").then(r => r.text()).then(html => document.getElementById("footer-container").innerHTML = html);
    // Header
    fetch("/includi/header.php").then(r => r.text()).then(html => {
      document.getElementById("header-container").innerHTML = html;
      initHeaderInteractions();
    });
  </script>
</body>
</html>
