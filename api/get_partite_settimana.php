<?php
header('Content-Type: application/json; charset=utf-8');

// Evita che warning e notice producano una risposta non valida o espongano dettagli interni.
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

/**
 * Termina la richiesta con una risposta JSON di errore controllata.
 */
function get_partite_settimana_error(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Restituisce il nome leggibile della fase usando i valori presenti in partite.
 */
function get_partite_settimana_fase_nome(?string $fase, ?string $round, ?string $leg): ?string
{
    $fase = strtoupper(trim((string)$fase));
    $round = strtoupper(trim((string)$round));
    $leg = strtoupper(trim((string)$leg));

    if ($fase === '' || $fase === 'REGULAR') {
        return 'Regular season';
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
            get_partite_settimana_error('Data non valida. Usa il formato YYYY-MM-DD.', 400);
        }
    } else {
        $referenceDate = new DateTimeImmutable('today', $timezone);
    }

    // "next monday" restituisce sempre il lunedi della settimana successiva.
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
            p.fase_leg,
            p.giocata
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
        ORDER BY
            p.data_partita ASC,
            p.ora_partita ASC,
            COALESCE(t.nome, p.torneo) ASC,
            p.fase ASC,
            CASE p.fase
                WHEN 'GOLD' THEN 1
                WHEN 'SILVER' THEN 2
                WHEN 'BRONZO' THEN 3
                ELSE 4
            END ASC,
            p.fase_round ASC,
            p.fase_leg ASC,
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
    $tournamentIndexes = [];
    $matchCount = 0;

    while ($row = $result->fetch_assoc()) {
        $tournamentId = $row['torneo_id'] !== null ? (int)$row['torneo_id'] : null;
        $tournamentName = (string)$row['torneo_nome'];
        $tournamentKey = $tournamentId !== null
            ? 'id:' . $tournamentId
            : 'codice:' . (string)$row['torneo_codice'];

        if (!isset($tournamentIndexes[$tournamentKey])) {
            $tournamentIndexes[$tournamentKey] = count($tournaments);
            $tournaments[] = [
                'id' => $tournamentId,
                'nome' => $tournamentName,
                'partite' => [],
            ];
        }

        $fase = strtoupper(trim((string)$row['fase']));
        $cupLevels = [
            'GOLD' => 'gold',
            'SILVER' => 'silver',
            'BRONZO' => 'bronze',
        ];
        $isRegularSeason = $fase === '' || $fase === 'REGULAR' || $fase === 'SPAREGGIO';

        $match = [
            'id' => (int)$row['id'],
            'fase_tipo' => $isRegularSeason ? 'regular_season' : 'coppa',
            'fase_nome' => get_partite_settimana_fase_nome(
                $row['fase'],
                $row['fase_round'],
                $row['fase_leg']
            ),
            'coppa_livello' => $cupLevels[$fase] ?? null,
            'giornata' => $row['giornata'] !== null ? (int)$row['giornata'] : null,
            'squadra_casa' => [
                'id' => $row['squadra_casa_id'] !== null ? (int)$row['squadra_casa_id'] : null,
                'nome' => (string)$row['squadra_casa'],
                'logo' => $row['squadra_casa_logo'] !== null
                    ? (string)$row['squadra_casa_logo']
                    : null,
            ],
            'squadra_ospite' => [
                'id' => $row['squadra_ospite_id'] !== null ? (int)$row['squadra_ospite_id'] : null,
                'nome' => (string)$row['squadra_ospite'],
                'logo' => $row['squadra_ospite_logo'] !== null
                    ? (string)$row['squadra_ospite_logo']
                    : null,
            ],
            'data' => (string)$row['data_partita'],
            'ora' => substr((string)$row['ora_partita'], 0, 5),
            'campo' => (string)$row['campo'],
            'stato' => (int)$row['giocata'] === 1 ? 'giocata' : 'programmata',
        ];

        $index = $tournamentIndexes[$tournamentKey];
        $tournaments[$index]['partite'][] = $match;
        $matchCount++;
    }

    $stmt->close();

    $payload = json_encode([
        'success' => true,
        'settimana' => [
            'dal' => $fromDate,
            'al' => $toDate,
        ],
        'numero_tornei' => count($tournaments),
        'numero_partite' => $matchCount,
        'tornei' => $tournaments,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        throw new RuntimeException('Serializzazione della risposta non riuscita.');
    }

    http_response_code(200);
    echo $payload;
} catch (Throwable $exception) {
    error_log('get_partite_settimana: ' . $exception->getMessage());
    get_partite_settimana_error('Errore interno del server.', 500);
}
