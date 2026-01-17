<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includi/db.php';

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione al database non disponibile']);
    exit;
}

$conn->set_charset('utf8mb4');

$giocatoreId = (int)($_GET['giocatore_id'] ?? 0);
$tipo = strtolower(trim($_GET['tipo'] ?? 'gol'));
$tipo = in_array($tipo, ['gol', 'presenze'], true) ? $tipo : 'gol';
$limit = (int)($_GET['limit'] ?? 200);
$limit = $limit > 0 ? min($limit, 500) : 200;

if ($giocatoreId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro giocatore_id mancante o non valido']);
    exit;
}

$defaultFoto = '/img/giocatori/unknown.jpg';
$excludedTournaments = ['SerieB']; // stesso filtro della classifica generale

$whereExcluded = '';
$paramsExcluded = [];
$typesExcluded = '';
if (!empty($excludedTournaments)) {
    $whereExcluded = ' AND p.torneo NOT IN (' . implode(',', array_fill(0, count($excludedTournaments), '?')) . ')';
    $paramsExcluded = $excludedTournaments;
    $typesExcluded = str_repeat('s', count($excludedTournaments));
}

$latestSquadPhotoSubquery = "
    SELECT sg2.foto
    FROM squadre_giocatori sg2
    WHERE sg2.giocatore_id = g.id
      AND sg2.foto IS NOT NULL AND sg2.foto <> ''
    ORDER BY sg2.created_at DESC, sg2.id DESC
    LIMIT 1
";

$fotoSelect = "
    CASE
        WHEN g.foto IS NULL OR g.foto = '' OR g.foto = '{$defaultFoto}'
            THEN COALESCE(($latestSquadPhotoSubquery), '{$defaultFoto}')
        ELSE g.foto
    END AS foto
";

// Dati riepilogativi del giocatore
$sqlPlayer = "
    SELECT 
        g.id,
        g.nome,
        g.cognome,
        g.ruolo,
        {$fotoSelect},
        COALESCE(SUM(CASE WHEN p.id IS NOT NULL THEN pg.goal ELSE 0 END), 0) AS gol_totali,
        COALESCE(SUM(CASE WHEN p.id IS NOT NULL AND pg.presenza = 1 THEN 1 ELSE 0 END), 0) AS presenze_totali,
        COALESCE(SUM(CASE WHEN p.id IS NOT NULL THEN pg.assist ELSE 0 END), 0) AS assist_totali
    FROM giocatori g
    LEFT JOIN partita_giocatore pg ON pg.giocatore_id = g.id
    LEFT JOIN partite p ON p.id = pg.partita_id AND p.giocata = 1{$whereExcluded}
    WHERE g.id = ?
    GROUP BY g.id
    LIMIT 1
";

$paramsPlayer = array_merge($paramsExcluded, [$giocatoreId]);
$typesPlayer = $typesExcluded . 'i';

$stmt = $conn->prepare($sqlPlayer);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno (player)']);
    exit;
}
$stmt->bind_param($typesPlayer, ...$paramsPlayer);
$stmt->execute();
$resPlayer = $stmt->get_result();
$playerRow = $resPlayer ? $resPlayer->fetch_assoc() : null;
$stmt->close();

if (!$playerRow) {
    http_response_code(404);
    echo json_encode(['error' => 'Giocatore non trovato']);
    exit;
}

$player = [
    'id' => (int)$playerRow['id'],
    'nome' => $playerRow['nome'],
    'cognome' => $playerRow['cognome'],
    'ruolo' => $playerRow['ruolo'],
    'foto' => $playerRow['foto'],
    'totali' => [
        'gol' => (int)$playerRow['gol_totali'],
        'presenze' => (int)$playerRow['presenze_totali'],
        'assist' => (int)$playerRow['assist_totali'],
    ],
];

// Partite rilevanti (gol o presenze)
$whereStat = $tipo === 'presenze' ? 'pg.presenza = 1' : 'pg.goal > 0';
$sqlMatches = "
    SELECT 
        pg.partita_id,
        pg.goal,
        pg.presenza,
        pg.assist,
        pg.autogol,
        pg.cartellino_giallo,
        pg.cartellino_rosso,
        pg.voto,
        p.torneo,
        p.squadra_casa,
        p.squadra_ospite,
        p.data_partita,
        p.ora_partita,
        p.giocata,
        p.gol_casa,
        p.gol_ospite,
        p.giornata,
        p.fase,
        p.fase_round,
        p.fase_leg,
        p.campo,
        t.nome AS torneo_nome,
        sc.logo AS logo_casa,
        so.logo AS logo_ospite
    FROM partita_giocatore pg
    JOIN partite p ON p.id = pg.partita_id
    LEFT JOIN tornei t ON (t.filetorneo = p.torneo OR t.filetorneo = CONCAT(p.torneo, '.php') OR t.nome = p.torneo)
    LEFT JOIN squadre sc ON sc.nome = p.squadra_casa AND sc.torneo = p.torneo
    LEFT JOIN squadre so ON so.nome = p.squadra_ospite AND so.torneo = p.torneo
    WHERE pg.giocatore_id = ? AND p.giocata = 1{$whereExcluded} AND {$whereStat}
    ORDER BY p.data_partita DESC, p.ora_partita DESC, pg.partita_id DESC
    LIMIT ?
";

$paramsMatches = array_merge([$giocatoreId], $paramsExcluded, [$limit]);
$typesMatches = 'i' . $typesExcluded . 'i';

$stmt = $conn->prepare($sqlMatches);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno (partite)']);
    exit;
}
$stmt->bind_param($typesMatches, ...$paramsMatches);
$stmt->execute();
$resMatches = $stmt->get_result();

$matches = [];
if ($resMatches) {
    while ($row = $resMatches->fetch_assoc()) {
        $matches[] = [
            'partita_id' => (int)$row['partita_id'],
            'goal' => (int)$row['goal'],
            'presenza' => (int)$row['presenza'],
            'assist' => (int)$row['assist'],
            'autogol' => (int)$row['autogol'],
            'cartellino_giallo' => (int)$row['cartellino_giallo'],
            'cartellino_rosso' => (int)$row['cartellino_rosso'],
            'voto' => $row['voto'] !== null ? (float)$row['voto'] : null,
            'torneo' => $row['torneo'],
            'torneo_nome' => $row['torneo_nome'] ?? null,
            'squadra_casa' => $row['squadra_casa'],
            'squadra_ospite' => $row['squadra_ospite'],
            'data_partita' => $row['data_partita'],
            'ora_partita' => $row['ora_partita'],
            'giocata' => (int)$row['giocata'],
            'gol_casa' => $row['gol_casa'] !== null ? (int)$row['gol_casa'] : null,
            'gol_ospite' => $row['gol_ospite'] !== null ? (int)$row['gol_ospite'] : null,
            'giornata' => $row['giornata'] !== null ? (int)$row['giornata'] : null,
            'fase' => $row['fase'],
            'fase_round' => $row['fase_round'],
            'fase_leg' => $row['fase_leg'],
            'campo' => $row['campo'],
            'logo_casa' => $row['logo_casa'],
            'logo_ospite' => $row['logo_ospite'],
        ];
    }
}
$stmt->close();

echo json_encode([
    'player' => $player,
    'matches' => $matches,
    'tipo' => $tipo,
    'filters' => [
        'limit' => $limit,
        'excluded_tournaments' => $excludedTournaments,
    ],
], JSON_UNESCAPED_UNICODE);
