<?php
require __DIR__ . '/../../includi/db.php'; // 🔹 aggiorna il percorso se serve

// ✅ Controllo parametri
if (!isset($_GET['torneo']) || empty($_GET['torneo'])) {
    die("❌ Errore: specifica il torneo con ?torneo=NomeTorneo");
}

if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    die("⚠️ Conferma richiesta. Aggiungi '&confirm=yes' all'URL per eseguire il reset.");
}

$torneo = $_GET['torneo'];

// 🔹 Query per azzerare solo le squadre del torneo scelto
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
$stmt->bind_param("s", $torneo);

if ($stmt->execute()) {
    echo "✅ Classifica del torneo '{$torneo}' azzerata con successo!";
} else {
    echo "❌ Errore durante il reset: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
