<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione al database non disponibile']);
    exit;
}

$conn->set_charset('utf8mb4');

function getAlboColumns(mysqli $conn): array {
    $cols = [];
    if ($res = $conn->query("SHOW COLUMNS FROM albo")) {
        while ($c = $res->fetch_assoc()) {
            $cols[strtolower($c['Field'])] = true;
        }
    }
    return $cols;
}

function dateFromParts(?int $month, ?int $year): string {
    if (empty($year)) return '';
    $m = ($month && $month >= 1 && $month <= 12) ? $month : 1;
    return sprintf('%04d-%02d-01', $year, $m);
}

function fetchAlboCustom(mysqli $conn): array {
    $check = $conn->query("SHOW TABLES LIKE 'albo'");
    if (!$check || $check->num_rows === 0) {
        return [];
    }
    $cols = getAlboColumns($conn);
    $premioCol = isset($cols['premio']) ? 'premio' : (isset($cols['categoria']) ? 'categoria' : "'' AS premio");

    $sql = "
        SELECT id, competizione, {$premioCol} AS premio, vincitrice, vincitrice_logo, torneo_logo, tabellone_url,
               inizio_mese, inizio_anno, fine_mese, fine_anno, created_at
        FROM albo
        ORDER BY COALESCE(fine_anno, inizio_anno, YEAR(created_at)) DESC,
                 COALESCE(fine_mese, inizio_mese, MONTH(created_at)) DESC,
                 id DESC
    ";

    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $anno = $row['fine_anno'] ?: $row['inizio_anno'] ?: '';
        $rows[] = [
            'torneo' => $row['competizione'],
            'categoria' => $row['premio'],
            'stato' => 'archivio',
            'data_inizio' => dateFromParts((int)$row['inizio_mese'], (int)$row['inizio_anno']),
            'data_fine' => dateFromParts((int)$row['fine_mese'], (int)$row['fine_anno']),
            'anno' => $anno,
            'filetorneo' => $row['tabellone_url'],
            'torneo_img' => $row['torneo_logo'],
            'vincitrice' => $row['vincitrice'],
            'logo_vincitrice' => $row['vincitrice_logo'],
        ];
    }
    return $rows;
}

function fetchAlboFromTornei(mysqli $conn): array {
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
            s.logo AS logo_vincitrice
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
        return [];
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
        ];
    }

    return $albo;
}

$albo = fetchAlboCustom($conn);
if (empty($albo)) {
    $albo = fetchAlboFromTornei($conn);
}

echo json_encode(['data' => $albo], JSON_UNESCAPED_UNICODE);
