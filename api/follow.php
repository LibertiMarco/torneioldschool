<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

require_once __DIR__ . '/../includi/db.php';

$userId = (int)$_SESSION['user_id'];

// Tabella follow
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

function respond($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_str(?string $v): string {
    return trim((string)$v);
}

function add_squad_follows(mysqli $conn, int $userId, string $torneoSlug): void {
    $stmt = $conn->prepare("SELECT nome FROM squadre WHERE torneo = ?");
    if (!$stmt) return;
    $stmt->bind_param('s', $torneoSlug);
    $stmt->execute();
    $res = $stmt->get_result();
    $squads = [];
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['nome'])) $squads[] = $row['nome'];
    }
    $stmt->close();
    if (empty($squads)) return;

    $ins = $conn->prepare("INSERT IGNORE INTO seguiti (utente_id, tipo, torneo_slug, squadra_nome) VALUES (?, 'squadra', ?, ?)");
    if (!$ins) return;
    foreach ($squads as $sq) {
        $ins->bind_param('iss', $userId, $torneoSlug, $sq);
        $ins->execute();
    }
    $ins->close();
}

function remove_squad_follows(mysqli $conn, int $userId, string $torneoSlug): void {
    $del = $conn->prepare("DELETE FROM seguiti WHERE utente_id = ? AND tipo = 'squadra' AND torneo_slug = ?");
    if (!$del) return;
    $del->bind_param('is', $userId, $torneoSlug);
    $del->execute();
    $del->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $resT = [];
    $resS = [];

    $stmt = $GLOBALS['conn']->prepare("SELECT tipo, torneo_slug, squadra_nome FROM seguiti WHERE utente_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $GLOBALS['userId']);
        if ($stmt->execute()) {
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                if ($row['tipo'] === 'torneo') {
                    $resT[] = $row['torneo_slug'];
                } elseif ($row['tipo'] === 'squadra') {
                    $resS[] = ['torneo' => $row['torneo_slug'], 'squadra' => $row['squadra_nome']];
                }
            }
        }
        $stmt->close();
    }
    respond(['tournaments' => $resT, 'teams' => $resS]);
}

// POST follow/unfollow
$tipo = strtolower(clean_str($_POST['tipo'] ?? ''));
$azione = strtolower(clean_str($_POST['azione'] ?? 'follow'));
$torneo = clean_str($_POST['torneo'] ?? '');
$squadra = clean_str($_POST['squadra'] ?? '');

if (!in_array($tipo, ['torneo', 'squadra'], true)) {
    respond(['error' => 'Tipo non valido']);
}

if ($torneo === '') {
    respond(['error' => 'Torneo richiesto']);
}

if ($tipo === 'squadra' && $squadra === '') {
    respond(['error' => 'Squadra richiesta']);
}

if ($azione === 'unfollow') {
    $stmt = $conn->prepare("DELETE FROM seguiti WHERE utente_id = ? AND tipo = ? AND torneo_slug = ? AND " . ($tipo === 'squadra' ? "squadra_nome = ?" : "squadra_nome IS NULL"));
    if ($stmt) {
        if ($tipo === 'squadra') {
            $stmt->bind_param('isss', $userId, $tipo, $torneo, $squadra);
        } else {
            $stmt->bind_param('iss', $userId, $tipo, $torneo);
        }
        $stmt->execute();
        $stmt->close();
    }
    if ($tipo === 'torneo') {
        remove_squad_follows($conn, $userId, $torneo);
    }
    respond(['status' => 'ok', 'followed' => false]);
}

// default: follow (insert ignore)
$stmt = $conn->prepare("INSERT IGNORE INTO seguiti (utente_id, tipo, torneo_slug, squadra_nome) VALUES (?,?,?,?)");
if ($stmt) {
    $squadraVal = ($tipo === 'squadra') ? $squadra : null;
    $stmt->bind_param('isss', $userId, $tipo, $torneo, $squadraVal);
    $stmt->execute();
    $stmt->close();
}

if ($tipo === 'torneo') {
    add_squad_follows($conn, $userId, $torneo);
}

respond(['status' => 'ok', 'followed' => true]);
