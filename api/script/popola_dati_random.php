<?php
require_once __DIR__ . '/../../includi/db.php';

// --- DATI DI BASE ---
$nomi = ['Marco', 'Luca', 'Giovanni', 'Andrea', 'Francesco', 'Mattia', 'Simone', 'Alessio', 'Daniele', 'Federico'];
$cognomi = ['Rossi', 'Bianchi', 'Verdi', 'Russo', 'Ferrari', 'Esposito', 'Romano', 'Conti', 'Gallo', 'Costa'];
$ruoli = ['Portiere', 'Difensore', 'Centrocampista', 'Attaccante'];
$campi = ['Sporting Club San Francesco, Napoli', 'Centro Sportivo La Paratina, Napoli', 'Sporting S.Antonio, Napoli', 'La Boutique del Calcio, Napoli'];

// --- INSERISCI SQUADRE ---
$squadre = ['Atalanta','Bologna','Cagliari','Como','Cremonese','Fiorentina','Genoa','Inter','Juventus','Lazio','Lecce','Milan','Napoli','Parma','Pisa','Roma','Sassuolo','Torino','Udinese','Verona'];
$torneo = "SerieA";

// Inserisci squadre
$stmt_squadra = $conn->prepare("INSERT INTO squadre (nome, torneo) VALUES (?, ?)");
foreach ($squadre as $sq) {
    $stmt_squadra->bind_param("ss", $sq, $torneo);
    $stmt_squadra->execute();
}
$stmt_squadra->close();
echo "✅ Inserite " . count($squadre) . " squadre<br>";

// --- INSERISCI ROSE ---
$stmt_giocatore = $conn->prepare("INSERT INTO giocatori (nome, cognome, ruolo) VALUES (?, ?, ?)");
foreach ($squadre as $sq) {
    for ($i = 0; $i < 10; $i++) { // 10 giocatori per squadra
        $nome = $nomi[array_rand($nomi)];
        $cognome = $cognomi[array_rand($cognomi)];
        $ruolo = $ruoli[array_rand($ruoli)];
        $stmt_giocatore->bind_param("sss", $nome, $cognome, $ruolo);
        $stmt_giocatore->execute();
    }
}
$stmt_giocatore->close();
echo "✅ Inseriti " . (count($squadre) * 10) . " giocatori<br>";

// --- CREA PARTITE CASUALI ---
$stmt_partita = $conn->prepare("
    INSERT INTO partite (squadra_casa, squadra_ospite, gol_casa, gol_ospite, data_partita, ora_partita, campo, giornata)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$giornata = 1;
foreach ($squadre as $i => $squadra_casa) {
    for ($j = $i + 1; $j < count($squadre); $j++) {
        $squadra_ospite = $squadre[$j];
        $gol_casa = rand(0, 5);
        $gol_ospite = rand(0, 5);
        $data = date('Y-m-d', strtotime("+$giornata days"));
        $ora = sprintf('%02d:%02d:00', rand(14, 21), rand(0, 59));
        $campo = $campi[array_rand($campi)];
        $stmt_partita->bind_param("ssiisssi", $squadra_casa, $squadra_ospite, $gol_casa, $gol_ospite, $data, $ora, $campo, $giornata);
        $stmt_partita->execute();
        $giornata++;
    }
}
$stmt_partita->close();

echo "✅ Inserite partite casuali per tutte le squadre<br>";

$conn->close();
?>
