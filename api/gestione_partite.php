<?php
require_once __DIR__ . '/../includi/admin_guard.php';

require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/partite_schema.php';
require_once __DIR__ . '/../includi/push_notifications.php';
require_once __DIR__ . '/../includi/torneo_phase_rules.php';

$errore = '';
$successo = '';
$azione = $_POST['azione'] ?? '';
$torneiDisponibili = [];
$squadrePerTorneo = [];
$campiPartita = [];
$fasiAmmesse = ['REGULAR', 'GOLD', 'SILVER', 'BRONZO'];
$fasiAmmesseConBronzo = ['REGULAR', 'GOLD', 'SILVER', 'BRONZO'];
$fasiAmmesseConSpareggio = ['REGULAR', 'SPAREGGIO', 'GOLD', 'SILVER', 'BRONZO'];
$maxGiornateRegular = 10;
$maxGiornateRegularOverrides = [
  'McLeague' => 14,
];
$roundMap = [
  'TRENTADUESIMI' => 6,
  'SEDICESIMI' => 5,
  'OTTAVI' => 4,
  'QUARTI' => 3,
  'SEMIFINALE' => 2,
  'FINALE' => 1,
];

$torneiHasSection = false;
$checkTorneoSection = @$conn->query("SHOW COLUMNS FROM tornei LIKE 'sezione'");
if ($checkTorneoSection && $checkTorneoSection->num_rows > 0) {
  $torneiHasSection = true;
}

$torneiSelectSql = $torneiHasSection
  ? "SELECT nome, filetorneo, sezione FROM tornei WHERE stato <> 'terminato' ORDER BY nome ASC"
  : "SELECT nome, filetorneo FROM tornei WHERE stato <> 'terminato' ORDER BY nome ASC";
$torneiRes = $conn->query($torneiSelectSql);
if ($torneiRes) {
  while ($row = $torneiRes->fetch_assoc()) {
    $slug = preg_replace('/\.(html?|php)$/i', '', $row['filetorneo'] ?? '');
    $torneiDisponibili[] = [
      'nome' => $row['nome'] ?: $slug,
      'slug' => $slug,
      'sezione' => strtolower(trim((string)($row['sezione'] ?? 'calcio'))) === 'esport' ? 'esport' : 'calcio',
    ];
  }
}

if (!empty($torneiDisponibili)) {
  $slugs = array_column($torneiDisponibili, 'slug');
  $placeholders = implode(',', array_fill(0, count($slugs), '?'));
  $types = str_repeat('s', count($slugs));
  $sq = $conn->prepare("SELECT nome, torneo FROM squadre WHERE torneo IN ($placeholders) ORDER BY nome ASC");
  if ($sq) {
    $sq->bind_param($types, ...$slugs);
    $sq->execute();
    $resSq = $sq->get_result();
    while ($r = $resSq->fetch_assoc()) {
      $slug = $r['torneo'] ?? '';
      if (!isset($squadrePerTorneo[$slug])) {
        $squadrePerTorneo[$slug] = [];
      }
      $squadrePerTorneo[$slug][] = $r['nome'];
    }
    $sq->close();
  }
}

function sanitize_text(?string $v): string {
  return trim((string)$v);
}

function sanitize_int(?string $v): int {
  return (int)($v === '' || $v === null ? 0 : $v);
}

function sanitize_fase(?string $v, array $allowed): string {
  $val = strtoupper(trim((string)$v));
  return in_array($val, $allowed, true) ? $val : 'REGULAR';
}

function is_spareggio_phase(?string $fase): bool {
  return strtoupper(trim((string)$fase)) === 'SPAREGGIO';
}

function phase_uses_round(?string $fase): bool {
  $normalized = strtoupper(trim((string)$fase));
  return $normalized !== '' && $normalized !== 'REGULAR' && $normalized !== 'SPAREGGIO';
}

function sanitize_leg(?string $v): ?string {
  $val = strtoupper(trim((string)$v));
  $allowed = ['ANDATA','RITORNO','UNICA'];
  if (!$val) return null;
  return in_array($val, $allowed, true) ? $val : null;
}

function default_match_fields(): array {
  return [
    'Sporting Club San Francesco, Napoli',
    'Centro Sportivo La Paratina, Napoli',
    'Paratina (campo sopra)',
    'Paratina (campo giu)',
    'Sporting S.Antonio, Napoli',
    'La Boutique del Calcio, Napoli',
    "Gioventu' Partenope",
    'Complesso Kennedy, Napoli',
    'Campo Centrale del Parco Corto Maltese, Napoli',
  ];
}

function ensure_match_fields_table(mysqli $conn): void {
  $conn->query("
    CREATE TABLE IF NOT EXISTS campi_partite (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      nome VARCHAR(255) NOT NULL,
      sort_order INT UNSIGNED NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_campi_partite_nome (nome),
      KEY idx_campi_partite_order (sort_order, nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ");

  $countRes = $conn->query("SELECT COUNT(*) AS totale FROM campi_partite");
  $totale = 0;
  if ($countRes) {
    $row = $countRes->fetch_assoc();
    $totale = (int)($row['totale'] ?? 0);
  }
  if ($totale > 0) {
    return;
  }

  $valori = default_match_fields();
  $resCampiEsistenti = $conn->query("
    SELECT DISTINCT campo
    FROM partite
    WHERE campo IS NOT NULL AND TRIM(campo) <> ''
    ORDER BY campo ASC
  ");
  if ($resCampiEsistenti) {
    while ($row = $resCampiEsistenti->fetch_assoc()) {
      $nome = trim((string)($row['campo'] ?? ''));
      if ($nome === '' || in_array($nome, $valori, true)) {
        continue;
      }
      $valori[] = $nome;
    }
  }

  $stmt = $conn->prepare("INSERT INTO campi_partite (nome, sort_order) VALUES (?, ?)");
  if (!$stmt) {
    return;
  }

  foreach ($valori as $idx => $nome) {
    $sortOrder = $idx + 1;
    $stmt->bind_param('si', $nome, $sortOrder);
    $stmt->execute();
  }
  $stmt->close();
}

function get_match_fields(mysqli $conn): array {
  $items = [];
  $res = $conn->query("
    SELECT cp.id, cp.nome, cp.sort_order, COUNT(p.id) AS uso_totale
    FROM campi_partite cp
    LEFT JOIN partite p ON p.campo = cp.nome
    GROUP BY cp.id, cp.nome, cp.sort_order
    ORDER BY cp.sort_order ASC, cp.nome ASC
  ");
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $items[] = $row;
    }
  }
  return $items;
}

function render_match_field_options(array $campi, string $selected = ''): string {
  $html = '<option value="">-- Seleziona campo --</option>';
  $selectedFound = false;

  foreach ($campi as $campo) {
    $nome = trim((string)($campo['nome'] ?? ''));
    if ($nome === '') {
      continue;
    }
    $isSelected = $selected !== '' && strcmp($selected, $nome) === 0;
    if ($isSelected) {
      $selectedFound = true;
    }
    $escaped = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $html .= '<option value="' . $escaped . '"' . ($isSelected ? ' selected' : '') . '>' . $escaped . '</option>';
  }

  if ($selected !== '' && !$selectedFound) {
    $escapedSelected = htmlspecialchars($selected, ENT_QUOTES, 'UTF-8');
    $html .= '<option value="' . $escapedSelected . '" selected>' . $escapedSelected . ' (storico)</option>';
  }

  return $html;
}

function round_to_giornata(?string $roundLabel, array $map): ?int {
  if ($roundLabel === null) return null;
  $key = strtoupper(trim($roundLabel));
  return $map[$key] ?? null;
}

function round_supports_two_legs(?string $roundLabel): bool {
  if ($roundLabel === null) return false;
  return in_array(
    strtoupper(trim($roundLabel)),
    ['TRENTADUESIMI', 'SEDICESIMI', 'OTTAVI', 'QUARTI', 'SEMIFINALE'],
    true
  );
}

function giornata_to_roundLabel(?int $giornata, array $map): ?string {
  if ($giornata === null) return null;
  $flip = array_flip($map);
  return $flip[$giornata] ?? null;
}

// Assicura che le colonne/tabella di supporto notifiche esistano
ensure_notifiche_table($conn);
ensure_follow_table($conn);
ensure_partite_phase_schema($conn);
ensure_partita_giocatore_team_schema($conn);
ensure_partite_notifica_flag($conn);
ensure_partite_unique_index($conn);
ensure_rigori_columns($conn);
ensure_match_fields_table($conn);

// ==== NOTIFICHE ====
function ensure_notifiche_table(mysqli $conn): void {
  $conn->query("
    CREATE TABLE IF NOT EXISTS notifiche (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      utente_id INT UNSIGNED NOT NULL,
      tipo VARCHAR(50) NOT NULL DEFAULT 'generic',
      titolo VARCHAR(255) NOT NULL,
      testo TEXT NULL,
      link VARCHAR(255) DEFAULT NULL,
      letto TINYINT(1) NOT NULL DEFAULT 0,
      creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_notifiche_user (utente_id, letto, creato_il),
      CONSTRAINT fk_notifiche_user FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ");
}

function ensure_partite_notifica_flag(mysqli $conn): void {
  $col = $conn->query("SHOW COLUMNS FROM partite LIKE 'notifica_esito_inviata'");
  if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE partite ADD COLUMN notifica_esito_inviata TINYINT(1) NOT NULL DEFAULT 0");
  }
}

// Vincolo di unicità per evitare duplicati nello stesso turno
function ensure_partite_unique_index(mysqli $conn): void {
  $idx = $conn->query("SHOW INDEX FROM partite WHERE Key_name = 'uq_partita_turno'");
  if ($idx && $idx->num_rows === 0) {
    $conn->query("CREATE UNIQUE INDEX uq_partita_turno ON partite (torneo, fase, giornata, squadra_casa, squadra_ospite)");
  }
}

function ensure_rigori_columns(mysqli $conn): void {
  $colDecisa = $conn->query("SHOW COLUMNS FROM partite LIKE 'decisa_rigori'");
  if ($colDecisa && $colDecisa->num_rows === 0) {
    $conn->query("ALTER TABLE partite ADD COLUMN decisa_rigori TINYINT(1) NOT NULL DEFAULT 0 AFTER campo");
  }
  $colRigCasa = $conn->query("SHOW COLUMNS FROM partite LIKE 'rigori_casa'");
  if ($colRigCasa && $colRigCasa->num_rows === 0) {
    $conn->query("ALTER TABLE partite ADD COLUMN rigori_casa INT DEFAULT NULL AFTER decisa_rigori");
  }
  $colRigOsp = $conn->query("SHOW COLUMNS FROM partite LIKE 'rigori_ospite'");
  if ($colRigOsp && $colRigOsp->num_rows === 0) {
    $conn->query("ALTER TABLE partite ADD COLUMN rigori_ospite INT DEFAULT NULL AFTER rigori_casa");
  }
}

function ensure_follow_table(mysqli $conn): void {
  $conn->query("
    CREATE TABLE IF NOT EXISTS seguiti (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      utente_id INT UNSIGNED NOT NULL,
      tipo ENUM('torneo','squadra') NOT NULL,
      torneo_slug VARCHAR(255) NOT NULL,
      squadra_nome VARCHAR(255) DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_follow (utente_id, tipo, torneo_slug, squadra_nome),
      INDEX idx_follow_user (utente_id, tipo),
      INDEX idx_follow_torneo (torneo_slug, tipo),
      CONSTRAINT fk_follow_user FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ");
}

function normalize_torneo(string $torneo): string {
  return trim($torneo);
}

function reset_classifica(mysqli $conn, string $torneo): void {
  $torneo = normalize_torneo($torneo);
  if ($torneo === '') return;
  $stmt = $conn->prepare("
    UPDATE squadre
    SET giocate = 0, vinte = 0, pareggiate = 0, perse = 0,
        punti = 0, gol_fatti = 0, gol_subiti = 0, differenza_reti = 0
    WHERE torneo = ?
  ");
  if ($stmt) {
    $stmt->bind_param('s', $torneo);
    $stmt->execute();
    $stmt->close();
  }
}

function normalize_team_name(string $name): string {
  $name = trim($name);
  $name = preg_replace('/\s+/', ' ', $name);
  return $name;
}

function applica_risultato_classifica_by_id(mysqli $conn, int $squadraId, int $gf, int $gs): void {
  if ($squadraId <= 0) return;

  $vittoria = $gf > $gs ? 1 : 0;
  $pareggio = $gf === $gs ? 1 : 0;
  $sconfitta = $gf < $gs ? 1 : 0;
  $punti = $vittoria ? 3 : ($pareggio ? 1 : 0);
  $stmt = $conn->prepare("
    UPDATE squadre
    SET giocate = giocate + 1,
        vinte = vinte + ?,
        pareggiate = pareggiate + ?,
        perse = perse + ?,
        punti = punti + ?,
        gol_fatti = gol_fatti + ?,
        gol_subiti = gol_subiti + ?,
        differenza_reti = gol_fatti - gol_subiti
    WHERE id = ?
    LIMIT 1
  ");
  if ($stmt) {
    $stmt->bind_param(
      'iiiiiii',
      $vittoria,
      $pareggio,
      $sconfitta,
      $punti,
      $gf,
      $gs,
      $squadraId
    );
    $stmt->execute();
    $stmt->close();
  }
}

/**
 * Imposta come "giocata" ogni partita che ha giÇÿ un risultato salvato o statistiche,
 * cosÇÿ da non perdere i match nella ricostruzione della classifica.
 */
function marca_partite_giocate_da_score(mysqli $conn, string $torneo): void {
  // Le statistiche giocatore non devono attivare automaticamente il flag "giocata".
  if ($torneo === '') return;
  $sql = "
    UPDATE partite p
    SET giocata = 1
    WHERE p.torneo = ?
      AND p.giocata = 0
      AND (p.gol_casa IS NOT NULL OR p.gol_ospite IS NOT NULL)
  ";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param('s', $torneo);
    $stmt->execute();
    $stmt->close();
  }
}

function ricostruisci_classifica_da_partite(mysqli $conn, string $torneo): void {
  $torneo = normalize_torneo($torneo);
  if ($torneo === '') return;
  reset_classifica($conn, $torneo);
  $sel = $conn->prepare("
    SELECT sc.id AS id_casa,
           so.id AS id_osp,
           COALESCE(p.gol_casa,0)   AS gol_casa,
           COALESCE(p.gol_ospite,0) AS gol_ospite
    FROM partite p
    INNER JOIN squadre sc ON sc.nome = p.squadra_casa AND sc.torneo = ?
    INNER JOIN squadre so ON so.nome = p.squadra_ospite AND so.torneo = ?
    WHERE p.torneo = ?
      AND p.giocata = 1
      AND UPPER(
            CASE
              WHEN TRIM(COALESCE(p.fase, '')) IN ('', 'GIRONE') THEN 'REGULAR'
              ELSE TRIM(COALESCE(p.fase, ''))
            END
          ) = 'REGULAR'
  ");
  if (!$sel) return;
  $sel->bind_param('sss', $torneo, $torneo, $torneo);
  if ($sel->execute()) {
    $res = $sel->get_result();
    while ($row = $res->fetch_assoc()) {
      $idCasa = (int)($row['id_casa'] ?? 0);
      $idOsp  = (int)($row['id_osp'] ?? 0);
      applica_risultato_classifica_by_id($conn, $idCasa, (int)$row['gol_casa'], (int)$row['gol_ospite']);
      applica_risultato_classifica_by_id($conn, $idOsp, (int)$row['gol_ospite'], (int)$row['gol_casa']);
    }
  }
  $sel->close();
}

function get_utenti_per_squadre(mysqli $conn, string $torneo, array $squadre): array {
  if (empty($squadre)) return [];
  $place = implode(',', array_fill(0, count($squadre), '?'));
  $types = str_repeat('s', count($squadre) + 1); // squadre + torneo
  $sql = "
    SELECT DISTINCT g.utente_id
    FROM squadre s
    JOIN squadre_giocatori sg ON sg.squadra_id = s.id
    JOIN giocatori g ON g.id = sg.giocatore_id
    WHERE s.torneo = ? AND s.nome IN ($place) AND g.utente_id IS NOT NULL
  ";
  $params = array_merge([$torneo], $squadre);
  $stmt = $conn->prepare($sql);
  if (!$stmt) return [];
  $stmt->bind_param($types, ...$params);
  $ids = [];
  if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $ids[] = (int)$row['utente_id'];
    }
  }
  $stmt->close();
  return array_values(array_unique($ids));
}

function get_followers_torneo(mysqli $conn, string $torneo): array {
  ensure_follow_table($conn);
  $stmt = $conn->prepare("SELECT utente_id FROM seguiti WHERE tipo = 'torneo' AND torneo_slug = ?");
  if (!$stmt) return [];
  $stmt->bind_param('s', $torneo);
  $ids = [];
  if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $ids[] = (int)$row['utente_id'];
  }
  $stmt->close();
  return $ids;
}

function get_followers_squadre(mysqli $conn, string $torneo, array $squadre): array {
  if (empty($squadre)) return [];
  ensure_follow_table($conn);
  $place = implode(',', array_fill(0, count($squadre), '?'));
  $types = 's' . str_repeat('s', count($squadre));
  $sql = "
    SELECT utente_id FROM seguiti
    WHERE tipo = 'squadra' AND torneo_slug = ? AND squadra_nome IN ($place)
  ";
  $params = array_merge([$torneo], $squadre);
  $stmt = $conn->prepare($sql);
  if (!$stmt) return [];
  $stmt->bind_param($types, ...$params);
  $ids = [];
  if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $ids[] = (int)$row['utente_id'];
  }
  $stmt->close();
  return $ids;
}

function aggiornaStatsGiocatoreGlobale(mysqli $conn, int $giocatoreId): void {
  torneo_stats_rebuild_player_global_aggregate($conn, $giocatoreId);
}

function aggiornaStatsGiocatoreSquadra(mysqli $conn, int $giocatoreId, int $squadraId): void {
  torneo_stats_rebuild_player_team_aggregate($conn, $giocatoreId, $squadraId);
}

function ricalcolaGiocatoriPartita(mysqli $conn, array $giocatoriIds, array $partitaInfo): void {
  if (empty($giocatoriIds) || empty($partitaInfo['torneo'] ?? '')) return;
  $teams = array_values(array_filter([
    $partitaInfo['squadra_casa'] ?? '',
    $partitaInfo['squadra_ospite'] ?? '',
  ]));

  foreach ($giocatoriIds as $gid) {
    $gid = (int)$gid;
    if ($gid <= 0) continue;
    aggiornaStatsGiocatoreGlobale($conn, $gid);
    if (empty($teams)) continue;
    $placeholders = implode(',', array_fill(0, count($teams), '?'));
    $types = 'is' . str_repeat('s', count($teams));
    $sql = "
      SELECT sg.squadra_id
      FROM squadre_giocatori sg
      JOIN squadre s ON s.id = sg.squadra_id
      WHERE sg.giocatore_id = ?
        AND s.torneo = ?
        AND s.nome IN ($placeholders)
    ";
    $params = array_merge([$gid, $partitaInfo['torneo']], $teams);
    $teamStmt = $conn->prepare($sql);
    if (!$teamStmt) continue;
    $teamStmt->bind_param($types, ...$params);
    if ($teamStmt->execute()) {
      $res = $teamStmt->get_result();
      while ($row = $res->fetch_assoc()) {
        aggiornaStatsGiocatoreSquadra($conn, $gid, (int)$row['squadra_id']);
      }
    }
    $teamStmt->close();
  }
}

function push_notifica_users(mysqli $conn, array $userIds, string $tipo, string $titolo, string $testo, string $link = ''): void {
  tos_push_store_notifications_for_users(
    $conn,
    $userIds,
    $tipo,
    $titolo,
    $testo,
    $link,
    ['tag' => 'match-' . preg_replace('/[^a-z0-9_-]/i', '-', $tipo)]
  );
}

function inviaNotificaEsito(mysqli $conn, int $partitaId, ?array $info = null): void {
  ensure_partite_notifica_flag($conn);

  $meta = $conn->prepare("SELECT torneo, fase, squadra_casa, squadra_ospite, gol_casa, gol_ospite, giocata, notifica_esito_inviata, data_partita, ora_partita FROM partite WHERE id=?");
  if (!$meta) return;
  $meta->bind_param('i', $partitaId);
  $meta->execute();
  $row = $meta->get_result()->fetch_assoc();
  $meta->close();
  if (!$row) return;

  $giocata = (int)($row['giocata'] ?? 0) === 1;
  $giaNotificata = (int)($row['notifica_esito_inviata'] ?? 0) === 1;
  if (!$giocata || $giaNotificata) return;

  $torneo = $info['torneo'] ?? $row['torneo'];
  $casa = $info['squadra_casa'] ?? $row['squadra_casa'];
  $osp = $info['squadra_ospite'] ?? $row['squadra_ospite'];
  $golCasa = $info['gol_casa'] ?? (int)($row['gol_casa'] ?? 0);
  $golOsp = $info['gol_ospite'] ?? (int)($row['gol_ospite'] ?? 0);
  $matchLabel = $casa . ' - ' . $osp;
  $scoreLabel = $golCasa . ' - ' . $golOsp;
  $whenLabel = trim(($row['data_partita'] ?? '') . ' ' . ($row['ora_partita'] ?? ''));
  $matchLink = '/tornei/partita_eventi.php?id=' . $partitaId . '&torneo=' . rawurlencode($torneo);

  $uids = array_unique(array_merge(
    get_utenti_per_squadre($conn, $torneo, [$casa, $osp]),
    get_followers_torneo($conn, $torneo),
    get_followers_squadre($conn, $torneo, [$casa, $osp])
  ));

  if (!empty($uids)) {
    push_notifica_users(
      $conn,
      $uids,
      'match_finale',
      'Risultato finale',
      $matchLabel . ' | ' . $scoreLabel . ($whenLabel ? ' | ' . $whenLabel : ''),
      $matchLink
    );
  }

  $upd = $conn->prepare("UPDATE partite SET notifica_esito_inviata = 1 WHERE id = ?");
  if ($upd) {
    $upd->bind_param('i', $partitaId);
    $upd->execute();
    $upd->close();
  }
}

// ===== OPERAZIONI CRUD =====
function squadraHaGiaPartita(
  $conn,
  $torneo,
  $fase,
  $giornata,
  $casa,
  $ospite,
  $excludeId = null,
  $fase_round = null,
  $fase_leg = null
) {
  $sql = "
    SELECT id, squadra_casa, squadra_ospite, fase_round, fase_leg
    FROM partite
    WHERE torneo = ?
      AND UPPER(TRIM(COALESCE(fase, ''))) = ?
      AND giornata = ?
      AND (squadra_casa = ? OR squadra_ospite = ? OR squadra_casa = ? OR squadra_ospite = ?)
  ";
  $faseNormalized = strtoupper(trim((string)$fase));
  $types = "ssissss";
  $params = [$torneo, $faseNormalized, $giornata, $casa, $casa, $ospite, $ospite];
  if ($excludeId !== null) {
    $sql .= " AND id <> ?";
    $types .= "i";
    $params[] = $excludeId;
  }

  $stmt = $conn->prepare($sql);
  if (!$stmt) return false;
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

  $targetRound = strtoupper($fase_round ?? '');
  $targetLeg = strtoupper($fase_leg ?? '');

  while ($row = $res->fetch_assoc()) {
    $rowRound = strtoupper($row['fase_round'] ?? '');
    $rowLeg = strtoupper($row['fase_leg'] ?? '');
    $samePair = (
      strcasecmp($row['squadra_casa'], $casa) === 0 && strcasecmp($row['squadra_ospite'], $ospite) === 0
    ) || (
      strcasecmp($row['squadra_casa'], $ospite) === 0 && strcasecmp($row['squadra_ospite'], $casa) === 0
    );

    // consenti andata/ritorno sullo stesso accoppiamento per i turni a doppia sfida
    $isTwoLegTarget = round_supports_two_legs($targetRound);
    $isTwoLegRow = round_supports_two_legs($rowRound);
    $hasLegPair = in_array($targetLeg, ['ANDATA', 'RITORNO'], true) && in_array($rowLeg, ['ANDATA', 'RITORNO'], true);
    if ($isTwoLegTarget && $isTwoLegRow && $targetRound === $rowRound && $samePair && $hasLegPair) {
      if ($rowLeg !== $targetLeg) {
        continue;
      }
    }

    $stmt->close();
    return true;
  }

  $stmt->close();
  return false;
}

function partitaRegularGiaInseritaInAltraGiornata(
  mysqli $conn,
  string $torneo,
  string $fase,
  int $giornata,
  string $casa,
  string $ospite,
  ?int $excludeId = null
): bool {
  if (strcasecmp(trim($fase), 'REGULAR') !== 0) {
    return false;
  }

  if (strcasecmp(trim($torneo), 'McLeague') === 0) {
    return false;
  }

  if ($giornata <= 0 || $casa === '' || $ospite === '') {
    return false;
  }

  $sql = "
    SELECT id
    FROM partite
    WHERE torneo = ?
      AND UPPER(TRIM(COALESCE(fase, ''))) IN ('', 'REGULAR', 'GIRONE')
      AND giornata <> ?
      AND (
        (squadra_casa = ? AND squadra_ospite = ?)
        OR
        (squadra_casa = ? AND squadra_ospite = ?)
      )
  ";
  $types = 'sissss';
  $params = [$torneo, $giornata, $casa, $ospite, $ospite, $casa];

  if ($excludeId !== null) {
    $sql .= " AND id <> ?";
    $types .= 'i';
    $params[] = $excludeId;
  }

  $sql .= " LIMIT 1";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }

  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $found = $res && $res->num_rows > 0;
  $stmt->close();

  return $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($azione === 'crea') {
    $torneo = sanitize_text($_POST['torneo'] ?? '');
    $fase = sanitize_fase($_POST['fase'] ?? '', $fasiAmmesseConSpareggio);
    $casa = sanitize_text($_POST['squadra_casa'] ?? '');
    $ospite = sanitize_text($_POST['squadra_ospite'] ?? '');
    $data = sanitize_text($_POST['data_partita'] ?? '');
    $ora = sanitize_text($_POST['ora_partita'] ?? '');
    $campo = sanitize_text($_POST['campo'] ?? '');
    $roundSelezionato = sanitize_text($_POST['round_eliminazione'] ?? '');
    $giornata = sanitize_int($_POST['giornata'] ?? '');
    $faseUsesRound = phase_uses_round($fase);
    $isSpareggio = is_spareggio_phase($fase);
    $faseRound = $faseUsesRound ? strtoupper($roundSelezionato) : null;
    $faseRound = ($faseRound && in_array($faseRound, ['TRENTADUESIMI','SEDICESIMI','OTTAVI','QUARTI','SEMIFINALE','FINALE'], true)) ? $faseRound : null;
    $faseLegInput = $faseUsesRound ? sanitize_leg($_POST['fase_leg'] ?? '') : null;
    $faseLeg = $faseUsesRound
      ? ($faseLegInput ?: (round_supports_two_legs($faseRound) ? 'ANDATA' : 'UNICA'))
      : ($isSpareggio ? 'UNICA' : null);
    $giocata = 0; // sempre non giocata alla creazione
    $gol_casa = sanitize_int($_POST['gol_casa'] ?? '0');
    $gol_ospite = sanitize_int($_POST['gol_ospite'] ?? '0');
    $link_youtube = sanitize_text($_POST['link_youtube'] ?? '');
    $link_instagram = sanitize_text($_POST['link_instagram'] ?? '');
    $arbitro = sanitize_text($_POST['arbitro'] ?? '');
    $decisa_rigori = isset($_POST['decisa_rigori']) ? 1 : 0;
    $rigori_casa = $decisa_rigori ? sanitize_int($_POST['rigori_casa'] ?? '') : null;
    $rigori_ospite = $decisa_rigori ? sanitize_int($_POST['rigori_ospite'] ?? '') : null;

    if ($isSpareggio) {
      $giornata = 1;
    }

    if ($torneo === '' || $fase === '' || $casa === '' || $ospite === '' || $data === '' || $ora === '' || $campo === '' || ($fase === 'REGULAR' && $giornata <= 0) || ($faseUsesRound && $roundSelezionato === '')) {
      $errore = 'Compila tutti i campi obbligatori.';
    } elseif ($casa === $ospite) {
      $errore = 'Le due squadre non possono coincidere.';
    } elseif ($faseUsesRound && !$faseLeg) {
      $errore = 'Seleziona il tipo di gara (andata/ritorno/unica).';
    } else {
      if ($faseUsesRound) {
        $giornata = round_to_giornata($roundSelezionato, $roundMap) ?? 0;
      } elseif ($isSpareggio) {
        $giornata = 1;
      }
      // controllo: una squadra non può avere due partite nella stessa giornata della stessa fase
      if (squadraHaGiaPartita($conn, $torneo, $fase, $giornata, $casa, $ospite, null, $faseRound, $faseLeg)) {
        $errore = 'Una delle squadre ha già una partita in questa giornata per questa fase.';
      } elseif (partitaRegularGiaInseritaInAltraGiornata($conn, $torneo, $fase, $giornata, $casa, $ospite)) {
        $errore = 'Questa partita è già stata inserita in un\'altra giornata della fase REGULAR.';
      }

      if (!empty($errore)) {
        // non procedere oltre
      } else {
        $stmt = $conn->prepare("INSERT INTO partite (torneo, fase, fase_round, fase_leg, squadra_casa, squadra_ospite, gol_casa, gol_ospite, data_partita, ora_partita, campo, decisa_rigori, rigori_casa, rigori_ospite, giornata, giocata, arbitro, link_youtube, link_instagram, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        if ($stmt) {
          $stmt->bind_param(
            'ssssssiisssiiiiisss',
            $torneo,
            $fase,
            $faseRound,
            $faseLeg,
            $casa,
            $ospite,
            $gol_casa,
            $gol_ospite,
            $data,
            $ora,
            $campo,
            $decisa_rigori,
            $rigori_casa,
            $rigori_ospite,
            $giornata,
            $giocata,
            $arbitro,
            $link_youtube,
            $link_instagram
          );
          if ($stmt->execute()) {
            $successo = 'Partita creata correttamente.';
            // crea automaticamente il ritorno per i turni a doppia sfida impostati come andata
            if (round_supports_two_legs($faseRound) && strtoupper($faseLeg) === 'ANDATA') {
              if (!squadraHaGiaPartita($conn, $torneo, $fase, $giornata, $ospite, $casa, null, $faseRound, 'RITORNO')) {
                $stmtR = $conn->prepare("INSERT INTO partite (torneo, fase, fase_round, fase_leg, squadra_casa, squadra_ospite, gol_casa, gol_ospite, data_partita, ora_partita, campo, decisa_rigori, rigori_casa, rigori_ospite, giornata, giocata, arbitro, link_youtube, link_instagram, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
                if ($stmtR) {
                  // usa segnaposto evidente per il ritorno (poi modificabile)
                  $defaultDate = '2000-01-01';
                  $defaultOra = '00:00:00';
                  $defaultCampo = 'Da definire';
                  $golZero = 0;
                  $giocataZero = 0;
                  $decisaRigoriZero = 0;
                  $rigoriNull = null;
                  $ytEmpty = '';
                  $igEmpty = '';
                  $giornataInt = (int)$giornata;
                  $faseLegRitorno = 'RITORNO';
                  $stmtR->bind_param(
                    'ssssssiisssiiiiisss',
                    $torneo,      // s
                    $fase,        // s
                    $faseRound,   // s
                    $faseLegRitorno, // s
                    $ospite,      // s
                    $casa,        // s
                    $golZero,     // i gol_casa
                    $golZero,     // i gol_ospite
                    $defaultDate, // s data
                    $defaultOra,  // s ora
                    $defaultCampo,// s campo
                    $decisaRigoriZero, // i
                    $rigoriNull,  // i
                    $rigoriNull,  // i
                    $giornataInt, // i giornata
                    $giocataZero, // i giocata
                    $arbitro,     // s
                    $ytEmpty,     // s youtube
                    $igEmpty      // s instagram
                  );
                  if ($stmtR->execute()) {
                    $successo .= ' Creato automaticamente il ritorno (data/ora/campo da definire).';
                  } else {
                    $successo .= ' (Ritorno non creato: controlla duplicati o dati mancanti)';
                  }
                  $stmtR->close();
                }
              } else {
                $successo .= ' (Ritorno già presente, non duplicato).';
              }
            }
          } else {
            if ((int)$stmt->errno === 1062) {
              $errore = 'Esiste già una partita per questo turno con una delle squadre selezionate.';
            } else {
              $errore = 'Inserimento non riuscito.';
            }
          }
          $stmt->close();
        } else {
          $errore = 'Errore interno durante la creazione.';
        }
      }
    }
  }

  if ($azione === 'modifica') {
    $id = (int)($_POST['partita_id'] ?? 0);
    $torneo = sanitize_text($_POST['torneo_mod'] ?? '');
    $fase = sanitize_fase($_POST['fase_mod'] ?? '', $fasiAmmesseConSpareggio);
    $casa = sanitize_text($_POST['squadra_casa_mod'] ?? '');
    $ospite = sanitize_text($_POST['squadra_ospite_mod'] ?? '');
    $data = sanitize_text($_POST['data_partita_mod'] ?? '');
    $ora = sanitize_text($_POST['ora_partita_mod'] ?? '');
    $campo = sanitize_text($_POST['campo_mod'] ?? '');
    $roundSelezionato = sanitize_text($_POST['round_eliminazione_mod'] ?? '');
    $giornata = sanitize_int($_POST['giornata_mod'] ?? '');
    $faseUsesRound = phase_uses_round($fase);
    $isSpareggio = is_spareggio_phase($fase);
    $faseRound = $faseUsesRound ? strtoupper($roundSelezionato) : null;
    $faseLegInput = $faseUsesRound ? sanitize_leg($_POST['fase_leg_mod'] ?? '') : null;
    $faseLeg = $faseUsesRound
      ? ($faseLegInput ?: (round_supports_two_legs($faseRound) ? 'ANDATA' : 'UNICA'))
      : ($isSpareggio ? 'UNICA' : null);
    // usa il flag inviato dal form; non forziamo piï¿½ true di default
    $giocata = isset($_POST['giocata_mod']) && $_POST['giocata_mod'] === '1' ? 1 : 0;
    $gol_casa = sanitize_int($_POST['gol_casa_mod'] ?? '0');
    $gol_ospite = sanitize_int($_POST['gol_ospite_mod'] ?? '0');
    $link_youtube = sanitize_text($_POST['link_youtube_mod'] ?? '');
    $link_instagram = sanitize_text($_POST['link_instagram_mod'] ?? '');
    $arbitro = sanitize_text($_POST['arbitro_mod'] ?? '');
    $decisa_rigori = isset($_POST['decisa_rigori_mod']) ? 1 : 0;
    $rigori_casa = $decisa_rigori ? sanitize_int($_POST['rigori_casa_mod'] ?? '') : null;
    $rigori_ospite = $decisa_rigori ? sanitize_int($_POST['rigori_ospite_mod'] ?? '') : null;
    $giocataPrecedente = 0;
    if ($id > 0) {
      $oldGiocataStmt = $conn->prepare("SELECT giocata FROM partite WHERE id=?");
      if ($oldGiocataStmt) {
        $oldGiocataStmt->bind_param('i', $id);
        if ($oldGiocataStmt->execute()) {
          $oldRow = $oldGiocataStmt->get_result()->fetch_assoc();
          $giocataPrecedente = isset($oldRow['giocata']) ? (int)$oldRow['giocata'] : 0;
        }
        $oldGiocataStmt->close();
      }
    }

    if ($isSpareggio) {
      $giornata = 1;
    }

    if ($id <= 0 || $torneo === '' || $fase === '' || $casa === '' || $ospite === '' || $data === '' || $ora === '' || $campo === '' || ($fase === 'REGULAR' && $giornata <= 0) || ($faseUsesRound && $roundSelezionato === '')) {
      $errore = 'Seleziona una partita e compila i campi obbligatori.';
    } elseif ($casa === $ospite) {
      $errore = 'Le due squadre non possono coincidere.';
    } elseif ($faseUsesRound && !$faseLeg) {
      $errore = 'Seleziona il tipo di gara (andata/ritorno/unica).';
    } else {
      if ($faseUsesRound) {
        $giornata = round_to_giornata($roundSelezionato, $roundMap) ?? 0;
      } elseif ($isSpareggio) {
        $giornata = 1;
      }
      if (squadraHaGiaPartita($conn, $torneo, $fase, $giornata, $casa, $ospite, $id, $faseRound, $faseLeg)) {
        $errore = 'Una delle squadre ha già una partita in questa giornata per questa fase.';
      } elseif (partitaRegularGiaInseritaInAltraGiornata($conn, $torneo, $fase, $giornata, $casa, $ospite, $id)) {
        $errore = 'Questa partita è già stata inserita in un\'altra giornata della fase REGULAR.';
      }
      if (!empty($errore)) {
        // non procedere oltre
      } else {
        $stmt = $conn->prepare("UPDATE partite SET torneo=?, fase=?, fase_round=?, fase_leg=?, squadra_casa=?, squadra_ospite=?, gol_casa=?, gol_ospite=?, data_partita=?, ora_partita=?, campo=?, decisa_rigori=?, rigori_casa=?, rigori_ospite=?, giornata=?, giocata=?, arbitro=?, link_youtube=?, link_instagram=? WHERE id=?");
        if ($stmt) {
          $stmt->bind_param(
            'ssssssiisssiiiiisssi',
            $torneo,
            $fase,
            $faseRound,
            $faseLeg,
            $casa,
            $ospite,
            $gol_casa,
            $gol_ospite,
            $data,
            $ora,
            $campo,
            $decisa_rigori,
            $rigori_casa,
            $rigori_ospite,
            $giornata,
            $giocata,
            $arbitro,
            $link_youtube,
            $link_instagram,
            $id
          );
          if ($stmt->execute()) {
            $successo = 'Partita aggiornata correttamente.';
            $haAttivatoGiocata = ($giocata === 1 && (int)$giocataPrecedente !== 1);
            $haDisattivatoGiocata = ($giocata === 0 && (int)$giocataPrecedente === 1);
            // Ricalcolo sempre la classifica se la partita e' (o era) segnata come giocata
            // per riflettere eventuali modifiche a gol/squadre senza dover premere "ricalcola".
            if ($giocata === 1 || (int)$giocataPrecedente === 1) {
              ricostruisci_classifica_da_partite($conn, $torneo);
            }
            inviaNotificaEsito($conn, $id, [
              'partita_id' => $id,
              'torneo' => $torneo,
              'fase' => $fase,
              'squadra_casa' => $casa,
              'squadra_ospite' => $ospite,
              'gol_casa' => $gol_casa,
              'gol_ospite' => $gol_ospite,
            ]);
          } else {
            $errore = 'Aggiornamento non riuscito.';
          }
          $stmt->close();
        } else {
          $errore = 'Errore interno durante l\'aggiornamento.';
        }
      }
    }
  }

  if ($azione === 'riapri_giocata') {
    $id = (int)($_POST['partita_id'] ?? 0);
    $partitaInfo = null;
    if ($id > 0) {
      $sel = $conn->prepare("SELECT torneo, fase, giocata FROM partite WHERE id=?");
      if ($sel) {
        $sel->bind_param('i', $id);
        if ($sel->execute()) {
          $partitaInfo = $sel->get_result()->fetch_assoc();
        }
        $sel->close();
      }
    }

    if ($id <= 0) {
      $errore = 'Seleziona una partita valida.';
    } elseif (!$partitaInfo) {
      $errore = 'Partita non trovata.';
    } elseif ((int)($partitaInfo['giocata'] ?? 0) !== 1) {
      $errore = 'Questa partita non risulta giocata.';
    } else {
      $upd = $conn->prepare("UPDATE partite SET giocata = 0, notifica_esito_inviata = 0 WHERE id=?");
      if ($upd) {
        $upd->bind_param('i', $id);
        if ($upd->execute()) {
          $successo = 'Partita segnata come non giocata.';
          if (strtoupper($partitaInfo['fase'] ?? '') === 'REGULAR') {
            ricostruisci_classifica_da_partite($conn, (string)$partitaInfo['torneo']);
          }
        } else {
          $errore = 'Aggiornamento non riuscito.';
        }
        $upd->close();
      } else {
        $errore = 'Errore interno durante l\'aggiornamento.';
      }
    }
  }

  if ($azione === 'aggiorna_link') {
    $id = (int)($_POST['partita_id'] ?? 0);
    $arbitro = sanitize_text($_POST['arbitro_link'] ?? '');
    $link_youtube = sanitize_text($_POST['link_youtube_link'] ?? '');
    $link_instagram = sanitize_text($_POST['link_instagram_link'] ?? '');

    if ($id <= 0) {
      $errore = 'Seleziona una partita valida.';
    } else {
      $info = null;
      $sel = $conn->prepare("SELECT giocata FROM partite WHERE id=?");
      if ($sel) {
        $sel->bind_param('i', $id);
        if ($sel->execute()) {
          $info = $sel->get_result()->fetch_assoc();
        }
        $sel->close();
      }
      if (!$info) {
        $errore = 'Partita non trovata.';
      } elseif ((int)($info['giocata'] ?? 0) !== 1) {
        $errore = 'Questa partita non risulta giocata.';
      }
    }

    if ($errore === '' && $id > 0) {
      $stmt = $conn->prepare("UPDATE partite SET arbitro=?, link_youtube=?, link_instagram=? WHERE id=?");
      if ($stmt) {
        $stmt->bind_param('sssi', $arbitro, $link_youtube, $link_instagram, $id);
        if ($stmt->execute()) {
          $successo = 'Dati aggiornati correttamente.';
        } else {
          $errore = 'Aggiornamento non riuscito.';
        }
        $stmt->close();
      } else {
        $errore = 'Errore interno durante l\'aggiornamento.';
      }
    }
  }

  if ($azione === 'elimina') {
    $id = (int)($_POST['partita_id'] ?? 0);
    $partitaInfo = null;
    $giocatoriCoinvolti = [];
    if ($id > 0) {
      $oldStmt = $conn->prepare("SELECT torneo, squadra_casa, squadra_ospite FROM partite WHERE id=?");
      if ($oldStmt) {
        $oldStmt->bind_param('i', $id);
        if ($oldStmt->execute()) {
          $partitaInfo = $oldStmt->get_result()->fetch_assoc();
        }
        $oldStmt->close();
      }
      $statsStmt = $conn->prepare("SELECT DISTINCT giocatore_id FROM partita_giocatore WHERE partita_id=?");
      if ($statsStmt) {
        $statsStmt->bind_param('i', $id);
        if ($statsStmt->execute()) {
          $res = $statsStmt->get_result();
          while ($row = $res->fetch_assoc()) {
            $giocatoriCoinvolti[] = (int)($row['giocatore_id'] ?? 0);
          }
        }
        $statsStmt->close();
      }
    }
    if ($id <= 0) {
      $errore = 'Seleziona una partita valida da eliminare.';
    } else {
      $txStarted = $conn->begin_transaction();
      $operazioneOk = true;

      $delStats = $conn->prepare("DELETE FROM partita_giocatore WHERE partita_id=?");
      if ($delStats) {
        $delStats->bind_param('i', $id);
        $operazioneOk = $delStats->execute();
        $delStats->close();
      } else {
        $operazioneOk = false;
        $errore = 'Errore interno durante l\'eliminazione.';
      }

      if ($operazioneOk) {
        $stmt = $conn->prepare("DELETE FROM partite WHERE id=?");
        if ($stmt) {
          $stmt->bind_param('i', $id);
          $operazioneOk = $stmt->execute();
          $stmt->close();
        } else {
          $operazioneOk = false;
          $errore = 'Errore interno durante l\'eliminazione.';
        }
      }

      if ($operazioneOk) {
        if ($txStarted !== false) { $conn->commit(); }
        $successo = 'Partita eliminata.';
        if ($partitaInfo && !empty($partitaInfo['torneo'])) {
          ricostruisci_classifica_da_partite($conn, $partitaInfo['torneo']);
        }
        if (!empty($giocatoriCoinvolti) && $partitaInfo) {
          ricalcolaGiocatoriPartita($conn, $giocatoriCoinvolti, $partitaInfo);
        }
      } else {
        if ($txStarted !== false) { $conn->rollback(); }
        if (empty($errore)) {
          $errore = 'Eliminazione non riuscita.';
        }
      }
    }
  }

  if ($azione === 'crea_campo') {
    $campoNome = sanitize_text($_POST['campo_nome'] ?? '');

    if ($campoNome === '') {
      $errore = 'Inserisci il nome del campo da aggiungere.';
    } else {
      $maxOrderRes = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_order FROM campi_partite");
      $nextOrder = 1;
      if ($maxOrderRes) {
        $maxOrderRow = $maxOrderRes->fetch_assoc();
        $nextOrder = ((int)($maxOrderRow['max_order'] ?? 0)) + 1;
      }

      $stmt = $conn->prepare("INSERT INTO campi_partite (nome, sort_order) VALUES (?, ?)");
      if ($stmt) {
        $stmt->bind_param('si', $campoNome, $nextOrder);
        if ($stmt->execute()) {
          $successo = 'Campo aggiunto correttamente alla picklist.';
        } else {
          $errore = (int)$stmt->errno === 1062
            ? 'Questo campo esiste già nella picklist.'
            : 'Inserimento del campo non riuscito.';
        }
        $stmt->close();
      } else {
        $errore = 'Errore interno durante la creazione del campo.';
      }
    }
  }

  if ($azione === 'modifica_campo') {
    $campoId = (int)($_POST['campo_id'] ?? 0);
    $campoNome = sanitize_text($_POST['campo_nome'] ?? '');

    if ($campoId <= 0 || $campoNome === '') {
      $errore = 'Seleziona un campo valido e inserisci il nuovo nome.';
    } else {
      $campoEsistente = null;
      $sel = $conn->prepare("SELECT nome FROM campi_partite WHERE id = ? LIMIT 1");
      if ($sel) {
        $sel->bind_param('i', $campoId);
        if ($sel->execute()) {
          $campoEsistente = $sel->get_result()->fetch_assoc();
        }
        $sel->close();
      }

      if (!$campoEsistente) {
        $errore = 'Campo non trovato.';
      } else {
        $nomePrecedente = trim((string)($campoEsistente['nome'] ?? ''));
        $txStarted = $conn->begin_transaction();
        $operazioneOk = true;

        $updCampo = $conn->prepare("UPDATE campi_partite SET nome = ? WHERE id = ?");
        if ($updCampo) {
          $updCampo->bind_param('si', $campoNome, $campoId);
          $operazioneOk = $updCampo->execute();
          if (!$operazioneOk && (int)$updCampo->errno === 1062) {
            $errore = 'Esiste già un altro campo con questo nome.';
          }
          $updCampo->close();
        } else {
          $operazioneOk = false;
          $errore = 'Errore interno durante l\'aggiornamento del campo.';
        }

        if ($operazioneOk && $nomePrecedente !== '' && strcmp($nomePrecedente, $campoNome) !== 0) {
          $updPartite = $conn->prepare("UPDATE partite SET campo = ? WHERE campo = ?");
          if ($updPartite) {
            $updPartite->bind_param('ss', $campoNome, $nomePrecedente);
            $operazioneOk = $updPartite->execute();
            $updPartite->close();
          } else {
            $operazioneOk = false;
            $errore = 'Errore interno durante l\'aggiornamento delle partite collegate.';
          }
        }

        if ($operazioneOk) {
          if ($txStarted !== false) {
            $conn->commit();
          }
          $successo = 'Campo aggiornato correttamente.';
        } else {
          if ($txStarted !== false) {
            $conn->rollback();
          }
          if ($errore === '') {
            $errore = 'Aggiornamento del campo non riuscito.';
          }
        }
      }
    }
  }

  if ($azione === 'elimina_campo') {
    $campoId = (int)($_POST['campo_id'] ?? 0);

    if ($campoId <= 0) {
      $errore = 'Seleziona un campo valido da eliminare.';
    } else {
      $campoInfo = null;
      $sel = $conn->prepare("
        SELECT cp.nome, COUNT(p.id) AS uso_totale
        FROM campi_partite cp
        LEFT JOIN partite p ON p.campo = cp.nome
        WHERE cp.id = ?
        GROUP BY cp.id, cp.nome
        LIMIT 1
      ");
      if ($sel) {
        $sel->bind_param('i', $campoId);
        if ($sel->execute()) {
          $campoInfo = $sel->get_result()->fetch_assoc();
        }
        $sel->close();
      }

      if (!$campoInfo) {
        $errore = 'Campo non trovato.';
      } else {
        $del = $conn->prepare("DELETE FROM campi_partite WHERE id = ?");
        if ($del) {
          $del->bind_param('i', $campoId);
          if ($del->execute()) {
            $usoTotale = (int)($campoInfo['uso_totale'] ?? 0);
            $successo = $usoTotale > 0
              ? 'Campo eliminato dalla picklist. Le partite già salvate che lo usano non sono state modificate.'
              : 'Campo eliminato correttamente dalla picklist.';
          } else {
            $errore = 'Eliminazione del campo non riuscita.';
          }
          $del->close();
        } else {
          $errore = 'Errore interno durante l\'eliminazione del campo.';
        }
      }
    }
  }
}

// AJAX helper: se la richiesta chiede JSON, rispondiamo senza ricaricare tutta la pagina
$isAjax = (
  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
  (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

$partite = [];
$partiteNonGiocate = [];
$partiteGiocate = [];
$campiPartita = get_match_fields($conn);
$res = $conn->query("SELECT id, torneo, fase, fase_round, fase_leg, squadra_casa, squadra_ospite, gol_casa, gol_ospite, data_partita, ora_partita, campo, decisa_rigori, rigori_casa, rigori_ospite, giornata, giocata, arbitro, link_youtube, link_instagram, created_at FROM partite ORDER BY data_partita DESC, ora_partita DESC, id DESC");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $partite[] = $row;
    if ((int)($row['giocata'] ?? 0) === 1) {
      $partiteGiocate[] = $row;
    } else {
      $partiteNonGiocate[] = $row;
    }
  }
}

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'success' => $errore === '' && $successo !== '',
    'error' => $errore,
    'message' => $successo,
    'campi' => $campiPartita,
    'partite' => $partite,
    'partite_non_giocate' => $partiteNonGiocate,
    'partite_giocate' => $partiteGiocate,
  ]);
  exit;
}

$tabAttiva = 'crea';
if (in_array($azione, ['modifica', 'riapri_giocata', 'aggiorna_link'], true)) {
  $tabAttiva = 'modifica';
} elseif ($azione === 'elimina') {
  $tabAttiva = 'elimina';
} elseif (in_array($azione, ['crea_campo', 'modifica_campo', 'elimina_campo'], true)) {
  $tabAttiva = 'campi';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-VZ982XSRRN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-VZ982XSRRN');
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Gestione Partite</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link rel="apple-touch-icon" href="/img/logo_old_school.png">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; background: #f7f9fc; }
    main.admin-wrapper { flex: 1 0 auto; }
    .tab-buttons { display: flex; gap: 12px; margin: 10px 0 20px; flex-wrap: wrap; }
    .tab-buttons button { padding: 12px 16px; border: 1px solid #cbd5e1; background: #ecf1f7; cursor: pointer; border-radius: 10px; font-weight: 600; color: #1c2a3a; box-shadow: 0 2px 6px rgba(0,0,0,0.04); transition: all .2s; }
    .tab-buttons button:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
    .tab-buttons button.active { background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border-color: #15293e; box-shadow: 0 8px 20px rgba(21,41,62,0.25); }

    .form-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 18px 18px 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .form-card h3 { margin: 0 0 14px; color: #15293e; font-size: 1.1rem; }

    .admin-form.inline { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px 18px; }
    .admin-form.inline .full { grid-column: 1 / -1; }
    .admin-form.inline label { font-weight: 600; color: #1c2a3a; }
    .admin-form.inline input,
    .admin-form.inline select { border-radius: 10px; border: 1px solid #d5dbe4; background: #fafbff; transition: border-color .2s, box-shadow .2s; width: 100%; display: block; }
    .admin-form.inline input:focus,
    .admin-form.inline select:focus { border-color: #15293e; box-shadow: 0 0 0 3px rgba(21,41,62,0.15); outline: none; }
    .required-label::after { content: " *"; color: #d72638; margin-left: 4px; font-weight: 700; }
    .checkbox-inline { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #15293e; }

    .rigori-group { display: flex; flex-direction: column; gap: 8px; }
    .rigori-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
    .rigori-fields.hidden { display: none; }

    .table-scroll { overflow-x: auto; background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
    #footer-container { margin-top: auto; padding-top: 40px; }

    .modern-danger { background: linear-gradient(135deg, #d72638, #b1172a); border: none; color: #fff; padding: 12px 18px; border-radius: 12px; box-shadow: 0 10px 25px rgba(183, 23, 42, 0.3); transition: transform .15s, box-shadow .15s; font-weight: 700; letter-spacing: 0.2px; }
    .modern-danger:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(183, 23, 42, 0.4); }

    .confirm-modal {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(0,0,0,0.45);
      backdrop-filter: blur(2px);
      z-index: 9999;
    }
    .confirm-modal.active { display: flex; }
    .confirm-card {
      background: #fff;
      border-radius: 14px;
      padding: 22px;
      width: min(420px, 90vw);
      box-shadow: 0 18px 34px rgba(0,0,0,0.15);
      border: 1px solid #e5eaf0;
    }
    .confirm-card h4 { margin: 0 0 8px; color: #15293e; }
    .confirm-card p { margin: 0 0 16px; color: #345; }
    .confirm-actions { display: flex; gap: 12px; justify-content: center; }
    .confirm-actions button { flex: 1 1 0; min-width: 140px; text-align: center; }
    .btn-ghost { border: 1px solid #d5dbe4; background: #fff; color: #1c2a3a; border-radius: 10px; padding: 12px 14px; cursor: pointer; font-weight: 700; }
    .btn-ghost:hover { border-color: #15293e; color: #15293e; }
    .btn-secondary-modern { border: 1px solid #cbd5e1; background: #f5f7fb; color: #15293e; border-radius: 10px; padding: 10px 14px; font-weight: 700; box-shadow: 0 6px 14px rgba(0,0,0,0.08); transition: transform .15s, box-shadow .15s; }
    .btn-secondary-modern:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(0,0,0,0.12); }
    .btn-stats { background: linear-gradient(135deg, #1f3f63, #2a5b8a); color: #fff; border: none; padding: 12px 16px; border-radius: 12px; font-weight: 700; box-shadow: 0 10px 22px rgba(31,63,99,0.25); cursor: pointer; transition: transform .15s, box-shadow .15s; }
    .btn-stats:hover { transform: translateY(-1px); box-shadow: 0 14px 26px rgba(31,63,99,0.32); }

    .stats-wrapper { margin-top: 10px; }
    .stats-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
    .stats-actions button { padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1; background: #f6f8fb; cursor: pointer; font-weight: 700; color: #1c2a3a; box-shadow: 0 6px 14px rgba(0,0,0,0.06); transition: all .15s; }
    .stats-actions button:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    .stats-actions button.active { background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border-color: #15293e; box-shadow: 0 12px 24px rgba(21,41,62,0.25); }
    .stats-form { display: none; background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 16px; box-shadow: 0 10px 24px rgba(0,0,0,0.06); }
    .stats-form.active { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px 16px; }
    .stats-form label { font-weight: 600; color: #15293e; }
    .stats-form input, .stats-form select { border-radius: 10px; border: 1px solid #d5dbe4; padding: 10px; background: #fafbff; transition: border-color .2s, box-shadow .2s; }
    .stats-form input:focus, .stats-form select:focus { border-color: #15293e; box-shadow: 0 0 0 3px rgba(21,41,62,0.15); outline: none; }
    .section-divider { border: 0; height: 1px; background: #15293e; margin: 16px 0; display: block; width: 100%; }
    .admin-form.inline .section-divider { grid-column: 1 / -1; }
    .stats-button-row { display: flex; justify-content: center; }
    @media (max-width: 767px) {
      .stats-button-row { justify-content: flex-start; }
    }

    .form-message { margin-top: 10px; padding: 10px 12px; border-radius: 10px; font-weight: 600; }
    .form-message.success { background: #e6f6ec; border: 1px solid #3ba776; color: #1f6a44; }
    .form-message.error { background: #fdecec; border: 1px solid #d72638; color: #8f1a27; }
    .section-note { margin: -4px 0 16px; color: #5b6b7d; }
    .field-manager-list { display: flex; flex-direction: column; gap: 12px; }
    .field-row-form { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 12px; align-items: end; padding: 14px; border: 1px solid #e5eaf0; border-radius: 12px; background: #f8fbff; }
    .field-row-form > div { min-width: 0; }
    .field-row-form input[type="text"] { width: 100%; }
    .field-row-meta { display: block; margin-top: 8px; color: #6a788c; font-size: 0.92rem; }
    .empty-state { color: #6a788c; }
    @media (max-width: 767px) {
      .field-row-form { grid-template-columns: 1fr; }
      .field-row-form button { width: 100%; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../includi/header.php'; ?>

<main class="admin-wrapper">
  <section class="admin-container">
    <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
    <h1 class="admin-title">Gestione Partite</h1>

    <div class="tab-buttons">
      <button type="button" data-tab="crea" class="<?= $tabAttiva === 'crea' ? 'active' : '' ?>">Crea</button>
      <button type="button" data-tab="modifica" class="<?= $tabAttiva === 'modifica' ? 'active' : '' ?>">Modifica</button>
      <button type="button" data-tab="elimina" class="<?= $tabAttiva === 'elimina' ? 'active' : '' ?>">Elimina</button>
      <button type="button" data-tab="campi" class="<?= $tabAttiva === 'campi' ? 'active' : '' ?>">Campi</button>
    </div>

    <!-- CREA -->
    <section class="tab-section <?= $tabAttiva === 'crea' ? 'active' : '' ?>" data-tab="crea">
      <div class="form-card">
        <h3>Crea partita</h3>
        <form class="admin-form inline" method="POST" id="formCrea">
        <input type="hidden" name="azione" value="crea">
        <div>
          <label class="required-label">Torneo</label>
          <select name="torneo" id="torneoCrea" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Fase</label>
          <select name="fase" required>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="giornataWrapper">
          <label class="required-label">Giornata</label>
          <select name="giornata" id="giornataCrea" data-default-max="<?= (int)$maxGiornateRegular ?>" required>
            <option value="">-- Seleziona giornata (1-<?= $maxGiornateRegular ?>) --</option>
            <?php for ($g = 1; $g <= $maxGiornateRegular; $g++): ?>
              <option value="<?= $g ?>"><?= $g ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div id="roundWrapper" class="hidden">
          <label class="required-label">Fase eliminazione</label>
          <select name="round_eliminazione" id="roundCrea">
            <option value="">-- Seleziona fase --</option>
            <option value="TRENTADUESIMI">Trentaduesimi di finale</option>
            <option value="SEDICESIMI">Sedicesimi di finale</option>
            <option value="OTTAVI">Ottavi di finale</option>
            <option value="QUARTI">Quarti di finale</option>
            <option value="SEMIFINALE">Semifinale</option>
            <option value="FINALE">Finale</option>
          </select>
        </div>
        <div id="legWrapper" class="hidden">
          <label class="required-label">Tipo gara</label>
          <select name="fase_leg" id="faseLegCrea">
            <option value="UNICA">Gara secca</option>
            <option value="ANDATA">Andata</option>
            <option value="RITORNO">Ritorno</option>
          </select>
        </div>
        <div>
          <label class="required-label">Squadra casa</label>
          <select name="squadra_casa" id="squadraCasaCrea" required>
            <option value="">-- Seleziona torneo/giornata --</option>
          </select>
        </div>
        <div>
          <label class="required-label">Squadra ospite</label>
          <select name="squadra_ospite" id="squadraOspiteCrea" required>
            <option value="">-- Seleziona torneo/giornata --</option>
          </select>
        </div>
        <input type="hidden" name="gol_casa" value="0">
        <input type="hidden" name="gol_ospite" value="0">
        <div>
          <label class="required-label">Data</label>
          <input type="date" name="data_partita" required>
        </div>
        <div>
          <label class="required-label">Ora</label>
          <input type="time" name="ora_partita" required>
        </div>
        <div>
          <label class="required-label">Campo</label>
          <select name="campo" id="campoCrea" required>
            <?= render_match_field_options($campiPartita) ?>
          </select>
        </div>
        <div>
          <label>Arbitro</label>
          <input type="text" name="arbitro" placeholder="Nome dell'arbitro">
        </div>
        <input type="hidden" name="giocata" value="0">
        <input type="hidden" name="link_youtube" value="">
        <input type="hidden" name="link_instagram" value="">
        <div class="full">
          <button type="submit" class="btn-primary">Crea partita</button>
        </div>
        <?php if (($successo && ($azione ?? '') === 'crea')): ?>
          <div class="form-message success"><?= htmlspecialchars($successo) ?></div>
        <?php elseif (($errore && ($azione ?? '') === 'crea')): ?>
          <div class="form-message error"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>
        </form>
      </div>
    </section>

    <!-- Sezione statistiche spostata su pagina dedicata -->
    <!-- MODIFICA -->
    <section class="tab-section <?= $tabAttiva === 'modifica' ? 'active' : '' ?>" data-tab="modifica">
      <div class="form-card">
        <h3>Modifica partita</h3>
        <form class="admin-form inline" method="POST" id="formModifica">
        <input type="hidden" name="azione" value="modifica">
        <div class="full">
          <label class="required-label">Seleziona torneo</label>
          <select id="selTorneoMod" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Fase</label>
          <select id="selFaseMod" required>
            <option value="">-- Seleziona fase --</option>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Giornata / Turno</label>
          <select id="selGiornataMod" required disabled>
            <option value="">-- Seleziona fase --</option>
          </select>
        </div>
        <div>
          <label class="required-label">Partita</label>
          <select name="partita_id" id="selPartitaMod" required disabled>
            <option value="">-- Seleziona giornata/turno --</option>
          </select>
        </div>
        <div class="full stats-button-row">
          <button type="button" id="btnStatsMod" class="btn-stats" style="display:none;">Statistiche partita</button>
        </div>
        <hr class="section-divider">
        <div>
          <label class="required-label">Torneo</label>
          <select name="torneo_mod" id="torneo_mod" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Fase</label>
          <select name="fase_mod" id="fase_mod" required>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Squadra casa</label>
          <select name="squadra_casa_mod" id="squadra_casa_mod" required>
            <option value="">-- Seleziona torneo prima --</option>
          </select>
        </div>
        <div>
          <label class="required-label">Squadra ospite</label>
          <select name="squadra_ospite_mod" id="squadra_ospite_mod" required>
            <option value="">-- Seleziona torneo prima --</option>
          </select>
        </div>
        <div>
          <label>Gol casa</label>
          <input type="number" name="gol_casa_mod" id="gol_casa_mod" min="0">
        </div>
        <div>
          <label>Gol ospite</label>
          <input type="number" name="gol_ospite_mod" id="gol_ospite_mod" min="0">
        </div>
        <div class="full rigori-group">
          <label class="checkbox-inline">
            <input type="checkbox" name="decisa_rigori_mod" id="decisa_rigori_mod" value="1">
            Terminata ai rigori (d.c.r.)
          </label>
          <div class="rigori-fields hidden" id="rigoriFieldsMod">
            <div>
              <label>Rigori casa</label>
              <input type="number" name="rigori_casa_mod" id="rigori_casa_mod" min="0">
            </div>
            <div>
              <label>Rigori ospite</label>
              <input type="number" name="rigori_ospite_mod" id="rigori_ospite_mod" min="0">
            </div>
          </div>
        </div>
        <div>
          <label class="required-label">Data</label>
          <input type="date" name="data_partita_mod" id="data_partita_mod" required>
        </div>
        <div>
          <label class="required-label">Ora</label>
          <input type="time" name="ora_partita_mod" id="ora_partita_mod" required>
        </div>
        <div>
          <label class="required-label">Campo</label>
          <select name="campo_mod" id="campo_mod" required>
            <?= render_match_field_options($campiPartita) ?>
          </select>
        </div>
        <div>
          <label>Arbitro</label>
          <input type="text" name="arbitro_mod" id="arbitro_mod" placeholder="Nome dell'arbitro">
        </div>
        <div id="giornataWrapperMod">
          <label class="required-label">Giornata</label>
          <input type="number" name="giornata_mod" id="giornata_mod" min="1" required>
        </div>
        <div id="roundWrapperMod" class="hidden">
          <label class="required-label">Fase eliminazione</label>
          <select name="round_eliminazione_mod" id="round_eliminazione_mod">
            <option value="">-- Seleziona fase --</option>
            <option value="TRENTADUESIMI">Trentaduesimi di finale</option>
            <option value="SEDICESIMI">Sedicesimi di finale</option>
            <option value="OTTAVI">Ottavi di finale</option>
            <option value="QUARTI">Quarti di finale</option>
            <option value="SEMIFINALE">Semifinale</option>
            <option value="FINALE">Finale</option>
          </select>
        </div>
        <div id="legWrapperMod" class="hidden">
          <label class="required-label">Tipo gara</label>
          <select name="fase_leg_mod" id="faseLegMod">
            <option value="UNICA">Gara secca</option>
            <option value="ANDATA">Andata</option>
            <option value="RITORNO">Ritorno</option>
          </select>
        </div>
        <div>
          <label>Giocata</label>
          <input type="checkbox" name="giocata_mod" id="giocata_mod" value="1">
        </div>
        <div>
          <label>Link YouTube</label>
          <input type="url" name="link_youtube_mod" id="link_youtube_mod" placeholder="https://youtube.com/...">
        </div>
        <div>
          <label>Link Instagram</label>
          <input type="url" name="link_instagram_mod" id="link_instagram_mod" placeholder="https://instagram.com/...">
        </div>
        <div class="full">
          <button type="submit" class="btn-primary">Salva modifiche</button>
        </div>
        <?php if (($successo && ($azione ?? '') === 'modifica')): ?>
          <div class="form-message success"><?= htmlspecialchars($successo) ?></div>
        <?php elseif (($errore && ($azione ?? '') === 'modifica')): ?>
          <div class="form-message error"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>
        </form>
      </div>
      <div class="form-card">
        <h3>Modifica partita giocata</h3>
        <form class="admin-form inline" method="POST" id="formRipristinaGiocata">
        <input type="hidden" name="azione" value="riapri_giocata">
        <div class="full">
          <label class="required-label">Seleziona torneo</label>
          <select id="selTorneoGioc" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Fase</label>
          <select id="selFaseGioc" required>
            <option value="">-- Seleziona fase --</option>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Giornata / Turno</label>
          <select id="selGiornataGioc" required disabled>
            <option value="">-- Seleziona fase --</option>
          </select>
        </div>
        <div>
          <label class="required-label">Partita giocata</label>
          <select name="partita_id" id="selPartitaGioc" required disabled>
            <option value="">-- Seleziona giornata/turno --</option>
          </select>
        </div>
        <div class="full">
          <button type="submit" class="btn-primary">Segna come non giocata</button>
        </div>
        <?php if (($successo && ($azione ?? '') === 'riapri_giocata')): ?>
          <div class="form-message success"><?= htmlspecialchars($successo) ?></div>
        <?php elseif (($errore && ($azione ?? '') === 'riapri_giocata')): ?>
          <div class="form-message error"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>
        </form>
      </div>
      <div class="form-card">
        <h3>Modifica arbitro e link</h3>
        <form class="admin-form inline" method="POST" id="formLinkArbitro">
        <input type="hidden" name="azione" value="aggiorna_link">
        <div class="full">
          <label class="required-label">Seleziona torneo</label>
          <select id="selTorneoLink" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Fase</label>
          <select id="selFaseLink" required>
            <option value="">-- Seleziona fase --</option>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Giornata / Turno</label>
          <select id="selGiornataLink" required disabled>
            <option value="">-- Seleziona fase --</option>
          </select>
        </div>
        <div>
          <label class="required-label">Partita</label>
          <select name="partita_id" id="selPartitaLink" required disabled>
            <option value="">-- Seleziona giornata/turno --</option>
          </select>
        </div>
        <div>
          <label>Arbitro</label>
          <input type="text" name="arbitro_link" id="arbitro_link" placeholder="Nome dell'arbitro">
        </div>
        <div>
          <label>Link YouTube</label>
          <input type="url" name="link_youtube_link" id="link_youtube_link" placeholder="https://youtube.com/...">
        </div>
        <div>
          <label>Link Instagram</label>
          <input type="url" name="link_instagram_link" id="link_instagram_link" placeholder="https://instagram.com/...">
        </div>
        <div class="full">
          <button type="submit" class="btn-primary">Salva dati arbitro/link</button>
        </div>
        <?php if (($successo && ($azione ?? '') === 'aggiorna_link')): ?>
          <div class="form-message success"><?= htmlspecialchars($successo) ?></div>
        <?php elseif (($errore && ($azione ?? '') === 'aggiorna_link')): ?>
          <div class="form-message error"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>
        </form>
      </div>
    </section>

    <!-- ELIMINA -->
    <section class="tab-section <?= $tabAttiva === 'elimina' ? 'active' : '' ?>" data-tab="elimina">
      <div class="form-card">
        <h3>Elimina partita</h3>
        <form method="POST" class="admin-form" id="formElimina">
        <input type="hidden" name="azione" value="elimina">
        <input type="hidden" name="partita_id" id="partitaEliminaHidden">
        <div>
          <label class="required-label">Torneo</label>
          <select id="selTorneoElim" required>
            <option value="">-- Seleziona torneo --</option>
            <?php foreach ($torneiDisponibili as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Fase</label>
          <select id="selFaseElim" required>
            <option value="">-- Seleziona fase --</option>
            <?php foreach ($fasiAmmesse as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="required-label">Giornata / Turno</label>
          <select id="selGiornataElim" required disabled>
            <option value="">-- Seleziona fase --</option>
          </select>
        </div>
        <div>
          <label class="required-label">Partita</label>
          <select id="selPartitaElim" required disabled>
            <option value="">-- Seleziona giornata/turno --</option>
          </select>
        </div>
        <button type="button" id="btnApriConfermaElimina" class="btn-danger modern-danger">Elimina partita</button>
        <?php if (($successo && ($azione ?? '') === 'elimina')): ?>
          <div class="form-message success"><?= htmlspecialchars($successo) ?></div>
        <?php elseif (($errore && ($azione ?? '') === 'elimina')): ?>
          <div class="form-message error"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>
        </form>
      </div>
    </section>

    <section class="tab-section <?= $tabAttiva === 'campi' ? 'active' : '' ?>" data-tab="campi">
      <div class="form-card">
        <h3>Gestione campi</h3>
        <p class="section-note">Qui gestisci i valori disponibili nella picklist "Campo" usata quando crei o modifichi una partita.</p>
        <form class="admin-form inline" method="POST" id="formCampoCrea">
          <input type="hidden" name="azione" value="crea_campo">
          <div class="full">
            <label class="required-label">Nuovo campo</label>
            <input type="text" name="campo_nome" maxlength="255" placeholder="Es. Centro Sportivo..." required>
          </div>
          <div class="full">
            <button type="submit" class="btn-primary">Aggiungi campo</button>
          </div>
        </form>
        <?php if (($successo && in_array($azione, ['crea_campo', 'modifica_campo', 'elimina_campo'], true))): ?>
          <div class="form-message success"><?= htmlspecialchars($successo) ?></div>
        <?php elseif (($errore && in_array($azione, ['crea_campo', 'modifica_campo', 'elimina_campo'], true))): ?>
          <div class="form-message error"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>
      </div>

      <div class="form-card">
        <h3>Campi disponibili</h3>
        <?php if (empty($campiPartita)): ?>
          <p class="empty-state">Nessun campo disponibile.</p>
        <?php else: ?>
          <div class="field-manager-list">
            <?php foreach ($campiPartita as $campoItem): ?>
              <form method="POST" class="field-row-form">
                <input type="hidden" name="campo_id" value="<?= (int)($campoItem['id'] ?? 0) ?>">
                <div>
                  <label class="required-label">Nome campo</label>
                  <input
                    type="text"
                    name="campo_nome"
                    maxlength="255"
                    value="<?= htmlspecialchars((string)($campoItem['nome'] ?? '')) ?>"
                    required
                  >
                  <small class="field-row-meta">
                    Usato in <?= (int)($campoItem['uso_totale'] ?? 0) ?> partite.
                    L'eliminazione lo rimuove solo dalla picklist e non modifica le partite già salvate.
                  </small>
                </div>
                <button type="submit" name="azione" value="modifica_campo" class="btn-secondary-modern">Salva</button>
                <button
                  type="submit"
                  name="azione"
                  value="elimina_campo"
                  class="btn-danger modern-danger"
                  onclick="return confirm('Eliminare questo campo dalla picklist? Le partite già salvate non verranno modificate.');"
                >Elimina</button>
              </form>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </section>
</main>

<div id="footer-container"></div>

<div class="confirm-modal" id="modalElimina">
  <div class="confirm-card">
    <h4>Conferma eliminazione</h4>
    <p id="modalEliminaTesto">Sei sicuro di voler eliminare questa partita?</p>
    <div class="confirm-actions">
      <button type="button" class="btn-ghost" id="btnAnnullaElimina">Annulla</button>
      <button type="button" class="modern-danger" id="btnConfermaElimina">Elimina</button>
    </div>
  </div>
</div>

<div class="confirm-modal" id="modalEliminaStat">
  <div class="confirm-card">
    <h4>Conferma eliminazione</h4>
    <p id="modalEliminaStatTesto">Sei sicuro di voler eliminare questa statistica?</p>
    <div class="confirm-actions">
      <button type="button" class="btn-ghost" id="btnAnnullaEliminaStat">Annulla</button>
      <button type="button" class="modern-danger" id="btnConfermaEliminaStat">Elimina</button>
    </div>
  </div>
</div>

<script>
  let partiteData = <?php echo json_encode($partite, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  let partiteModData = <?php echo json_encode($partiteNonGiocate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  let partiteGiocateData = <?php echo json_encode($partiteGiocate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  const torneiMeta = <?php echo json_encode($torneiDisponibili, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  const squadreMap = <?php echo json_encode($squadrePerTorneo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  const matchFieldsData = <?php echo json_encode($campiPartita, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  const roundLabelMap = {
    'TRENTADUESIMI': 6,
    'SEDICESIMI': 5,
    'OTTAVI': 4,
    'QUARTI': 3,
    'SEMIFINALE': 2,
    'FINALE': 1,
  };
  const twoLegRounds = new Set(['TRENTADUESIMI', 'SEDICESIMI', 'OTTAVI', 'QUARTI', 'SEMIFINALE']);
  const isTwoLegRound = (roundVal) => twoLegRounds.has((roundVal || '').toUpperCase());
  const roundLabelFromGiornata = Object.fromEntries(Object.entries(roundLabelMap).map(([k,v]) => [String(v), k]));
  const roundLabelByKey = roundLabelFromGiornata;
  const basePhaseOptions = <?php echo json_encode($fasiAmmesseConBronzo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  const torneoSectionMap = Object.fromEntries((torneiMeta || []).map(item => [
    String(item?.slug || ''),
    String(item?.sezione || 'calcio').trim().toLowerCase() === 'esport' ? 'esport' : 'calcio'
  ]));
  const ONLINE_MATCH_FIELD = 'Online';
  const isFormula1Tournament = (torneoSlug = '') => String(torneoSlug || '').trim().toLowerCase() === 'formula1';
  const isEsportTournament = (torneoSlug = '') => torneoSectionMap[String(torneoSlug || '').trim()] === 'esport';
  const isRegularPhase = (phase = '') => String(phase || '').trim().toUpperCase() === 'REGULAR';
  const isSpareggioPhase = (phase = '') => String(phase || '').trim().toUpperCase() === 'SPAREGGIO';
  const isRoundBasedPhase = (phase = '') => !isRegularPhase(phase) && !isSpareggioPhase(phase);
  const regularGiornateMaxDefault = <?= (int)$maxGiornateRegular ?>;
  const regularGiornateMaxOverrides = <?= json_encode($maxGiornateRegularOverrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const getRegularGiornateMax = (torneoSlug = '') => {
    const normalized = String(torneoSlug || '').trim().toLowerCase();
    for (const [slug, limit] of Object.entries(regularGiornateMaxOverrides || {})) {
      if (String(slug || '').trim().toLowerCase() === normalized) {
        const parsed = Number.parseInt(limit, 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : regularGiornateMaxDefault;
      }
    }
    return regularGiornateMaxDefault;
  };
  const getSpareggioSlotLabel = (slotValue) => {
    const normalized = String(slotValue ?? '').trim();
    return normalized === '' || normalized === '1' ? 'Spareggio' : `Spareggio ${normalized}`;
  };

  const syncMatchFieldSelect = (selectEl, torneoSlug = '', selectedValue = '', preserveUnknown = false) => {
    if (!selectEl) return;

    const currentValue = String(selectedValue ?? '').trim();
    const values = [];
    const seen = new Set();
    const addValue = (rawValue) => {
      const value = String(rawValue ?? '').trim();
      const key = value.toLowerCase();
      if (!value || seen.has(key)) return;
      seen.add(key);
      values.push(value);
    };

    (matchFieldsData || []).forEach(item => addValue(item?.nome));
    if (isEsportTournament(torneoSlug)) {
      addValue(ONLINE_MATCH_FIELD);
    }
    if (preserveUnknown) {
      addValue(currentValue);
    }

    selectEl.innerHTML = '<option value="">-- Seleziona campo --</option>';
    values.forEach(value => {
      const label = preserveUnknown && currentValue && value === currentValue && !((matchFieldsData || []).some(item => String(item?.nome || '').trim().toLowerCase() === value.toLowerCase()))
        ? `${value} (storico)`
        : value;
      selectEl.add(new Option(label, value));
    });

    const matchingValue = values.find(value => value.toLowerCase() === currentValue.toLowerCase());
    if (currentValue && matchingValue) {
      selectEl.value = matchingValue;
    } else {
      selectEl.value = '';
    }
  };

  // Tabs
  const tabButtons = document.querySelectorAll('.tab-buttons button');
  const tabSections = document.querySelectorAll('.tab-section');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      tabSections.forEach(sec => sec.classList.remove('active'));
      btn.classList.add('active');
      const target = btn.dataset.tab;
      const section = document.querySelector(`.tab-section[data-tab="${target}"]`);
      if (section) section.classList.add('active');
    });
  });

  const populateSquadre = (torneoSlug, selectId, selectedValue = '') => {
    const select = document.getElementById(selectId);
    if (!select) return;
    select.innerHTML = '<option value=\"\">-- Seleziona --</option>';
    const lista = squadreMap[torneoSlug] || [];
    lista.forEach(nome => {
      const opt = new Option(nome, nome);
      select.add(opt);
    });
    if (selectedValue) select.value = selectedValue;
  };

  const getPhaseOptionsForTournament = (torneoSlug = '') => {
    const options = [...basePhaseOptions];
    if (isFormula1Tournament(torneoSlug) && !options.includes('SPAREGGIO')) {
      options.splice(1, 0, 'SPAREGGIO');
    }
    return options;
  };

  const syncPhaseSelectOptions = (selectEl, torneoSlug = '', placeholder = '') => {
    if (!selectEl) return;
    const currentValue = (selectEl.value || '').toUpperCase();
    const options = getPhaseOptionsForTournament(torneoSlug);
    selectEl.innerHTML = '';
    if (placeholder) {
      selectEl.add(new Option(placeholder, ''));
    }
    options.forEach(phase => {
      selectEl.add(new Option(phase, phase));
    });
    if (currentValue && options.includes(currentValue)) {
      selectEl.value = currentValue;
    } else if (placeholder) {
      selectEl.value = '';
    } else if (options.length) {
      selectEl.value = options[0];
    }
  };

  const enforceDifferentTeams = (idA, idB) => {
    const a = document.getElementById(idA);
    const b = document.getElementById(idB);
    if (!a || !b) return;
    a.addEventListener('change', () => {
      if (a.value && a.value === b.value) {
        b.value = '';
      }
    });
    b.addEventListener('change', () => {
      if (a.value && a.value === b.value) {
        b.value = '';
      }
    });
  };

  const torneoCrea = document.getElementById('torneoCrea');
  const faseCrea = document.querySelector('select[name="fase"]');
  const giornataCrea = document.getElementById('giornataCrea');
  const roundCrea = document.getElementById('roundCrea');
  const faseLegCrea = document.getElementById('faseLegCrea');
  const campoCrea = document.getElementById('campoCrea');

  const syncCreateGiornataOptions = (torneoSlug = '', selectedValue = '') => {
    if (!giornataCrea) return;
    const maxGiornate = getRegularGiornateMax(torneoSlug);
    const currentValue = String(selectedValue || giornataCrea.value || '').trim();
    giornataCrea.innerHTML = `<option value="">-- Seleziona giornata (1-${maxGiornate}) --</option>`;
    for (let g = 1; g <= maxGiornate; g += 1) {
      giornataCrea.add(new Option(String(g), String(g)));
    }
    if (currentValue) {
      const hasOption = Array.from(giornataCrea.options).some(opt => opt.value === currentValue);
      giornataCrea.value = hasOption ? currentValue : '';
    } else {
      giornataCrea.value = '';
    }
  };

  syncPhaseSelectOptions(faseCrea, torneoCrea?.value || '');
  syncCreateGiornataOptions(torneoCrea?.value || '');
  syncMatchFieldSelect(campoCrea, torneoCrea?.value || '');

  const getGiornataTarget = () => {
    const faseVal = (faseCrea?.value || '').toUpperCase();
    if (isRegularPhase(faseVal)) {
      const g = parseInt(giornataCrea?.value || '', 10);
      return isNaN(g) ? null : g;
    }
    if (isSpareggioPhase(faseVal)) {
      return 1;
    }
    const lbl = roundCrea?.value || '';
    return roundLabelMap[lbl] ? roundLabelMap[lbl] : null;
  };

  const populateSquadreFiltrate = () => {
    const torneoVal = torneoCrea?.value || '';
    const faseVal = (faseCrea?.value || '').toUpperCase();
    const giornataVal = getGiornataTarget();
    const legVal = (document.getElementById('faseLegCrea')?.value || '').toUpperCase();
    const roundVal = (roundCrea?.value || '').toUpperCase();
    const isReturnMatch = faseVal !== 'REGULAR' && isTwoLegRound(roundVal) && legVal === 'RITORNO';
    const casaSel = document.getElementById('squadraCasaCrea');
    const ospSel = document.getElementById('squadraOspiteCrea');
    const resetSelect = (sel, placeholder) => {
      if (!sel) return;
      sel.innerHTML = `<option value="">${placeholder}</option>`;
      sel.disabled = true;
    };
    if (!torneoVal || !faseVal || !giornataVal) {
      resetSelect(casaSel, '-- Seleziona torneo/giornata --');
      resetSelect(ospSel, '-- Seleziona torneo/giornata --');
      return;
    }
    const lista = squadreMap[torneoVal] || [];
    const occupate = new Set();
    const returnAllowed = new Set();
    const matches = partiteData.filter(p =>
      p.torneo === torneoVal &&
      (p.fase || 'REGULAR').toUpperCase() === faseVal &&
      String(p.giornata) === String(giornataVal)
    );
    matches.forEach(p => {
      const leg = (p.fase_leg || '').toUpperCase();
      const roundDb = (p.fase_round || '').toUpperCase();
      const teams = [p.squadra_casa, p.squadra_ospite];
      const allowReturn = isReturnMatch && roundDb === roundVal && leg === 'ANDATA';
      if (allowReturn) {
        teams.forEach(t => returnAllowed.add(t));
        return;
      }
      teams.forEach(t => occupate.add(t));
    });
    const disponibili = lista.filter(nome => !occupate.has(nome) || returnAllowed.has(nome));
    const fill = (sel) => {
      if (!sel) return;
      sel.disabled = false;
      sel.innerHTML = '<option value=\"\">-- Seleziona --</option>';
      disponibili.forEach(nome => {
        sel.add(new Option(nome, nome));
      });
    };
    fill(casaSel);
    fill(ospSel);
  };

  if (torneoCrea) {
    torneoCrea.addEventListener('change', () => {
      syncPhaseSelectOptions(faseCrea, torneoCrea.value || '');
      syncCreateGiornataOptions(torneoCrea.value || '');
      syncMatchFieldSelect(campoCrea, torneoCrea.value || '', campoCrea?.value || '', false);
      populateSquadreFiltrate();
    });
  }
  if (faseCrea) {
    faseCrea.addEventListener('change', populateSquadreFiltrate);
  }
  if (giornataCrea) {
    giornataCrea.addEventListener('change', populateSquadreFiltrate);
  }
  if (roundCrea) {
    roundCrea.addEventListener('change', () => {
      refreshCreateLayout();
      populateSquadreFiltrate();
    });
  }
  if (faseLegCrea) {
    faseLegCrea.addEventListener('change', () => {
      refreshCreateLayout();
      populateSquadreFiltrate();
    });
  }
  // inizializza filtrando appena caricata la pagina
  populateSquadreFiltrate();

  [
    { torneoId: 'selTorneoMod', faseId: 'selFaseMod', placeholder: '-- Seleziona fase --' },
    { torneoId: 'torneo_mod', faseId: 'fase_mod' },
    { torneoId: 'selTorneoGioc', faseId: 'selFaseGioc', placeholder: '-- Seleziona fase --' },
    { torneoId: 'selTorneoLink', faseId: 'selFaseLink', placeholder: '-- Seleziona fase --' },
    { torneoId: 'selTorneoElim', faseId: 'selFaseElim', placeholder: '-- Seleziona fase --' }
  ].forEach(({ torneoId, faseId, placeholder = '' }) => {
    const torneoSelect = document.getElementById(torneoId);
    const faseSelect = document.getElementById(faseId);
    syncPhaseSelectOptions(faseSelect, torneoSelect?.value || '', placeholder);
    torneoSelect?.addEventListener('change', () => {
      syncPhaseSelectOptions(faseSelect, torneoSelect.value || '', placeholder);
    });
  });

  const torneoModFormSelect = document.getElementById('torneo_mod');
  torneoModFormSelect?.addEventListener('change', () => {
    const torneoVal = torneoModFormSelect.value || '';
    syncPhaseSelectOptions(document.getElementById('fase_mod'), torneoVal);
    populateSquadre(torneoVal, 'squadra_casa_mod');
    populateSquadre(torneoVal, 'squadra_ospite_mod');
    syncMatchFieldSelect(document.getElementById('campo_mod'), torneoVal, document.getElementById('campo_mod')?.value || '', false);
  });

  const renderFormMessage = (formId, type, text) => {
    const form = document.getElementById(formId);
    if (!form || !text) return;
    let box = form.querySelector('.form-inline-message');
    if (!box) {
      box = document.createElement('div');
      box.className = 'form-inline-message form-message';
      form.appendChild(box);
    }
    box.classList.toggle('success', type === 'success');
    box.classList.toggle('error', type !== 'success');
    box.textContent = text;
  };

  enforceDifferentTeams('squadraCasaCrea', 'squadraOspiteCrea');

  function toggleRoundGiornata(faseSelect, giornataWrapId, roundWrapId, legWrapId = '', legSelectId = '') {
    const phaseVal = (faseSelect?.value || '').toUpperCase();
    const isRegular = isRegularPhase(phaseVal);
    const isSpareggio = isSpareggioPhase(phaseVal);
    const isRoundPhase = isRoundBasedPhase(phaseVal);
    const giornataWrap = document.getElementById(giornataWrapId);
    const roundWrap = document.getElementById(roundWrapId);
    const legWrap = legWrapId ? document.getElementById(legWrapId) : null;
    const legSelect = legSelectId ? document.getElementById(legSelectId) : null;
    if (giornataWrap) giornataWrap.classList.toggle('hidden', !isRegular);
    if (roundWrap) roundWrap.classList.toggle('hidden', !isRoundPhase);
    const giornataField = giornataWrap ? giornataWrap.querySelector('input, select') : null;
    const roundSelect = roundWrap ? roundWrap.querySelector('select') : null;
    if (giornataField) {
      giornataField.required = isRegular;
      if (!isRegular) giornataField.value = '';
    }
    if (roundSelect) {
      roundSelect.required = isRoundPhase;
      if (!isRoundPhase) roundSelect.value = '';
    }
    const roundVal = (roundSelect?.value || '').toUpperCase();
    const isLegRound = isRoundPhase && isTwoLegRound(roundVal);

    if (legWrap) legWrap.classList.toggle('hidden', !isLegRound);
    if (legSelect) {
      if (!isLegRound) {
        legSelect.value = 'UNICA';
      }
    }

  }

  function refreshCreateLayout() {
    toggleRoundGiornata(faseCrea, 'giornataWrapper', 'roundWrapper', 'legWrapper', 'faseLegCrea');
  }

  const toggleRigoriGroup = (checkboxId, fieldsId) => {
    const chk = document.getElementById(checkboxId);
    const wrap = document.getElementById(fieldsId);
    const active = !!chk?.checked;
    if (wrap) {
      wrap.classList.toggle('hidden', !active);
      if (!active) {
        wrap.querySelectorAll('input[type="number"]').forEach(inp => { inp.value = ''; });
      }
    }
  };

  if (faseCrea) {
    refreshCreateLayout();
    faseCrea.addEventListener('change', () => {
      refreshCreateLayout();
      populateSquadreFiltrate();
    });
  }

  const chkRigoriModEl = document.getElementById('decisa_rigori_mod');
  chkRigoriModEl?.addEventListener('change', () => {
    toggleRigoriGroup('decisa_rigori_mod', 'rigoriFieldsMod');
  });

  const fillField = (id, val) => { const el = document.getElementById(id); if (el) { if (el.type === 'checkbox') { el.checked = !!val; } else { el.value = val ?? ''; } } };
  const clearModificaFields = () => {
    const ids = [
      'torneo_mod','fase_mod','squadra_casa_mod','squadra_ospite_mod',
      'gol_casa_mod','gol_ospite_mod','data_partita_mod','ora_partita_mod',
      'campo_mod','giornata_mod','round_eliminazione_mod','faseLegMod',
      'link_youtube_mod','link_instagram_mod','arbitro_mod','decisa_rigori_mod','rigori_casa_mod','rigori_ospite_mod'
    ];
    ids.forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      if (el.type === 'checkbox') {
        el.checked = false;
      } else {
        el.value = '';
      }
    });
    syncMatchFieldSelect(document.getElementById('campo_mod'), '', '', false);
    toggleRoundGiornata(document.getElementById('fase_mod'), 'giornataWrapperMod', 'roundWrapperMod', 'legWrapperMod', 'faseLegMod');
    toggleRigoriGroup('decisa_rigori_mod', 'rigoriFieldsMod');
  };

  const applyPartitaModForm = (partita) => {
    if (!partita) return;
    const btnStats = document.getElementById('btnStatsMod');
    if (btnStats) {
      btnStats.style.display = 'inline-block';
      btnStats.setAttribute('data-id', partita.id);
    }
    const torneoMod = document.getElementById('torneo_mod');
    if (torneoMod) {
      if (![...torneoMod.options].some(o => o.value === partita.torneo)) {
        const opt = new Option(partita.torneo, partita.torneo, true, true);
        torneoMod.add(opt);
      }
      torneoMod.value = partita.torneo;
      syncPhaseSelectOptions(document.getElementById('fase_mod'), partita.torneo || '');
      populateSquadre(partita.torneo, 'squadra_casa_mod', partita.squadra_casa);
      populateSquadre(partita.torneo, 'squadra_ospite_mod', partita.squadra_ospite);
    }
    fillField('fase_mod', partita.fase);
    const faseModSelect = document.getElementById('fase_mod');
    const roundSel = document.getElementById('round_eliminazione_mod');
    const giornataInput = document.getElementById('giornata_mod');
    const legModSelect = document.getElementById('faseLegMod');
    const isModRegular = isRegularPhase(partita.fase || '');
    const isModSpareggio = isSpareggioPhase(partita.fase || '');
    if (partita.fase && isRoundBasedPhase(partita.fase || '')) {
      const lbl = roundLabelFromGiornata[String(partita.giornata)] || '';
      if (roundSel) roundSel.value = lbl;
      if (giornataInput) giornataInput.value = '';
    } else {
      if (roundSel) roundSel.value = '';
      fillField('giornata_mod', isModSpareggio ? '' : partita.giornata);
    }
    if (legModSelect) {
      let legVal = (partita.fase_leg || '').toUpperCase();
      const roundVal = (roundSel?.value || '').toUpperCase();
      if (isModSpareggio) {
        legVal = 'UNICA';
      } else if (!legVal && !isModRegular) {
        legVal = isTwoLegRound(roundVal) ? 'ANDATA' : 'UNICA';
      }
      legModSelect.value = legVal || 'UNICA';
    }
    if (faseModSelect) toggleRoundGiornata(faseModSelect, 'giornataWrapperMod', 'roundWrapperMod', 'legWrapperMod', 'faseLegMod');
    fillField('gol_casa_mod', partita.gol_casa);
    fillField('gol_ospite_mod', partita.gol_ospite);
    fillField('rigori_casa_mod', partita.rigori_casa);
    fillField('rigori_ospite_mod', partita.rigori_ospite);
    const aiRigori = Number(partita.decisa_rigori || 0) === 1;
    const chkRigoriMod = document.getElementById('decisa_rigori_mod');
    if (chkRigoriMod) chkRigoriMod.checked = aiRigori;
    toggleRigoriGroup('decisa_rigori_mod', 'rigoriFieldsMod');
    fillField('data_partita_mod', partita.data_partita);
    fillField('ora_partita_mod', partita.ora_partita);
    syncMatchFieldSelect(document.getElementById('campo_mod'), partita.torneo || '', partita.campo || '', true);
    fillField('arbitro_mod', partita.arbitro || '');
    fillField('giocata_mod', Number(partita.giocata || 0) === 1);
    fillField('link_youtube_mod', partita.link_youtube);
    fillField('link_instagram_mod', partita.link_instagram);
  };

  const setupSelector = ({ torneoId, faseId, giornataId, partitaId, onPartita, getData }) => {
    const torneoSel = document.getElementById(torneoId);
    const faseSel = document.getElementById(faseId);
    const giorSel = document.getElementById(giornataId);
    const partSel = document.getElementById(partitaId);
    const dataSource = typeof getData === 'function' ? getData : () => partiteData;

    const resetSelect = (sel, placeholder) => {
      if (!sel) return;
      sel.innerHTML = `<option value=\"\">${placeholder}</option>`;
      sel.disabled = true;
    };

    const populateGiornate = () => {
      if (!torneoSel || !faseSel || !giorSel) return;
      resetSelect(giorSel, '-- Seleziona fase --');
      resetSelect(partSel, '-- Seleziona giornata/turno --');
      const torneoVal = torneoSel.value;
      const faseVal = (faseSel.value || '').toUpperCase();
      if (!torneoVal || !faseVal) return;
      const filtrate = dataSource().filter(p =>
        p.torneo === torneoVal && (p.fase || '').toUpperCase() === faseVal
      );
      const uniche = Array.from(new Set(filtrate.map(p => p.giornata === null ? '' : String(p.giornata))));
      uniche.sort((a, b) => Number(a) - Number(b));
      uniche.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g;
        if (isRegularPhase(faseVal)) {
          opt.textContent = `Giornata ${g}`;
        } else if (isSpareggioPhase(faseVal)) {
          opt.textContent = getSpareggioSlotLabel(g);
        } else {
          opt.textContent = roundLabelByKey[String(g)] || 'Turno';
        }
        giorSel.appendChild(opt);
      });
      giorSel.disabled = uniche.length === 0;
      partSel.disabled = true;
    };

    const populatePartite = () => {
      if (!torneoSel || !faseSel || !giorSel || !partSel) return;
      resetSelect(partSel, '-- Seleziona giornata/turno --');
      const torneoVal = torneoSel.value;
      const faseVal = (faseSel.value || '').toUpperCase();
      const gVal = giorSel.value;
      if (!torneoVal || !faseVal || gVal === '') return;
      const filtrate = dataSource().filter(p =>
        p.torneo === torneoVal &&
        (p.fase || '').toUpperCase() === faseVal &&
        String(p.giornata ?? '') === gVal
      );
      filtrate.forEach(p => {
        const label = `${p.squadra_casa} - ${p.squadra_ospite} (${p.data_partita} ${p.ora_partita ?? ''})`;
        const opt = new Option(label, p.id);
        partSel.add(opt);
      });
      partSel.disabled = filtrate.length === 0;
    };

    torneoSel?.addEventListener('change', populateGiornate);
    faseSel?.addEventListener('change', populateGiornate);
    giorSel?.addEventListener('change', populatePartite);
    partSel?.addEventListener('change', () => {
      const id = parseInt(partSel.value, 10);
      const partita = dataSource().find(p => parseInt(p.id, 10) === id);
      onPartita?.(partita || null);
    });
  };

  setupSelector({
    torneoId: 'selTorneoMod',
    faseId: 'selFaseMod',
    giornataId: 'selGiornataMod',
    partitaId: 'selPartitaMod',
    getData: () => partiteModData,
    onPartita: (partita) => {
      if (partita) {
        applyPartitaModForm(partita);
      } else {
        clearModificaFields();
      }
    }
  });
  enforceDifferentTeams('squadra_casa_mod', 'squadra_ospite_mod');

  setupSelector({
    torneoId: 'selTorneoGioc',
    faseId: 'selFaseGioc',
    giornataId: 'selGiornataGioc',
    partitaId: 'selPartitaGioc',
    getData: () => partiteGiocateData,
    onPartita: () => {}
  });

  setupSelector({
    torneoId: 'selTorneoLink',
    faseId: 'selFaseLink',
    giornataId: 'selGiornataLink',
    partitaId: 'selPartitaLink',
    getData: () => partiteGiocateData,
    onPartita: (partita) => {
      const fill = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val ?? '';
      };
      if (!partita) {
        fill('arbitro_link', '');
        fill('link_youtube_link', '');
        fill('link_instagram_link', '');
        return;
      }
      fill('arbitro_link', partita.arbitro || '');
      fill('link_youtube_link', partita.link_youtube || '');
      fill('link_instagram_link', partita.link_instagram || '');
    }
  });

  const faseModSelect = document.getElementById('fase_mod');
  const roundModSelect = document.getElementById('round_eliminazione_mod');
  const faseLegModSelect = document.getElementById('faseLegMod');
  const refreshModLayout = () => {
    if (faseModSelect) toggleRoundGiornata(faseModSelect, 'giornataWrapperMod', 'roundWrapperMod', 'legWrapperMod', 'faseLegMod');
  };
  if (faseModSelect) {
    faseModSelect.addEventListener('change', refreshModLayout);
    refreshModLayout();
  }
  if (roundModSelect) {
    roundModSelect.addEventListener('change', refreshModLayout);
  }
  if (faseLegModSelect) {
    faseLegModSelect.addEventListener('change', refreshModLayout);
  }

  setupSelector({
    torneoId: 'selTorneoElim',
    faseId: 'selFaseElim',
    giornataId: 'selGiornataElim',
    partitaId: 'selPartitaElim',
    onPartita: (partita) => {
      const hidden = document.getElementById('partitaEliminaHidden');
      if (hidden) hidden.value = partita ? partita.id : '';
      const testo = document.getElementById('modalEliminaTesto');
      if (testo) {
        if (partita) {
          testo.textContent = `Eliminare ${partita.squadra_casa} - ${partita.squadra_ospite} (${partita.data_partita} ${partita.ora_partita ?? ''})?`;
        } else {
          testo.textContent = 'Sei sicuro di voler eliminare questa partita?';
        }
      }
    }
  });

  const modal = document.getElementById('modalElimina');
  const btnApri = document.getElementById('btnApriConfermaElimina');
  const btnChiudi = document.getElementById('btnAnnullaElimina');
  const btnConferma = document.getElementById('btnConfermaElimina');
  const formElim = document.getElementById('formElimina');
  btnApri?.addEventListener('click', () => {
    if (modal) modal.classList.add('active');
  });
  btnChiudi?.addEventListener('click', () => modal?.classList.remove('active'));
  btnConferma?.addEventListener('click', () => {
    if (formElim) formElim.submit();
  });
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) modal.classList.remove('active');
  });

  const btnStatsMod = document.getElementById('btnStatsMod');
  btnStatsMod?.addEventListener('click', () => {
    const id = btnStatsMod.getAttribute('data-id');
    if (!id) return;
    saveModState();
    window.location.href = `/api/statistiche_partita.php?partitaid=${id}`;
  });

  // Memorizza la selezione corrente (torneo/fase/giornata/partita) per ripristinarla al ritorno dallo schermo statistiche
  function saveModState() {
    const state = {
      tab: 'modifica',
      torneo: document.getElementById('selTorneoMod')?.value || '',
      fase: document.getElementById('selFaseMod')?.value || '',
      giornata: document.getElementById('selGiornataMod')?.value || '',
      partita: document.getElementById('selPartitaMod')?.value || ''
    };
    sessionStorage.setItem('gestionePartiteModState', JSON.stringify(state));
  }

  function updatePartitaCache(partita) {
    if (!partita || !partita.id) return;
    const idStr = String(partita.id);
    const idx = partiteData.findIndex(p => String(p.id) === idStr);
    if (idx >= 0) {
      partiteData[idx] = partita;
    } else {
      partiteData.push(partita);
    }
    const removeById = (arr) => {
      const i = arr.findIndex(p => String(p.id) === idStr);
      if (i >= 0) arr.splice(i, 1);
    };
    removeById(partiteModData);
    removeById(partiteGiocateData);
    if (Number(partita.giocata || 0) === 1) {
      partiteGiocateData.push(partita);
    } else {
      partiteModData.push(partita);
    }
  }

  async function fetchPartita(id) {
    if (!id) return null;
    try {
      const res = await fetch(`/api/get_partita.php?id=${id}`);
      const data = await res.json();
      return data && !data.error ? data : null;
    } catch (e) {
      return null;
    }
  }

  // Ripristina la selezione se presente in sessionStorage
  function restoreModState() {
    const raw = sessionStorage.getItem('gestionePartiteModState');
    if (!raw) return;
    let state;
    try { state = JSON.parse(raw); } catch (e) { return; }

    // Attiva tab Modifica
    const tabBtn = document.querySelector('.tab-buttons button[data-tab="modifica"]');
    tabBtn?.click();

    const torneoSel = document.getElementById('selTorneoMod');
    const faseSel = document.getElementById('selFaseMod');
    const giorSel = document.getElementById('selGiornataMod');
    const partSel = document.getElementById('selPartitaMod');

    if (torneoSel) torneoSel.value = state.torneo || '';
    if (faseSel) faseSel.value = state.fase || '';

    // Trigger popolamento giornate/partite
    faseSel?.dispatchEvent(new Event('change'));
    torneoSel?.dispatchEvent(new Event('change'));

    if (giorSel && state.giornata !== undefined) {
      giorSel.value = state.giornata;
      giorSel.dispatchEvent(new Event('change'));
    }
    if (partSel && state.partita) {
      partSel.value = state.partita;
      partSel.dispatchEvent(new Event('change'));
    }

    // Recupera partita aggiornata (es. gol aggiornati da statistiche) e applica al form
    if (state.partita) {
      fetchPartita(state.partita).then((p) => {
        if (p) {
          updatePartitaCache(p);
          applyPartitaModForm(p);
        }
      });
    }

    sessionStorage.removeItem('gestionePartiteModState');
  }

  restoreModState();
  window.addEventListener('pageshow', () => {
    restoreModState();
    // Se una partita è selezionata, ricarica i dati aggiornati (es. gol salvati da statistiche_partita)
    const partSel = document.getElementById('selPartitaMod');
    const selectedId = partSel?.value;
    if (selectedId) {
      fetchPartita(selectedId).then(p => {
        if (!p) return;
        updatePartitaCache(p);
        applyPartitaModForm(p);
      });
    }
  });

  // Footer
  const footer = document.getElementById('footer-container');
  if (footer) {
    fetch('/includi/footer.html')
      .then(r => r.text())
      .then(html => { footer.innerHTML = html; })
      .catch(err => console.error('Errore footer:', err));
  }

  const refreshSelectorsFromData = () => {
    populateSquadreFiltrate();
    document.getElementById('selTorneoMod')?.dispatchEvent(new Event('change'));
    document.getElementById('selTorneoElim')?.dispatchEvent(new Event('change'));
    document.getElementById('selTorneoGioc')?.dispatchEvent(new Event('change'));
    document.getElementById('selTorneoLink')?.dispatchEvent(new Event('change'));
  };

  const attachAjaxForm = (formId) => {
    const form = document.getElementById(formId);
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const submitBtn = form.querySelector('button[type="submit"]');
      const oldText = submitBtn?.textContent;
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Salvataggio...'; }
      try {
        const res = await fetch(window.location.href, {
          method: 'POST',
          body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const raw = await res.text();
        let data = null;
        try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }
        if (!res.ok) {
          const msg = (data && (data.error || data.message)) || `Errore ${res.status}`;
          renderFormMessage(formId, 'error', msg);
          return;
        }
        if (!data) {
          const msg = raw && raw.includes('login.php') ? 'Sessione scaduta, accedi di nuovo.' : 'Risposta non valida dal server.';
          renderFormMessage(formId, 'error', msg);
          return;
        }
        if (data) {
          if (Array.isArray(data.partite)) {
            partiteData = data.partite;
          }
          if (Array.isArray(data.partite_non_giocate)) {
            partiteModData = data.partite_non_giocate;
          }
          if (Array.isArray(data.partite_giocate)) {
            partiteGiocateData = data.partite_giocate;
          }
          if (Array.isArray(data.partite) || Array.isArray(data.partite_non_giocate) || Array.isArray(data.partite_giocate)) {
            refreshSelectorsFromData();
          }
        }
        if (data && data.success) {
          renderFormMessage(formId, 'success', data.message || 'Operazione completata');
          if (formId === 'formCrea') {
            form.reset();
            refreshCreateLayout();
            populateSquadreFiltrate();
          } else if (formId === 'formModifica') {
            const partSel = document.getElementById('selPartitaMod');
            if (partSel) {
              partSel.value = '';
              partSel.dispatchEvent(new Event('change'));
            }
          } else if (formId === 'formRipristinaGiocata') {
            form.reset();
          }
        } else {
          renderFormMessage(formId, 'error', data?.error || 'Errore nel salvataggio');
        }
      } catch (err) {
        renderFormMessage(formId, 'error', 'Errore di rete, riprova.');
      } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = oldText; }
      }
    });
  };

  attachAjaxForm('formCrea');
  attachAjaxForm('formModifica');
  attachAjaxForm('formRipristinaGiocata');
  attachAjaxForm('formLinkArbitro');
</script>

</body>
</html>
