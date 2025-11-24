<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includi/db.php';
header('Content-Type: application/json; charset=utf-8');

$azione = $_GET['azione'] ?? '';
$mediaBasePath = '/img/blog_media/';

function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function build_cover_query(string $alias = 'cover'): string {
    global $mediaBasePath;
    return "COALESCE(
                (SELECT CONCAT('{$mediaBasePath}', file_path)
                 FROM blog_media
                 WHERE post_id = blog_post.id AND tipo = 'image'
                 ORDER BY ordine ASC, id ASC
                 LIMIT 1),
                CASE
                    WHEN immagine IS NULL OR immagine = '' THEN ''
                    ELSE CONCAT('/img/blog/', immagine)
                END
            ) AS {$alias}";
}

// Ultimi 4 articoli
if ($azione === 'ultimi') {
    $sql = "SELECT id,
                   titolo,
                   " . build_cover_query('cover') . ",
                   SUBSTRING(contenuto, 1, 180) AS anteprima,
                   DATE_FORMAT(data_pubblicazione, '%d/%m/%Y') AS data
            FROM blog_post
            ORDER BY data_pubblicazione DESC
            LIMIT 4";

    if ($result = $conn->query($sql)) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['immagine'] = $row['cover'] ?? '';
        }
        unset($row);
        json_response($rows);
    }

    json_response(['error' => 'Impossibile recuperare gli articoli'], 500);
}

// Tutti gli articoli
if ($azione === 'lista') {
    $sql = "SELECT id,
                   titolo,
                   " . build_cover_query('cover') . ",
                   SUBSTRING(contenuto, 1, 220) AS anteprima,
                   DATE_FORMAT(data_pubblicazione, '%d/%m/%Y') AS data
            FROM blog_post
            ORDER BY data_pubblicazione DESC";

    if ($result = $conn->query($sql)) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['immagine'] = $row['cover'] ?? '';
        }
        unset($row);
        json_response($rows);
    }

    json_response(['error' => 'Impossibile recuperare la lista'], 500);
}

// Singolo articolo
if ($azione === 'articolo' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $conn->prepare(
        "SELECT titolo,
                contenuto,
                " . build_cover_query('cover') . ",
                DATE_FORMAT(data_pubblicazione, '%d/%m/%Y %H:%i') AS data
         FROM blog_post
         WHERE id = ?"
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($articolo = $result->fetch_assoc()) {
        $mediaStmt = $conn->prepare(
            "SELECT id, tipo, file_path, ordine
             FROM blog_media
             WHERE post_id = ?
             ORDER BY ordine ASC, id ASC"
        );

        $mediaItems = [];
        if ($mediaStmt) {
            $mediaStmt->bind_param('i', $id);
            $mediaStmt->execute();
            $mediaRes = $mediaStmt->get_result();
            while ($row = $mediaRes->fetch_assoc()) {
                $mediaItems[] = [
                    'id' => (int)$row['id'],
                    'tipo' => $row['tipo'],
                    'url' => $row['file_path'] ? $mediaBasePath . ltrim($row['file_path'], '/') : '',
                    'ordine' => (int)$row['ordine']
                ];
            }
            $mediaStmt->close();
        }

        $articolo['media'] = $mediaItems;
        $articolo['immagine'] = $articolo['cover'] ?? '';
        json_response($articolo);
    }

    json_response(['error' => 'Articolo non trovato'], 404);
}

// Lista commenti per articolo
if ($azione === 'commenti' && isset($_GET['id'])) {
    $postId = (int)$_GET['id'];
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $isAdmin = isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin';

    $stmt = $conn->prepare(
        "SELECT c.id,
                c.utente_id,
                CONCAT_WS(' ', u.nome, u.cognome) AS autore,
                c.commento,
                DATE_FORMAT(c.creato_il, '%d/%m/%Y %H:%i') AS data,
                COALESCE(u.avatar, '') AS avatar,
                c.parent_id
         FROM blog_commenti c
         INNER JOIN utenti u ON u.id = c.utente_id
         WHERE c.post_id = ?
         ORDER BY c.creato_il ASC"
    );

    if (!$stmt) {
        json_response(['error' => 'Impossibile recuperare i commenti.'], 500);
    }

    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $lookup = [];

    foreach ($rows as $row) {
        $ownerId = (int)$row['utente_id'];
        $row['can_delete'] = $isAdmin || ($currentUserId && $ownerId === $currentUserId);
        $row['replies'] = [];
        $row['parent_id'] = $row['parent_id'] ? (int)$row['parent_id'] : null;
        $lookup[$row['id']] = $row;
    }

    foreach ($lookup as $id => &$comment) {
        if ($comment['parent_id'] && isset($lookup[$comment['parent_id']])) {
            $lookup[$comment['parent_id']]['replies'][] = $comment;
        }
    }
    unset($comment);

    $root = [];
    foreach ($lookup as &$comment) {
        unset($comment['utente_id']);
        if (!$comment['parent_id']) {
            $root[] = $comment;
        }
    }
    unset($comment);

    json_response($root);
}

// Elimina commento (owner o admin)
if ($azione === 'commenti_elimina' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        json_response(['error' => 'Devi effettuare il login per eliminare un commento.'], 401);
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $commentId = isset($payload['id']) ? (int)$payload['id'] : 0;
    if ($commentId <= 0) {
        json_response(['error' => 'ID commento non valido.'], 400);
    }

    $check = $conn->prepare("SELECT utente_id FROM blog_commenti WHERE id = ?");
    if (!$check) {
        json_response(['error' => 'Errore tecnico.'], 500);
    }
    $check->bind_param('i', $commentId);
    $check->execute();
    $res = $check->get_result();
    if (!$res || !$res->num_rows) {
        json_response(['error' => 'Commento non trovato.'], 404);
    }
    $row = $res->fetch_assoc();
    $ownerId = (int)$row['utente_id'];
    $check->close();

    $currentUserId = (int)$_SESSION['user_id'];
    $isAdmin = isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin';
    if (!$isAdmin && $ownerId !== $currentUserId) {
        json_response(['error' => 'Non hai i permessi per eliminare questo commento.'], 403);
    }

    $del = $conn->prepare("DELETE FROM blog_commenti WHERE id = ?");
    if (!$del) {
        json_response(['error' => 'Errore nella preparazione della cancellazione.'], 500);
    }
    $del->bind_param('i', $commentId);
    if ($del->execute()) {
        json_response(['success' => true]);
    }
    json_response(['error' => 'Impossibile eliminare il commento.'], 500);
}

// Salva commento
if ($azione === 'commenti_salva' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        json_response(['error' => 'Per commentare devi aver effettuato il login.'], 401);
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $postId = isset($payload['post_id']) ? (int)$payload['post_id'] : 0;
    $commento = trim($payload['commento'] ?? '');
    $parentId = isset($payload['parent_id']) ? (int)$payload['parent_id'] : null;

    if ($postId <= 0 || $commento === '') {
        json_response(['error' => 'Commento non valido.'], 400);
    }

    $length = function_exists('mb_strlen') ? mb_strlen($commento) : strlen($commento);
    if ($length > 1200) {
        json_response(['error' => 'Il commento è troppo lungo. Limite 1.200 caratteri.'], 400);
    }

    $check = $conn->prepare("SELECT id FROM blog_post WHERE id = ?");
    $check->bind_param('i', $postId);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;

    if (!$exists) {
        json_response(['error' => 'Articolo inesistente.'], 404);
    }

    $parentOwnerId = null;
    if ($parentId) {
        $parentCheck = $conn->prepare(
            "SELECT id, parent_id, utente_id FROM blog_commenti WHERE id = ? AND post_id = ?"
        );
        $parentCheck->bind_param('ii', $parentId, $postId);
        $parentCheck->execute();
        $parentResult = $parentCheck->get_result()->fetch_assoc();

        if (!$parentResult) {
            json_response(['error' => 'Commento principale inesistente.'], 400);
        }

        if (!empty($parentResult['parent_id'])) {
            json_response(['error' => 'Puoi rispondere solo ai commenti principali.'], 400);
        }

        $parentOwnerId = (int)$parentResult['utente_id'];
    } else {
        $parentId = null;
    }

    $stmt = $conn->prepare(
        "INSERT INTO blog_commenti (post_id, utente_id, commento, parent_id, creato_il)
         VALUES (?, ?, ?, ?, NOW())"
    );

    if (!$stmt) {
        json_response(['error' => 'Impossibile salvare il commento.'], 500);
    }

    $userId = (int)$_SESSION['user_id'];
    $stmt->bind_param('iisi', $postId, $userId, $commento, $parentId);

    if ($stmt->execute()) {
        $newCommentId = $stmt->insert_id;

        if ($parentOwnerId && $parentOwnerId !== $userId) {
            $notifSql = "INSERT INTO notifiche_commenti (utente_id, commento_id, post_id, creato_il)
                         VALUES (?, ?, ?, NOW())";
            if ($notifStmt = $conn->prepare($notifSql)) {
                $notifStmt->bind_param('iii', $parentOwnerId, $newCommentId, $postId);
                $notifStmt->execute();
            }
        }

        json_response(['success' => true, 'message' => 'Commento pubblicato con successo.']);
    }

    json_response(['error' => 'Salvataggio non riuscito. Riprova più tardi.'], 500);
}

json_response(["error" => "Azione non valida"], 400);
