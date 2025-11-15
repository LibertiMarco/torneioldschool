<?php
// Connessione al database
$host = "localhost";
$user = "root";
$pass = "";
$db = "torneioldschool";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connessione fallita: " . $conn->connect_error);
}

// Dati dell'utente da creare
$email = "marcoliberti001@gmail.com";   // <-- cambialo
$password_piana = "Marco01";            // <-- cambialo
$ruolo = "admin";                 // <-- puoi mettere "utente", "admin", ecc.

// Genera l'hash sicuro della password
$password_hash = password_hash($password_piana, PASSWORD_DEFAULT);

// Inserisci nel DB
$sql = "INSERT INTO utenti (email, password, ruolo) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $email, $password_hash, $ruolo);

if ($stmt->execute()) {
  echo "✅ Utente creato con successo!<br>";
  echo "Email: $email<br>";
  echo "Password: $password_piana<br>";
  echo "Tipo utenza: $ruolo<br>";
  echo "Hash salvato nel DB:<br>$password_hash";
} else {
  echo "❌ Errore: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
