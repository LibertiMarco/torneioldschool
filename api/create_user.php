<?php
require_once __DIR__ . '/crud/utente.php';
$utente = new Utente();

$email = trim($_POST['email']);
$username = trim($_POST['username']);
$password = trim($_POST['password']);
$ruolo = trim($_POST['ruolo']);

$result = $utente->crea($email, $username, $password, $ruolo);

header('Content-Type: application/json');
echo json_encode($result);
