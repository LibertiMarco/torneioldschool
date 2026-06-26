<?php
require_once __DIR__ . '/../../includi/security.php';

if (!tos_is_cli()) {
    http_response_code(403);
    exit('Accesso consentito solo da CLI.');
}

require __DIR__ . '/../../includi/db.php';

$torneo = trim((string)($argv[1] ?? ''));
if ($torneo === '') {
    exit("Errore: specifica il torneo. Uso: php api/script/reset_classifica.php torneo_slug\n");
}

$sql = "
    UPDATE squadre
    SET
        giocate = 0,
        vinte = 0,
        pareggiate = 0,
        perse = 0,
        punti = 0,
        gol_fatti = 0,
        gol_subiti = 0,
        differenza_reti = 0
    WHERE torneo = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    fwrite(STDERR, "Errore prepare: " . $conn->error . "\n");
    exit(1);
}

$stmt->bind_param("s", $torneo);

if ($stmt->execute()) {
    echo "Classifica del torneo '{$torneo}' azzerata con successo.\n";
} else {
    fwrite(STDERR, "Errore durante il reset: " . $stmt->error . "\n");
    exit(1);
}

$stmt->close();
$conn->close();
