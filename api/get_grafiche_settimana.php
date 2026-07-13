<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');

$databaseLoaded = false;
ob_start();
register_shutdown_function(static function () use (&$databaseLoaded): void {
    if ($databaseLoaded) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server.',
    ], JSON_UNESCAPED_UNICODE);
});

require_once __DIR__ . '/../includi/db.php';
$databaseLoaded = true;

function get_grafiche_settimana_error(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function get_grafiche_settimana_fase_nome(?string $fase, ?string $round, ?string $leg): string
{
    $fase = strtoupper(trim((string)$fase));
    $round = strtoupper(trim((string)$round));
    $leg = strtoupper(trim((string)$leg));

    if ($fase === '' || $fase === 'REGULAR') {
        return 'Regular Season';
    }

    if ($fase === 'SPAREGGIO') {
        return 'Spareggio';
    }

    $roundNames = [
        'TRENTADUESIMI' => 'Trentaduesimi',
        'SEDICESIMI' => 'Sedicesimi',
        'OTTAVI' => 'Ottavi',
        'QUARTI' => 'Quarti',
        'SEMIFINALE' => 'Semifinale',
        'FINALE' => 'Finale',
    ];

    $name = $roundNames[$round] ?? ucfirst(strtolower($fase));
    if ($leg === 'ANDATA') {
        $name .= ' di andata';
    } elseif ($leg === 'RITORNO') {
        $name .= ' di ritorno';
    }

    return $name;
}

function get_grafiche_settimana_slug(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value !== '' && function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');

    return $value !== '' ? $value : $fallback;
}

function get_grafiche_settimana_logo_assoluto(?string $logo): ?string
{
    $logo = trim((string)$logo);
    if ($logo === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $logo)) {
        return $logo;
    }

    return function_exists('tos_absolute_url') ? tos_absolute_url($logo) : null;
}

function get_grafiche_settimana_squadra($id, string $nome, $logo): array
{
    $logoPath = $logo !== null && trim((string)$logo) !== '' ? (string)$logo : null;

    return [
        'id' => $id !== null ? (int)$id : null,
        'nome' => $nome,
        'logo' => $logoPath,
        'logo_url_assoluto' => get_grafiche_settimana_logo_assoluto($logoPath),
    ];
}

function get_grafiche_settimana_sezioni(string $type, array $matches): array
{
    if ($type === 'regular_season') {
        foreach ($matches as &$match) {
            unset($match['_coppa_livello']);
        }
        unset($match);

        return [[
            'nome' => 'Regular Season',
            'partite' => $matches,
        ]];
    }

    $sectionNames = [
        'gold' => 'Coppa Gold',
        'silver' => 'Coppa Silver',
        'bronze' => 'Coppa Bronze',
    ];
    $matchesByLevel = [];

    foreach ($matches as $match) {
        $level = $match['_coppa_livello'] ?? null;
        $sectionKey = isset($sectionNames[$level]) ? $level : 'altro';
        unset($match['_coppa_livello']);
        $matchesByLevel[$sectionKey][] = $match;
    }

    $sections = [];
    foreach (['gold', 'silver', 'bronze', 'altro'] as $sectionKey) {
        if (empty($matchesByLevel[$sectionKey])) {
            continue;
        }

        $sections[] = [
            'nome' => $sectionNames[$sectionKey] ?? 'Coppa',
            'partite' => $matchesByLevel[$sectionKey],
        ];
    }

    return $sections;
}

try {
    $timezone = new DateTimeZone('Europe/Rome');
    $dataParam = isset($_GET['data']) ? trim((string)$_GET['data']) : '';

    if ($dataParam !== '') {
        $referenceDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dataParam, $timezone);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0);

        if (!$referenceDate instanceof DateTimeImmutable
            || $hasDateErrors
            || $referenceDate->format('Y-m-d') !== $dataParam) {
            get_grafiche_settimana_error('Data non valida. Usa il formato YYYY-MM-DD.', 400);
        }
    } else {
        $referenceDate = new DateTimeImmutable('today', $timezone);
    }

    $weekStart = $referenceDate->modify('next monday');
    $weekEnd = $weekStart->modify('+6 days');
    $fromDate = $weekStart->format('Y-m-d');
    $toDate = $weekEnd->format('Y-m-d');

    $sql = "
        SELECT
            p.id,
            p.torneo AS torneo_codice,
            t.id AS torneo_id,
            COALESCE(t.nome, p.torneo) AS torneo_nome,
            p.squadra_casa,
            sc.id AS squadra_casa_id,
            sc.logo AS squadra_casa_logo,
            p.squadra_ospite,
            so.id AS squadra_ospite_id,
            so.logo AS squadra_ospite_logo,
            p.data_partita,
            p.ora_partita,
            p.campo,
            p.giornata,
            p.fase,
            p.fase_round,
            p.fase_leg
        FROM partite p
        LEFT JOIN tornei t
          ON t.id = (
              SELECT t2.id
              FROM tornei t2
              WHERE t2.filetorneo = p.torneo
                 OR t2.filetorneo = CONCAT(p.torneo, '.php')
                 OR t2.nome = p.torneo
              ORDER BY
                  (t2.filetorneo = p.torneo) DESC,
                  (t2.filetorneo = CONCAT(p.torneo, '.php')) DESC,
                  t2.id ASC
              LIMIT 1
          )
        LEFT JOIN squadre sc
          ON sc.nome = p.squadra_casa
         AND sc.torneo = p.torneo
        LEFT JOIN squadre so
          ON so.nome = p.squadra_ospite
         AND so.torneo = p.torneo
        WHERE p.data_partita >= ?
          AND p.data_partita <= ?
          AND p.giocata = 0
        ORDER BY
            p.data_partita ASC,
            p.ora_partita ASC,
            COALESCE(t.nome, p.torneo) ASC,
            CASE
                WHEN p.fase IN ('REGULAR', 'SPAREGGIO') THEN 1
                ELSE 2
            END ASC,
            p.giornata ASC,
            p.fase_round ASC,
            p.fase_leg ASC,
            CASE p.fase
                WHEN 'GOLD' THEN 1
                WHEN 'SILVER' THEN 2
                WHEN 'BRONZO' THEN 3
                ELSE 4
            END ASC,
            p.id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Preparazione della query non riuscita.');
    }

    $stmt->bind_param('ss', $fromDate, $toDate);
    if (!$stmt->execute()) {
        throw new RuntimeException('Esecuzione della query non riuscita.');
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new RuntimeException('Lettura dei risultati non riuscita.');
    }

    $tournaments = [];
    $tournamentOrder = [];
    $cupLevels = [
        'GOLD' => 'gold',
        'SILVER' => 'silver',
        'BRONZO' => 'bronze',
    ];

    while ($row = $result->fetch_assoc()) {
        $tournamentId = $row['torneo_id'] !== null ? (int)$row['torneo_id'] : null;
        $tournamentKey = $tournamentId !== null
            ? 'id:' . $tournamentId
            : 'codice:' . (string)$row['torneo_codice'];

        if (!isset($tournaments[$tournamentKey])) {
            $tournamentOrder[] = $tournamentKey;
            $tournaments[$tournamentKey] = [
                'id' => $tournamentId,
                'nome' => (string)$row['torneo_nome'],
                'codice' => (string)$row['torneo_codice'],
                'gruppi' => [],
                'ordine_gruppi' => [],
            ];
        }

        $fase = strtoupper(trim((string)$row['fase']));
        $isRegularSeason = $fase === '' || $fase === 'REGULAR' || $fase === 'SPAREGGIO';
        $type = $isRegularSeason ? 'regular_season' : 'coppa';
        $phaseName = get_grafiche_settimana_fase_nome(
            $row['fase'],
            $row['fase_round'],
            $row['fase_leg']
        );
        $matchDay = $row['giornata'] !== null ? (int)$row['giornata'] : null;

        if ($type === 'regular_season') {
            $groupKey = 'regular:' . ($matchDay !== null ? $matchDay : 'nd');
            $subtitle = 'Regular Season';
            $label = $matchDay !== null ? $matchDay . 'ª Giornata' : 'Giornata da definire';
            $logicalGroup = 'regular-season_giornata-' . ($matchDay !== null ? $matchDay : 'nd');
        } else {
            $phaseSlug = get_grafiche_settimana_slug($phaseName, 'fase');
            $groupKey = 'coppa:' . $phaseName;
            $subtitle = $phaseName;
            $label = null;
            $logicalGroup = 'coppa_' . $phaseSlug;
        }

        if (!isset($tournaments[$tournamentKey]['gruppi'][$groupKey])) {
            $tournaments[$tournamentKey]['ordine_gruppi'][] = $groupKey;
            $tournaments[$tournamentKey]['gruppi'][$groupKey] = [
                'tipo' => $type,
                'sottotitolo' => $subtitle,
                'label' => $label,
                'id_gruppo' => $logicalGroup,
                'partite' => [],
            ];
        }

        $tournaments[$tournamentKey]['gruppi'][$groupKey]['partite'][] = [
            'id' => (int)$row['id'],
            'data' => (string)$row['data_partita'],
            'ora' => substr((string)$row['ora_partita'], 0, 5),
            'campo' => (string)$row['campo'],
            'squadra_casa' => get_grafiche_settimana_squadra(
                $row['squadra_casa_id'],
                (string)$row['squadra_casa'],
                $row['squadra_casa_logo']
            ),
            'squadra_ospite' => get_grafiche_settimana_squadra(
                $row['squadra_ospite_id'],
                (string)$row['squadra_ospite'],
                $row['squadra_ospite_logo']
            ),
            '_coppa_livello' => $cupLevels[$fase] ?? null,
        ];
    }

    $stmt->close();

    $outputTournaments = [];
    $graphicsCount = 0;

    foreach ($tournamentOrder as $tournamentKey) {
        $tournament = $tournaments[$tournamentKey];
        $tournamentSlug = get_grafiche_settimana_slug(
            $tournament['nome'],
            $tournament['id'] !== null ? 'torneo-' . $tournament['id'] : 'torneo'
        );
        $graphics = [];

        foreach ($tournament['ordine_gruppi'] as $groupKey) {
            $group = $tournament['gruppi'][$groupKey];
            $chunks = array_chunk($group['partite'], 4);
            $storyTotal = count($chunks);

            foreach ($chunks as $chunkIndex => $matches) {
                $storyIndex = $chunkIndex + 1;
                $graphics[] = [
                    'id_logico' => $tournamentSlug . '_' . $group['id_gruppo'] . '_' . $storyIndex,
                    'tipo' => $group['tipo'],
                    'titolo' => 'MATCHDAY',
                    'sottotitolo' => $group['sottotitolo'],
                    'label' => $group['label'],
                    'story_index' => $storyIndex,
                    'story_totali' => $storyTotal,
                    'numero_partite' => count($matches),
                    'sezioni' => get_grafiche_settimana_sezioni($group['tipo'], $matches),
                ];
                $graphicsCount++;
            }
        }

        if ($graphics) {
            $outputTournaments[] = [
                'id' => $tournament['id'],
                'nome' => $tournament['nome'],
                'grafiche' => $graphics,
            ];
        }
    }

    $payload = json_encode([
        'success' => true,
        'settimana' => [
            'dal' => $fromDate,
            'al' => $toDate,
        ],
        'numero_tornei' => count($outputTournaments),
        'numero_grafiche' => $graphicsCount,
        'tornei' => $outputTournaments,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        throw new RuntimeException('Serializzazione della risposta non riuscita.');
    }

    http_response_code(200);
    echo $payload;
} catch (Throwable $exception) {
    error_log('get_grafiche_settimana: ' . $exception->getMessage());
    get_grafiche_settimana_error('Errore interno del server.', 500);
}
