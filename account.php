<?php
require_once __DIR__ . '/includi/security.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/mail_helper.php';
require_once __DIR__ . '/includi/consent_helpers.php';
require_once __DIR__ . '/includi/seo.php';

$baseUrl = seo_base_url();
$accountSeo = [
    'title' => 'Il mio account - Tornei Old School',
    'description' => 'Gestisci profilo, avatar e preferenze dei tornei Old School.',
    'url' => $baseUrl . '/account.php',
    'canonical' => $baseUrl . '/account.php',
];
$accountBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Account', 'url' => $baseUrl . '/account.php'],
]);

$userId = (int)$_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';
$infoMessage = '';
$accountCsrf = csrf_get_token('account_form');

function caricaUtente($conn, $id) {
    $stmt = $conn->prepare("SELECT id, nome, cognome, email, avatar, password FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $utente = $result->fetch_assoc();
    $stmt->close();
    return $utente;
}

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

function risolviAvatarUrl($avatarPath) {
    if (!empty($avatarPath)) {
        if (preg_match('#^https?://#i', $avatarPath)) {
            return $avatarPath;
        }
        return '/' . ltrim($avatarPath, '/');
    }
    return '/img/icone/user.png';
}

$currentUser = caricaUtente($conn, $userId);
if (!$currentUser) {
    header("Location: /logout.php");
    exit;
}
$consents = consent_current_snapshot($conn, $userId, $currentUser['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'account_form')) {
        $errorMessage = "Sessione scaduta. Ricarica la pagina e riprova.";
    } elseif (isset($_POST['revoca_consensi'])) {
        $consents = consent_save($conn, $userId, $currentUser['email'] ?? '', [
            'marketing' => 0,
            'newsletter' => 0,
            'tracking' => 0,
        ], 'account', 'revoke_all');
        $successMessage = "Consensi marketing/newsletter/tracciamento revocati.";
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $cognome = trim($_POST['cognome'] ?? '');
        $email = $currentUser['email'];
        $password = trim($_POST['password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        $currentPassword = trim($_POST['current_password'] ?? '');
        $consensoMarketing = !empty($_POST['consenso_marketing']);
        $consensoNewsletter = !empty($_POST['consenso_newsletter']);
        $consensoTracking = !empty($_POST['consenso_tracking']);
        $avatarPath = $currentUser['avatar'] ?? null;
        $emailChanged = false;

        $passwordRegex = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\'":\\\\|,.<>\/?]).{8,}$/';

        if ($nome === '' || $cognome === '' || $email === '') {
            $errorMessage = "Compila tutti i campi obbligatori.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Inserisci un'email valida.";
        } elseif ($password !== '' && $password !== $confirmPassword) {
            $errorMessage = "Le password non coincidono.";
        } elseif ($password !== '' && !preg_match($passwordRegex, $password)) {
            $errorMessage = "La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.";
        } elseif ($password !== '' && $currentPassword === '') {
            $errorMessage = "Per cambiare password inserisci prima quella attuale.";
        }

    if (!$errorMessage && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024; // 2MB
            if ($_FILES['avatar']['size'] > $maxSize) {
                $errorMessage = "La foto deve essere inferiore a 2MB.";
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $_FILES['avatar']['tmp_name']) : false;
                if ($finfo instanceof finfo) {
                    unset($finfo); // finfo_close deprecato, lasciamo al GC
                }
                if (!$mime) {
                    $errorMessage = "Impossibile determinare il formato dell'immagine.";
                }

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp'
                ];

                if (!$errorMessage && !isset($allowed[$mime])) {
                    $errorMessage = "Formato immagine non valido. Usa JPG, PNG, GIF o WEBP.";
                } else {
                    $uploadDir = __DIR__ . '/img/utenti';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }
                    $estensione = $allowed[$mime];
                    $filename = generaNomeAvatar($nome, $cognome, $estensione, $uploadDir);
                    $destination = $uploadDir . '/' . $filename;

                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                        $nuovoPercorso = 'img/utenti/' . $filename;
                        if (!empty($avatarPath) && strpos($avatarPath, 'img/utenti/') === 0) {
                            $vecchioAssoluto = __DIR__ . '/' . ltrim($avatarPath, '/');
                            if (is_file($vecchioAssoluto)) {
                                @unlink($vecchioAssoluto);
                            }
                        }
                        $avatarPath = $nuovoPercorso;
                    } else {
                        $errorMessage = "Impossibile salvare la foto. Riprova.";
                    }
                }
            }
        } else {
            $errorMessage = "Errore nel caricamento dell'immagine.";
        }
    }

    if (!$errorMessage) {
        if ($password !== '') {
            $stmtPwd = $conn->prepare("SELECT password FROM utenti WHERE id = ?");
            $stmtPwd->bind_param("i", $userId);
            $stmtPwd->execute();
            $resPwd = $stmtPwd->get_result()->fetch_assoc();
            $stmtPwd->close();

            $hashAttuale = $resPwd['password'] ?? '';
            if (!password_verify($currentPassword, $hashAttuale)) {
                $errorMessage = "La password attuale non è corretta.";
            }
        }
    }

    if (!$errorMessage) {
        $updateFields = ["nome=?", "cognome=?", "email=?", "avatar=?"];
        $types = "ssss";
        $params = [$nome, $cognome, $email, $avatarPath];

        if ($password !== '') {
            $updateFields[] = "password=?";
            $types .= "s";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $types .= "i";
        $params[] = $userId;

        $sql = "UPDATE utenti SET " . implode(', ', $updateFields) . " WHERE id=?";
        $stmt = $conn->prepare($sql);
        $bindParams = [$types];
        foreach ($params as $idx => $value) {
            $bindParams[] = &$params[$idx];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        if ($stmt->execute()) {
            $_SESSION['nome'] = $nome;
            $_SESSION['cognome'] = $cognome;
            $_SESSION['email'] = $email;
            $_SESSION['avatar'] = $avatarPath;
            $consents = consent_save($conn, $userId, $currentUser['email'] ?? '', [
                'marketing' => $consensoMarketing,
                'newsletter' => $consensoNewsletter,
                'tracking' => $consensoTracking,
                'terms' => 1,
            ], 'account');
            $successMessage = "Impostazioni aggiornate con successo.";

            $currentUser = caricaUtente($conn, $userId);
        } else {
            $errorMessage = "Errore durante l'aggiornamento dell'account.";
        }

        $stmt->close();
    }
}
}

$avatarUrl = risolviAvatarUrl($currentUser['avatar'] ?? '');
$nomeCompleto = trim(($currentUser['nome'] ?? '') . ' ' . ($currentUser['cognome'] ?? ''));
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($accountSeo); ?>
  <?php render_jsonld($accountBreadcrumbs); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    body {
      background: #f4f6fb;
    }
    .account-page {
      padding: 100px 20px 80px;
      display: flex;
      justify-content: center;
    }
    .account-card {
      width: 100%;
      max-width: 820px;
      background: #fff;
      border-radius: 18px;
      padding: 34px 34px 42px;
      box-shadow: 0 30px 60px rgba(15, 23, 42, 0.1);
      border: 1px solid rgba(15, 23, 42, 0.08);
    }
    .account-head {
      display: flex;
      gap: 18px;
      align-items: center;
      margin-bottom: 18px;
    }
    .account-head .avatar-lg {
      width: 68px;
      height: 68px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid rgba(21, 41, 62, 0.1);
      background: #f4f6fb;
    }
    .account-head .eyebrow {
      text-transform: uppercase;
      letter-spacing: 1px;
      font-size: 0.75rem;
      color: #64748b;
      font-weight: 700;
      margin: 0 0 6px;
    }
    .account-head h1 {
      margin: 0;
      font-size: 1.9rem;
      color: #0f172a;
      letter-spacing: 0.2px;
    }
    .account-head p {
      margin: 2px 0 0;
      color: #475569;
    }
    .banner {
      border-radius: 12px;
      padding: 12px 14px;
      margin-bottom: 14px;
      font-weight: 600;
    }
    .banner.error {
      background: #fdecec;
      color: #c01c28;
      border: 1px solid #f3c2c2;
    }
    .banner.success {
      background: #e9f7ef;
      color: #0f8755;
      border: 1px solid #cce8d6;
    }
    .banner.info {
      background: #e6f2ff;
      color: #0f5499;
      border: 1px solid #c5ddff;
    }
    .account-form {
      display: flex;
      flex-direction: column;
      gap: 18px;
      margin-top: 10px;
    }
    .field-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
    }
    .form-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .form-field label {
      font-weight: 700;
      color: #0f172a;
    }
    .form-field input {
      padding: 11px 12px;
      border-radius: 10px;
      border: 1px solid #d0d7e1;
      font-size: 1rem;
      background: #fff;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-field input:focus {
      outline: none;
      border-color: #15293e;
      box-shadow: 0 0 0 3px rgba(21, 41, 62, 0.12);
    }
    .avatar-upload {
      display: flex;
      gap: 14px;
      align-items: center;
      padding: 14px;
      border: 1px dashed #cfd7e3;
      border-radius: 12px;
      background: #f8fafc;
    }
    .avatar-upload img {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(21, 41, 62, 0.1);
      background: #fff;
    }
    .upload-actions {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .file-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 12px;
      background: #15293e;
      color: #fff !important;
      border-radius: 9px;
      font-weight: 700;
      cursor: pointer;
      border: none;
      text-decoration: none;
      width: fit-content;
      transition: transform 0.2s ease, background 0.2s ease;
    }
    .file-btn:hover {
      transform: translateY(-1px);
      background: #0e1d2e;
    }
    .file-btn input {
      display: none;
    }
    .file-name {
      font-size: 0.95rem;
      color: #475569;
    }
    .hint {
      color: #64748b;
      font-size: 0.9rem;
    }
    .consent-panel {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 14px 16px;
      margin-top: 4px;
    }
    .consent-panel h3 {
      margin: 0 0 10px;
      font-size: 1.05rem;
      color: #0f172a;
    }
    .consent-row {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      margin-bottom: 10px;
      color: #0f172a;
    }
    .consent-row input[type="checkbox"] {
      margin-top: 4px;
    }
    .consent-hint {
      color: #475569;
      font-size: 0.9rem;
      margin: 6px 0 0;
    }
    .save-btn {
      margin-top: 10px;
      background: linear-gradient(120deg, #15293e, #1f3d60);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 14px;
      font-size: 1.05rem;
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.18);
    }
    .save-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 16px 32px rgba(15, 23, 42, 0.22);
    }
    .revoke-btn {
      margin-top: 10px;
      background: #fff;
      color: #b91c1c;
      border: 1px solid #fca5a5;
      border-radius: 12px;
      padding: 12px 14px;
      font-size: 0.98rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s ease, transform 0.2s ease;
    }
    .revoke-btn:hover {
      background: #fef2f2;
      transform: translateY(-1px);
    }
    .password-area {
      display: flex;
      gap: 10px;
    }
    .toggle-password {
      background: #e2e8f0;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 0 12px;
      cursor: pointer;
      font-weight: 700;
      color: #0f172a;
    }
    @media (max-width: 640px) {
      .account-card {
        padding: 26px 22px 34px;
      }
      .account-head {
        flex-direction: column;
        align-items: flex-start;
      }
      .avatar-upload {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/includi/header.php'; ?>

  <main class="account-page">
    <section class="account-card">
      <div class="account-head">
        <img class="avatar-lg" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar utente">
        <div>
          <div class="eyebrow">Impostazioni account</div>
          <h1><?= htmlspecialchars($nomeCompleto) ?></h1>
          <p><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
        </div>
      </div>

      <?php if ($errorMessage): ?>
        <div class="banner error"><?= htmlspecialchars($errorMessage) ?></div>
      <?php endif; ?>
      <?php if ($successMessage): ?>
        <div class="banner success"><?= htmlspecialchars($successMessage) ?></div>
      <?php endif; ?>
      <?php if ($infoMessage): ?>
        <div class="banner info"><?= htmlspecialchars($infoMessage) ?></div>
      <?php endif; ?>

      <form class="account-form" method="POST" enctype="multipart/form-data" autocomplete="off">
        <?= csrf_field('account_form') ?>
        <div class="field-grid">
          <div class="form-field">
            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($currentUser['nome'] ?? '') ?>" required>
          </div>
          <div class="form-field">
            <label for="cognome">Cognome</label>
            <input type="text" id="cognome" name="cognome" value="<?= htmlspecialchars($currentUser['cognome'] ?? '') ?>" required>
          </div>
          <div class="form-field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" readonly>
          </div>
          <div class="form-field">
            <label for="password">Nuova password (opzionale)</label>
            <div class="password-area">
              <input type="password" id="password" name="password" placeholder="Lascia vuoto per non cambiare">
              <button type="button" class="toggle-password" data-target="password">Mostra</button>
            </div>
            <div class="hint">
              <span id="passwordCheck" aria-live="polite"></span>
              <small id="passwordMessage">Almeno 8 caratteri, una maiuscola, un numero e un simbolo.</small>
            </div>
          </div>
          <div class="form-field">
            <label for="confirm_password">Conferma password</label>
            <div class="password-area">
              <input type="password" id="confirm_password" name="confirm_password" placeholder="Ripeti la nuova password">
              <button type="button" class="toggle-password" data-target="confirm_password">Mostra</button>
            </div>
            <div class="hint">
              <span id="confirmCheck" aria-live="polite"></span>
              <small id="confirmMessage">Le password devono coincidere.</small>
            </div>
          </div>
          <div class="form-field">
            <label for="current_password">Password attuale (richiesta per cambiare password)</label>
            <div class="password-area">
              <input type="password" id="current_password" name="current_password" placeholder="Inserisci la password attuale">
              <button type="button" class="toggle-password" data-target="current_password">Mostra</button>
            </div>
            <div class="hint" id="currentPwdHint">Obbligatoria solo se imposti una nuova password.</div>
          </div>
          <div class="form-field" style="grid-column: 1 / -1;">
            <label for="avatar">Foto profilo</label>
            <div class="avatar-upload">
              <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Anteprima avatar" id="avatarPreview">
              <div class="upload-actions">
                <label class="file-btn">
                  <input type="file" id="avatar" name="avatar" accept="image/*">
                  Carica nuova foto
                </label>
                <span class="file-name" id="avatarName"><?= $currentUser['avatar'] ? basename($currentUser['avatar']) : 'Nessun file selezionato' ?></span>
                <span class="hint">Formati: JPG, PNG, GIF, WEBP. Max 2MB.</span>
              </div>
            </div>
          </div>
          <div class="form-field" style="grid-column: 1 / -1;">
            <div class="consent-panel">
              <h3>Consensi marketing e preferenze</h3>
              <div class="consent-row">
                <input type="checkbox" id="consenso_newsletter" name="consenso_newsletter" <?= !empty($consents['newsletter']) ? 'checked' : '' ?>>
                <label for="consenso_newsletter">Newsletter con novit� e calendari tornei (facoltativo).</label>
              </div>
              <div class="consent-row">
                <input type="checkbox" id="consenso_marketing" name="consenso_marketing" <?= !empty($consents['marketing']) ? 'checked' : '' ?>>
                <label for="consenso_marketing">Comunicazioni promozionali e info sui tornei (facoltativo).</label>
              </div>
              <div class="consent-row">
                <input type="checkbox" id="consenso_tracking" name="consenso_tracking" <?= !empty($consents['tracking']) ? 'checked' : '' ?>>
                <label for="consenso_tracking">Tracciamento utilizzo del sito per migliorare i servizi (facoltativo).</label>
              </div>
              <p class="consent-hint">Togli la spunta per revocare questi consensi. Privacy e termini restano attivi per mantenere l'account.</p>
            </div>
          </div>
        </div>
        <button type="submit" class="save-btn">Salva modifiche</button>
      </form>
      <form method="POST" style="margin-top: 6px;">
        <?= csrf_field('account_form') ?>
        <input type="hidden" name="revoca_consensi" value="1">
        <button type="submit" class="revoke-btn">Revoca marketing / newsletter / tracciamento</button>
      </form>

      <hr style="margin:24px 0; border:none; border-top:1px solid #e2e8f0;">
      <div style="background:#fff5f5;border:1px solid #fecdd3;border-radius:12px;padding:12px 14px;">
        <p style="margin:0 0 10px;font-weight:700;color:#b91c1c;">Vuoi eliminare il tuo account?</p>
        <p style="margin:0 0 10px;color:#b91c1c;">Puoi cancellare definitivamente il profilo e i consensi dal link dedicato.</p>
        <a class="revoke-btn" href="/account_delete.php" style="display:inline-block;text-decoration:none;text-align:center;">Vai alla cancellazione account</a>
      </div>
    </section>
  </main>

  <div id="footer-container"></div>

  <script>
    fetch("/includi/footer.html")
      .then(r => r.text())
      .then(html => document.getElementById("footer-container").innerHTML = html);

    document.addEventListener("DOMContentLoaded", () => {
      try {
        const current = JSON.parse(localStorage.getItem("tosConsent") || "{}");
        const consentState = {
          tracking: <?= !empty($consents['tracking']) ? 'true' : 'false' ?>,
          marketing: <?= !empty($consents['marketing']) ? 'true' : 'false' ?>,
          newsletter: <?= !empty($consents['newsletter']) ? 'true' : 'false' ?>,
          recaptcha: !!current.recaptcha,
          ts: Date.now(),
        };
        localStorage.setItem("tosConsent", JSON.stringify(consentState));
      } catch (err) {}

      const passwordButtons = document.querySelectorAll(".toggle-password");
      passwordButtons.forEach(btn => {
        btn.addEventListener("click", () => {
          const targetId = btn.getAttribute("data-target");
          const input = targetId ? document.getElementById(targetId) : null;
          if (!input) return;
          const isHidden = input.type === "password";
          input.type = isHidden ? "text" : "password";
          btn.textContent = isHidden ? "Nascondi" : "Mostra";
        });
      });

      const avatarInput = document.getElementById("avatar");
      const avatarName = document.getElementById("avatarName");
      const avatarPreview = document.getElementById("avatarPreview");

      if (avatarInput) {
        avatarInput.addEventListener("change", () => {
          const file = avatarInput.files && avatarInput.files[0] ? avatarInput.files[0] : null;
          if (avatarName) {
            avatarName.textContent = file ? file.name : "Nessun file selezionato";
          }
          if (file && avatarPreview) {
            const reader = new FileReader();
            reader.onload = e => {
              avatarPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
          }
        });
      }

      const passwordInput = document.getElementById("password");
      const confirmInput = document.getElementById("confirm_password");
      const currentInput = document.getElementById("current_password");
      const passwordMessage = document.getElementById("passwordMessage");
      const passwordCheck = document.getElementById("passwordCheck");
      const confirmMessage = document.getElementById("confirmMessage");
      const confirmCheck = document.getElementById("confirmCheck");
      const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]).{8,}$/;

      const updatePasswordStrength = () => {
        if (!passwordInput || !passwordMessage || !passwordCheck) return;
        if (!passwordInput.value) {
          passwordMessage.textContent = "Almeno 8 caratteri, una maiuscola, un numero e un simbolo.";
          passwordMessage.style.color = "#64748b";
          passwordCheck.textContent = "";
          return;
        }
        if (regex.test(passwordInput.value)) {
          passwordMessage.textContent = "Password valida.";
          passwordMessage.style.color = "green";
          passwordCheck.textContent = "OK";
          passwordCheck.style.color = "green";
        } else {
          passwordMessage.textContent = "Password non valida.";
          passwordMessage.style.color = "red";
          passwordCheck.textContent = "X";
          passwordCheck.style.color = "red";
        }
      };

      const updateConfirm = () => {
        if (!passwordInput || !confirmInput || !confirmMessage || !confirmCheck) return;
        if (!confirmInput.value) {
          confirmMessage.textContent = "Le password devono coincidere.";
          confirmMessage.style.color = "#64748b";
          confirmCheck.textContent = "";
          return;
        }
        if (confirmInput.value === passwordInput.value) {
          confirmMessage.textContent = "Le password coincidono.";
          confirmMessage.style.color = "green";
          confirmCheck.textContent = "OK";
          confirmCheck.style.color = "green";
        } else {
          confirmMessage.textContent = "Le password non coincidono.";
          confirmMessage.style.color = "red";
          confirmCheck.textContent = "X";
          confirmCheck.style.color = "red";
        }
      };

      const requireCurrentIfNeeded = () => {
        if (!passwordInput || !currentInput) return;
        if (passwordInput.value) {
          currentInput.required = true;
          currentInput.setAttribute("aria-required", "true");
        } else {
          currentInput.required = false;
          currentInput.removeAttribute("aria-required");
        }
      };

      if (passwordInput) {
        passwordInput.addEventListener("input", () => {
          updatePasswordStrength();
          updateConfirm();
          requireCurrentIfNeeded();
        });
      }

      if (confirmInput) {
        confirmInput.addEventListener("input", updateConfirm);
      }

      if (currentInput) {
        requireCurrentIfNeeded();
      }
    });
  </script>
</body>
</html>
