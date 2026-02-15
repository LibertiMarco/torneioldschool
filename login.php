<?php
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';
$loginDebugEnabled = getenv('LOGIN_DEBUG') === '1';
if ($loginDebugEnabled) {
    // Forza un log locale visibile (fallback se php.ini punta altrove)
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/error.txt');
}
if (!function_exists('login_debug_log')) {
    function login_debug_log(string $message, bool $enabled, array $context = []): void
    {
        if (!$enabled) {
            return;
        }
        $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        error_log('[login-debug] ' . $message . $ctx);
    }
}

login_debug_log('page_load', $loginDebugEnabled, [
    'session_id' => session_id(),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'error_log' => ini_get('error_log'),
]);

$recaptchaSiteKey = getenv('RECAPTCHA_SITE_KEY') ?: '';
$recaptchaSecretKey = getenv('RECAPTCHA_SECRET_KEY') ?: '';

$baseUrl = seo_base_url();
$loginSeo = [
    'title' => 'Login - Tornei Old School',
    'description' => 'Accedi al tuo profilo per seguire tornei, classifiche e commenti.',
    'url' => $baseUrl . '/login.php',
    'canonical' => $baseUrl . '/login.php',
];
$loginBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Login', 'url' => $baseUrl . '/login.php'],
]);

$defaultRedirect = login_with_base_path('/index.php');
if (isset($_GET['redirect'])) {
    $candidateRedirect = login_sanitize_redirect($_GET['redirect']);
    if ($candidateRedirect) {
        login_remember_redirect($candidateRedirect, $defaultRedirect);
        login_debug_log('set_redirect_from_get', $loginDebugEnabled, ['redirect' => $candidateRedirect]);
    }
}

if (empty($_SESSION['login_redirect']) && !empty($_SERVER['HTTP_REFERER'])) {
    $refererRedirect = login_sanitize_redirect($_SERVER['HTTP_REFERER']);
    if ($refererRedirect) {
        login_remember_redirect($refererRedirect, $defaultRedirect);
        login_debug_log('set_redirect_from_referer', $loginDebugEnabled, ['redirect' => $refererRedirect]);
    }
}

$redirectTarget = login_get_redirect($defaultRedirect);
$alreadyLogged = isset($_SESSION['user_id']);
if ($alreadyLogged) {
    unset($_SESSION['login_redirect']);
    header("Location: {$redirectTarget}");
    exit;
}

$noCacheHeaders = [
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
    'Pragma: no-cache',
    'Expires: Mon, 01 Jan 1990 00:00:00 GMT',
];
foreach ($noCacheHeaders as $h) {
    header($h);
}

$error = "";
$invalidCredentialsMessage = "Email o password errati.";
$loginCsrf = csrf_get_token('login_form');

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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $postRedirect = login_sanitize_redirect($_POST['redirect'] ?? null);
    if ($postRedirect) {
        $redirectTarget = login_remember_redirect($postRedirect, $defaultRedirect);
        login_debug_log('set_redirect_from_post', $loginDebugEnabled, ['redirect' => $postRedirect]);
    }

    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'login_form')) {
        $error = "Sessione scaduta. Ricarica la pagina e riprova.";
        login_debug_log('csrf_invalid', $loginDebugEnabled, [
            'session_id' => session_id(),
            'posted_token_len' => strlen($_POST['_csrf'] ?? ''),
            'session_token_len' => strlen($_SESSION['_csrf_tokens']['login_form'] ?? ''),
        ]);
    } elseif (honeypot_triggered()) {
        $error = "Richiesta non valida.";
        login_debug_log('honeypot_triggered', $loginDebugEnabled, ['session_id' => session_id()]);
    } elseif (!rate_limit_allow('login_attempt', 5, 900)) {
        $wait = rate_limit_retry_after('login_attempt', 900);
        $error = "Troppi tentativi ravvicinati. Riprova tra {$wait} secondi.";
        login_debug_log('rate_limited', $loginDebugEnabled, ['wait_seconds' => $wait]);
    } elseif ($recaptchaSecretKey === '' || $recaptchaSiteKey === '') {
        $error = "Servizio non disponibile: reCAPTCHA non configurato.";
        login_debug_log('recaptcha_missing_config', $loginDebugEnabled);
    } elseif (!verify_recaptcha($recaptchaSecretKey, $_POST['g-recaptcha-response'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '')) {
        $error = "Verifica reCAPTCHA non valida. Riprova.";
        login_debug_log('recaptcha_failed', $loginDebugEnabled, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    }

    if (!$error) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $rememberMe = !empty($_POST['remember_me']);

        // query di selezione
        $sql = "SELECT id, email, nome, cognome, ruolo, password, avatar, email_verificata FROM utenti WHERE email = ?";
        $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // verifica password (usa password_hash() in fase di registrazione)
        if (password_verify($password, $row['password'])) {
            if (isset($row['email_verificata']) && (int)$row['email_verificata'] !== 1) {
                // Utente corretto ma non verificato: reindirizza a pagina dedicata
                $pendingEmail = urlencode($email);
                unset($_SESSION['login_redirect']);
                header("Location: /verify_pending.php?email={$pendingEmail}");
                exit;
            } else {
            // imposta le variabili di sessione
            $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
            if ($rememberMe) {
                $baseParams = session_get_cookie_params();
                session_set_cookie_params([
                    'lifetime' => REMEMBER_COOKIE_LIFETIME,
                    'path' => $baseParams['path'] ?? '/',
                    'domain' => $baseParams['domain'] ?? '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => $baseParams['samesite'] ?? 'Lax',
                ]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['nome'] = $row['nome'];
            $_SESSION['cognome'] = $row['cognome'];
            $_SESSION['ruolo'] = $row['ruolo']; // "admin" oppure "user"
            $_SESSION['avatar'] = $row['avatar'] ?? null;
            $_SESSION['remember_me'] = $rememberMe;
            login_debug_log('login_success', $loginDebugEnabled, [
                'user_id' => $row['id'],
                'remember' => $rememberMe,
                'session_id' => session_id(),
            ]);

            $cookieParams = session_get_cookie_params();
            if ($rememberMe) {
                $cookieLifetime = REMEMBER_COOKIE_LIFETIME;
                $rememberSelector = bin2hex(random_bytes(9));
                $rememberValidator = bin2hex(random_bytes(32));
                $rememberHash = hash('sha256', $rememberValidator);
                $rememberExpires = date('Y-m-d H:i:s', time() + $cookieLifetime);

                $rememberSaved = false;
                $rememberStmt = $conn->prepare("UPDATE utenti SET remember_selector = ?, remember_token_hash = ?, remember_expires_at = ? WHERE id = ?");
                if ($rememberStmt) {
                    $rememberStmt->bind_param("sssi", $rememberSelector, $rememberHash, $rememberExpires, $row['id']);
                    $rememberSaved = $rememberStmt->execute();
                    if (!$rememberSaved) {
                        error_log('remember save failed: ' . $rememberStmt->error);
                        login_debug_log('remember_save_failed', $loginDebugEnabled, ['error' => $rememberStmt->error]);
                    }
                    $rememberStmt->close();
                } else {
                    error_log('remember save prepare failed: ' . $conn->error);
                    login_debug_log('remember_prepare_failed', $loginDebugEnabled, ['error' => $conn->error]);
                }

                if ($rememberSaved) {
                    $rememberCookieValue = $rememberSelector . ':' . $rememberValidator;
                    setcookie(session_name(), session_id(), [
                        'expires' => time() + $cookieLifetime,
                        'path' => $cookieParams['path'] ?? '/',
                        'domain' => $cookieParams['domain'] ?? '',
                        'secure' => (bool)($cookieParams['secure'] ?? false),
                        'httponly' => (bool)($cookieParams['httponly'] ?? true),
                        'samesite' => $cookieParams['samesite'] ?? 'Lax',
                    ]);
                    setcookie(REMEMBER_COOKIE_NAME, $rememberCookieValue, [
                        'expires' => time() + $cookieLifetime,
                        'path' => $cookieParams['path'] ?? '/',
                        'domain' => $cookieParams['domain'] ?? '',
                        'secure' => (bool)($cookieParams['secure'] ?? false),
                        'httponly' => true,
                        'samesite' => $cookieParams['samesite'] ?? 'Lax',
                    ]);
                } else {
                    $_SESSION['remember_me'] = false;
                    login_debug_log('remember_not_saved', $loginDebugEnabled);
                }
            } else {
                $_SESSION['remember_me'] = false;
                $rememberStmt = $conn->prepare("UPDATE utenti SET remember_selector = NULL, remember_token_hash = NULL, remember_expires_at = NULL WHERE id = ?");
                if ($rememberStmt) {
                    $rememberStmt->bind_param("i", $row['id']);
                    $rememberStmt->execute();
                    $rememberStmt->close();
                }
                setcookie(REMEMBER_COOKIE_NAME, '', [
                    'expires' => time() - 3600,
                    'path' => $cookieParams['path'] ?? '/',
                    'domain' => $cookieParams['domain'] ?? '',
                    'secure' => (bool)($cookieParams['secure'] ?? false),
                    'httponly' => true,
                    'samesite' => $cookieParams['samesite'] ?? 'Lax',
                ]);
            }

            // salva la sessione e poi reindirizza
            session_write_close();
            $target = login_get_redirect($defaultRedirect);
            unset($_SESSION['login_redirect']);
            header("Location: {$target}");
            exit;
            }
        } else {
            $error = $invalidCredentialsMessage;
            login_debug_log('invalid_credentials', $loginDebugEnabled, ['email' => $email, 'session_id' => session_id()]);
        }
    } else {
        $error = $invalidCredentialsMessage;
        login_debug_log('user_not_found', $loginDebugEnabled, ['email' => $email, 'session_id' => session_id()]);
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
  <?php render_seo_tags($loginSeo); ?>
  <?php render_jsonld($loginBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: calc(100vh - 160px);
      background-color: #f4f4f4;
      padding: 30px 20px 30px;
    }
    .login-box {
      background-color: #ffffff;
      padding: 40px 50px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 400px;
      width: 100%;
    }
    .login-box h2 {
      color: #15293e;
      margin-bottom: 25px;
      font-size: 1.8rem;
      font-weight: 700;
    }
    .login-form {
      display: flex;
      flex-direction: column;
      gap: 15px;
      text-align: left;
    }
    .hp-field {
      position: absolute;
      left: -9999px;
      top: auto;
      width: 1px;
      height: 1px;
      overflow: hidden;
    }
    .login-form label {
      font-weight: 600;
      color: #15293e;
    }
    .login-form input[type="email"],
    .login-form input[type="password"],
    .login-form input[type="text"] {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.2s;
    }
    .login-form input[type="email"]:focus,
    .login-form input[type="password"]:focus,
    .login-form input[type="text"]:focus {
      border-color: #15293e;
      outline: none;
    }
    .remember-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }
    .remember-me {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      color: #15293e;
      font-size: 0.95rem;
    }
    .remember-me input {
      width: auto;
      padding: 0;
      margin: 0;
      accent-color: #15293e;
    }
    .login-btn {
      background-color: #15293e;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 12px;
      font-size: 1.05rem;
      font-weight: 700;
      cursor: pointer;
      transition: 0.3s ease;
      margin-top: 10px;
    }
    .login-btn:hover {
      background-color: #0e1d2e;
      transform: scale(1.03);
    }
    .error-message {
      color: red;
      margin-top: 10px;
      font-weight: 500;
    }
    .login-footer {
      margin-top: 20px;
      font-size: 0.9rem;
      color: #555;
    }
    .login-footer a {
      color: #15293e;
      text-decoration: none;
      font-weight: 600;
    }
    .login-footer a:hover {
      text-decoration: underline;
    }
    .recaptcha-box {
      margin: 10px 0 6px;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      transform-origin: left top;
    }
    .recaptcha-box .g-recaptcha {
      transform: scale(1);
      transform-origin: left top;
    }
    @media (max-width: 640px) {
      .recaptcha-box .g-recaptcha {
        transform: scale(0.9);
      }
    }
    .password-field {
      position: relative;
      display: flex;
      align-items: center;
    }
    .password-field input {
      padding-right: 40px;
    }
    .toggle-password {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      color: #555;
      font-size: 0.95rem;
    }
    .toggle-password:focus-visible {
      outline: 2px solid #15293e;
      outline-offset: 2px;
    }
    .toggle-password svg {
      width: 22px;
      height: 22px;
    }
    .toggle-password .icon-eye-off {
      display: none;
    }
    .toggle-password.is-visible .icon-eye {
      display: none;
    }
    .toggle-password.is-visible .icon-eye-off {
      display: block;
    }
    .inline-resend {
      margin-top: 8px;
    }
    .resend-link {
      background: none;
      border: none;
      color: #15293e;
      text-decoration: underline;
      font-weight: bold;
      cursor: pointer;
      padding: 0;
      font-size: 0.95rem;
    }
    .resend-link:hover {
      color: #0e1d2e;
    }
  </style>
</head>
<body>
  <div id="header-container"></div>
  <div class="login-container">
    <div class="login-box">
      <h2>Accedi</h2>
      <form class="login-form" method="POST" action="">
        <?= csrf_field('login_form') ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget) ?>">
        <div class="hp-field" aria-hidden="true">
          <label for="hp_field">Lascia vuoto</label>
          <input type="text" id="hp_field" name="hp_field" tabindex="-1" autocomplete="off">
        </div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <div class="password-field">
          <input type="password" id="password" name="password" required>
          <button type="button" class="toggle-password" aria-label="Mostra password" id="togglePassword">
            <svg class="icon-eye" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7zm0 12c-2.7 0-5-2.3-5-5s2.3-5 5-5 5 2.3 5 5-2.3 5-5 5zm0-8a3 3 0 100 6 3 3 0 000-6z"/>
            </svg>
            <svg class="icon-eye-off" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M2.3 3.7l2 2A12.7 12.7 0 002 12c1 2.5 5 7 10 7 1.7 0 3.3-.5 4.8-1.4l2.2 2.2 1.4-1.4-17-17-1.3 1.3zm7.1 7.1l1.9 1.9a1 1 0 01-1.9-1.9zm3.5 3.5l1.9 1.9a3 3 0 01-3.8-3.8l1.9 1.9zm8.8-.3c.5-.8.8-1.5.8-2.1-1-2.5-5-7-10-7-1.2 0-2.5.3-3.6.8l1.6 1.6a6 6 0 017.4 7.4l1.5 1.5a13.5 13.5 0 002.3-2.2z"/>
            </svg>
          </button>
        </div>

        <div class="remember-row">
          <label class="remember-me">
            <input type="checkbox" name="remember_me" value="1">
            <span>Resta connesso</span>
          </label>
        </div>
        <div class="recaptcha-box">
          <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($recaptchaSiteKey) ?>"></div>
        </div>

        <button type="submit" class="login-btn">Entra</button>

        <?php if ($error): ?>
          <div class="error-message">
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
      </form>

      <div class="login-footer">
        <p>Non hai un account? <a href="register.php">Registrati</a></p>
        <p><a href="forgot_password.php">Password dimenticata?</a></p>
      </div>
    </div>
  </div>
  <div id="footer-container"></div>

  <script src="/includi/app.min.js?v=20251220"></script>
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
          if (header) {
            header.classList.toggle("scrolled", window.scrollY > 50);
          }
        });
      });

    // Toggle password visibility
    (function () {
      const passwordInput = document.getElementById("password");
      const toggleButton = document.getElementById("togglePassword");
      if (!passwordInput || !toggleButton) return;

      toggleButton.addEventListener("click", () => {
        const isHidden = passwordInput.type === "password";
        passwordInput.type = isHidden ? "text" : "password";
        toggleButton.classList.toggle("is-visible", isHidden);
        toggleButton.setAttribute("aria-label", isHidden ? "Nascondi password" : "Mostra password");
      });
    })();

    // Lazy-load reCAPTCHA al primo tocco/focus sul form (considerato necessario per sicurezza anti-bot)
    (function () {
      const form = document.querySelector(".login-form");
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
