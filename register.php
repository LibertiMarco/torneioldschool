<?php
session_start();
require_once __DIR__ . '/includi/db.php';

$error = "";

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
                // Crittografia password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Inserimento con ruolo "utente"
                $ruolo = 'utente';
                $sql = "INSERT INTO utenti (nome, cognome, email, password, ruolo) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $nome, $cognome, $email, $hashed_password, $ruolo);

                if ($stmt->execute()) {
                    // Redirect diretto alla login
                    header("Location: login.php");
                    exit;
                } else {
                    $error = "Errore durante la registrazione. Riprova.";
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
    .register-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background-color: #f4f4f4;
      padding: 20px;
    }
    .register-box {
      background-color: #ffffff;
      padding: 40px 50px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 450px;
      width: 100%;
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
  </style>
</head>
<body>
  <div id="header-container"></div>
  <br><br><br>

  <div class="register-container">
    <div class="register-box">
      <h2>Registrati</h2>
      <form class="register-form" method="POST" action="">
        <label for="nome">Nome</label>
        <input type="text" id="nome" name="nome" required>

        <label for="cognome">Cognome</label>
        <input type="text" id="cognome" name="cognome" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <div style="display: flex; align-items: center; margin-top: 5px;">
          <span id="passwordCheck"></span>
          <small id="passwordMessage" style="color: red;">
            La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.
          </small>
        </div>

        <label for="confirm_password">Conferma Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <!-- Controllo conferma password -->
        <div style="display: flex; align-items: center; margin-top: 5px;">
          <span id="confirmCheck"></span>
          <small id="confirmMessage" style="color: red;"></small>
        </div>

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

  <div id="footer-container"></div>

  <script src="/torneioldschool/includi/header-interactions.js"></script>
  <script>
    // FOOTER e HEADER
    fetch("/torneioldschool/includi/footer.html")
      .then(r => r.text())
      .then(html => document.getElementById("footer-container").innerHTML = html);
    fetch("/torneioldschool/includi/header.php")
      .then(r => r.text())
      .then(html => {
        document.getElementById("header-container").innerHTML = html;
        initHeaderInteractions();
        const header = document.querySelector(".site-header");
        window.addEventListener("scroll", () => {
          header?.classList.toggle("scrolled", window.scrollY > 50);
        });
      });

    // ✅ Controllo forza password
    const passwordInput = document.getElementById('password');
    const passwordMessage = document.getElementById('passwordMessage');
    const passwordCheck = document.getElementById('passwordCheck');

    passwordInput.addEventListener('input', function() {
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

    // ✅ Controllo conferma password
    const confirmInput = document.getElementById('confirm_password');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmCheck = document.getElementById('confirmCheck');

    function checkConfirmPassword() {
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

    passwordInput.addEventListener('input', checkConfirmPassword);
    confirmInput.addEventListener('input', checkConfirmPassword);
  </script>
</body>
</html>
