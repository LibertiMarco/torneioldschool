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

$notifications = [];
$unreadCount = 0;
$markRead = isset($_GET['mark_read']) && $_GET['mark_read'] === '1';
$badgeOnly = isset($_GET['badge_only']) && $_GET['badge_only'] === '1';

// Elimina una singola notifica (generica o commento)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)($_POST['delete_id'] ?? 0);
    $tipoDel = strtolower(trim($_POST['type'] ?? 'generic'));
    $deleted = 0;

    if ($deleteId > 0) {
        if ($tipoDel === 'comment') {
            $del = $conn->prepare("DELETE FROM notifiche_commenti WHERE id = ? AND utente_id = ?");
            if ($del) {
                $del->bind_param('ii', $deleteId, $userId);
                $del->execute();
                $deleted = $del->affected_rows;
                $del->close();
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

if ($badgeOnly && !$markRead) {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifiche WHERE utente_id = ? AND letto = 0");
    if ($countStmt) {
        $countStmt->bind_param('i', $userId);
        if ($countStmt->execute()) {
            $countStmt->bind_result($countGeneric);
            if ($countStmt->fetch()) {
                $unreadCount += (int)$countGeneric;
            }
        }
        $countStmt->close();
    }

    $countCommentStmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifiche_commenti WHERE utente_id = ? AND letto = 0");
    if ($countCommentStmt) {
        $countCommentStmt->bind_param('i', $userId);
        if ($countCommentStmt->execute()) {
            $countCommentStmt->bind_result($countComments);
            if ($countCommentStmt->fetch()) {
                $unreadCount += (int)$countComments;
            }
        }
        $countCommentStmt->close();
    }

    echo json_encode([
        'unread' => $unreadCount,
        'notifications' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Notifiche generiche
$stmt = $conn->prepare("
    SELECT id, titolo, testo, link, letto, creato_il, tipo
    FROM notifiche
    WHERE utente_id = ?
    ORDER BY creato_il DESC
    LIMIT 20
");
if (!$stmt) {
    $stmt = $conn->prepare("
        SELECT id, titolo, testo, link, letto, creato_il, 'generic' AS tipo
        FROM notifiche
        WHERE utente_id = ?
        ORDER BY creato_il DESC
        LIMIT 20
    ");
}
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

// Ordina per data (le due sorgenti sono gi� ordinate, ma uniamo comunque)
usort($notifications, function($a, $b) {
    return strcmp($b['time'], $a['time']);
});

if ($markRead) {
    $up = $conn->prepare("UPDATE notifiche SET letto = 1 WHERE utente_id = ?");
    if ($up) { $up->bind_param('i', $userId); $up->execute(); $up->close(); }
    $upc = $conn->prepare("UPDATE notifiche_commenti SET letto = 1 WHERE utente_id = ?");
    if ($upc) { $upc->bind_param('i', $userId); $upc->execute(); $upc->close(); }
}

echo json_encode([
    'unread' => $unreadCount,
    'notifications' => $notifications,
], JSON_UNESCAPED_UNICODE);
