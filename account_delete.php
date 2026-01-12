<?php
require_once __DIR__ . '/includi/security.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';

$userId = (int)$_SESSION['user_id'];

$baseUrl = seo_base_url();
$seo = [
    'title' => 'Elimina account - Tornei Old School',
    'description' => 'Richiesta di cancellazione account e dati associati.',
    'url' => $baseUrl . '/account_delete.php',
    'canonical' => $baseUrl . '/account_delete.php',
];
$breadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Account', 'url' => $baseUrl . '/account.php'],
    ['name' => 'Elimina account', 'url' => $baseUrl . '/account_delete.php'],
]);

$error = '';
$success = '';
$csrf = csrf_get_token('delete_account');

// Recupera hash password per verifica
function get_user_password(mysqli $conn, int $userId): ?string {
    $stmt = $conn->prepare("SELECT password FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row['password'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'delete_account')) {
        $error = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } elseif (honeypot_triggered()) {
        $error = 'Richiesta non valida.';
    } elseif (!rate_limit_allow('delete_account', 2, 3600)) {
        $wait = rate_limit_retry_after('delete_account', 3600);
        $error = "Hai giÃ  inviato una richiesta recentemente. Riprova tra {$wait} secondi.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = !empty($_POST['confirm_delete']);

        if (!$confirm) {
            $error = 'Devi confermare la cancellazione.';
        } elseif ($password === '') {
            $error = 'Inserisci la password per confermare.';
        } else {
            $hash = get_user_password($conn, $userId);
            if (!$hash || !password_verify($password, $hash)) {
                $error = 'Password non corretta.';
            } else {
                // Cancella eventuali log di consenso (per pulizia)
                $delLog = $conn->prepare("DELETE FROM consensi_log WHERE user_id = ?");
                if ($delLog) {
                    $delLog->bind_param("i", $userId);
                    $delLog->execute();
                    $delLog->close();
                }

                // Cancella utente (cascade gestisce altre tabelle)
                $delUser = $conn->prepare("DELETE FROM utenti WHERE id = ?");
                if ($delUser && $delUser->bind_param("i", $userId) && $delUser->execute()) {
                    $delUser->close();
                    session_unset();
                    session_destroy();
                    $success = 'Account eliminato correttamente. Verrai reindirizzato alla home.';
                    header("Refresh: 2; URL=/");
                } else {
                    $error = 'Errore durante la cancellazione. Riprova piÃ¹ tardi.';
                }
            }
        }
    }
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
  <?php render_seo_tags($seo); ?>
  <?php render_jsonld($breadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    body { background: #f4f6fb; }
    .delete-wrapper {
      min-height: calc(100vh - 180px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px 120px;
    }
    .delete-card {
      max-width: 520px;
      width: 100%;
      background: #fff;
      border-radius: 16px;
      padding: 30px;
      box-shadow: 0 24px 50px rgba(15,23,42,0.12);
      border: 1px solid rgba(15,23,42,0.08);
    }
    .delete-card h1 {
      margin-top: 0;
      margin-bottom: 10px;
      color: #0f172a;
      font-size: 1.8rem;
    }
    .delete-card p { color: #475569; line-height: 1.6; }
    .danger {
      background: #fff1f2;
      border: 1px solid #fecdd3;
      color: #b91c1c;
      padding: 10px 12px;
      border-radius: 10px;
      font-weight: 700;
      margin: 12px 0;
    }
    .form-field { display: flex; flex-direction: column; gap: 6px; margin-top: 12px; }
    .form-field input { padding: 11px 12px; border: 1px solid #d0d7e1; border-radius: 10px; }
    .submit-btn {
      margin-top: 16px;
      width: 100%;
      background: linear-gradient(120deg, #b91c1c, #8b1414);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 12px;
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      box-shadow: 0 14px 28px rgba(185, 28, 28, 0.25);
    }
    .submit-btn:hover { transform: translateY(-1px); }
    .message { margin-top: 12px; font-weight: 700; }
    .message.error { color: #b91c1c; }
    .message.success { color: #0f8755; }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="delete-wrapper">
    <div class="delete-card">
      <h1>Elimina account</h1>
      <p>Questa operazione rimuove il tuo profilo e i consensi associati. Alcuni dati statistici potrebbero restare anonimizzati (es. eventi senza utente).</p>
      <div class="danger">Operazione irreversibile. Conferma con la tua password.</div>
      <form method="POST" action="">
        <?= csrf_field('delete_account') ?>
        <div class="hp-field" aria-hidden="true">
          <input type="text" name="hp_field" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;">
          <input type="checkbox" name="confirm_delete" value="1" required>
          <span>Ho compreso e voglio eliminare il mio account.</span>
        </label>
        <button type="submit" class="submit-btn">Elimina definitivamente</button>
      </form>
      <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <p style="margin-top:14px;"><a href="/account.php">Annulla e torna all'account</a></p>
    </div>
  </main>

  <div id="footer-container"></div>
  <script src="/includi/app.min.js?v=20251220"></script>
  <script src="/includi/consent-sync.js?v=20251220" defer></script>
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


