<?php
require_once __DIR__ . '/env_loader.php';

function env_or_default(string $key, string $default = ''): string
{
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

$host = env_or_default('DB_HOST', 'localhost');
$user = env_or_default('DB_USER', '');
$pass = env_or_default('DB_PASSWORD', '');
$dbname = env_or_default('DB_NAME', '');

if ($user === '' || $dbname === '') {
    die('Configurazione DB mancante: definisci DB_USER e DB_NAME.');
}

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
