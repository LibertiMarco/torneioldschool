<?php
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/crud/partita.php';
header('Content-Type: application/json; charset=utf-8');

$azione = $_GET['azione'] ?? $_POST['azione'] ?? '';

/* ==========================================================
   LISTA STATISTICHE DELLA PARTITA
========================================================== */
if ($azione === 'list') {

    if (empty($_GET['partita_id'])) { echo json_encode([]); exit; }

    $partita_id = (int)$_GET['partita_id'];

    $sql = "SELECT 
                pg.id,
                pg.partita_id,
                pg.giocatore_id,
                g.nome,
                g.cognome,
                s.nome AS squadra,
                pg.goal,
                pg.assist,
                pg.cartellino_giallo,
                pg.cartellino_rosso,
                pg.voto
            FROM partita_giocatore pg
            JOIN giocatori g ON g.id = pg.giocatore_id
            JOIN partite p ON p.id = pg.partita_id
            JOIN squadre s ON s.torneo = p.torneo AND s.nome IN (p.squadra_casa, p.squadra_ospite)
            JOIN squadre_giocatori sg ON sg.squadra_id = s.id AND sg.giocatore_id = g.id
            WHERE pg.partita_id = ?
            ORDER BY g.cognome, g.nome";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $partita_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $out = [];

    while ($row = $res->fetch_assoc()) { $out[] = $row; }

    echo json_encode($out);
    exit;
}

function aggiornaGiocatoreGlobale(mysqli $conn, int $giocatoreId): void {
    $q = $conn->prepare("SELECT 
        COUNT(*) AS presenze,
        COALESCE(SUM(goal),0) AS goal,
        COALESCE(SUM(assist),0) AS assist,
        COALESCE(SUM(cartellino_giallo),0) AS gialli,
        COALESCE(SUM(cartellino_rosso),0) AS rossi,
        SUM(CASE WHEN voto IS NOT NULL THEN voto ELSE 0 END) AS somma_voti,
        SUM(CASE WHEN voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
        FROM partita_giocatore WHERE giocatore_id=?");
    $q->bind_param("i", $giocatoreId);
    $q->execute();
    $r = $q->get_result()->fetch_assoc() ?: [];
    $media = ($r['num_voti'] ?? 0) > 0 ? round(($r['somma_voti'] ?? 0) / $r['num_voti'], 2) : null;
    $upd = $conn->prepare("UPDATE giocatori SET presenze=?, reti=?, assist=?, gialli=?, rossi=?, media_voti=? WHERE id=?");
    $upd->bind_param(
        "iiiiidi",
        $r['presenze'],
        $r['goal'],
        $r['assist'],
        $r['gialli'],
        $r['rossi'],
        $media,
        $giocatoreId
    );
    $upd->execute();
}

function aggiornaGiocatoreSquadra(mysqli $conn, int $giocatoreId, int $squadraId): void {
    $teamInfo = $conn->prepare("SELECT nome, torneo FROM squadre WHERE id=?");
    $teamInfo->bind_param("i", $squadraId);
    $teamInfo->execute();
    $t = $teamInfo->get_result()->fetch_assoc();
    if (!$t) return;
    $nome = $t['nome'];
    $torneo = $t['torneo'];

    // Considera solo le partite di Regular Season per le statistiche di squadra/torneo
    $q = $conn->prepare("SELECT 
        COUNT(*) AS presenze,
        COALESCE(SUM(pg.goal),0) AS goal,
        COALESCE(SUM(pg.assist),0) AS assist,
        COALESCE(SUM(pg.cartellino_giallo),0) AS gialli,
        COALESCE(SUM(pg.cartellino_rosso),0) AS rossi,
        SUM(CASE WHEN pg.voto IS NOT NULL THEN pg.voto ELSE 0 END) AS somma_voti,
        SUM(CASE WHEN pg.voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
        FROM partita_giocatore pg
        JOIN partite p ON p.id = pg.partita_id
        WHERE pg.giocatore_id = ?
          AND p.torneo = ?
          AND (p.squadra_casa = ? OR p.squadra_ospite = ?)
          AND UPPER(COALESCE(p.fase, 'REGULAR')) = 'REGULAR'");
    $q->bind_param("isss", $giocatoreId, $torneo, $nome, $nome);
    $q->execute();
    $r = $q->get_result()->fetch_assoc() ?: [];
    $media = ($r['num_voti'] ?? 0) > 0 ? round(($r['somma_voti'] ?? 0) / $r['num_voti'], 2) : null;

    $upd = $conn->prepare("UPDATE squadre_giocatori SET presenze=?, reti=?, assist=?, gialli=?, rossi=?, media_voti=? WHERE giocatore_id=? AND squadra_id=?");
    $upd->bind_param(
        "iiiiidii",
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
}

function squadraPerPartitaGiocatore(mysqli $conn, int $partitaId, int $giocatoreId): ?int {
    $q = $conn->prepare("SELECT p.torneo, p.squadra_casa, p.squadra_ospite FROM partite p WHERE p.id=?");
    $q->bind_param("i", $partitaId);
    $q->execute();
    $p = $q->get_result()->fetch_assoc();
    if (!$p) return null;

    $sq = $conn->prepare("SELECT sg.squadra_id FROM squadre_giocatori sg JOIN squadre s ON s.id = sg.squadra_id WHERE sg.giocatore_id=? AND s.torneo=? AND s.nome IN (?, ?) LIMIT 1");
    $sq->bind_param("isss", $giocatoreId, $p['torneo'], $p['squadra_casa'], $p['squadra_ospite']);
    $sq->execute();
    $res = $sq->get_result()->fetch_assoc();
    return $res['squadra_id'] ?? null;
}

function fasePartita(mysqli $conn, int $partitaId): string {
    $q = $conn->prepare("SELECT UPPER(COALESCE(fase, 'REGULAR')) AS fase FROM partite WHERE id=?");
    $q->bind_param("i", $partitaId);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    return $r['fase'] ?? 'REGULAR';
}

function ricalcolaStatistiche(mysqli $conn, int $partitaId, int $giocatoreId): void {
    aggiornaGiocatoreGlobale($conn, $giocatoreId);
    $fase = fasePartita($conn, $partitaId);
    $squadraId = squadraPerPartitaGiocatore($conn, $partitaId, $giocatoreId);
    // aggiorna le stats per squadra/torneo solo in Regular Season
    if ($squadraId && $fase === 'REGULAR') {
      aggiornaGiocatoreSquadra($conn, $giocatoreId, $squadraId);
    }
}

/**
 * Aggiorna automaticamente i gol della partita sommando quelli inseriti nelle statistiche.
 * Somma i goal per squadra (casa/ospite) e li scrive su partite.gol_casa / partite.gol_ospite.
 */
function aggiornaGolPartita(mysqli $conn, int $partitaId): ?array {
    $partInfo = $conn->prepare("SELECT torneo, fase, squadra_casa, squadra_ospite, gol_casa AS old_gol_casa, gol_ospite AS old_gol_ospite FROM partite WHERE id=?");
    $partInfo->bind_param("i", $partitaId);
    $partInfo->execute();
    $p = $partInfo->get_result()->fetch_assoc();
    if (!$p) return null;

    // Somma i gol per squadra aggregando per nome squadra (evita duplicazioni per giocatori trasferiti)
    $sumSql = $conn->prepare("
        SELECT 
          SUM(CASE WHEN agg.squadra = p.squadra_casa THEN agg.gol ELSE 0 END) AS gol_casa,
          SUM(CASE WHEN agg.squadra = p.squadra_ospite THEN agg.gol ELSE 0 END) AS gol_osp
        FROM partite p
        LEFT JOIN (
          SELECT pg.partita_id, s.nome AS squadra, SUM(pg.goal) AS gol
          FROM partita_giocatore pg
          JOIN partite pp ON pp.id = pg.partita_id
          JOIN squadre_giocatori sg ON sg.giocatore_id = pg.giocatore_id
          JOIN squadre s ON s.id = sg.squadra_id
          WHERE pg.partita_id = ?
            AND s.torneo = pp.torneo
            AND s.nome IN (pp.squadra_casa, pp.squadra_ospite)
          GROUP BY pg.partita_id, s.nome
        ) AS agg ON agg.partita_id = p.id
        WHERE p.id = ?
    ");
    $sumSql->bind_param("ii", $partitaId, $partitaId);
    $sumSql->execute();
    $res = $sumSql->get_result()->fetch_assoc() ?: [];

    $golCasa = (int)($res['gol_casa'] ?? 0);
    $golOsp = (int)($res['gol_osp'] ?? 0);

    $upd = $conn->prepare("UPDATE partite SET gol_casa = ?, gol_ospite = ? WHERE id = ?");
    $upd->bind_param("iii", $golCasa, $golOsp, $partitaId);
    $upd->execute();

    return [
        'partita_id' => $partitaId,
        'torneo' => $p['torneo'],
        'fase' => $p['fase'] ?? 'REGULAR',
        'squadra_casa' => $p['squadra_casa'],
        'squadra_ospite' => $p['squadra_ospite'],
        'gol_casa' => $golCasa,
        'gol_ospite' => $golOsp,
        'old_gol_casa' => (int)($p['old_gol_casa'] ?? 0),
        'old_gol_ospite' => (int)($p['old_gol_ospite'] ?? 0)
    ];
}

function aggiornaClassificaDaInfo(?array $info): void {
    if (!$info) return;
    $partitaModel = new Partita();
    $vecchi = [
        'torneo' => $info['torneo'],
        'squadra_casa' => $info['squadra_casa'],
        'squadra_ospite' => $info['squadra_ospite'],
        'gol_casa' => $info['old_gol_casa'] ?? 0,
        'gol_ospite' => $info['old_gol_ospite'] ?? 0,
        'fase' => $info['fase'] ?? 'REGULAR'
    ];
    $partitaModel->aggiornaClassifica(
        $info['torneo'],
        $info['squadra_casa'],
        $info['squadra_ospite'],
        $info['gol_casa'],
        $info['gol_ospite'],
        $vecchi,
        $info['fase'] ?? 'REGULAR'
    );
}

/* ==========================================================
   NOTIFICHE RISULTATO FINALE
========================================================== */
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

function ensure_partite_notifica_flag(mysqli $conn): void {
    $col = $conn->query("SHOW COLUMNS FROM partite LIKE 'notifica_esito_inviata'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE partite ADD COLUMN notifica_esito_inviata TINYINT(1) NOT NULL DEFAULT 0");
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

// assicura setup tabelle/colonne all'import del file
ensure_notifiche_table($conn);
ensure_follow_table($conn);
ensure_partite_notifica_flag($conn);

/* ==========================================================
   LISTA GIOCATORI DISPONIBILI PER LA PARTITA
   (solo squadre in campo E NON giÃ  inseriti)
========================================================== */
if ($azione === 'list_giocatori') {

    if (empty($_GET['partita_id'])) { echo json_encode([]); exit; }

    $partita_id = (int)$_GET['partita_id'];

    // Recupero info partita (torneo, squadre)
    $q = $conn->prepare("SELECT torneo, squadra_casa, squadra_ospite FROM partite WHERE id=?");
    $q->bind_param("i", $partita_id);
    $q->execute();
    $p = $q->get_result()->fetch_assoc();

    if (!$p) { echo json_encode([]); exit; }

    // Giocatori delle due squadre, esclusi quelli giÃ  inseriti per questa partita
    $sql = "SELECT DISTINCT g.id, g.nome, g.cognome, s.nome AS squadra
            FROM squadre s
            JOIN squadre_giocatori sg ON sg.squadra_id = s.id
            JOIN giocatori g ON g.id = sg.giocatore_id
            WHERE s.torneo = ?
              AND s.nome IN (?, ?)
              AND g.id NOT IN (SELECT giocatore_id FROM partita_giocatore WHERE partita_id = ?)
            ORDER BY g.cognome, g.nome";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $p['torneo'], $p['squadra_casa'], $p['squadra_ospite'], $partita_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $out = [];

    while ($row = $res->fetch_assoc()) { $out[] = $row; }

    echo json_encode($out);
    exit;
}

/* ==========================================================
   AGGIUNTA STATISTICA
========================================================== */
if ($azione === 'add') {

    $partita_id = (int)$_POST['partita_id'];
    $giocatore  = (int)$_POST['giocatore_id'];
    $goal       = (int)$_POST['goal'];
    $assist     = (int)$_POST['assist'];
    $giallo     = (int)$_POST['cartellino_giallo'];
    $rosso      = (int)$_POST['cartellino_rosso'];
    $voto       = $_POST['voto'] === "" ? null : (float)$_POST['voto'];

    /* ðŸ”¥ CONTROLLO DUPLICATO */
    $check = $conn->prepare("SELECT id FROM partita_giocatore WHERE partita_id = ? AND giocatore_id = ?");
    $check->bind_param("ii", $partita_id, $giocatore);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();

    if ($exists) {
        echo json_encode(["error" => "exists"]);
        exit;
    }

    /* âž• INSERIMENTO */
    $sql = "INSERT INTO partita_giocatore
            (partita_id, giocatore_id, presenza, goal, assist, cartellino_giallo, cartellino_rosso, voto)
            VALUES (?, ?, 1, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiiid", $partita_id, $giocatore, $goal, $assist, $giallo, $rosso, $voto);
    $stmt->execute();

    ricalcolaStatistiche($conn, $partita_id, $giocatore);
    $infoClassifica = aggiornaGolPartita($conn, $partita_id);
    aggiornaClassificaDaInfo($infoClassifica);
    inviaNotificaEsito($conn, $partita_id, $infoClassifica);

    echo json_encode(["success" => true, "message" => "Statistica aggiunta"]);
    exit;
}

/* ==========================================================
   MODIFICA
========================================================== */
if ($azione === 'edit') {

    $id     = (int)$_POST['id'];
    $goal   = (int)$_POST['goal'];
    $assist = (int)$_POST['assist'];
    $giallo = (int)$_POST['cartellino_giallo'];
    $rosso  = (int)$_POST['cartellino_rosso'];
    $voto   = $_POST['voto'] === "" ? null : (float)$_POST['voto'];

    if ($voto === null) {
        $sql = "UPDATE partita_giocatore 
                SET goal=?, assist=?, cartellino_giallo=?, cartellino_rosso=?, voto=NULL
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $goal, $assist, $giallo, $rosso, $id);
    } else {
        $sql = "UPDATE partita_giocatore 
                SET goal=?, assist=?, cartellino_giallo=?, cartellino_rosso=?, voto=?
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiidi", $goal, $assist, $giallo, $rosso, $voto, $id);
    }

    $stmt->execute();

    $partita_id_q = $conn->prepare("SELECT partita_id, giocatore_id FROM partita_giocatore WHERE id=?");
    $partita_id_q->bind_param("i", $id);
    $partita_id_q->execute();
    $rPrev = $partita_id_q->get_result()->fetch_assoc();

    echo json_encode(["success" => true, "message" => "Statistica aggiornata"]);

    if ($rPrev) {
      ricalcolaStatistiche($conn, (int)$rPrev['partita_id'], (int)$rPrev['giocatore_id']);
      $infoClassifica = aggiornaGolPartita($conn, (int)$rPrev['partita_id']);
      aggiornaClassificaDaInfo($infoClassifica);
      maybeNotificaFinale($conn, $infoClassifica);
    }
    exit;
}

/* ==========================================================
   ELIMINA
========================================================== */
if ($azione === 'delete') {

    $id = (int)$_POST['id'];

    $partita_id_q = $conn->prepare("SELECT partita_id, giocatore_id FROM partita_giocatore WHERE id=?");
    $partita_id_q->bind_param("i", $id);
    $partita_id_q->execute();
    $rPrev = $partita_id_q->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM partita_giocatore WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($rPrev) {
      ricalcolaStatistiche($conn, (int)$rPrev['partita_id'], (int)$rPrev['giocatore_id']);
      $infoClassifica = aggiornaGolPartita($conn, (int)$rPrev['partita_id']);
      aggiornaClassificaDaInfo($infoClassifica);
      maybeNotificaFinale($conn, $infoClassifica);
    }

    echo json_encode(["success" => true, "message" => "Statistica eliminata"]);
    exit;
}

/* ========================================================== */
echo json_encode(["error" => "Azione non valida"]);
