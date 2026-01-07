<?php
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';

$baseUrl = seo_base_url();
$seo = [
    'title' => 'Reimposta password - Tornei Old School',
    'description' => 'Imposta una nuova password per il tuo account.',
    'url' => $baseUrl . '/reset_password.php',
    'canonical' => $baseUrl . '/reset_password.php',
];
$breadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Accedi', 'url' => $baseUrl . '/login.php'],
    ['name' => 'Reimposta password', 'url' => $baseUrl . '/reset_password.php'],
]);

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');
$error = '';
$success = '';
$csrf = csrf_get_token('reset_password');

function load_token(mysqli $conn, string $token, string $email) {
    $sql = "SELECT prt.id, prt.user_id, prt.token, prt.expires_at, prt.used_at, u.email
            FROM password_reset_tokens prt
            INNER JOIN utenti u ON u.id = prt.user_id
            WHERE prt.token = ? AND u.email = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("ss", $token, $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

function mark_token_used(mysqli $conn, int $id): void {
    $stmt = $conn->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

if ($token === '' || $email === '') {
    $error = "Link non valido.";
} else {
    $row = load_token($conn, $token, $email);
    if (!$row) {
        $error = "Link non valido.";
    } else {
        $now = new DateTime();
        $exp = new DateTime($row['expires_at']);
        if (!empty($row['used_at'])) {
            $error = "Link giÃ  utilizzato. Richiedi un nuovo reset.";
        } elseif ($exp < $now) {
            $error = "Link scaduto. Richiedi un nuovo reset.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'reset_password')) {
        $error = "Sessione scaduta. Ricarica la pagina e riprova.";
    } elseif (honeypot_triggered()) {
        $error = "Richiesta non valida.";
    } elseif (!rate_limit_allow('reset_password', 5, 60)) {
        $wait = rate_limit_retry_after('reset_password', 60);
        $error = "Troppi tentativi ravvicinati. Riprova tra {$wait} secondi.";
    } else {
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        $regex = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\'":\\\\|,.<>\/?]).{8,}$/';
        if ($password === '' || $confirm === '') {
            $error = "Inserisci e conferma la nuova password.";
        } elseif ($password !== $confirm) {
            $error = "Le password non coincidono.";
        } elseif (!preg_match($regex, $password)) {
            $error = "La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo.";
        } else {
            $row = load_token($conn, $token, $email);
            if (!$row) {
                $error = "Link non valido.";
            } elseif (!empty($row['used_at'])) {
                $error = "Link giÃ  utilizzato. Richiedi un nuovo reset.";
            } else {
                $now = new DateTime();
                $exp = new DateTime($row['expires_at']);
                if ($exp < $now) {
                    $error = "Link scaduto. Richiedi un nuovo reset.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE utenti SET password = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $hash, $row['user_id']);
                        if ($stmt->execute()) {
                            mark_token_used($conn, (int)$row['id']);
                            $success = "Password aggiornata correttamente. Puoi accedere con le nuove credenziali.";
                        } else {
                            $error = "Errore durante l'aggiornamento. Riprova.";
                        }
                        $stmt->close();
                    } else {
                        $error = "Errore interno. Riprova.";
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
    body { background: #f4f4f4; }
    .rp-container {
      min-height: calc(100vh - 180px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px 120px;
    }
    .rp-card {
      width: 100%;
      max-width: 460px;
      background: #fff;
      border-radius: 16px;
      padding: 30px 28px;
      box-shadow: 0 26px 48px rgba(15,23,42,0.12);
      border: 1px solid rgba(15,23,42,0.08);
      text-align: left;
    }
    .rp-card h1 { margin: 0 0 12px; color: #0f172a; font-size: 1.8rem; }
    .rp-card p { color: #475569; line-height: 1.6; margin: 0 0 10px; }
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
    .message { margin-top: 10px; font-weight: 700; }
    .message.error { color: #b91c1c; }
    .message.success { color: #0f8755; }
    .hint { color: #475569; font-size: 0.9rem; }
    .hp-field { position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden; }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="rp-container">
    <div class="rp-card">
      <h1>Imposta una nuova password</h1>
      <p>La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo.</p>
      <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" action="">
        <?= csrf_field('reset_password') ?>
        <div class="hp-field" aria-hidden="true">
          <input type="text" name="hp_field" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-field">
          <label for="password">Nuova password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="form-field">
          <label for="confirm_password">Conferma password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <p class="hint">Dopo il salvataggio, usa le nuove credenziali per accedere.</p>
        <button type="submit" class="submit-btn">Aggiorna password</button>
      </form>
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
  </script>
</body>
</html>
