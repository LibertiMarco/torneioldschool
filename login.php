<?php
session_start();
require_once __DIR__ . '/includi/db.php';

$error = "";
$needsVerificationResend = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

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
                $error = "Per accedere devi prima confermare l'indirizzo email. Controlla la tua casella di posta.";
                $needsVerificationResend = $email;
            } else {
            // imposta le variabili di sessione
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['nome'] = $row['nome'];
            $_SESSION['cognome'] = $row['cognome'];
            $_SESSION['ruolo'] = $row['ruolo']; // "admin" oppure "user"
            $_SESSION['avatar'] = $row['avatar'] ?? null;

            // salva la sessione e poi reindirizza
            session_write_close();
            header("Location: tornei.php");
            exit;
            }
        } else {
            $error = "Password errata.";
        }
    } else {
        $error = "Email non trovata.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Tornei Old School</title>
  <link rel="stylesheet" href="style.css">
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
    .login-form label {
      font-weight: 600;
      color: #15293e;
    }
    .login-form input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.2s;
    }
    .login-form input:focus {
      border-color: #15293e;
      outline: none;
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
  </style>
</head>
<body>
  <div id="header-container"></div>
  <div class="login-container">
    <div class="login-box">
      <h2>Accedi</h2>
      <form class="login-form" method="POST" action="">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="login-btn">Entra</button>

        <?php if ($error): ?>
          <div class="error-message">
            <?= htmlspecialchars($error) ?>
            <?php if ($needsVerificationResend): ?>
              <br>
              <a href="resend_verification.php?email=<?= urlencode($needsVerificationResend) ?>" style="color:#15293e;text-decoration:underline;font-weight:bold;">Reinvia email di conferma</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </form>

      <div class="login-footer">
        <p>Non hai un account? <a href="register.php">Registrati</a></p>
      </div>
    </div>
  </div>
  <div id="footer-container"></div>

  <script src="/torneioldschool/includi/header-interactions.js"></script>
  <script>
    // FOOTER
    fetch("/torneioldschool/includi/footer.html")
      .then(r => r.text())
      .then(html => document.getElementById("footer-container").innerHTML = html);

    // HEADER
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
  </script>
</body>
</html>
