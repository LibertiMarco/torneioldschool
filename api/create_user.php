<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/crud/utente.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito'], JSON_UNESCAPED_UNICODE);
    exit;
}

csrf_or_same_origin_require('admin_utenti');

$email = trim((string)($_POST['email'] ?? ''));
$nome = trim((string)($_POST['nome'] ?? ($_POST['username'] ?? '')));
$cognome = trim((string)($_POST['cognome'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));
$ruolo = trim((string)($_POST['ruolo'] ?? 'user'));

if ($email === '' || $nome === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Parametri obbligatori mancanti'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedRoles = ['user', 'admin', 'sysadmin'];
if (!in_array($ruolo, $allowedRoles, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Ruolo non valido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$utente = new Utente();
$result = $utente->crea($email, $nome, $cognome, $password, $ruolo);

if (isset($result['error'])) {
    http_response_code(422);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
