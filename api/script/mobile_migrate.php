<?php
// CLI only. Preview by default; no database access without --apply.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$schema = file_get_contents(__DIR__ . '/../../migrations/mobile_auth.sql');
if (!in_array('--apply', $argv, true)) {
    echo "Anteprima: tre tabelle aggiuntive per le sessioni mobile. Nessuna modifica eseguita.\n\n" . $schema;
    exit;
}
require_once __DIR__ . '/../mobile/v1/_bootstrap.php';
if (!in_array(getenv('DB_HOST') ?: 'localhost', ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Questa procedura automatica consente soltanto il database locale. Applicare la migrazione allo staging tramite il processo di rilascio.\n");
    exit(1);
}
try {
    $db = mobile_db();
    foreach (explode(';', $schema) as $sql) {
        if (trim($sql) !== '') { $db->exec($sql); }
    }
    echo "Migrazione locale completata. Le API restano disattivate finché MOBILE_API_ENABLED non vale 1.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Migrazione non completata. Verificare connessione e privilegi del database locale.\n");
    exit(1);
}
