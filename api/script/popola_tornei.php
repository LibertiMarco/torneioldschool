<?php
require_once __DIR__ . '/../../includi/db.php'; // connessione al DB

// Disattiva avvisi per evitare output indesiderato
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Tornei di esempio
$tornei = [
    // === IN CORSO ===
    [
        'nome' => 'Coppa Napoli',
        'stato' => 'in corso',
        'data_inizio' => '2025-10-01',
        'data_fine' => '2025-10-20',
        'img' => 'img/torneo1.jpg'
    ],
    [
        'nome' => 'Champions Street',
        'stato' => 'in corso',
        'data_inizio' => '2025-10-05',
        'data_fine' => '2025-10-25',
        'img' => 'img/torneo2.jpg'
    ],

    // === PROGRAMMATI ===
    [
        'nome' => 'Winter League',
        'stato' => 'programmato',
        'data_inizio' => '2026-01-10',
        'data_fine' => '2026-01-30',
        'img' => 'img/torneo3.jpg'
    ],
    [
        'nome' => 'Primavera Cup',
        'stato' => 'programmato',
        'data_inizio' => '2026-03-01',
        'data_fine' => '2026-03-20',
        'img' => 'img/torneo6.jpg'
    ],

    // === TERMINATI ===
    [
        'nome' => 'Summer Cup',
        'stato' => 'terminato',
        'data_inizio' => '2024-06-10',
        'data_fine' => '2024-06-30',
        'img' => 'img/torneo4.jpg'
    ],
    [
        'nome' => 'Old School Cup',
        'stato' => 'terminato',
        'data_inizio' => '2024-05-05',
        'data_fine' => '2024-05-25',
        'img' => 'img/torneo5.jpg'
    ]
];

// Query di inserimento
$stmt = $conn->prepare("
  INSERT INTO tornei (nome, stato, data_inizio, data_fine, img)
  VALUES (?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Errore nella preparazione della query: " . $conn->error);
}

foreach ($tornei as $t) {
    $stmt->bind_param(
        "sssss",
        $t['nome'],
        $t['stato'],
        $t['data_inizio'],
        $t['data_fine'],
        $t['img']
    );
    $stmt->execute();
}

$stmt->close();
$conn->close();

echo "<h2>✅ Tornei inseriti correttamente nella tabella!</h2>";
echo "<p><a href='/tornei.php'>Vai alla pagina Tornei</a></p>";
?>
