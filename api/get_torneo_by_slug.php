<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['slug']) || $_GET['slug'] === '') {
    echo json_encode(['error' => 'Slug mancante']);
    exit;
}

$slug = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['slug']);
if ($slug === '') {
    echo json_encode(['error' => 'Slug non valido']);
    exit;
}

require_once __DIR__ . '/../includi/db.php';

$filenamePhp = $slug . '.php';
$filenameHtml = $slug . '.html';
$stmt = $conn->prepare("SELECT nome, img, categoria FROM tornei WHERE filetorneo IN (?, ?) ORDER BY (filetorneo LIKE '%.php') DESC LIMIT 1");
$stmt->bind_param('ss', $filenamePhp, $filenameHtml);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'nome' => $row['nome'],
        'img' => $row['img'],
        'categoria' => $row['categoria']
    ]);
} else {
    echo json_encode(['error' => 'Torneo non trovato']);
}
