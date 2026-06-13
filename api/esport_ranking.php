<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

require_once __DIR__ . '/../includi/security.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/api_cache.php';
require_once __DIR__ . '/../includi/content_sections.php';

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione al database non disponibile'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->set_charset('utf8mb4');

function esportRankingLower(string $value): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function esportRankingStripExtension(string $value): string
{
    return preg_replace('/\.(html?|php)$/i', '', trim($value)) ?? trim($value);
}

function esportRankingNormalizeTournamentKey(string $value): string
{
    return esportRankingLower(esportRankingStripExtension($value));
}

function esportRankingNormalizeSearchToken(string $value): string
{
    return str_replace([' ', '-', '_', '.'], '', esportRankingLower(trim($value)));
}

function esportRankingBaseTournamentKey(string $value): string
{
    return preg_replace('/_(gold|silver|bronzo|bronze)$/i', '', esportRankingNormalizeTournamentKey($value)) ?? esportRankingNormalizeTournamentKey($value);
}

function esportRankingNormalizeTeamKey(string $value): string
{
    return esportRankingLower(trim($value));
}

function esportRankingNormalizePersonKey(string $value): string
{
    $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    return esportRankingLower($normalized);
}

function esportRankingSplitDisplayName(string $value): array
{
    $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    if ($normalized === '') {
        return ['', ''];
    }

    $parts = preg_split('/\s+/u', $normalized) ?: [$normalized];
    if (count($parts) === 1) {
        return [$parts[0], ''];
    }

    $cognome = array_pop($parts);
    $nome = trim(implode(' ', $parts));
    return [$nome, $cognome];
}

function esportRankingBuildProfile(array $matchedPlayer, string $displayName, string $fallbackPhoto, int $fallbackId): array
{
    if (!empty($matchedPlayer)) {
        return [
            'id' => (int)($matchedPlayer['id'] ?? 0),
            'nome' => trim((string)($matchedPlayer['nome'] ?? '')),
            'cognome' => trim((string)($matchedPlayer['cognome'] ?? '')),
            'foto' => trim((string)($matchedPlayer['foto'] ?? '')) ?: $fallbackPhoto,
        ];
    }

    [$nome, $cognome] = esportRankingSplitDisplayName($displayName);
    return [
        'id' => $fallbackId,
        'nome' => $nome,
        'cognome' => $cognome,
        'foto' => $fallbackPhoto,
    ];
}

function esportRankingNormalizeGroupKey(?string $value): string
{
    $group = strtoupper(trim((string)($value ?? '')));
    $group = preg_replace('/^GIRONE\s+/u', '', $group) ?? $group;
    $group = preg_replace('/^GRUPPO\s+/u', '', $group) ?? $group;
    return $group;
}

function esportRankingNormalizePhase(?string $value): string
{
    $phase = strtoupper(trim((string)($value ?? '')));
    return ($phase === '' || $phase === 'GIRONE') ? 'REGULAR' : $phase;
}

function esportRankingBuildPlaceholders(int $count): string
{
    return implode(',', array_fill(0, max(0, $count), '?'));
}

function esportRankingResolveCanonicalTournament(string $rawTournament, array $aliasMap): ?string
{
    $baseKey = esportRankingBaseTournamentKey($rawTournament);
    if (isset($aliasMap[$baseKey])) {
        return $aliasMap[$baseKey];
    }

    $exactKey = esportRankingNormalizeTournamentKey($rawTournament);
    return $aliasMap[$exactKey] ?? null;
}

function esportRankingDetectCup(?string $phase, string $tournament): ?string
{
    $normalizedPhase = esportRankingNormalizePhase($phase);
    if ($normalizedPhase === 'GOLD') {
        return 'gold';
    }
    if ($normalizedPhase === 'SILVER') {
        return 'silver';
    }

    $normalizedTournament = esportRankingNormalizeTournamentKey($tournament);
    if (str_ends_with($normalizedTournament, '_gold')) {
        return 'gold';
    }
    if (str_ends_with($normalizedTournament, '_silver')) {
        return 'silver';
    }

    return null;
}

function esportRankingResolveRound(array $match): ?string
{
    $round = strtoupper(trim((string)($match['fase_round'] ?? '')));
    if (in_array($round, ['OTTAVI', 'QUARTI', 'SEMIFINALE', 'FINALE'], true)) {
        return $round;
    }

    $giornata = isset($match['giornata']) ? (int)$match['giornata'] : 0;
    $roundByDay = [
        4 => 'OTTAVI',
        3 => 'QUARTI',
        2 => 'SEMIFINALE',
        1 => 'FINALE',
    ];

    return $roundByDay[$giornata] ?? null;
}

function esportRankingRoundLevel(?string $round): int
{
    return match ($round) {
        'OTTAVI' => 1,
        'QUARTI' => 2,
        'SEMIFINALE' => 3,
        'FINALE' => 4,
        default => 0,
    };
}

function esportRankingMatchHasScore(array $match): bool
{
    return (int)($match['giocata'] ?? 0) === 1
        && $match['gol_casa'] !== null
        && $match['gol_ospite'] !== null
        && $match['gol_casa'] !== ''
        && $match['gol_ospite'] !== '';
}

function esportRankingSingleMatchWinnerKey(array $match): ?string
{
    if (!esportRankingMatchHasScore($match)) {
        return null;
    }

    $homeKey = esportRankingNormalizeTeamKey((string)($match['squadra_casa'] ?? ''));
    $awayKey = esportRankingNormalizeTeamKey((string)($match['squadra_ospite'] ?? ''));
    if ($homeKey === '' || $awayKey === '') {
        return null;
    }

    $homeGoals = (int)$match['gol_casa'];
    $awayGoals = (int)$match['gol_ospite'];
    if ($homeGoals > $awayGoals) {
        return $homeKey;
    }
    if ($awayGoals > $homeGoals) {
        return $awayKey;
    }

    if ((int)($match['decisa_rigori'] ?? 0) === 1 && $match['rigori_casa'] !== null && $match['rigori_ospite'] !== null) {
        $homePens = (int)$match['rigori_casa'];
        $awayPens = (int)$match['rigori_ospite'];
        if ($homePens > $awayPens) {
            return $homeKey;
        }
        if ($awayPens > $homePens) {
            return $awayKey;
        }
    }

    return null;
}

function esportRankingResolveFinalWinnerKey(array $matches): ?string
{
    if (empty($matches)) {
        return null;
    }

    $aggregate = [];
    foreach ($matches as $match) {
        if (!esportRankingMatchHasScore($match)) {
            continue;
        }

        $homeKey = esportRankingNormalizeTeamKey((string)($match['squadra_casa'] ?? ''));
        $awayKey = esportRankingNormalizeTeamKey((string)($match['squadra_ospite'] ?? ''));
        if ($homeKey === '' || $awayKey === '') {
            continue;
        }

        $aggregate[$homeKey] = ($aggregate[$homeKey] ?? 0) + (int)$match['gol_casa'];
        $aggregate[$awayKey] = ($aggregate[$awayKey] ?? 0) + (int)$match['gol_ospite'];
    }

    if (count($aggregate) === 2) {
        arsort($aggregate);
        $keys = array_keys($aggregate);
        $firstKey = $keys[0] ?? null;
        $secondKey = $keys[1] ?? null;
        if ($firstKey !== null && $secondKey !== null && (int)$aggregate[$firstKey] > (int)$aggregate[$secondKey]) {
            return $firstKey;
        }
    }

    for ($i = count($matches) - 1; $i >= 0; $i--) {
        $winnerKey = esportRankingSingleMatchWinnerKey($matches[$i]);
        if ($winnerKey !== null) {
            return $winnerKey;
        }
    }

    return null;
}

function esportRankingStagePoints(string $cup, int $roundLevel, bool $isWinner): int
{
    if ($cup === 'gold') {
        if ($isWinner) {
            return 100;
        }
        return match ($roundLevel) {
            4 => 75,
            3 => 55,
            2 => 35,
            default => 0,
        };
    }

    if ($cup === 'silver') {
        if ($isWinner) {
            return 30;
        }
        return match ($roundLevel) {
            4 => 20,
            3 => 15,
            2 => 10,
            default => 0,
        };
    }

    return 0;
}

function esportRankingPickPhoto(array $row): string
{
    $defaultPhoto = '/img/giocatori/unknown.jpg';
    $associationPhoto = trim((string)($row['foto_associazione'] ?? ''));
    if ($associationPhoto !== '') {
        return $associationPhoto;
    }

    $playerPhoto = trim((string)($row['giocatore_foto'] ?? ''));
    if ($playerPhoto !== '') {
        return $playerPhoto;
    }

    return $defaultPhoto;
}

function esportRankingShouldReplaceTeam(array $currentTeam, array $candidateTeam): bool
{
    $currentScore = [
        (int)($currentTeam['giocate'] ?? 0),
        (int)($currentTeam['punti'] ?? 0),
        (int)($currentTeam['differenza_reti'] ?? 0),
        (int)($currentTeam['gol_fatti'] ?? 0),
        $currentTeam['girone'] !== '' ? 1 : 0,
    ];
    $candidateScore = [
        (int)($candidateTeam['giocate'] ?? 0),
        (int)($candidateTeam['punti'] ?? 0),
        (int)($candidateTeam['differenza_reti'] ?? 0),
        (int)($candidateTeam['gol_fatti'] ?? 0),
        $candidateTeam['girone'] !== '' ? 1 : 0,
    ];

    return $candidateScore > $currentScore;
}

function esportRankingCompareTeams(array $left, array $right): int
{
    $diff = (int)($right['punti'] ?? 0) <=> (int)($left['punti'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    $diff = (int)($right['differenza_reti'] ?? 0) <=> (int)($left['differenza_reti'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    $diff = (int)($right['gol_fatti'] ?? 0) <=> (int)($left['gol_fatti'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    $diff = (int)($left['gol_subiti'] ?? 0) <=> (int)($right['gol_subiti'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    return strcasecmp((string)($left['nome'] ?? ''), (string)($right['nome'] ?? ''));
}

function esportRankingHighlight(array $entry): array
{
    $gold = (int)($entry['gold'] ?? 0);
    if ($gold >= 100) {
        return ['Vincitore Coppa Gold', 800];
    }
    if ($gold >= 75) {
        return ['Finalista Coppa Gold', 700];
    }
    if ($gold >= 55) {
        return ['Semifinalista Coppa Gold', 600];
    }
    if ($gold >= 35) {
        return ['Quarti Coppa Gold', 500];
    }

    $silver = (int)($entry['silver'] ?? 0);
    if ($silver >= 30) {
        return ['Vincitore Coppa Silver', 400];
    }
    if ($silver >= 20) {
        return ['Finalista Coppa Silver', 350];
    }
    if ($silver >= 15) {
        return ['Semifinalista Coppa Silver', 300];
    }
    if ($silver >= 10) {
        return ['Quarti Coppa Silver', 250];
    }

    $groupBonus = (int)($entry['bonus_gironi'] ?? 0);
    if ($groupBonus >= 10) {
        return ['1 posto nel girone', 200];
    }
    if ($groupBonus >= 5) {
        return ['2 posto nel girone', 150];
    }

    return ['', 0];
}

$isAuthenticated = isset($_SESSION['user_id']);
$maxLimit = $isAuthenticated ? 50 : 5;
$requestedLimit = (int)($_GET['limit'] ?? 10);
$limit = max(1, min($maxLimit, $requestedLimit));
$categoryFilter = trim((string)($_GET['categoria'] ?? 'ea fc'));
if ($categoryFilter === '') {
    $categoryFilter = 'ea fc';
}

$cacheKey = tos_api_cache_build_key('esport_ranking', [
    'authenticated' => $isAuthenticated ? 1 : 0,
    'categoria' => $categoryFilter,
    'limit' => $limit,
]);
$cachedPayload = tos_api_cache_read($cacheKey, 60);
if ($cachedPayload !== null) {
    echo $cachedPayload;
    exit;
}

$torneiSectionReady = ensure_tornei_section_column($conn);
$compactCategoryFilter = '%' . esportRankingNormalizeSearchToken($categoryFilter) . '%';
$categoriaComparableSql = "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(categoria), ' ', ''), '-', ''), '_', ''), '.', '')";
$nomeComparableSql = "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(nome), ' ', ''), '-', ''), '_', ''), '.', '')";
$fileComparableSql = "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(filetorneo), ' ', ''), '-', ''), '_', ''), '.', '')";
$torneiSql = "
    SELECT nome, filetorneo, categoria, config
    FROM tornei
    WHERE (
        {$categoriaComparableSql} LIKE ?
        OR {$nomeComparableSql} LIKE ?
        OR {$fileComparableSql} LIKE ?
    )
";
if ($torneiSectionReady) {
    $torneiSql .= " AND sezione = 'esport'";
}
$torneiSql .= " ORDER BY data_fine DESC, data_inizio DESC, nome ASC";

$torneiStmt = $conn->prepare($torneiSql);
if (!$torneiStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno durante la lettura dei tornei esport'], JSON_UNESCAPED_UNICODE);
    exit;
}

$torneiStmt->bind_param('sss', $compactCategoryFilter, $compactCategoryFilter, $compactCategoryFilter);
$torneiStmt->execute();
$torneiResult = $torneiStmt->get_result();

$tournaments = [];
$tournamentAliasMap = [];
$teamLookupKeys = [];
$matchLookupKeys = [];

while ($row = $torneiResult->fetch_assoc()) {
    $slug = esportRankingStripExtension((string)($row['filetorneo'] ?? ''));
    if ($slug === '') {
        $slug = esportRankingStripExtension((string)($row['nome'] ?? ''));
    }
    if ($slug === '') {
        continue;
    }

    $canonicalTournament = esportRankingNormalizeTournamentKey($slug);
    $displayName = trim((string)($row['nome'] ?? ''));
    if ($displayName === '') {
        $displayName = $slug;
    }

    if (!isset($tournaments[$canonicalTournament])) {
        $tournaments[$canonicalTournament] = [
            'slug' => $slug,
            'nome' => $displayName,
            'categoria' => trim((string)($row['categoria'] ?? '')),
        ];
    }

    $aliases = array_unique(array_filter([
        esportRankingNormalizeTournamentKey($slug),
        esportRankingNormalizeTournamentKey($displayName),
    ]));

    foreach ($aliases as $alias) {
        $tournamentAliasMap[$alias] = $canonicalTournament;
        $teamLookupKeys[$alias] = true;
        $matchLookupKeys[$alias] = true;
        $matchLookupKeys[$alias . '_gold'] = true;
        $matchLookupKeys[$alias . '_silver'] = true;
    }
}

$torneiStmt->close();

if (empty($tournaments)) {
    $emptyPayload = json_encode([
        'label' => 'EA FC',
        'data' => [],
        'meta' => [
            'categoria_filter' => $categoryFilter,
            'can_view_full' => $isAuthenticated,
            'has_more' => false,
            'max_limit' => $maxLimit,
            'requested_limit' => $requestedLimit,
            'total_players' => 0,
            'tournaments' => [],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if ($emptyPayload !== false) {
        tos_api_cache_write($cacheKey, $emptyPayload);
        echo $emptyPayload;
    } else {
        echo json_encode([
            'label' => 'EA FC',
            'data' => [],
            'meta' => [
                'categoria_filter' => $categoryFilter,
                'can_view_full' => $isAuthenticated,
                'has_more' => false,
                'max_limit' => $maxLimit,
                'requested_limit' => $requestedLimit,
                'total_players' => 0,
                'tournaments' => [],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$teamsByTournament = [];
$playerProfiles = [];

$teamLookupList = array_keys($teamLookupKeys);
$teamPlaceholders = esportRankingBuildPlaceholders(count($teamLookupList));
$teamsSql = "
    SELECT
        s.torneo,
        s.id AS squadra_id,
        s.nome AS squadra_nome,
        COALESCE(s.girone, '') AS girone,
        s.logo,
        s.punti,
        s.giocate,
        s.vinte,
        s.pareggiate,
        s.perse,
        s.gol_fatti,
        s.gol_subiti,
        s.differenza_reti
    FROM squadre s
    WHERE LOWER(TRIM(s.torneo)) IN ($teamPlaceholders)
    ORDER BY s.torneo ASC, s.nome ASC
";

$teamsStmt = $conn->prepare($teamsSql);
if (!$teamsStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno durante la lettura delle squadre esport'], JSON_UNESCAPED_UNICODE);
    exit;
}

$teamTypes = str_repeat('s', count($teamLookupList));
$teamsStmt->bind_param($teamTypes, ...$teamLookupList);
$teamsStmt->execute();
$teamsResult = $teamsStmt->get_result();

while ($row = $teamsResult->fetch_assoc()) {
    $canonicalTournament = esportRankingResolveCanonicalTournament((string)($row['torneo'] ?? ''), $tournamentAliasMap);
    if ($canonicalTournament === null) {
        continue;
    }

    $teamName = trim((string)($row['squadra_nome'] ?? ''));
    $teamKey = esportRankingNormalizeTeamKey($teamName);
    if ($teamKey === '') {
        continue;
    }

    $candidateTeam = [
        'nome' => $teamName,
        'profile_key' => esportRankingNormalizePersonKey($teamName),
        'girone' => esportRankingNormalizeGroupKey((string)($row['girone'] ?? '')),
        'logo' => trim((string)($row['logo'] ?? '')),
        'punti' => (int)($row['punti'] ?? 0),
        'giocate' => (int)($row['giocate'] ?? 0),
        'vinte' => (int)($row['vinte'] ?? 0),
        'pareggiate' => (int)($row['pareggiate'] ?? 0),
        'perse' => (int)($row['perse'] ?? 0),
        'gol_fatti' => (int)($row['gol_fatti'] ?? 0),
        'gol_subiti' => (int)($row['gol_subiti'] ?? 0),
        'differenza_reti' => (int)($row['differenza_reti'] ?? 0),
    ];

    if (!isset($teamsByTournament[$canonicalTournament][$teamKey])) {
        $teamsByTournament[$canonicalTournament][$teamKey] = $candidateTeam;
    } elseif (esportRankingShouldReplaceTeam($teamsByTournament[$canonicalTournament][$teamKey], $candidateTeam)) {
        $teamsByTournament[$canonicalTournament][$teamKey] = $candidateTeam;
    }
}

$teamsStmt->close();

if (!empty($teamsByTournament)) {
    $playersByFullName = [];
    $playersResult = $conn->query("SELECT id, nome, cognome, foto FROM giocatori ORDER BY id ASC");
    if ($playersResult instanceof mysqli_result) {
        while ($row = $playersResult->fetch_assoc()) {
            $fullName = trim((string)($row['nome'] ?? '') . ' ' . (string)($row['cognome'] ?? ''));
            $fullNameKey = esportRankingNormalizePersonKey($fullName);
            if ($fullNameKey === '' || isset($playersByFullName[$fullNameKey])) {
                continue;
            }

            $playersByFullName[$fullNameKey] = [
                'id' => (int)($row['id'] ?? 0),
                'nome' => trim((string)($row['nome'] ?? '')),
                'cognome' => trim((string)($row['cognome'] ?? '')),
                'foto' => trim((string)($row['foto'] ?? '')) ?: '/img/giocatori/unknown.jpg',
            ];
        }
        $playersResult->free();
    }

    $syntheticPlayerIds = [];
    $nextSyntheticPlayerId = -1;

    foreach ($teamsByTournament as $canonicalTournament => $teams) {
        foreach ($teams as $teamKey => $team) {
            $profileKey = (string)($team['profile_key'] ?? '');
            if ($profileKey === '') {
                continue;
            }

            $fallbackPhoto = trim((string)($team['logo'] ?? '')) ?: '/img/giocatori/unknown.jpg';
            $matchedPlayer = $playersByFullName[$profileKey] ?? [];

            if (!isset($syntheticPlayerIds[$profileKey])) {
                $syntheticPlayerIds[$profileKey] = $nextSyntheticPlayerId;
                $nextSyntheticPlayerId--;
            }

            $profile = esportRankingBuildProfile(
                $matchedPlayer,
                (string)($team['nome'] ?? ''),
                $fallbackPhoto,
                $syntheticPlayerIds[$profileKey]
            );

            if (!isset($playerProfiles[$profileKey])) {
                $playerProfiles[$profileKey] = $profile;
                continue;
            }

            $currentPhoto = trim((string)($playerProfiles[$profileKey]['foto'] ?? ''));
            if (($currentPhoto === '' || str_contains($currentPhoto, 'unknown.jpg')) && !str_contains($profile['foto'], 'unknown.jpg')) {
                $playerProfiles[$profileKey]['foto'] = $profile['foto'];
            }
        }
    }
}

$regularPointsByTeam = [];
$knockoutStages = [];
$finalMatches = [];

$matchLookupList = array_keys($matchLookupKeys);
$matchPlaceholders = esportRankingBuildPlaceholders(count($matchLookupList));
$matchesSql = "
    SELECT
        id,
        torneo,
        fase,
        fase_round,
        fase_leg,
        giornata,
        squadra_casa,
        squadra_ospite,
        gol_casa,
        gol_ospite,
        giocata,
        decisa_rigori,
        rigori_casa,
        rigori_ospite,
        data_partita,
        ora_partita
    FROM partite
    WHERE LOWER(TRIM(torneo)) IN ($matchPlaceholders)
      AND giocata = 1
    ORDER BY data_partita ASC, ora_partita ASC, id ASC
";

$matchesStmt = $conn->prepare($matchesSql);
if ($matchesStmt) {
    $matchTypes = str_repeat('s', count($matchLookupList));
    $matchesStmt->bind_param($matchTypes, ...$matchLookupList);
    $matchesStmt->execute();
    $matchesResult = $matchesStmt->get_result();

    while ($row = $matchesResult->fetch_assoc()) {
        if (!esportRankingMatchHasScore($row)) {
            continue;
        }

        $canonicalTournament = esportRankingResolveCanonicalTournament((string)($row['torneo'] ?? ''), $tournamentAliasMap);
        if ($canonicalTournament === null) {
            continue;
        }

        $homeTeamKey = esportRankingNormalizeTeamKey((string)($row['squadra_casa'] ?? ''));
        $awayTeamKey = esportRankingNormalizeTeamKey((string)($row['squadra_ospite'] ?? ''));
        if ($homeTeamKey === '' || $awayTeamKey === '') {
            continue;
        }

        $cup = esportRankingDetectCup((string)($row['fase'] ?? ''), (string)($row['torneo'] ?? ''));
        if ($cup === null) {
            if (esportRankingNormalizePhase((string)($row['fase'] ?? '')) !== 'REGULAR') {
                continue;
            }

            $homeGoals = (int)$row['gol_casa'];
            $awayGoals = (int)$row['gol_ospite'];

            if ($homeGoals > $awayGoals) {
                $regularPointsByTeam[$canonicalTournament][$homeTeamKey] = ($regularPointsByTeam[$canonicalTournament][$homeTeamKey] ?? 0) + 3;
            } elseif ($awayGoals > $homeGoals) {
                $regularPointsByTeam[$canonicalTournament][$awayTeamKey] = ($regularPointsByTeam[$canonicalTournament][$awayTeamKey] ?? 0) + 3;
            } else {
                $regularPointsByTeam[$canonicalTournament][$homeTeamKey] = ($regularPointsByTeam[$canonicalTournament][$homeTeamKey] ?? 0) + 1;
                $regularPointsByTeam[$canonicalTournament][$awayTeamKey] = ($regularPointsByTeam[$canonicalTournament][$awayTeamKey] ?? 0) + 1;
            }

            continue;
        }

        if (!in_array($cup, ['gold', 'silver'], true)) {
            continue;
        }

        $round = esportRankingResolveRound($row);
        $roundLevel = esportRankingRoundLevel($round);
        if ($roundLevel <= 0) {
            continue;
        }

        $knockoutStages[$canonicalTournament][$cup][$homeTeamKey] = max($knockoutStages[$canonicalTournament][$cup][$homeTeamKey] ?? 0, $roundLevel);
        $knockoutStages[$canonicalTournament][$cup][$awayTeamKey] = max($knockoutStages[$canonicalTournament][$cup][$awayTeamKey] ?? 0, $roundLevel);

        if ($round === 'FINALE') {
            $finalMatches[$canonicalTournament][$cup][] = $row;
        }
    }

    $matchesStmt->close();
}

$playerTournamentBreakdown = [];

foreach ($teamsByTournament as $canonicalTournament => $teams) {
    $groupRows = [];
    foreach ($teams as $teamKey => $team) {
        $groupKey = $team['girone'] !== '' ? $team['girone'] : '__SINGLE_GROUP__';
        $groupRows[$groupKey][$teamKey] = $team;
    }

    $groupBonuses = [];
    foreach ($groupRows as $groupTeams) {
        $orderedTeams = array_values($groupTeams);
        usort($orderedTeams, 'esportRankingCompareTeams');

        foreach ($orderedTeams as $index => $team) {
            $orderedTeamKey = esportRankingNormalizeTeamKey((string)($team['nome'] ?? ''));
            if ($orderedTeamKey === '') {
                continue;
            }
            if ($index === 0) {
                $groupBonuses[$orderedTeamKey] = 10;
            } elseif ($index === 1) {
                $groupBonuses[$orderedTeamKey] = 5;
            } else {
                $groupBonuses[$orderedTeamKey] = $groupBonuses[$orderedTeamKey] ?? 0;
            }
        }
    }

    $cupPointsByTeam = [];
    foreach (['gold', 'silver'] as $cupKey) {
        $winnerKey = esportRankingResolveFinalWinnerKey($finalMatches[$canonicalTournament][$cupKey] ?? []);
        foreach ($knockoutStages[$canonicalTournament][$cupKey] ?? [] as $teamKey => $roundLevel) {
            $cupPointsByTeam[$teamKey][$cupKey] = esportRankingStagePoints($cupKey, (int)$roundLevel, $winnerKey !== null && $winnerKey === $teamKey);
        }
    }

    foreach ($teams as $teamKey => $team) {
        $profileKey = (string)($team['profile_key'] ?? '');
        if ($profileKey === '' || !isset($playerProfiles[$profileKey])) {
            continue;
        }

        $regularPoints = (int)($regularPointsByTeam[$canonicalTournament][$teamKey] ?? 0);
        $groupBonus = (int)($groupBonuses[$teamKey] ?? 0);
        $goldPoints = (int)($cupPointsByTeam[$teamKey]['gold'] ?? 0);
        $silverPoints = (int)($cupPointsByTeam[$teamKey]['silver'] ?? 0);

        if (!isset($playerTournamentBreakdown[$profileKey][$canonicalTournament])) {
            $playerTournamentBreakdown[$profileKey][$canonicalTournament] = [
                'partecipazione' => 10,
                'gironi' => 0,
                'bonus_gironi' => 0,
                'gold' => 0,
                'silver' => 0,
                'tournament_name' => $tournaments[$canonicalTournament]['nome'] ?? $canonicalTournament,
                'teams' => [],
            ];
        }

        $playerTournamentBreakdown[$profileKey][$canonicalTournament]['gironi'] += $regularPoints;
        $playerTournamentBreakdown[$profileKey][$canonicalTournament]['bonus_gironi'] = max(
            (int)$playerTournamentBreakdown[$profileKey][$canonicalTournament]['bonus_gironi'],
            $groupBonus
        );
        $playerTournamentBreakdown[$profileKey][$canonicalTournament]['gold'] = max(
            (int)$playerTournamentBreakdown[$profileKey][$canonicalTournament]['gold'],
            $goldPoints
        );
        $playerTournamentBreakdown[$profileKey][$canonicalTournament]['silver'] = max(
            (int)$playerTournamentBreakdown[$profileKey][$canonicalTournament]['silver'],
            $silverPoints
        );
        $playerTournamentBreakdown[$profileKey][$canonicalTournament]['teams'][$teamKey] = $team['nome'];
    }
}

$ranking = [];
foreach ($playerTournamentBreakdown as $profileKey => $tournamentEntries) {
    if (!isset($playerProfiles[$profileKey])) {
        continue;
    }

    $participationPoints = 0;
    $groupStagePoints = 0;
    $groupBonusPoints = 0;
    $goldPoints = 0;
    $silverPoints = 0;
    $allTeams = [];
    $bestResultLabel = '';
    $bestResultWeight = -1;

    foreach ($tournamentEntries as $entry) {
        $participationPoints += (int)($entry['partecipazione'] ?? 0);
        $groupStagePoints += (int)($entry['gironi'] ?? 0);
        $groupBonusPoints += (int)($entry['bonus_gironi'] ?? 0);
        $goldPoints += (int)($entry['gold'] ?? 0);
        $silverPoints += (int)($entry['silver'] ?? 0);

        foreach ($entry['teams'] as $teamName) {
            $allTeams[$teamName] = true;
        }

        [$label, $weight] = esportRankingHighlight($entry);
        if ($weight > $bestResultWeight) {
            $bestResultWeight = $weight;
            $bestResultLabel = $label !== ''
                ? $label . ' - ' . (string)($entry['tournament_name'] ?? '')
                : '';
        }
    }

    $totalPoints = $participationPoints + $groupStagePoints + $groupBonusPoints + $goldPoints + $silverPoints;
    $ranking[] = [
        'id' => $playerProfiles[$profileKey]['id'] ?? 0,
        'nome' => $playerProfiles[$profileKey]['nome'] ?? '',
        'cognome' => $playerProfiles[$profileKey]['cognome'] ?? '',
        'foto' => $playerProfiles[$profileKey]['foto'] ?? '/img/giocatori/unknown.jpg',
        'punti' => $totalPoints,
        'punti_partecipazione' => $participationPoints,
        'punti_gironi' => $groupStagePoints,
        'bonus_gironi' => $groupBonusPoints,
        'punti_gold' => $goldPoints,
        'punti_silver' => $silverPoints,
        'tornei_giocati' => count($tournamentEntries),
        'squadre' => array_values(array_keys($allTeams)),
        'best_result' => $bestResultLabel,
    ];
}

usort($ranking, static function (array $left, array $right): int {
    $diff = (int)($right['punti'] ?? 0) <=> (int)($left['punti'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    $diff = (int)($right['punti_gold'] ?? 0) <=> (int)($left['punti_gold'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    $diff = (int)($right['punti_silver'] ?? 0) <=> (int)($left['punti_silver'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    $diff = (int)($right['bonus_gironi'] ?? 0) <=> (int)($left['bonus_gironi'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    $diff = (int)($right['punti_gironi'] ?? 0) <=> (int)($left['punti_gironi'] ?? 0);
    if ($diff !== 0) {
        return $diff;
    }

    $nameLeft = trim(((string)($left['cognome'] ?? '')) . ' ' . ((string)($left['nome'] ?? '')));
    $nameRight = trim(((string)($right['cognome'] ?? '')) . ' ' . ((string)($right['nome'] ?? '')));
    return strcasecmp($nameLeft, $nameRight);
});

$lastPoints = null;
$position = 0;
foreach ($ranking as $index => &$player) {
    $currentPoints = (int)($player['punti'] ?? 0);
    if ($lastPoints === null || $currentPoints !== $lastPoints) {
        $position = $index + 1;
        $lastPoints = $currentPoints;
    }
    $player['posizione'] = $position;
}
unset($player);

$payload = json_encode([
    'label' => 'EA FC',
    'data' => array_slice($ranking, 0, $limit),
    'meta' => [
        'categoria_filter' => $categoryFilter,
        'can_view_full' => $isAuthenticated,
        'has_more' => count($ranking) > $limit,
        'max_limit' => $maxLimit,
        'requested_limit' => $requestedLimit,
        'total_players' => count($ranking),
        'tournaments' => array_values(array_map(static function (array $tournament): array {
            return [
                'slug' => $tournament['slug'] ?? '',
                'nome' => $tournament['nome'] ?? '',
                'categoria' => $tournament['categoria'] ?? '',
            ];
        }, $tournaments)),
    ],
], JSON_UNESCAPED_UNICODE);

if ($payload !== false) {
    tos_api_cache_write($cacheKey, $payload);
    echo $payload;
} else {
    echo json_encode([
        'label' => 'EA FC',
        'data' => array_slice($ranking, 0, $limit),
        'meta' => [
            'categoria_filter' => $categoryFilter,
            'can_view_full' => $isAuthenticated,
            'has_more' => count($ranking) > $limit,
            'max_limit' => $maxLimit,
            'requested_limit' => $requestedLimit,
            'total_players' => count($ranking),
            'tournaments' => [],
        ],
    ], JSON_UNESCAPED_UNICODE);
}
