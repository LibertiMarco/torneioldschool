<?php
header('Content-Type: application/json; charset=utf-8');

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';

if (!$conn || $conn->connect_error) {
  echo json_encode(["error" => "Connessione fallita: " . $conn->connect_error]);
  exit();
}

$conn->set_charset("utf8mb4");

// --- PARAMETRO TORNEO ---
$torneo = isset($_GET['torneo']) ? $conn->real_escape_string($_GET['torneo']) : '';

if (empty($torneo)) {
  echo json_encode(["error" => "Parametro 'torneo' mancante"]);
  exit();
}

// --- QUERY CLASSIFICA ---
$sql = "
  SELECT nome, torneo, logo, punti, giocate, vinte, pareggiate, perse, 
         gol_fatti, gol_subiti, differenza_reti
  FROM squadre
  WHERE torneo = '$torneo'
  ORDER BY punti DESC, differenza_reti DESC, gol_fatti DESC, gol_subiti ASC, nome ASC
";

$result = $conn->query($sql);

if (!$result) {
  echo json_encode(["error" => "Errore nella query: " . $conn->error]);
  exit();
}

$classifica = [];
while ($row = $result->fetch_assoc()) {
  $classifica[] = $row;
}

echo json_encode($classifica, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$conn->close();
?>
