<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

require_once __DIR__ . '/../includi/db.php';

$userId = (int)$_SESSION['user_id'];

function ensure_notifiche_table(mysqli $conn): void {
    $sql = "
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
    ";
    if (!$conn->query($sql)) {
        error_log('notifiche table create failed: ' . $conn->error);
        // fallback senza FK per ambienti limitati
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
                INDEX idx_notifiche_user (utente_id, letto, creato_il)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
}

ensure_notifiche_table($conn);

$notificheColumns = [];
$colRes = $conn->query("SHOW COLUMNS FROM notifiche");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        $notificheColumns[strtolower($c['Field'])] = true;
    }
}
$hasTipoCol = isset($notificheColumns['tipo']);
if (!$hasTipoCol) {
    $conn->query("ALTER TABLE notifiche ADD COLUMN tipo VARCHAR(50) NOT NULL DEFAULT 'generic'");
    // ricarica lo stato colonne
    $notificheColumns = [];
    $colRes = $conn->query("SHOW COLUMNS FROM notifiche");
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            $notificheColumns[strtolower($c['Field'])] = true;
        }
    }
    $hasTipoCol = isset($notificheColumns['tipo']);
}

$notifications = [];
$unreadCount = 0;
$markRead = isset($_GET['mark_read']) && $_GET['mark_read'] === '1';

// Elimina una singola notifica (generica o commento)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)($_POST['delete_id'] ?? 0);
    $tipoDel = strtolower(trim($_POST['type'] ?? 'generic'));
    $deleted = 0;

    if ($deleteId > 0) {
        if ($tipoDel === 'comment') {
            $hasCommentiTable = $conn->query("SHOW TABLES LIKE 'notifiche_commenti'");
            if ($hasCommentiTable && $hasCommentiTable->num_rows > 0) {
                $del = $conn->prepare("DELETE FROM notifiche_commenti WHERE id = ? AND utente_id = ?");
                if ($del) {
                    $del->bind_param('ii', $deleteId, $userId);
                    $del->execute();
                    $deleted = $del->affected_rows;
                    $del->close();
                }
            }
        } else {
            $del = $conn->prepare("DELETE FROM notifiche WHERE id = ? AND utente_id = ?");
            if ($del) {
                $del->bind_param('ii', $deleteId, $userId);
                $del->execute();
                $deleted = $del->affected_rows;
                $del->close();
            }
        }
    }

    echo json_encode([
        'success' => $deleted > 0,
        'deleted' => $deleted,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Notifiche generiche
$tipoSelect = $hasTipoCol ? 'tipo' : "'generic' AS tipo";
$stmt = $conn->prepare("
    SELECT id, titolo, testo, link, letto, creato_il, {$tipoSelect}
    FROM notifiche
    WHERE utente_id = ?
    ORDER BY creato_il DESC
    LIMIT 20
");
if ($stmt) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'id' => (int)$row['id'],
                'title' => $row['titolo'],
                'text' => $row['testo'] ?? '',
                'link' => $row['link'] ?? '',
                'read' => (int)$row['letto'] === 1,
                'time' => $row['creato_il'],
                'type' => $row['tipo'] ?? 'generic',
            ];
            if ((int)$row['letto'] === 0) $unreadCount++;
        }
    }
    $stmt->close();
}

// Notifiche di commento (tabella esistente)
$hasCommentiTable = $conn->query("SHOW TABLES LIKE 'notifiche_commenti'");
if ($hasCommentiTable && $hasCommentiTable->num_rows > 0) {
    $stmtC = $conn->prepare("
        SELECT nc.id, nc.letto, nc.creato_il, nc.post_id, bp.titolo AS post_titolo
        FROM notifiche_commenti nc
        JOIN blog_post bp ON bp.id = nc.post_id
        WHERE nc.utente_id = ?
        ORDER BY nc.creato_il DESC
        LIMIT 20
    ");
    if ($stmtC) {
        $stmtC->bind_param('i', $userId);
        if ($stmtC->execute()) {
            $res = $stmtC->get_result();
            while ($row = $res->fetch_assoc()) {
                $postId = (int)($row['post_id'] ?? 0);
                $postTitle = $row['post_titolo'] ?? '';
                $linkArticolo = $postTitle !== '' ? "/articolo.php?titolo=" . rawurlencode($postTitle) : ($postId > 0 ? "/articolo.php?id=" . $postId : "/blog.php");
                $notifications[] = [
                    'id' => (int)$row['id'],
                    'title' => 'Qualcuno ha risposto al tuo commento...',
                    'text' => 'Apri l\'articolo per leggere la risposta.',
                    'link' => $linkArticolo,
                    'read' => (int)$row['letto'] === 1,
                    'time' => $row['creato_il'],
                    'type' => 'comment',
                ];
                if ((int)$row['letto'] === 0) $unreadCount++;
            }
        }
        $stmtC->close();
    }
}

// Ordina per data (le due sorgenti sono gi� ordinate, ma uniamo comunque)
usort($notifications, function($a, $b) {
    return strcmp($b['time'], $a['time']);
});

if ($markRead) {
    $up = $conn->prepare("UPDATE notifiche SET letto = 1 WHERE utente_id = ?");
    if ($up) { $up->bind_param('i', $userId); $up->execute(); $up->close(); }
    if ($hasCommentiTable && $hasCommentiTable->num_rows > 0) {
        $upc = $conn->prepare("UPDATE notifiche_commenti SET letto = 1 WHERE utente_id = ?");
        if ($upc) { $upc->bind_param('i', $userId); $upc->execute(); $upc->close(); }
    }
}

echo json_encode([
    'unread' => $unreadCount,
    'notifications' => $notifications,
], JSON_UNESCAPED_UNICODE);
