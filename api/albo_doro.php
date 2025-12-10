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

    // Costruisci le colonne in modo resiliente a schemi diversi/vecchi
    $premioCol = isset($cols['premio']) ? 'premio' : (isset($cols['categoria']) ? 'categoria' : "''");
    $torneoLogoCol = isset($cols['torneo_logo']) ? 'torneo_logo' : "''";
    $tabelloneCol = isset($cols['tabellone_url']) ? 'tabellone_url' : "''";
    $vincitriceLogoCol = isset($cols['vincitrice_logo']) ? 'vincitrice_logo' : "''";
    $inizioMeseCol = isset($cols['inizio_mese']) ? 'inizio_mese' : 'NULL';
    $inizioAnnoCol = isset($cols['inizio_anno']) ? 'inizio_anno' : 'NULL';
    $fineMeseCol = isset($cols['fine_mese']) ? 'fine_mese' : 'NULL';
    $fineAnnoCol = isset($cols['fine_anno']) ? 'fine_anno' : 'NULL';
    $createdCol = isset($cols['created_at']) ? 'created_at' : 'NULL';
    $hasSort = isset($cols['ordinamento']);
    $sortSelect = $hasSort ? ', ordinamento' : '';
    $sortOrder = $hasSort ? 'COALESCE(ordinamento, 999999),' : '';

    $sql = "
        SELECT id,
               competizione,
               {$premioCol} AS premio,
               vincitrice,
               {$vincitriceLogoCol} AS vincitrice_logo,
               {$torneoLogoCol} AS torneo_logo,
               {$tabelloneCol} AS tabellone_url,
               {$inizioMeseCol} AS inizio_mese,
               {$inizioAnnoCol} AS inizio_anno,
               {$fineMeseCol} AS fine_mese,
               {$fineAnnoCol} AS fine_anno,
               {$createdCol} AS created_at{$sortSelect}
        FROM albo
        ORDER BY {$sortOrder} COALESCE(fine_anno, inizio_anno, YEAR(created_at)) DESC,
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
        $tabUrl = trim((string)($row['tabellone_url'] ?? ''));
        if ($tabUrl === '' || $tabUrl === '0') {
            $tabUrl = '';
        }
        $rows[] = [
            'competizione' => $row['competizione'],
            'premio' => $row['premio'],
            'stato' => 'archivio',
            'data_inizio' => dateFromParts((int)$row['inizio_mese'], (int)$row['inizio_anno']),
            'data_fine' => dateFromParts((int)$row['fine_mese'], (int)$row['fine_anno']),
            'anno' => $anno,
            'filetorneo' => $tabUrl,
            'torneo_logo' => $row['torneo_logo'],
            'vincitrice' => $row['vincitrice'],
            'logo_vincitrice' => $row['vincitrice_logo'],
            'ordinamento' => $hasSort ? (int)$row['ordinamento'] : null,
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

        $tabUrl = trim((string)($row['filetorneo'] ?? ''));
        if ($tabUrl === '' || $tabUrl === '0') {
            $tabUrl = '';
        }

        $albo[] = [
            // Uniformiamo le chiavi con quelle usate dall'albo personalizzato
            'competizione' => $row['torneo'],
            'premio' => $row['categoria'],
            'categoria' => $row['categoria'],
            'stato' => $row['stato'],
            'data_inizio' => $row['data_inizio'] ?: '',
            'data_fine' => $row['data_fine'] ?: '',
            'anno' => $anno,
            'filetorneo' => $tabUrl,
            'torneo_logo' => $row['torneo_img'],
            'vincitrice' => $row['vincitrice'] ?: '',
            'logo_vincitrice' => $row['logo_vincitrice'] ?: '',
        ];
    }

    return $albo;
}

$raw = fetchAlboCustom($conn);
if (empty($raw)) {
    $raw = fetchAlboFromTornei($conn);
}

// Raggruppa per competizione e raccoglie premi multipli
$grouped = [];
foreach ($raw as $item) {
    $key = $item['competizione'] ?? ($item['torneo'] ?? 'Torneo');
    $competizione = $item['competizione'] ?? ($item['torneo'] ?? 'Torneo');
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'competizione' => $competizione,
            'torneo_logo' => $item['torneo_logo'] ?? $item['torneo_img'] ?? '/img/logo_old_school.png',
            'data_inizio' => $item['data_inizio'] ?? '',
            'data_fine' => $item['data_fine'] ?? '',
            'anno' => $item['anno'] ?? '',
            'ordinamento' => $item['ordinamento'] ?? null,
            'filetorneo' => ($item['filetorneo'] ?? ''),
            'premi' => [],
        ];
    }
    $grouped[$key]['premi'][] = [
        'premio' => $item['premio'] ?? $item['categoria'] ?? 'Premio',
        'vincitrice' => $item['vincitrice'] ?? '',
        'logo_vincitrice' => $item['logo_vincitrice'] ?? '',
    ];
}

// Ordina per anno e data_fine
usort($grouped, function ($a, $b) {
    $ao = $a['ordinamento'] ?? null;
    $bo = $b['ordinamento'] ?? null;
    if ($ao !== null || $bo !== null) {
        return ($ao ?? PHP_INT_MAX) <=> ($bo ?? PHP_INT_MAX);
    }
    return strcmp($b['anno'] ?? '', $a['anno'] ?? '');
});

echo json_encode(['data' => array_values($grouped)], JSON_UNESCAPED_UNICODE);
