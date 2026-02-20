<?php
require_once __DIR__ . '/../includi/admin_guard.php';

require_once __DIR__ . '/../includi/db.php';

$errore = '';
$successo = '';
$torneiDisponibili = [];
$squadrePerTorneo = [];
$fasiAmmesse = ['REGULAR', 'GOLD', 'SILVER'];
$roundMap = [
  'TRENTADUESIMI' => 6,
  'SEDICESIMI' => 5,
  'OTTAVI' => 4,
  'QUARTI' => 3,
  'SEMIFINALE' => 2,
  'FINALE' => 1,
];

$torneiRes = $conn->query("SELECT nome, filetorneo FROM tornei WHERE stato <> 'terminato' ORDER BY nome ASC");
if ($torneiRes) {
  while ($row = $torneiRes->fetch_assoc()) {
    $slug = preg_replace('/\.(html?|php)$/i', '', $row['filetorneo'] ?? '');
    $torneiDisponibili[] = [
      'nome' => $row['nome'] ?: $slug,
      'slug' => $slug,
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

function sanitize_leg(?string $v): ?string {
  $val = strtoupper(trim((string)$v));
  $allowed = ['ANDATA','RITORNO','UNICA'];
  if (!$val) return null;
  return in_array($val, $allowed, true) ? $val : null;
}

function round_to_giornata(?string $roundLabel, array $map): ?int {
  if ($roundLabel === null) return null;
  $key = strtoupper(trim($roundLabel));
  return $map[$key] ?? null;
}

function giornata_to_roundLabel(?int $giornata, array $map): ?string {
  if ($giornata === null) return null;
  $flip = array_flip($map);
  return $flip[$giornata] ?? null;
}

// Assicura che le colonne/tabella di supporto notifiche esistano
ensure_notifiche_table($conn);
ensure_follow_table($conn);
ensure_partite_notifica_flag($conn);
ensure_partite_unique_index($conn);
ensure_rigori_columns($conn);

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

/**
 * Garantisce che la squadra esista per il torneo indicato.
 * Se non esiste, la crea con statistiche a zero e prova a riutilizzare
 * un eventuale logo esistente per lo stesso nome su altri tornei.
 */
function ensure_squadra_exists(mysqli $conn, string $torneo, string $nome): void {
  $torneo = trim($torneo);
  $nome = normalize_team_name($nome);
  if ($torneo === '' || $nome === '') return;

  $check = $conn->prepare("SELECT 1 FROM squadre WHERE torneo = ? AND nome = ? LIMIT 1");
  if ($check) {
    $check->bind_param('ss', $torneo, $nome);
    if ($check->execute() && $check->get_result()->fetch_row()) {
      $check->close();
      return; // già presente
    }
    $check->close();
  }

  // tenta di riciclare un logo se esiste per lo stesso nome (altro torneo)
  $logo = null;
  $logoStmt = $conn->prepare("SELECT logo FROM squadre WHERE nome = ? AND logo IS NOT NULL AND logo <> '' ORDER BY created_at DESC, id DESC LIMIT 1");
  if ($logoStmt) {
    $logoStmt->bind_param('s', $nome);
    if ($logoStmt->execute()) {
      $resLogo = $logoStmt->get_result()->fetch_assoc();
      $logo = $resLogo['logo'] ?? null;
    }
    $logoStmt->close();
  }

  $ins = $conn->prepare("INSERT INTO squadre (nome, torneo, logo) VALUES (?, ?, ?)");
  if ($ins) {
    $ins->bind_param('sss', $nome, $torneo, $logo);
    $ins->execute();
    $ins->close();
  }
}

function applica_risultato_classifica(mysqli $conn, string $torneo, string $squadra, int $gf, int $gs): void {
  $torneo = normalize_torneo($torneo);
  $squadra = normalize_team_name($squadra);
  ensure_squadra_exists($conn, $torneo, $squadra);

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
    WHERE torneo = ? AND nome = ?
    LIMIT 1
  ");
  if ($stmt) {
    $stmt->bind_param(
      'iiiiiiis',
      $vittoria,
      $pareggio,
      $sconfitta,
      $punti,
      $gf,
      $gs,
      $torneo,
      $squadra
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
  if ($torneo === '') return;
  $sql = "
    UPDATE partite p
    SET giocata = 1
    WHERE p.torneo = ?
      AND p.giocata = 0
      AND (
        p.gol_casa IS NOT NULL OR p.gol_ospite IS NOT NULL
        OR EXISTS (SELECT 1 FROM partita_giocatore pg WHERE pg.partita_id = p.id)
      )
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
    SELECT squadra_casa,
           squadra_ospite,
           COALESCE(gol_casa,0)   AS gol_casa,
           COALESCE(gol_ospite,0) AS gol_ospite
    FROM partite
    WHERE torneo = ?
      AND giocata = 1
      AND UPPER(
            CASE
              WHEN TRIM(COALESCE(fase, '')) IN ('', 'GIRONE') THEN 'REGULAR'
              ELSE TRIM(COALESCE(fase, ''))
            END
          ) = 'REGULAR'
  ");
  if (!$sel) return;
  $sel->bind_param('s', $torneo);
  if ($sel->execute()) {
    $res = $sel->get_result();
    while ($row = $res->fetch_assoc()) {
      $casa = normalize_team_name($row['squadra_casa'] ?? '');
      $osp = normalize_team_name($row['squadra_ospite'] ?? '');
      ensure_squadra_exists($conn, $torneo, $casa);
      ensure_squadra_exists($conn, $torneo, $osp);
      applica_risultato_classifica($conn, $torneo, $casa, (int)$row['gol_casa'], (int)$row['gol_ospite']);
      applica_risultato_classifica($conn, $torneo, $osp, (int)$row['gol_ospite'], (int)$row['gol_casa']);
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
  $q = $conn->prepare("
    SELECT 
      COUNT(*) AS presenze,
      COALESCE(SUM(goal), 0) AS goal,
      COALESCE(SUM(assist), 0) AS assist,
      COALESCE(SUM(cartellino_giallo), 0) AS gialli,
      COALESCE(SUM(cartellino_rosso), 0) AS rossi,
      SUM(CASE WHEN voto IS NOT NULL THEN voto ELSE 0 END) AS somma_voti,
      SUM(CASE WHEN voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
    FROM partita_giocatore
    WHERE giocatore_id = ?
  ");
  if (!$q) return;
  $q->bind_param('i', $giocatoreId);
  $q->execute();
  $r = $q->get_result()->fetch_assoc() ?: [];
  $q->close();

  $media = ($r['num_voti'] ?? 0) > 0 ? round(($r['somma_voti'] ?? 0) / $r['num_voti'], 2) : null;
  $upd = $conn->prepare("
    UPDATE giocatori
    SET presenze = ?, reti = ?, assist = ?, gialli = ?, rossi = ?, media_voti = ?
    WHERE id = ?
  ");
  if ($upd) {
    $upd->bind_param(
      'iiiiidi',
      $r['presenze'],
      $r['goal'],
      $r['assist'],
      $r['gialli'],
      $r['rossi'],
      $media,
      $giocatoreId
    );
    $upd->execute();
    $upd->close();
  }
}

function aggiornaStatsGiocatoreSquadra(mysqli $conn, int $giocatoreId, int $squadraId): void {
  $teamInfo = $conn->prepare("SELECT nome, torneo FROM squadre WHERE id = ?");
  if (!$teamInfo) return;
  $teamInfo->bind_param('i', $squadraId);
  $teamInfo->execute();
  $team = $teamInfo->get_result()->fetch_assoc();
  $teamInfo->close();
  if (!$team) return;

  $isCoppaAfrica = strtoupper($team['torneo'] ?? '') === 'COPPADAFRICA';
  $phaseFilter = $isCoppaAfrica ? "" : "AND UPPER(CASE WHEN TRIM(p.fase) IN ('', 'GIRONE') THEN 'REGULAR' ELSE TRIM(p.fase) END) = 'REGULAR'";

  $sql = "
    SELECT 
      COUNT(*) AS presenze,
      COALESCE(SUM(pg.goal), 0) AS goal,
      COALESCE(SUM(pg.assist), 0) AS assist,
      COALESCE(SUM(pg.cartellino_giallo), 0) AS gialli,
      COALESCE(SUM(pg.cartellino_rosso), 0) AS rossi,
      SUM(CASE WHEN pg.voto IS NOT NULL THEN pg.voto ELSE 0 END) AS somma_voti,
      SUM(CASE WHEN pg.voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
    FROM partita_giocatore pg
    JOIN partite p ON p.id = pg.partita_id
    WHERE pg.giocatore_id = ?
      AND p.torneo = ?
      AND (p.squadra_casa = ? OR p.squadra_ospite = ?)
      $phaseFilter
  ";
  $q = $conn->prepare($sql);
  if (!$q) return;
  $q->bind_param('isss', $giocatoreId, $team['torneo'], $team['nome'], $team['nome']);
  $q->execute();
  $r = $q->get_result()->fetch_assoc() ?: [];
  $q->close();

  $media = ($r['num_voti'] ?? 0) > 0 ? round(($r['somma_voti'] ?? 0) / $r['num_voti'], 2) : null;
  $upd = $conn->prepare("
    UPDATE squadre_giocatori
    SET presenze = ?, reti = ?, assist = ?, gialli = ?, rossi = ?, media_voti = ?
    WHERE giocatore_id = ? AND squadra_id = ?
  ");
  if ($upd) {
    $upd->bind_param(
      'iiiiidii',
      $r['presenze'],
      $r['goal'],
      $r['assist'],
      $r['gialli'],
      $r['rossi'],
      $media,
      $giocatoreId,
      $squadraId
    );
    $upd->execute();
    $upd->close();
  }
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
  if (empty($userIds)) return;
  ensure_notifiche_table($conn);
  $stmt = $conn->prepare("INSERT INTO notifiche (utente_id, tipo, titolo, testo, link) VALUES (?, ?, ?, ?, ?)");
  if (!$stmt) return;
  foreach ($userIds as $uid) {
    $uid = (int)$uid;
    $stmt->bind_param('issss', $uid, $tipo, $titolo, $testo, $link);
    $stmt->execute();
  }
  $stmt->close();
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
    WHERE torneo = ? AND fase = ? AND giornata = ?
      AND (squadra_casa = ? OR squadra_ospite = ? OR squadra_casa = ? OR squadra_ospite = ?)
  ";
  $types = "sisssss";
  $params = [$torneo, $fase, $giornata, $casa, $casa, $ospite, $ospite];
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

    // consenti andata/ritorno in semifinale con coppia identica ma leg diverso
    $isSemiTarget = $targetRound === 'SEMIFINALE';
    $isSemiRow = $rowRound === 'SEMIFINALE';
    if ($isSemiTarget && $isSemiRow && $samePair && $targetLeg && $rowLeg) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $azione = $_POST['azione'] ?? '';

  if ($azione === 'crea') {
    $torneo = sanitize_text($_POST['torneo'] ?? '');
    $fase = sanitize_fase($_POST['fase'] ?? '', $fasiAmmesse);
    $casa = sanitize_text($_POST['squadra_casa'] ?? '');
    $ospite = sanitize_text($_POST['squadra_ospite'] ?? '');
    $data = sanitize_text($_POST['data_partita'] ?? '');
    $ora = sanitize_text($_POST['ora_partita'] ?? '');
    $campo = sanitize_text($_POST['campo'] ?? '');
    $roundSelezionato = sanitize_text($_POST['round_eliminazione'] ?? '');
    $giornata = sanitize_int($_POST['giornata'] ?? '');
    $faseRound = $fase !== 'REGULAR' ? strtoupper($roundSelezionato) : null;
    $faseRound = ($faseRound && in_array($faseRound, ['OTTAVI','QUARTI','SEMIFINALE','FINALE'], true)) ? $faseRound : null;
    $faseLegInput = $fase !== 'REGULAR' ? sanitize_leg($_POST['fase_leg'] ?? '') : null;
    $faseLeg = $fase !== 'REGULAR'
      ? ($faseLegInput ?: ($faseRound === 'SEMIFINALE' ? 'ANDATA' : 'UNICA'))
      : null;
    $giocata = 0; // sempre non giocata alla creazione
    $gol_casa = sanitize_int($_POST['gol_casa'] ?? '0');
    $gol_ospite = sanitize_int($_POST['gol_ospite'] ?? '0');
    $link_youtube = sanitize_text($_POST['link_youtube'] ?? '');
    $link_instagram = sanitize_text($_POST['link_instagram'] ?? '');
    $arbitro = sanitize_text($_POST['arbitro'] ?? '');
    $decisa_rigori = isset($_POST['decisa_rigori']) ? 1 : 0;
    $rigori_casa = $decisa_rigori ? sanitize_int($_POST['rigori_casa'] ?? '') : null;
    $rigori_ospite = $decisa_rigori ? sanitize_int($_POST['rigori_ospite'] ?? '') : null;

    if ($torneo === '' || $fase === '' || $casa === '' || $ospite === '' || $data === '' || $ora === '' || $campo === '' || ($fase === 'REGULAR' && $giornata <= 0) || ($fase !== 'REGULAR' && $roundSelezionato === '')) {
      $errore = 'Compila tutti i campi obbligatori.';
    } elseif ($casa === $ospite) {
      $errore = 'Le due squadre non possono coincidere.';
    } elseif ($fase !== 'REGULAR' && !$faseLeg) {
      $errore = 'Seleziona il tipo di gara (andata/ritorno/unica).';
    } else {
      if ($fase !== 'REGULAR') {
        $giornata = round_to_giornata($roundSelezionato, $roundMap) ?? 0;
      }
      // controllo: una squadra non può avere due partite nella stessa giornata della stessa fase
      if (squadraHaGiaPartita($conn, $torneo, $fase, $giornata, $casa, $ospite, null, $faseRound, $faseLeg)) {
        $errore = 'Una delle squadre ha già una partita in questa giornata per questa fase.';
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
            // crea automaticamente il ritorno per le semifinali andata con dati da definire
            if ($faseRound === 'SEMIFINALE' && strtoupper($faseLeg) === 'ANDATA') {
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
    $fase = sanitize_fase($_POST['fase_mod'] ?? '', $fasiAmmesse);
    $casa = sanitize_text($_POST['squadra_casa_mod'] ?? '');
    $ospite = sanitize_text($_POST['squadra_ospite_mod'] ?? '');
    $data = sanitize_text($_POST['data_partita_mod'] ?? '');
    $ora = sanitize_text($_POST['ora_partita_mod'] ?? '');
    $campo = sanitize_text($_POST['campo_mod'] ?? '');
    $roundSelezionato = sanitize_text($_POST['round_eliminazione_mod'] ?? '');
    $giornata = sanitize_int($_POST['giornata_mod'] ?? '');
    $faseRound = $fase !== 'REGULAR' ? strtoupper($roundSelezionato) : null;
    $faseLegInput = $fase !== 'REGULAR' ? sanitize_leg($_POST['fase_leg_mod'] ?? '') : null;
    $faseLeg = $fase !== 'REGULAR'
      ? ($faseLegInput ?: ($faseRound === 'SEMIFINALE' ? 'ANDATA' : 'UNICA'))
      : null;
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

    if ($id <= 0 || $torneo === '' || $fase === '' || $casa === '' || $ospite === '' || $data === '' || $ora === '' || $campo === '' || ($fase === 'REGULAR' && $giornata <= 0) || ($fase !== 'REGULAR' && $roundSelezionato === '')) {
      $errore = 'Seleziona una partita e compila i campi obbligatori.';
    } elseif ($casa === $ospite) {
      $errore = 'Le due squadre non possono coincidere.';
    } elseif ($fase !== 'REGULAR' && !$faseLeg) {
      $errore = 'Seleziona il tipo di gara (andata/ritorno/unica).';
    } else {
      if ($fase !== 'REGULAR') {
        $giornata = round_to_giornata($roundSelezionato, $roundMap) ?? 0;
      }
      if (squadraHaGiaPartita($conn, $torneo, $fase, $giornata, $casa, $ospite, $id, $faseRound, $faseLeg)) {
        $errore = 'Una delle squadre ha già una partita in questa giornata per questa fase.';
      }
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
}

// AJAX helper: se la richiesta chiede JSON, rispondiamo senza ricaricare tutta la pagina
$isAjax = (
  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
  (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

$partite = [];
$partiteNonGiocate = [];
$partiteGiocate = [];
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
    'partite' => $partite,
    'partite_non_giocate' => $partiteNonGiocate,
    'partite_giocate' => $partiteGiocate,
  ]);
  exit;
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
  </style>
</head>
<body>
<?php include __DIR__ . '/../includi/header.php'; ?>

<main class="admin-wrapper">
  <section class="admin-container">
    <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
    <h1 class="admin-title">Gestione Partite</h1>

    <div class="tab-buttons">
      <button type="button" data-tab="crea" class="active">Crea</button>
      <button type="button" data-tab="modifica">Modifica</button>
      <button type="button" data-tab="elimina">Elimina</button>
    </div>

    <!-- CREA -->
    <section class="tab-section active" data-tab="crea">
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
          <select name="giornata" id="giornataCrea" required>
            <option value="">-- Seleziona giornata (1-8) --</option>
            <?php for ($g = 1; $g <= 8; $g++): ?>
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
          <select name="campo" required>
            <option value="">-- Seleziona campo --</option>
            <option value="Sporting Club San Francesco, Napoli">Sporting Club San Francesco, Napoli</option>
            <option value="Centro Sportivo La Paratina, Napoli">Centro Sportivo La Paratina, Napoli</option>
            <option value="Sporting S.Antonio, Napoli">Sporting S.Antonio, Napoli</option>
            <option value="La Boutique del Calcio, Napoli">La Boutique del Calcio, Napoli</option>
            <option value="Gioventu' Partenope">Gioventu' Partenope, Melito di Napoli</option>
            <option value="Campo Centrale del Parco Corto Maltese, Napoli">Campo Centrale del Parco Corto Maltese, Napoli</option>
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
    <section class="tab-section" data-tab="modifica">
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
            <option value="">-- Seleziona campo --</option>
            <option value="Sporting Club San Francesco, Napoli">Sporting Club San Francesco, Napoli</option>
            <option value="Centro Sportivo La Paratina, Napoli">Centro Sportivo La Paratina, Napoli</option>
            <option value="Sporting S.Antonio, Napoli">Sporting S.Antonio, Napoli</option>
            <option value="La Boutique del Calcio, Napoli">La Boutique del Calcio, Napoli</option>
            <option value="Gioventu' Partenope">Gioventu' Partenope, Melito di Napoli</option>
            <option value="Campo Centrale del Parco Corto Maltese, Napoli">Campo Centrale del Parco Corto Maltese, Napoli</option>
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
          <input type="checkbox" name="giocata_mod" id="giocata_mod" value="1" checked>
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
    <section class="tab-section" data-tab="elimina">
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
  const squadreMap = <?php echo json_encode($squadrePerTorneo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  const roundLabelMap = {
    'TRENTADUESIMI': 6,
    'SEDICESIMI': 5,
    'OTTAVI': 4,
    'QUARTI': 3,
    'SEMIFINALE': 2,
    'FINALE': 1,
  };
  const roundLabelFromGiornata = Object.fromEntries(Object.entries(roundLabelMap).map(([k,v]) => [String(v), k]));
  const roundLabelByKey = roundLabelFromGiornata;

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

  const getGiornataTarget = () => {
    const faseVal = (faseCrea?.value || '').toUpperCase();
    if (faseVal === 'REGULAR') {
      const g = parseInt(giornataCrea?.value || '', 10);
      return isNaN(g) ? null : g;
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
    const isSemiReturn = faseVal !== 'REGULAR' && roundVal === 'SEMIFINALE' && legVal === 'RITORNO';
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
    const semiAllowed = new Set();
    const matches = partiteData.filter(p =>
      p.torneo === torneoVal &&
      (p.fase || 'REGULAR').toUpperCase() === faseVal &&
      String(p.giornata) === String(giornataVal)
    );
    matches.forEach(p => {
      const leg = (p.fase_leg || '').toUpperCase();
      const roundDb = (p.fase_round || '').toUpperCase();
      const teams = [p.squadra_casa, p.squadra_ospite];
      const allowReturn = isSemiReturn && roundDb === 'SEMIFINALE' && leg === 'ANDATA';
      if (allowReturn) {
        teams.forEach(t => semiAllowed.add(t));
        return;
      }
      teams.forEach(t => occupate.add(t));
    });
    const disponibili = lista.filter(nome => !occupate.has(nome) || semiAllowed.has(nome));
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
    torneoCrea.addEventListener('change', populateSquadreFiltrate);
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
    const isRegular = (faseSelect.value || '').toUpperCase() === 'REGULAR';
    const giornataWrap = document.getElementById(giornataWrapId);
    const roundWrap = document.getElementById(roundWrapId);
    const legWrap = legWrapId ? document.getElementById(legWrapId) : null;
    const legSelect = legSelectId ? document.getElementById(legSelectId) : null;
    if (giornataWrap) giornataWrap.classList.toggle('hidden', !isRegular);
    if (roundWrap) roundWrap.classList.toggle('hidden', isRegular);
    const giornataField = giornataWrap ? giornataWrap.querySelector('input, select') : null;
    const roundSelect = roundWrap ? roundWrap.querySelector('select') : null;
    if (giornataField) {
      giornataField.required = isRegular;
      if (!isRegular) giornataField.value = '';
    }
    if (roundSelect) {
      roundSelect.required = !isRegular;
      if (isRegular) roundSelect.value = '';
    }
    const roundVal = (roundSelect?.value || '').toUpperCase();
    const isLegRound = !isRegular && ['OTTAVI','QUARTI','SEMIFINALE'].includes(roundVal);

    if (legWrap) legWrap.classList.toggle('hidden', !isLegRound);
    if (legSelect) {
      if (!isLegRound) {
        legSelect.value = 'UNICA';
      } else if (roundVal !== 'SEMIFINALE' && legSelect.value === 'RITORNO') {
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
      populateSquadre(partita.torneo, 'squadra_casa_mod', partita.squadra_casa);
      populateSquadre(partita.torneo, 'squadra_ospite_mod', partita.squadra_ospite);
    }
    fillField('fase_mod', partita.fase);
    const faseModSelect = document.getElementById('fase_mod');
    const roundSel = document.getElementById('round_eliminazione_mod');
    const giornataInput = document.getElementById('giornata_mod');
    const legModSelect = document.getElementById('faseLegMod');
    const isModRegular = (partita.fase || '').toUpperCase() === 'REGULAR';
    if (partita.fase && !isModRegular) {
      const lbl = roundLabelFromGiornata[String(partita.giornata)] || '';
      if (roundSel) roundSel.value = lbl;
      if (giornataInput) giornataInput.value = '';
    } else {
      if (roundSel) roundSel.value = '';
      fillField('giornata_mod', partita.giornata);
    }
    if (legModSelect) {
      let legVal = (partita.fase_leg || '').toUpperCase();
      const roundVal = (roundSel?.value || '').toUpperCase();
      if (!legVal && !isModRegular) {
        legVal = roundVal === 'SEMIFINALE' ? 'ANDATA' : 'UNICA';
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
    fillField('campo_mod', partita.campo);
    fillField('arbitro_mod', partita.arbitro || '');
    // in modifica flag giocata sempre settato a true
    fillField('giocata_mod', true);
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
        if (faseVal === 'REGULAR') {
          opt.textContent = `Giornata ${g}`;
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
