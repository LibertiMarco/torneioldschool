<?php
header("Content-Type: application/json; charset=UTF-8");
// Evita che warning/notices sporchino il JSON
ini_set('display_errors', '0');

require_once __DIR__ . '/../includi/db.php';

function respondError(string $msg, int $code = 500): void {
  http_response_code($code);
  echo json_encode(["error" => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $torneo = $_GET['torneo'] ?? '';
  $fase = strtoupper($_GET['fase'] ?? '');
  $idPartita = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $fasiAmmesse = ['REGULAR','GOLD','SILVER'];
  if(!$torneo && $idPartita <= 0){ respondError("Parametro 'torneo' mancante.", 400); }

  // Se non arriva il torneo ma arriva l'id, recuperalo dalla partita
  if (!$torneo && $idPartita > 0) {
    $tmp = $conn->prepare("SELECT torneo FROM partite WHERE id=? LIMIT 1");
    if ($tmp) {
      $tmp->bind_param("i", $idPartita);
      if ($tmp->execute()) {
        $resTmp = $tmp->get_result();
        $rowTmp = $resTmp->fetch_assoc();
        $torneo = $rowTmp['torneo'] ?? '';
      }
      $tmp->close();
    }
    if (!$torneo) { respondError("Torneo non trovato per la partita indicata.", 404); }
  }

  $logoMap = [];
  $logoStmt = $conn->prepare("SELECT nome, logo FROM squadre WHERE torneo=?");
  if (!$logoStmt) {
    respondError("Errore lettura loghi: " . $conn->error);
  }
  $logoStmt->bind_param("s", $torneo);
  if (!$logoStmt->execute()) {
    respondError("Errore esecuzione loghi: " . $logoStmt->error);
  }
  $logoRes = $logoStmt->get_result();
  while ($logoRow = $logoRes->fetch_assoc()) {
    $logoMap[$logoRow['nome']] = $logoRow['logo'];
  }
  $logoStmt->close();

  $query = "SELECT * FROM partite WHERE torneo=?";
  $types = "s";
  $params = [$torneo];
  if ($idPartita > 0) {
    $query .= " AND id=?";
    $types .= "i";
    $params[] = $idPartita;
  }
  if ($fase && in_array($fase, $fasiAmmesse, true)) {
    $query .= " AND fase=?";
    $types .= "s";
    $params[] = $fase;
  }
  $query .= " ORDER BY 
    CASE WHEN fase = 'REGULAR' THEN COALESCE(giornata, 0) ELSE 999 END,
    data_partita ASC,
    ora_partita ASC";

  $st = $conn->prepare($query);
  if (!$st) {
    respondError("Errore preparazione query partite: " . $conn->error);
  }
  $st->bind_param($types, ...$params);
  if (!$st->execute()) {
    respondError("Errore esecuzione query partite: " . $st->error);
  }
  $r = $st->get_result();

  $giornate=[];
  $lista = [];
  while($row=$r->fetch_assoc()){
    $record = [
      "id"=>$row['id'],
      "squadra_casa"=>$row['squadra_casa'],
      "squadra_ospite"=>$row['squadra_ospite'],
      "logo_casa"=>$logoMap[$row['squadra_casa']] ?? null,
      "logo_ospite"=>$logoMap[$row['squadra_ospite']] ?? null,
      "gol_casa"=>$row['gol_casa'],
      "gol_ospite"=>$row['gol_ospite'],
      "data_partita"=>$row['data_partita'],
      "ora_partita"=>$row['ora_partita'],
      "campo"=>$row['campo'],
      "giornata"=>$row['giornata'],
      "fase"=>$row['fase'],
      "fase_round"=>null,
      "fase_leg"=>null,
      "arbitro"=>$row['arbitro'] ?? '',
      "link_youtube"=>$row['link_youtube'] ?? null,
      "link_instagram"=>$row['link_instagram'] ?? null,
      "giocata"=>$row['giocata'] ?? 0
    ];

    $lista[] = $record;
    $key = $row['giornata'];
    if ($key === null) {
      $key = 0;
    }
    if(!isset($giornate[$key])) $giornate[$key]=[];
    $giornate[$key][]=$record;
  }

  if ($idPartita > 0) {
    echo json_encode($lista, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
  }

  echo json_encode($giornate, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
} catch (Throwable $e) {
  respondError("Errore interno: " . $e->getMessage());
}
