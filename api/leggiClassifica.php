<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30, stale-while-revalidate=300');

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/api_cache.php';

if (!$conn || $conn->connect_error) {
  echo json_encode(["error" => "Connessione fallita: " . $conn->connect_error]);
  exit();
}

$conn->set_charset("utf8mb4");

// --- PARAMETRO TORNEO ---
$torneo = trim((string)($_GET['torneo'] ?? ''));

if (empty($torneo)) {
  echo json_encode(["error" => "Parametro 'torneo' mancante"]);
  exit();
}

$cacheKey = tos_api_cache_build_key('leggiClassifica', ['torneo' => $torneo]);
$cachedPayload = tos_api_cache_read($cacheKey, 30);
if ($cachedPayload !== null) {
  echo $cachedPayload;
  exit();
}

// --- QUERY CLASSIFICA ---
$sql = "
  SELECT id, nome, torneo, logo, punti, giocate, vinte, pareggiate, perse,
         gol_fatti, gol_subiti, differenza_reti, girone
  FROM squadre
  WHERE torneo = ?
  ORDER BY punti DESC, differenza_reti DESC, gol_fatti DESC, gol_subiti ASC, nome ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  $sqlFallback = "
    SELECT id, nome, torneo, logo, punti, giocate, vinte, pareggiate, perse,
           gol_fatti, gol_subiti, differenza_reti, NULL AS girone
    FROM squadre
    WHERE torneo = ?
    ORDER BY punti DESC, differenza_reti DESC, gol_fatti DESC, gol_subiti ASC, nome ASC
  ";
  $stmt = $conn->prepare($sqlFallback);
}

if (!$stmt) {
  echo json_encode(["error" => "Errore nella query: " . $conn->error]);
  exit();
}

$stmt->bind_param('s', $torneo);
if (!$stmt->execute()) {
  $stmt->close();
  echo json_encode(["error" => "Errore nella query: " . $conn->error]);
  exit();
}

$result = $stmt->get_result();
$classifica = [];
while ($row = $result->fetch_assoc()) {
  $classifica[] = $row;
}

$stmt->close();
$payload = json_encode($classifica, JSON_UNESCAPED_UNICODE);
if ($payload !== false) {
  tos_api_cache_write($cacheKey, $payload);
  echo $payload;
} else {
  echo json_encode($classifica, JSON_UNESCAPED_UNICODE);
}
$conn->close();
?>
