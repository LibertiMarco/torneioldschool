<?php
require_once __DIR__ . '/env_loader.php';

if (!function_exists('env_or_default')) {
    function env_or_default(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
    }
}

$host = env_or_default('DB_HOST', 'localhost');
$user = env_or_default('DB_USER', '');
$pass = env_or_default('DB_PASSWORD', '');
$dbname = env_or_default('DB_NAME', '');

if ($user === '' || $dbname === '') {
    die('Configurazione DB mancante: definisci DB_USER e DB_NAME.');
}

// Evita eccezioni fatali su connessione fallita: gestiamo noi l'errore
mysqli_report(MYSQLI_REPORT_OFF);
try {
    $conn = @new mysqli($host, $user, $pass, $dbname);
} catch (mysqli_sql_exception $e) {
    error_log('Connessione DB fallita: ' . $e->getMessage());
    http_response_code(500);
    exit('Connessione al database non disponibile.');
}

if ($conn->connect_error) {
    error_log('Connessione DB fallita: ' . $conn->connect_error);
    http_response_code(500);
    exit('Connessione al database non disponibile.');
}

$conn->set_charset("utf8mb4");
