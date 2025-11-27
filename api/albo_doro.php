<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione al database non disponibile']);
    exit;
}

$conn->set_charset('utf8mb4');

$sql = "
    SELECT
        t.nome AS torneo,
        t.categoria,
        t.stato,
        t.data_inizio,
        t.data_fine,
        t.filetorneo,
        t.img AS torneo_img,
        s.nome AS vincitrice,
        s.logo AS logo_vincitrice,
        s.punti,
        s.vinte,
        s.pareggiate,
        s.perse,
        s.gol_fatti,
        s.gol_subiti,
        s.differenza_reti
    FROM tornei t
    LEFT JOIN squadre s ON s.id = (
        SELECT s2.id
        FROM squadre s2
        WHERE s2.torneo = t.nome
        ORDER BY s2.punti DESC, s2.differenza_reti DESC, s2.gol_fatti DESC, s2.gol_subiti ASC, s2.nome ASC
        LIMIT 1
    )
    WHERE t.stato = 'terminato'
    ORDER BY COALESCE(t.data_fine, t.data_inizio) DESC, t.nome ASC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore nella query: ' . $conn->error]);
    exit;
}

$albo = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['vincitrice'])) {
        continue;
    }

    $anno = '';
    if (!empty($row['data_fine']) && $row['data_fine'] !== '0000-00-00') {
        $anno = date('Y', strtotime($row['data_fine']));
    } elseif (!empty($row['data_inizio']) && $row['data_inizio'] !== '0000-00-00') {
        $anno = date('Y', strtotime($row['data_inizio']));
    }

    $albo[] = [
        'torneo' => $row['torneo'],
        'categoria' => $row['categoria'],
        'stato' => $row['stato'],
        'data_inizio' => $row['data_inizio'],
        'data_fine' => $row['data_fine'],
        'anno' => $anno,
        'filetorneo' => $row['filetorneo'],
        'torneo_img' => $row['torneo_img'],
        'vincitrice' => $row['vincitrice'],
        'logo_vincitrice' => $row['logo_vincitrice'],
        'punti' => (int)($row['punti'] ?? 0),
        'differenza_reti' => (int)($row['differenza_reti'] ?? 0),
    ];
}

echo json_encode(['data' => $albo], JSON_UNESCAPED_UNICODE);
