<?php
// Isolated test database only; never loads the website connection settings.
require_once __DIR__ . '/../api/crud/torneo.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if ((int)getenv('TOS_RECORDS_TEST_PORT') !== 3319) {
    throw new RuntimeException('Use the isolated MySQL test instance on port 3319.');
}
$db = new mysqli('127.0.0.1', 'root', '', '', 3319);
$database = 'tos_tournament_identity_' . bin2hex(random_bytes(6));
$directory = sys_get_temp_dir() . '/' . $database;
mkdir($directory);
$db->query("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4");
$db->select_db($database);
function identity_expect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
try {
    $db->query("CREATE TABLE tornei (
        id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255), stato VARCHAR(20),
        data_inizio DATE, data_fine DATE, img VARCHAR(255), filetorneo VARCHAR(255),
        categoria VARCHAR(100), sezione VARCHAR(20), squadre_complete INT, config LONGTEXT,
        UNIQUE KEY legacy_name (nome), UNIQUE KEY file_identity (filetorneo),
        KEY name_section (nome, sezione))");
    $db->query("INSERT INTO tornei (nome,filetorneo) VALUES ('Coppa Estate','coppa-storica.php')");
    $legacy = $db->query('SELECT * FROM tornei')->fetch_assoc();
    $reflection = new ReflectionClass(Torneo::class);
    $model = $reflection->newInstanceWithoutConstructor();
    foreach (['conn'=>$db, 'hasConfig'=>true, 'hasSection'=>true] as $property=>$value) {
        $reflection->getProperty($property)->setValue($model, $value);
    }
    $slugs = [];
    for ($i = 0; $i < 2; $i++) {
        $slug = tournament_generate_slug('Coppa Estate', $directory, [$model, 'fileSlugExists']);
        $slugs[] = $slug;
        identity_expect($model->crea('Coppa Estate', 'programmato', '2026-11-09', '2026-11-10',
            $slug . '.php', 'Calcio a 5', 'calcio', null, 0, []), 'Same-name tournament must be created');
        identity_expect($model->fileSlugExists($slug), 'Generated file is reserved in the database');
        identity_expect($model->getBySlug($slug)['nome'] === 'Coppa Estate', 'Distinct page resolves to its tournament');
    }
    identity_expect($slugs[0] !== $slugs[1], 'Same display name gets separate file identities');
    identity_expect($db->query('SELECT COUNT(*) n FROM tornei')->fetch_assoc()['n'] === '3', 'All same-name tournaments retained');
    identity_expect($db->query('SELECT * FROM tornei WHERE id=1')->fetch_assoc() === $legacy, 'Existing tournament unchanged');
    $indexes = $db->query('SHOW INDEX FROM tornei')->fetch_all(MYSQLI_ASSOC);
    foreach (['PRIMARY'=>0, 'file_identity'=>0, 'legacy_name'=>1, 'name_section'=>1] as $key=>$nonUnique) {
        $rows = array_values(array_filter($indexes, static fn($row)=>$row['Key_name'] === $key));
        identity_expect($rows && (int)$rows[0]['Non_unique'] === $nonUnique, 'Only display-name uniqueness changes: ' . $key);
    }
    $calls = 0;
    $slug = tournament_generate_slug('Coppa città / 2026', $directory, function($candidate) use (&$calls, $directory) {
        $calls++;
        if ($calls === 1) return true; // Existing database identity.
        if ($calls === 2) file_put_contents($directory . '/script-' . $candidate . '.js', 'existing');
        return false;
    });
    identity_expect($calls === 3 && preg_match('/^coppa-[a-z-]+-2026-[a-f0-9]{16}$/', $slug) === 1,
        'Safe readable file names retry both database and filesystem collisions: ' . $slug . ' (attempts ' . $calls . ')');
    identity_expect(str_starts_with(tournament_generate_slug('!!!', $directory, fn()=>false), 'torneo-'), 'Fallback for non-letter names');
    $source = file_get_contents(__DIR__ . '/../api/gestione_tornei.php');
    $createForm = explode('<!-- FORM MODIFICA', explode('<!-- FORM CREAZIONE -->', $source)[1])[0];
    identity_expect(!str_contains(explode('</form>', $createForm)[0], 'name="filetorneo"'), 'Creation requires no manual file field');
    echo "Tournament identity: duplicate names, legacy migration, distinct files and collision checks passed.\n";
} finally {
    $db->query("DROP DATABASE `{$database}`");
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
