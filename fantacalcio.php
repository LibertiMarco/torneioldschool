<?php
require_once __DIR__ . '/includi/security.php';
if (!isset($_SESSION['user_id'])) {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '/fantacalcio.php';
    login_remember_redirect($currentPath);
    header("Location: /login.php");
    exit;
}

require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';
require_once __DIR__ . '/includi/user_features.php';

$userId = (int)$_SESSION['user_id'];
$userRole = (string)($_SESSION['ruolo'] ?? 'user');
$canAccess = user_can_access_feature($conn, $userId, $userRole, 'fantacalcio');

if (!$canAccess) {
    http_response_code(403);
}

$baseUrl = seo_base_url();
$seo = [
    'title' => 'Fantacalcio',
    'description' => 'Area riservata Fantacalcio.',
    'url' => $baseUrl . '/fantacalcio.php',
    'canonical' => $baseUrl . '/fantacalcio.php',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <?php render_seo_tags($seo); ?>
  <link rel="stylesheet" href="/style.min.css">
  <style>
    body { background: #f4f6fb; }
    .feature-page { max-width: 900px; margin: 0 auto; padding: 110px 20px 60px; }
    .feature-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
      padding: 28px;
      box-shadow: 0 18px 36px rgba(15, 31, 51, 0.08);
    }
    .feature-eyebrow {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      background: #e8edf5;
      color: #15293e;
      font-size: 0.82rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 12px;
    }
    .feature-card h1 {
      margin: 0 0 10px;
      color: #15293e;
      font-size: 2rem;
    }
    .feature-card p {
      margin: 0 0 12px;
      color: #4c5b71;
      line-height: 1.6;
      font-size: 1rem;
    }
    .feature-status {
      margin-top: 18px;
      padding: 14px 16px;
      border-radius: 12px;
      background: #f8fafc;
      border: 1px solid #d7e1ee;
      color: #15293e;
      font-weight: 600;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/includi/header.php'; ?>

<main class="feature-page">
  <section class="feature-card">
    <span class="feature-eyebrow">Funzione nascosta</span>
    <h1>Fantacalcio</h1>
    <?php if ($canAccess): ?>
      <p>Questa area e visibile solo agli account con il flag Fantacalcio attivo e agli amministratori.</p>
      <p>La struttura di accesso e menu e pronta. Qui puoi poi inserire la logica reale del Fantacalcio.</p>
      <div class="feature-status">Sezione attiva ma ancora in preparazione.</div>
    <?php else: ?>
      <p>Non hai i permessi per accedere a questa sezione.</p>
    <?php endif; ?>
  </section>
</main>

<div id="footer-container"></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const footer = document.getElementById('footer-container');
  if (!footer) return;
  fetch('/includi/footer.html')
    .then(response => response.text())
    .then(html => footer.innerHTML = html)
    .catch(err => console.error('Errore caricamento footer:', err));
});
</script>
</body>
</html>
