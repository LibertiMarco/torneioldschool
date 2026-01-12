<?php
session_start();
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/mail_helper.php';
require_once __DIR__ . '/includi/consent_helpers.php';
require_once __DIR__ . '/includi/seo.php';

$baseUrl = seo_base_url();
$registerSeo = [
    'title' => 'Registrati - Tornei Old School',
    'description' => 'Crea un account per partecipare ai tornei, salvare statistiche e commentare le partite.',
    'url' => $baseUrl . '/register.php',
    'canonical' => $baseUrl . '/register.php',
];
$registerBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Registrati', 'url' => $baseUrl . '/register.php'],
]);

$alreadyLogged = isset($_SESSION['user_id']);
if ($alreadyLogged) {
    header("Location: /index.php");
    exit;
}

$error = "";
$successMessage = "";
$avatarPath = null;
$registerCsrf = csrf_get_token('register_form');
$captchaQuestion = '';

function generaNomeAvatar($nome, $cognome, $estensione, $uploadDir) {
    $nomeSan = preg_replace('/[^A-Za-z0-9]/', '', ucwords(strtolower($nome)));
    $cognomeSan = preg_replace('/[^A-Za-z0-9]/', '', ucwords(strtolower($cognome)));
    $base = $nomeSan . $cognomeSan;
    if ($base === '') {
        $base = 'avatar';
    }

    $filename = $base . '.' . $estensione;
    $counter = 2;
    while (file_exists($uploadDir . '/' . $filename)) {
        $filename = $base . $counter . '.' . $estensione;
        $counter++;
    }
    return $filename;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'register_form')) {
        $error = "Sessione scaduta. Ricarica la pagina e riprova.";
    } elseif (honeypot_triggered()) {
        $error = "Richiesta non valida.";
    } elseif (!rate_limit_allow('register_form', 3, 900)) {
        $wait = rate_limit_retry_after('register_form', 900);
        $error = "Troppi tentativi ravvicinati. Riprova tra {$wait} secondi.";
    } elseif (!captcha_is_valid('register_form', $_POST['captcha_answer'] ?? null)) {
        $error = "Verifica anti-spam non valida.";
    }

    if (!$error) {
        $nome = trim($_POST['nome']);
        $cognome = trim($_POST['cognome']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $accettaPrivacy = !empty($_POST['accetta_privacy']);
        $accettaTermini = !empty($_POST['accetta_termini']);
        $consensoMarketing = !empty($_POST['consenso_marketing']);
        $consensoNewsletter = !empty($_POST['consenso_newsletter']);

        // Validazione base
        if (empty($nome) || empty($cognome) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "Compila tutti i campi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Inserisci un'email valida.";
        } else {
            [$deliverable, $emailError] = tos_email_is_deliverable($email);
            if (!$deliverable) {
                $error = $emailError;
            } elseif ($password !== $confirm_password) {
                $error = "Le password non coincidono.";
            } elseif (!$accettaPrivacy || !$accettaTermini) {
                $error = "Devi accettare l'informativa privacy e i termini di servizio.";
            }
        }

        if (!$error) {
            // Controllo forza password lato server
            $pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\'":\\|,.<>\/?]).{8,}$/';
            if (!preg_match($pattern, $password)) {
                $error = "La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.";
            }
        }

        if (!$error) {
            // Verifica se l'email esiste gia
            $check = $conn->prepare("SELECT id FROM utenti WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();

            if ($result && $result->num_rows > 0) {
                $error = "Esiste gia un account con questa email.";
            }
        }

        // Gestione avatar (opzionale)
        if (!$error && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $maxSize = 2 * 1024 * 1024; // 2MB
                if ($_FILES['avatar']['size'] > $maxSize) {
                    $error = "La foto deve essere inferiore a 2MB.";
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = $finfo ? finfo_file($finfo, $_FILES['avatar']['tmp_name']) : false;
                    if ($finfo instanceof finfo) {
                        unset($finfo);
                    }
                    if (!$mime) {
                        $error = "Impossibile determinare il tipo di immagine caricato.";
                    }
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/gif'  => 'gif',
                        'image/webp' => 'webp'
                    ];

                    if (!$error && !isset($allowed[$mime])) {
                        $error = "Formato immagine non valido. Usa JPG, PNG, GIF o WEBP.";
                    } else {
                        $uploadDir = __DIR__ . '/img/utenti';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0775, true);
                        }
                        $estensione = $allowed[$mime];
                        $filename = generaNomeAvatar($nome, $cognome, $estensione, $uploadDir);
                        $destination = $uploadDir . '/' . $filename;

                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                            $avatarPath = 'img/utenti/' . $filename;
                        } else {
                            $error = "Impossibile salvare la foto. Riprova.";
                        }
                    }
                }
            } else {
                $error = "Errore nel caricamento dell'immagine.";
            }
        }

        if (!$error) {
            try {
                $tokenVerifica = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $error = "Errore tecnico nella generazione del token. Riprova piu tardi.";
            }
        }

        if (!$error) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $ruolo = 'user';
            $tokenScadenza = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

            $sql = "INSERT INTO utenti (nome, cognome, email, password, ruolo, avatar, token_verifica, token_verifica_scadenza)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $nome, $cognome, $email, $hashed_password, $ruolo, $avatarPath, $tokenVerifica, $tokenScadenza);

            if ($stmt->execute()) {
                $newUserId = $stmt->insert_id ?: $conn->insert_id;
                consent_save($conn, (int)$newUserId, $email, [
                    'marketing' => $consensoMarketing,
                    'newsletter' => $consensoNewsletter,
                    'terms' => 1,
                    'tracking' => 0,
                ], 'register');
                if (inviaEmailVerifica($email, $nome, $tokenVerifica)) {
                    $successMessage = "Registrazione completata! Ti abbiamo inviato una email di conferma a {$email}.";
                } else {
                    $successMessage = "Registrazione riuscita, ma non e stato possibile inviare l'email di conferma. Contattaci per ricevere assistenza.";
                }
                $_POST = [];
            } else {
                $error = "Errore durante la registrazione. Riprova.";
            }
        }
    }
}

$captchaQuestion = captcha_generate('register_form');?>
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
  <?php render_seo_tags($registerSeo); ?>
  <?php render_jsonld($registerBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    .register-page {
      background-color: #f4f4f4;
      padding: 24px 20px;
    }
    .register-container {
      width: 100%;
      max-width: 520px;
      margin: 0 auto;
      padding: 0;
      min-height: auto;
      background: transparent;
      display: block;
    }
    .register-box {
      background-color: #ffffff;
      padding: 48px 56px;
      border-radius: 18px;
      box-shadow: 0 35px 70px rgba(15,23,42,0.12);
      text-align: center;
      max-width: 500px;
      width: 100%;
      border: 1px solid rgba(21,41,62,0.08);
    }
    .register-box h2 {
      color: #15293e;
      margin-bottom: 25px;
      font-size: 1.8rem;
      font-weight: 700;
    }
    .register-form {
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
    .register-form label {
      font-weight: 600;
      color: #15293e;
    }
    .register-form input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.2s;
    }
    .register-form input:focus {
      border-color: #15293e;
      outline: none;
    }
    .register-btn {
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
    .register-btn:hover {
      background-color: #0e1d2e;
      transform: scale(1.03);
    }
    .error-message {
      color: red;
      margin-top: 10px;
      font-weight: 500;
    }
    .success-message {
      margin-top: 20px;
      padding: 14px 16px;
      border-radius: 10px;
      background: #e6f6ed;
      color: #0b5d2c;
      font-weight: 600;
      border: 1px solid #b8e4c7;
      text-align: left;
    }
    .register-footer {
      margin-top: 20px;
      font-size: 0.9rem;
      color: #555;
    }
    .register-footer a {
      color: #15293e;
      text-decoration: none;
      font-weight: 600;
    }
    .register-footer a:hover {
      text-decoration: underline;
    }
    .file-upload {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
      padding: 12px 16px;
      border: 1px dashed #bfc7d4;
      border-radius: 10px;
      background: #f7f9fc;
    }
    .file-upload input[type="file"] {
      display: none;
    }
    .file-upload .file-btn {
      background: #15293e;
      color: #fff;
      padding: 10px 18px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: transform 0.2s ease, background 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .file-upload .file-btn:hover {
      background: #0e1d2e;
      transform: translateY(-1px);
    }
    .file-upload .file-name {
      font-size: 0.9rem;
      color: #5f6b7b;
    }
    #passwordMessage, #confirmMessage {
      font-size: 0.9rem;
      display: block;
      margin-top: 5px;
    }
    #passwordCheck, #confirmCheck {
      font-weight: bold;
      font-size: 18px;
      margin-right: 5px;
    }
    .password-field {
      position: relative;
      display: flex;
      align-items: center;
    }
    .password-field input {
      padding-right: 42px;
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
    .consent-box {
      background: linear-gradient(135deg, #f9fbff, #f4f6fb);
      border: 1px solid #e1e7f0;
      border-radius: 14px;
      padding: 16px 18px;
      margin-top: 6px;
      box-shadow: 0 18px 40px rgba(21,41,62,0.07);
    }
    .consent-head {
      display: flex;
      gap: 12px;
      align-items: center;
      margin-bottom: 10px;
      flex-wrap: wrap;
    }
    .consent-badge {
      background: #15293e;
      color: #fff;
      font-weight: 700;
      font-size: 0.9rem;
      padding: 6px 10px;
      border-radius: 10px;
      letter-spacing: 0.01em;
    }
    .consent-subtitle {
      color: #4b5563;
      font-size: 0.95rem;
      margin: 0;
    }
    .consent-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin: 0;
      padding: 0;
    }
    .consent-item {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 12px;
      align-items: flex-start;
      padding: 12px;
      border-radius: 12px;
      background: #fff;
      border: 1px solid #e6ebf3;
      box-shadow: 0 6px 18px rgba(21,41,62,0.05);
    }
    .consent-item input[type="checkbox"] {
      appearance: none;
      width: 20px;
      height: 20px;
      margin-top: 4px;
      border-radius: 6px;
      border: 1.5px solid #9aa5b5;
      background: #fff;
      display: grid;
      place-items: center;
      transition: all 0.15s ease;
      cursor: pointer;
    }
    .consent-item input[type="checkbox"]:checked {
      background: #15293e;
      border-color: #15293e;
      box-shadow: 0 8px 18px rgba(21,41,62,0.18);
    }
    .consent-item input[type="checkbox"]::after {
      content: "?";
      color: transparent;
      font-weight: 800;
      font-size: 12px;
    }
    .consent-item input[type="checkbox"]:checked::after {
      color: #fff;
    }
    .consent-copy {
      display: flex;
      flex-direction: column;
      gap: 4px;
      color: #15293e;
      font-size: 0.96rem;
      line-height: 1.45;
    }
    .consent-title {
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .consent-tag {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.01em;
    }
    .consent-tag.required {
      background: #fde8e2;
      color: #b91c1c;
      border: 1px solid #fca5a5;
    }
    .consent-tag.optional {
      background: #ecfdf3;
      color: #15803d;
      border: 1px solid #bbf7d0;
    }
    .consent-help {
      color: #4b5563;
      font-size: 0.9rem;
      margin: 0;
    }
    .consent-note {
      color: #555;
      font-size: 0.9rem;
      margin: 8px 0 0;
    }
  </style>
</head>
<body>
  <div id="header-container"></div>

  <main class="register-page">
    <div class="register-container">
      <div class="register-box">
      <h2>Registrati</h2>
      <?php if ($successMessage): ?>
        <div class="success-message"><?= htmlspecialchars($successMessage) ?></div>
      <?php endif; ?>
      <form class="register-form" method="POST" action="" enctype="multipart/form-data">
        <?= csrf_field('register_form') ?>
        <div class="hp-field" aria-hidden="true">
          <label for="hp_field">Lascia vuoto</label>
          <input type="text" id="hp_field" name="hp_field" tabindex="-1" autocomplete="off">
        </div>
        <label for="nome">Nome</label>
        <input type="text" id="nome" name="nome" required>

        <label for="cognome">Cognome</label>
        <input type="text" id="cognome" name="cognome" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <div class="password-field">
          <input type="password" id="password" name="password" required>
          <button type="button" class="toggle-password" data-target="password" aria-label="Mostra password">
            <svg class="icon-eye" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7zm0 12c-2.7 0-5-2.3-5-5s2.3-5 5-5 5 2.3 5 5-2.3 5-5 5zm0-8a3 3 0 100 6 3 3 0 000-6z"/>
            </svg>
            <svg class="icon-eye-off" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M2.3 3.7l2 2A12.7 12.7 0 002 12c1 2.5 5 7 10 7 1.7 0 3.3-.5 4.8-1.4l2.2 2.2 1.4-1.4-17-17-1.3 1.3zm7.1 7.1l1.9 1.9a1 1 0 01-1.9-1.9zm3.5 3.5l1.9 1.9a3 3 0 01-3.8-3.8l1.9 1.9zm8.8-.3c.5-.8.8-1.5.8-2.1-1-2.5-5-7-10-7-1.2 0-2.5.3-3.6.8l1.6 1.6a6 6 0 017.4 7.4l1.5 1.5a13.5 13.5 0 002.3-2.2z"/>
            </svg>
          </button>
        </div>

        <div style="display: flex; align-items: center; margin-top: 5px;">
          <span id="passwordCheck"></span>
          <small id="passwordMessage" style="color: red;">
            La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.
          </small>
        </div>

        <label for="confirm_password">Conferma Password</label>
        <div class="password-field">
          <input type="password" id="confirm_password" name="confirm_password" required>
          <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Mostra conferma password">
            <svg class="icon-eye" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7zm0 12c-2.7 0-5-2.3-5-5s2.3-5 5-5 5 2.3 5 5-2.3 5-5 5zm0-8a3 3 0 100 6 3 3 0 000-6z"/>
            </svg>
            <svg class="icon-eye-off" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M2.3 3.7l2 2A12.7 12.7 0 002 12c1 2.5 5 7 10 7 1.7 0 3.3-.5 4.8-1.4l2.2 2.2 1.4-1.4-17-17-1.3 1.3zm7.1 7.1l1.9 1.9a1 1 0 01-1.9-1.9zm3.5 3.5l1.9 1.9a3 3 0 01-3.8-3.8l1.9 1.9zm8.8-.3c.5-.8.8-1.5.8-2.1-1-2.5-5-7-10-7-1.2 0-2.5.3-3.6.8l1.6 1.6a6 6 0 017.4 7.4l1.5 1.5a13.5 13.5 0 002.3-2.2z"/>
            </svg>
          </button>
        </div>

        <div style="display: flex; align-items: center; margin-top: 5px;">
          <span id="confirmCheck"></span>
          <small id="confirmMessage" style="color: red;">Le password devono coincidere.</small>
        </div>

        <label for="avatar">Foto profilo (opzionale)</label>
        <div class="file-upload">
          <input type="file" id="avatar" name="avatar" accept="image/*">
          <label for="avatar" class="file-btn">
            <span>??</span> Scegli foto
          </label>
          <span class="file-name" id="avatarName">Nessun file selezionato</span>
        </div>
                <small style="color:#666;">File JPG, PNG, GIF o WEBP - max 2MB.</small>

                <div class="consent-box">
          <div class="consent-head">
            <span class="consent-badge">Consensi</span>
            <p class="consent-subtitle">Completa quelli obbligatori e, se vuoi, attiva gli aggiornamenti facoltativi.</p>
          </div>
          <div class="consent-list">
            <label class="consent-item">
              <input type="checkbox" name="accetta_privacy" required>
              <div class="consent-copy">
                <span class="consent-title">
                  Privacy e trattamento dati
                  <span class="consent-tag required">Obbligatorio</span>
                </span>
                <p class="consent-help">Ho letto la <a href="/privacy.php" target="_blank">Privacy Policy</a> e acconsento al trattamento dei dati per l'iscrizione.</p>
              </div>
            </label>
            <label class="consent-item">
              <input type="checkbox" name="accetta_termini" required>
              <div class="consent-copy">
                <span class="consent-title">
                  Termini di servizio / regolamento tornei
                  <span class="consent-tag required">Obbligatorio</span>
                </span>
                <p class="consent-help">Accetto le regole per partecipare e utilizzare il servizio.</p>
              </div>
            </label>
            <label class="consent-item">
              <input type="checkbox" name="consenso_foto">
              <div class="consent-copy">
                <span class="consent-title">
                  Utilizzo della foto profilo
                  <span class="consent-tag optional">Facoltativo</span>
                </span>
                <p class="consent-help">Permettiamo di mostrare la tua foto in profili e classifiche.</p>
              </div>
            </label>
            <label class="consent-item">
              <input type="checkbox" name="consenso_newsletter">
              <div class="consent-copy">
                <span class="consent-title">
                  Newsletter tornei
                  <span class="consent-tag optional">Facoltativo</span>
                </span>
                <p class="consent-help">Aggiornamenti su novita e calendari dei tornei.</p>
              </div>
            </label>
            <label class="consent-item">
              <input type="checkbox" name="consenso_marketing">
              <div class="consent-copy">
                <span class="consent-title">
                  Comunicazioni promozionali
                  <span class="consent-tag optional">Facoltativo</span>
                </span>
                <p class="consent-help">Info dedicate sui tornei e iniziative speciali.</p>
              </div>
            </label>
          </div>
        </div>
        <p class="consent-note">Puoi modificare o revocare marketing/newsletter e tracciamento in qualsiasi momento dal tuo account o dal link "Gestisci preferenze" nel footer.</p>

        <label for="captcha_answer">Verifica: quanto fa <?= htmlspecialchars($captchaQuestion) ?>?</label>
        <input type="number" id="captcha_answer" name="captcha_answer" inputmode="numeric" required>

        <button type="submit" class="register-btn">Crea Account</button>

        <?php if ($error): ?>
          <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
      </form>

        <div class="register-footer">
          <p>Hai gia un account? <a href="login.php">Accedi</a></p>
        </div>
      </div>
    </div>
  </main>

  <div id="footer-container"></div>

  <script src="/includi/app.min.js?v=20251220"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
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

      const avatarInput = document.getElementById('avatar');
      const avatarName = document.getElementById('avatarName');
      if (avatarInput && avatarName) {
        avatarInput.addEventListener('change', () => {
          const file = avatarInput.files && avatarInput.files[0] ? avatarInput.files[0] : null;
          avatarName.textContent = file ? file.name : 'Nessun file selezionato';
        });
      }

      document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
          const targetId = btn.getAttribute('data-target');
          const target = document.getElementById(targetId);
          if (!target) return;

          const shouldShow = target.type === 'password';
          target.type = shouldShow ? 'text' : 'password';
          btn.classList.toggle('is-visible', shouldShow);
          const ariaLabel = btn.getAttribute('aria-label') || '';
          const labelBase = ariaLabel.indexOf('conferma') !== -1 ? 'conferma password' : 'password';
          btn.setAttribute('aria-label', shouldShow ? `Nascondi ${labelBase}` : `Mostra ${labelBase}`);
        });
      });

      const passwordInput = document.getElementById('password');
      const passwordMessage = document.getElementById('passwordMessage');
      const passwordCheck = document.getElementById('passwordCheck');
      const confirmInput = document.getElementById('confirm_password');
      const confirmMessage = document.getElementById('confirmMessage');
      const confirmCheck = document.getElementById('confirmCheck');

      if (passwordInput && passwordMessage && passwordCheck) {
        passwordInput.addEventListener('input', () => {
          const password = passwordInput.value;
          const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]).{8,}$/;

          if (regex.test(password)) {
            passwordMessage.style.color = 'green';
            passwordCheck.textContent = '?';
            passwordCheck.style.color = 'green';
          } else {
            passwordMessage.style.color = 'red';
            passwordCheck.textContent = '?';
            passwordCheck.style.color = 'red';
          }
        });
      }

      function checkConfirmPassword() {
        if (!passwordInput || !confirmInput || !confirmMessage || !confirmCheck) {
          return;
        }
        if (confirmInput.value === '') {
          confirmCheck.textContent = '';
          confirmMessage.textContent = '';
          return;
        }
        if (confirmInput.value === passwordInput.value) {
          confirmMessage.style.color = 'green';
          confirmMessage.textContent = 'Le password coincidono.';
          confirmCheck.textContent = '?';
          confirmCheck.style.color = 'green';
        } else {
          confirmMessage.style.color = 'red';
          confirmMessage.textContent = 'Le password non coincidono.';
          confirmCheck.textContent = '?';
          confirmCheck.style.color = 'red';
        }
      }

      if (passwordInput) {
        passwordInput.addEventListener('input', checkConfirmPassword);
      }
      if (confirmInput) {
        confirmInput.addEventListener('input', checkConfirmPassword);
      }
    });
  </script>
</body>
</html>


















