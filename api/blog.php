<?php
require_once __DIR__ . '/../includi/db.php';
header('Content-Type: application/json; charset=utf-8');

$azione = $_GET['azione'] ?? '';

// Ultimi 4 articoli
if ($azione === 'ultimi') {
    $sql = "SELECT id, titolo, immagine, 
                   DATE_FORMAT(data_pubblicazione, '%d/%m/%Y') AS data
            FROM blog_post
            ORDER BY data_pubblicazione DESC
            LIMIT 4";

    $result = $conn->query($sql);
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}

// Tutti gli articoli
if ($azione === 'lista') {
    $sql = "SELECT id, titolo, immagine,
                   DATE_FORMAT(data_pubblicazione, '%d/%m/%Y') AS data
            FROM blog_post
            ORDER BY data_pubblicazione DESC";

    $result = $conn->query($sql);
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}

// Singolo articolo
if ($azione === 'articolo' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $sql = "SELECT titolo, contenuto, immagine,
                   DATE_FORMAT(data_pubblicazione, '%d/%m/%Y %H:%i') AS data
            FROM blog_post 
            WHERE id = $id";

    $result = $conn->query($sql);
    echo json_encode($result->fetch_assoc());
    exit;
}

echo json_encode(["error" => "Azione non valida"]);
