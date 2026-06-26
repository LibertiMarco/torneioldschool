<?php
require_once __DIR__ . '/../../includi/security.php';

if (!tos_is_cli()) {
    http_response_code(403);
    exit('Accesso consentito solo da CLI.');
}

require_once __DIR__ . '/../crud/utente.php';

$email = trim((string)($argv[1] ?? ''));
$password = trim((string)($argv[2] ?? ''));
$ruolo = trim((string)($argv[3] ?? 'user'));
$nome = trim((string)($argv[4] ?? 'Admin'));
$cognome = trim((string)($argv[5] ?? ''));

if ($email === '' || $password === '') {
    exit("Uso: php api/script/crea_utente.php email password [ruolo] [nome] [cognome]\n");
}

$allowedRoles = ['user', 'admin', 'sysadmin'];
if (!in_array($ruolo, $allowedRoles, true)) {
    exit("Errore: ruolo non valido. Valori ammessi: user, admin, sysadmin.\n");
}

$utente = new Utente();
$result = $utente->crea($email, $nome, $cognome, $password, $ruolo);

if (!empty($result['error'])) {
    fwrite(STDERR, "Errore: " . $result['error'] . "\n");
    exit(1);
}

echo "Utente creato correttamente: {$email} ({$ruolo})\n";
