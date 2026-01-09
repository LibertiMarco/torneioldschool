<?php
session_start();
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';

$baseUrl = seo_base_url();
$verifySeo = [
    'title' => 'Conferma email - Tornei Old School',
    'description' => 'Verifica il tuo indirizzo email per completare la registrazione su Tornei Old School.',
    'url' => $baseUrl . '/verify_email.php',
    'canonical' => $baseUrl . '/verify_email.php',
];
$verifyBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Conferma email', 'url' => $baseUrl . '/verify_email.php'],
]);

$status = 'error';
$message = 'Link di verifica non valido.';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($email && $token) {
    $stmt = $conn->prepare("SELECT id, token_verifica, token_verifica_scadenza, email_verificata FROM utenti WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        $message = "Utente non trovato.";
    } elseif ((int)$user['email_verificata'] === 1) {
        $status = 'info';
        $message = "Email già confermata. Puoi accedere con le tue credenziali.";
    } elseif (empty($user['token_verifica']) || !hash_equals($user['token_verifica'], $token)) {
        $message = "Token di verifica non valido. Richiedi una nuova email di conferma.";
    } else {
        $scadenza = $user['token_verifica_scadenza'] ? new DateTime($user['token_verifica_scadenza']) : null;
        $now = new DateTime();

        if ($scadenza && $scadenza < $now) {
            $message = "Il link di verifica è scaduto. Richiedi una nuova email di conferma.";
        } else {
            $update = $conn->prepare("UPDATE utenti SET email_verificata = 1, token_verifica = NULL, token_verifica_scadenza = NULL WHERE id = ?");
            $update->bind_param("i", $user['id']);

            if ($update->execute()) {
                $status = 'success';
                $message = "Perfetto! Il tuo indirizzo email è stato confermato. Ora puoi accedere.";
            } else {
                $message = "Si è verificato un errore durante la conferma dell'email. Riprova più tardi.";
            }
        }
    }
} else {
    $message = "Parametri mancanti.";
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
  <?php render_seo_tags($verifySeo); ?>
  <?php render_jsonld($verifyBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    body {
      background: #f4f6fb;
    }
    .verify-wrapper {
      min-height: calc(100vh - 200px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px 120px;
    }
    .verify-card {
      max-width: 520px;
      width: 100%;
      background: #fff;
      border-radius: 18px;
      padding: 48px 40px;
      text-align: center;
      box-shadow: 0 30px 60px rgba(15,23,42,0.1);
      border: 1px solid rgba(15,23,42,0.08);
    }
    .verify-card h1 {
      font-size: 2rem;
      margin-bottom: 12px;
      color: #0f172a;
    }
    .verify-card p {
      color: #4b5565;
      line-height: 1.5;
      margin-bottom: 24px;
    }
    .verify-card.success {
      border-color: #b8e4c7;
    }
    .verify-card.success h1 {
      color: #0f8755;
    }
    .verify-card.error h1 {
      color: #c01c28;
    }
    .verify-card.info h1 {
      color: #0f5499;
    }
    .verify-card .actions {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .verify-card a.btn {
      display: inline-block;
      padding: 12px 18px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      background: #15293e;
      color: #fff;
      transition: transform 0.2s ease, background 0.2s ease;
    }
    .verify-card a.btn:hover {
      background: #0e1d2e;
      transform: translateY(-1px);
    }
    .verify-card a.link-secondary {
      color: #15293e;
      font-weight: 600;
      text-decoration: underline;
      text-underline-offset: 4px;
    }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="verify-wrapper">
    <div class="verify-card <?= htmlspecialchars($status) ?>">
      <h1><?= $status === 'success' ? 'Email verificata!' : ($status === 'info' ? 'Informazione' : 'Ops...') ?></h1>
      <p><?= htmlspecialchars($message) ?></p>
      <div class="actions">
        <?php if ($status === 'success' || $status === 'info'): ?>
          <a class="btn" href="/login.php">Vai al login</a>
        <?php else: ?>
          <a class="btn" href="/register.php">Torna alla registrazione</a>
        <?php endif; ?>
        <a class="link-secondary" href="/">Torna alla home</a>
      </div>
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
  </script>
</body>
</html>
