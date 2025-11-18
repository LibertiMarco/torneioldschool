<?php
session_start();
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/mail_helper.php';

$error = "";
$successMessage = "";
$avatarPath = null;

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
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validazione base
    if (empty($nome) || empty($cognome) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Compila tutti i campi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Inserisci un'email valida.";
    } elseif ($password !== $confirm_password) {
        $error = "Le password non coincidono.";
    } else {
        // Controllo forza password lato server
        $pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\'":\\|,.<>\/?]).{8,}$/';
        if (!preg_match($pattern, $password)) {
            $error = "La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.";
        } else {
            // Verifica se l'email esiste già
            $check = $conn->prepare("SELECT id FROM utenti WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Esiste già un account con questa email.";
            } else {
                // Gestione avatar (opzionale)
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $maxSize = 2 * 1024 * 1024; // 2MB
                        if ($_FILES['avatar']['size'] > $maxSize) {
                            $error = "La foto deve essere inferiore a 2MB.";
                        } else {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
                            finfo_close($finfo);
                            $allowed = [
                                'image/jpeg' => 'jpg',
                                'image/png'  => 'png',
                                'image/gif'  => 'gif',
                                'image/webp' => 'webp'
                            ];

                            if (!isset($allowed[$mime])) {
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
                        $error = "Errore tecnico nella generazione del token. Riprova più tardi.";
                    }
                }

                if (!$error) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $ruolo = 'utente';
                    $tokenScadenza = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

                    $sql = "INSERT INTO utenti (nome, cognome, email, password, ruolo, avatar, token_verifica, token_verifica_scadenza)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssss", $nome, $cognome, $email, $hashed_password, $ruolo, $avatarPath, $tokenVerifica, $tokenScadenza);

                    if ($stmt->execute()) {
                        if (inviaEmailVerifica($email, $nome, $tokenVerifica)) {
                            $successMessage = "Registrazione completata! Ti abbiamo inviato una email di conferma a {$email}.";
                        } else {
                            $successMessage = "Registrazione riuscita, ma non è stato possibile inviare l'email di conferma. Contattaci per ricevere assistenza.";
                        }
                        $_POST = [];
                    } else {
                        $error = "Errore durante la registrazione. Riprova.";
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
  <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrati - Tornei Old School</title>
  <link rel="stylesheet" href="style.css">
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
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      cursor: pointer;
      width: 32px;
      height: 32px;
      padding: 0;
      font-size: 0;
    }
    .toggle-password:focus {
      outline: none;
    }
    .toggle-password::after {
      content: "👁️";
      font-size: 1.1rem;
      color: #5c667a;
      display: inline-block;
      line-height: 1;
    }
    .toggle-password.visible::after {
      content: "🙈";
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
        <label for="nome">Nome</label>
        <input type="text" id="nome" name="nome" required>

        <label for="cognome">Cognome</label>
        <input type="text" id="cognome" name="cognome" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <div class="password-field">
          <input type="password" id="password" name="password" required>
          <button type="button" class="toggle-password" data-target="password" data-visible="false" aria-label="Mostra password"></button>
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
          <button type="button" class="toggle-password" data-target="confirm_password" data-visible="false" aria-label="Mostra conferma password"></button>
        </div>

        <div style="display: flex; align-items: center; margin-top: 5px;">
          <span id="confirmCheck"></span>
          <small id="confirmMessage" style="color: red;">Le password devono coincidere.</small>
        </div>

        <label for="avatar">Foto profilo (opzionale)</label>
        <div class="file-upload">
          <input type="file" id="avatar" name="avatar" accept="image/*">
          <label for="avatar" class="file-btn">
            <span>📸</span> Scegli foto
          </label>
          <span class="file-name" id="avatarName">Nessun file selezionato</span>
        </div>
        <small style="color:#666;">File JPG, PNG, GIF o WEBP - max 2MB.</small>

        <button type="submit" class="register-btn">Crea Account</button>

        <?php if ($error): ?>
          <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
      </form>

        <div class="register-footer">
          <p>Hai già un account? <a href="login.php">Accedi</a></p>
        </div>
      </div>
    </div>
  </main>

  <div id="footer-container"></div>

  <script src="/torneioldschool/includi/header-interactions.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      fetch("/torneioldschool/includi/footer.html")
        .then(r => r.text())
        .then(html => document.getElementById("footer-container").innerHTML = html);
      fetch("/torneioldschool/includi/header.php")
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

          const currentlyVisible = btn.classList.contains('visible');
          target.type = currentlyVisible ? 'password' : 'text';
          btn.classList.toggle('visible', !currentlyVisible);
          btn.setAttribute('data-visible', currentlyVisible ? 'false' : 'true');
          btn.setAttribute('aria-label', currentlyVisible ? 'Mostra password' : 'Nascondi password');
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
            passwordCheck.textContent = '✓';
            passwordCheck.style.color = 'green';
          } else {
            passwordMessage.style.color = 'red';
            passwordCheck.textContent = '✗';
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
          confirmCheck.textContent = '✓';
          confirmCheck.style.color = 'green';
        } else {
          confirmMessage.style.color = 'red';
          confirmMessage.textContent = 'Le password non coincidono.';
          confirmCheck.textContent = '✗';
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
