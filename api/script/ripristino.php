<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'torneioldschool';

$schemaFile = dirname(__DIR__, 2) . '/database_schema.sql';
$dataFile   = dirname(__DIR__, 2) . '/database_data.sql'; // facoltativo, se presente verra importato

function fail(string $message): void
{
    echo '<b>Errore:</b> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '<br>';
    exit(1);
}

function loadSql(string $filePath): string
{
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        fail('Impossibile leggere il file: ' . $filePath);
    }

    // Rimuove eventuale BOM UTF-8 all'inizio del file
    return preg_replace('/^\xEF\xBB\xBF/', '', $sql);
}

function runSqlFile(mysqli $mysqli, string $filePath): void
{
    $sql = loadSql($filePath);

    if (!$mysqli->multi_query($sql)) {
        fail('Errore durante l\'esecuzione di ' . basename($filePath) . ': ' . $mysqli->error);
    }

    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
}

$mysqli = new mysqli($host, $user, $pass);
if ($mysqli->connect_errno) {
    fail('Connessione MySQL fallita: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

if (!is_readable($schemaFile)) {
    fail('File schema non trovato: ' . $schemaFile);
}

echo '>>> Connessione al server MySQL riuscita<br>';
echo '>>> Ricreo database `' . $dbName . '` da zero<br>';

// Drop & recreate per eliminare tablespace residui
if (!$mysqli->query("DROP DATABASE IF EXISTS `{$dbName}`")) {
    fail('Impossibile droppare il database: ' . $mysqli->error);
}
if (!$mysqli->query("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    fail('Impossibile creare il database: ' . $mysqli->error);
}
if (!$mysqli->select_db($dbName)) {
    fail('Impossibile selezionare il database: ' . $mysqli->error);
}

echo '>>> Ripristino database `' . $dbName . '` usando ' . basename($schemaFile) . '<br>';

runSqlFile($mysqli, $schemaFile);

if (is_readable($dataFile)) {
    echo '>>> Importo dati da ' . basename($dataFile) . '<br>';
    runSqlFile($mysqli, $dataFile);
} else {
    echo '>>> Nessun file di dati trovato (opzionale: database_data.sql)<br>';
}

echo '<h2>Ripristino completato</h2>';
?>
