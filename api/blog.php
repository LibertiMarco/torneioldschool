<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includi/db.php';
header('Content-Type: application/json; charset=utf-8');

$azione = $_GET['azione'] ?? '';

function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Ultimi 4 articoli
if ($azione === 'ultimi') {
    $sql = "SELECT id,
                   titolo,
                   CASE
                       WHEN immagine IS NULL OR immagine = '' THEN ''
                       ELSE CONCAT('/torneioldschool/img/blog/', immagine)
                   END AS immagine,
                   SUBSTRING(contenuto, 1, 180) AS anteprima,
                   DATE_FORMAT(data_pubblicazione, '%d/%m/%Y') AS data
            FROM blog_post
            ORDER BY data_pubblicazione DESC
            LIMIT 4";

    if ($result = $conn->query($sql)) {
        json_response($result->fetch_all(MYSQLI_ASSOC));
    }

    json_response(['error' => 'Impossibile recuperare gli articoli'], 500);
}

// Tutti gli articoli
if ($azione === 'lista') {
    $sql = "SELECT id,
                   titolo,
                   CASE
                       WHEN immagine IS NULL OR immagine = '' THEN ''
                       ELSE CONCAT('/torneioldschool/img/blog/', immagine)
                   END AS immagine,
                   SUBSTRING(contenuto, 1, 220) AS anteprima,
                   DATE_FORMAT(data_pubblicazione, '%d/%m/%Y') AS data
            FROM blog_post
            ORDER BY data_pubblicazione DESC";

    if ($result = $conn->query($sql)) {
        json_response($result->fetch_all(MYSQLI_ASSOC));
    }

    json_response(['error' => 'Impossibile recuperare la lista'], 500);
}

// Singolo articolo
if ($azione === 'articolo' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $conn->prepare(
        "SELECT titolo,
                contenuto,
                CASE
                    WHEN immagine IS NULL OR immagine = '' THEN ''
                    ELSE CONCAT('/torneioldschool/img/blog/', immagine)
                END AS immagine,
                DATE_FORMAT(data_pubblicazione, '%d/%m/%Y %H:%i') AS data
         FROM blog_post
         WHERE id = ?"
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($articolo = $result->fetch_assoc()) {
        json_response($articolo);
    }

    json_response(['error' => 'Articolo non trovato'], 404);
}

// Lista commenti per articolo
if ($azione === 'commenti' && isset($_GET['id'])) {
    $postId = (int)$_GET['id'];

    $stmt = $conn->prepare(
        "SELECT c.id,
                CONCAT_WS(' ', u.nome, u.cognome) AS autore,
                c.commento,
                DATE_FORMAT(c.creato_il, '%d/%m/%Y %H:%i') AS data,
                COALESCE(u.avatar, '') AS avatar
         FROM blog_commenti c
         INNER JOIN utenti u ON u.id = c.utente_id
         WHERE c.post_id = ?
         ORDER BY c.creato_il DESC"
    );

    if (!$stmt) {
        json_response(['error' => 'Impossibile recuperare i commenti.'], 500);
    }

    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $result = $stmt->get_result();

    json_response($result->fetch_all(MYSQLI_ASSOC));
}

// Salva commento
if ($azione === 'commenti_salva' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        json_response(['error' => 'Per commentare devi aver effettuato il login.'], 401);
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $postId = isset($payload['post_id']) ? (int)$payload['post_id'] : 0;
    $commento = trim($payload['commento'] ?? '');

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

    $stmt = $conn->prepare(
        "INSERT INTO blog_commenti (post_id, utente_id, commento, creato_il)
         VALUES (?, ?, ?, NOW())"
    );

    if (!$stmt) {
        json_response(['error' => 'Impossibile salvare il commento.'], 500);
    }

    $userId = (int)$_SESSION['user_id'];
    $stmt->bind_param('iis', $postId, $userId, $commento);

    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Commento pubblicato con successo.']);
    }

    json_response(['error' => 'Salvataggio non riuscito. Riprova più tardi.'], 500);
}

json_response(["error" => "Azione non valida"], 400);
