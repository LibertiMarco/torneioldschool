<?php
declare(strict_types=1);

/**
 * Trova i Reel pubblicati ieri, abbina torneo/giornata/squadre/risultato
 * e salva il permalink in partite.link_instagram.
 * CLI per cron; da web è accessibile solo agli amministratori autenticati.
 */
$isCli = PHP_SAPI === 'cli';
if (!$isCli && !defined('TOS_INSTAGRAM_SYNC_LIBRARY')) {
    require_once __DIR__ . '/../../includi/admin_guard.php';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Content-Type: text/html; charset=utf-8');
        require_once __DIR__ . '/../../includi/security.php';
        $defaultDate = (new DateTimeImmutable('yesterday', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
        $csrf = htmlspecialchars(csrf_get_token('instagram_match_sync'), ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($defaultDate, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Sincronizza link Instagram</title><style>body{font:16px Arial,sans-serif;background:#0b1420;color:#f4f7fb;margin:0;padding:24px}'
            . '.box{max-width:620px;margin:6vh auto;padding:24px;background:#142235;border-radius:14px}label{display:block;margin:18px 0 8px}'
            . 'input,button{font:inherit;padding:12px;border-radius:8px;border:1px solid #53677e}input{display:block;margin-top:8px;background:#fff;color:#111}'
            . '.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}button{cursor:pointer;font-weight:bold}.dry{background:#f2c94c;color:#111}'
            . '.apply{background:#176b45;color:#fff}small{color:#bdcce0}</style><main class="box"><h1>Sincronizza link Instagram</h1>'
            . '<p>Abbina i Reel alle partite tramite torneo, giornata, squadre e risultato, anche se pubblicati dopo la gara. La prova non cambia il database; il salvataggio aggiorna solo partite senza link.</p>'
            . '<form method="post"><input type="hidden" name="_csrf" value="' . $csrf . '">'
            . '<label for="date">Giorno dei Reel</label><input id="date" type="date" name="date" value="' . $date . '" required>'
            . '<div class="actions"><button class="dry" name="mode" value="dry-run">Controlla senza salvare</button>'
            . '<button class="apply" name="mode" value="apply" onclick="return confirm(\'Salvare i link Instagram trovati nelle partite?\')">Salva i link trovati</button></div>'
            . '<p><small>Pagina riservata agli amministratori. Gli abbinamenti ambigui vengono saltati.</small></p></form></main></html>';
        exit;
    }
    require_once __DIR__ . '/../../includi/security.php';
    if (!csrf_is_valid((string)($_POST['_csrf'] ?? ''), 'instagram_match_sync')) {
        http_response_code(400);
        exit('Sessione scaduta o richiesta non valida. Ricarica la pagina e riprova.');
    }
    if (!in_array((string)($_POST['mode'] ?? ''), ['dry-run', 'apply'], true)) {
        http_response_code(400);
        exit('Modalità di esecuzione non valida.');
    }
    header('Content-Type: text/plain; charset=utf-8');
    @set_time_limit(300);
}

require_once __DIR__ . '/../../includi/env_loader.php';
require_once __DIR__ . '/../../includi/instagram_token_store.php';
require_once __DIR__ . '/../../includi/match_video_sync.php';

date_default_timezone_set('Europe/Rome');

function sync_instagram_log(string $message): void
{
    if (defined('TOS_INSTAGRAM_SYNC_LIBRARY')) return;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, $line);
    } else {
        echo $line;
    }
}

function sync_instagram_fail(string $message, int $code = 1): never
{
    if (defined('TOS_INSTAGRAM_SYNC_LIBRARY')) throw new RuntimeException($message);
    sync_instagram_log('ERRORE: ' . $message);
    exit($code);
}

function sync_instagram_request(string $url, int $timeout = 45): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        return ['ok' => false, 'error' => 'Impossibile inizializzare la connessione a Instagram.'];
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'error' => $curlError ?: 'Instagram ha restituito una risposta non valida.'];
    }
    $error = $decoded['error']['message'] ?? $decoded['error_message'] ?? null;
    return [
        'ok' => $status >= 200 && $status < 300 && $error === null,
        'status' => $status,
        'data' => $decoded,
        'error' => is_string($error) ? $error : null,
    ];
}

function sync_instagram_refresh_token(array $state, int $timeout = 45): array
{
    $now = time();
    $token = trim((string)($state['access_token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'error' => 'Token Instagram non disponibile. Ricollega il profilo dal generatore.'];
    }
    $expiresAt = (int)($state['expires_at'] ?? 0);
    $schedule = tos_instagram_token_refresh_schedule($state, $now);
    $state = $schedule['state'];
    if (!$schedule['due']) {
        if ($schedule['changed'] && !tos_save_instagram_token_state($state)) {
            return ['ok' => false, 'error' => 'Non è stato possibile salvare la prossima data di rinnovo del token nel runtime privato.'];
        }
        return ['ok' => true, 'state' => $state];
    }

    $refreshUrl = 'https://graph.instagram.com/refresh_access_token?' . http_build_query([
        'grant_type' => 'ig_refresh_token',
        'access_token' => $token,
    ], '', '&', PHP_QUERY_RFC3986);
    $response = sync_instagram_request($refreshUrl, $timeout);
    $newToken = $response['ok'] ? trim((string)($response['data']['access_token'] ?? '')) : '';
    if ($newToken !== '') {
        $expiresIn = max(0, (int)($response['data']['expires_in'] ?? 5184000));
        $state['access_token'] = $newToken;
        $state['issued_at'] = $now;
        $state['expires_at'] = $now + $expiresIn;
        $state['refresh_after'] = $now + tos_instagram_token_refresh_interval_seconds();
        unset($state['refresh_retry_at']);
        if (!tos_save_instagram_token_state($state)) {
            return ['ok' => false, 'error' => 'Token rinnovato, ma non è stato possibile salvarlo nel runtime privato.'];
        }
        return ['ok' => true, 'state' => $state];
    }

    $state['refresh_after'] = $now + 86400;
    $state['refresh_retry_at'] = $state['refresh_after'];
    tos_save_instagram_token_state($state);
    if ($expiresAt > 0 && $expiresAt <= $now + 86400) {
        return ['ok' => false, 'error' => 'Il token Instagram sta per scadere e il rinnovo automatico non è riuscito. Ricollega Instagram dal generatore.'];
    }
    return ['ok' => true, 'state' => $state];
}

function sync_instagram_normalize(string $value): string
{
    $value = trim($value);
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function sync_instagram_contains_phrase(string $normalizedText, string $phrase): bool
{
    $phrase = sync_instagram_normalize($phrase);
    if (strlen($phrase) < 4 || $normalizedText === '') {
        return false;
    }
    return preg_match('/(?:^| )' . preg_quote($phrase, '/') . '(?:$| )/', $normalizedText) === 1;
}

function sync_instagram_tournament_aliases(array $match): array
{
    $aliases = [];
    foreach ([(string)($match['torneo_nome'] ?? ''), (string)($match['torneo'] ?? '')] as $raw) {
        $raw = preg_replace('/\.php$/i', '', trim($raw)) ?? '';
        $normalized = sync_instagram_normalize(str_replace(['_', '-'], ' ', $raw));
        if (strlen($normalized) >= 4) {
            $aliases[$normalized] = true;
        }
    }
    return array_keys($aliases);
}

function sync_instagram_caption_matches_tournament(string $caption, array $aliases): bool
{
    $text = sync_instagram_normalize($caption);
    foreach ($aliases as $alias) {
        if (sync_instagram_contains_phrase($text, (string)$alias)) {
            return true;
        }
    }
    return false;
}

function sync_instagram_caption_matches_team(string $caption, string $team): bool
{
    $text = sync_instagram_normalize($caption);
    $team = sync_instagram_normalize($team);
    if ($team === '') {
        return false;
    }
    if (sync_instagram_contains_phrase($text, $team)) {
        return true;
    }

    // Le didascalie spesso omettono suffissi societari; si accetta la forma
    // breve solo eliminando esclusivamente sigle e parole societarie comuni.
    $tokens = array_values(array_filter(explode(' ', $team), static fn(string $token): bool => !in_array(
        $token,
        ['fc', 'sc', 'cf', 'afc', 'club', 'clube', 'football', 'futebol', 'esporte', 'sports'],
        true
    )));
    $short = trim(implode(' ', $tokens));
    return $short !== $team && strlen($short) >= 4 && sync_instagram_contains_phrase($text, $short);
}

function sync_instagram_is_reel(array $media): bool
{
    if (strtoupper((string)($media['media_type'] ?? '')) !== 'VIDEO') {
        return false;
    }
    $productType = strtoupper((string)($media['media_product_type'] ?? ''));
    if ($productType !== '') {
        return $productType === 'REELS';
    }
    $permalink = strtolower((string)($media['permalink'] ?? ''));
    return preg_match('~instagram\.com/(?:reel|reels)/[^/?]+~', $permalink) === 1;
}

function sync_instagram_fetch_yesterdays_reels(string $userId, string $token, string $apiVersion, DateTimeImmutable $day, ?DateTimeImmutable $lastDay = null): array
{
    $start = $day->setTime(0, 0)->getTimestamp();
    $end = ($lastDay ?? $day)->modify('+1 day')->setTime(0, 0)->getTimestamp();
    $after = null;
    $reels = [];
    $pages = 0;
    $pageLimit = 50;
    $seenCursors = [];

    while (true) {
        // Read captions with each media page, avoiding one extra call per Reel.
        $params = [
            'fields' => 'id,caption,media_type,media_product_type,permalink,timestamp',
            'limit' => $pageLimit,
            'access_token' => $token,
        ];
        if ($after !== null) {
            $params['after'] = $after;
        }
        $url = 'https://graph.instagram.com/' . rawurlencode($apiVersion) . '/' . rawurlencode($userId)
            . '/media?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $response = sync_instagram_request($url);
        if (!$response['ok']) {
            $error = strtolower((string)($response['error'] ?? ''));
            if (str_contains($error, 'reduce the amount of data') && $pageLimit > 1) {
                $pageLimit = max(1, intdiv($pageLimit, 2));
                sync_instagram_log("Instagram richiede meno dati: nuovo tentativo con {$pageLimit} contenuti per pagina.");
                continue;
            }
        }
        if (!$response['ok']) {
            $message = (string)($response['error'] ?? 'Lettura dei Reel non riuscita.');
            $message = str_replace($token, '[token omesso]', $message);
            sync_instagram_fail('Instagram API: ' . $message);
        }

        $data = $response['data'];
        foreach (($data['data'] ?? []) as $media) {
            if (!is_array($media) || !isset($media['timestamp'])) {
                continue;
            }
            try {
                $createdAt = (new DateTimeImmutable((string)$media['timestamp']))->setTimezone(new DateTimeZone('Europe/Rome'));
            } catch (Throwable) {
                continue;
            }
            $createdTs = $createdAt->getTimestamp();
            if ($createdTs < $start) {
                continue;
            }
            if ($createdTs >= $end || !sync_instagram_is_reel($media)) {
                continue;
            }
            $mediaId = trim((string)($media['id'] ?? ''));
            if ($mediaId === '' || trim((string)($media['permalink'] ?? '')) === '') {
                continue;
            }
            $media['caption'] = (string)($media['caption'] ?? '');
            if (trim($media['caption']) === '') {
                continue;
            }
            $reels[] = $media;
        }

        $cursor = $data['paging']['cursors']['after'] ?? null;
        $hasNext = !empty($data['paging']['next']) && is_string($cursor) && $cursor !== '';
        $oldestTimestamp = null;
        foreach (($data['data'] ?? []) as $media) {
            if (isset($media['timestamp']) && is_string($media['timestamp'])) {
                try {
                    $ts = (new DateTimeImmutable($media['timestamp']))->getTimestamp();
                    $oldestTimestamp = $oldestTimestamp === null ? $ts : min($oldestTimestamp, $ts);
                } catch (Throwable) {
                }
            }
        }
        $after = $hasNext && ($oldestTimestamp === null || $oldestTimestamp >= $start) ? $cursor : null;
        $pages++;
        if ($after !== null) {
            if (isset($seenCursors[$after]) || $pages >= 1000) {
                sync_instagram_fail('Lettura Instagram incompleta: limite di paginazione raggiunto. Nessun link salvato.');
            }
            $seenCursors[$after] = true;
        }
        if ($after === null) {
            break;
        }
    }

    return $reels;
}

if (defined('TOS_INSTAGRAM_SYNC_LIBRARY')) return;

$dryRun = $isCli
    ? in_array('--dry-run', $argv, true)
    : (string)($_POST['mode'] ?? '') === 'dry-run';
$requestedDate = null;
if ($isCli) {
    foreach ($argv as $argument) {
        if (str_starts_with($argument, '--date=')) {
            $requestedDate = substr($argument, 7);
        }
    }
} elseif (isset($_POST['date'])) {
    $requestedDate = trim((string)$_POST['date']);
}
$yesterday = (new DateTimeImmutable('yesterday', new DateTimeZone('Europe/Rome')))->setTime(0, 0);
$targetDay = $yesterday;
if ($requestedDate !== null) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate, new DateTimeZone('Europe/Rome'));
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$parsed instanceof DateTimeImmutable
        || (is_array($dateErrors) && (($dateErrors['warning_count'] ?? 0) || ($dateErrors['error_count'] ?? 0)))
        || $parsed->format('Y-m-d') !== $requestedDate) {
        sync_instagram_fail('Data non valida. Usa --date=YYYY-MM-DD.');
    }
    $targetDay = $parsed;
}
$targetDate = $targetDay->format('Y-m-d');
$mode = $dryRun ? 'prova senza modifiche' : 'aggiornamento attivo';
sync_instagram_log("Avvio sincronizzazione per {$targetDate} ({$mode}).");

$state = tos_load_instagram_token_state();
if ($state === null) {
    $envToken = trim((string)getenv('INSTAGRAM_ACCESS_TOKEN'));
    $envUserId = trim((string)getenv('INSTAGRAM_USER_ID'));
    if ($envToken === '' || $envUserId === '') {
        sync_instagram_fail('Collegamento Instagram assente sul server. Collega il profilo dal generatore e salva il token nel runtime privato.');
    }
    $state = [
        'access_token' => $envToken,
        'user_id' => $envUserId,
        'issued_at' => 0,
        'expires_at' => 0,
        'refresh_after' => time() + tos_instagram_token_refresh_interval_seconds(),
    ];
    if (!tos_save_instagram_token_state($state)) {
        sync_instagram_fail('Impossibile salvare il token e la pianificazione del rinnovo nel runtime privato.');
    }
}
$refreshed = sync_instagram_refresh_token($state);
if (!$refreshed['ok']) {
    sync_instagram_fail((string)$refreshed['error']);
}
$state = $refreshed['state'];
$token = trim((string)$state['access_token']);
$userId = trim((string)$state['user_id']);
$apiVersion = trim((string)(getenv('INSTAGRAM_GRAPH_API_VERSION') ?: 'v26.0'));
if (!preg_match('/^v\d+\.\d+$/', $apiVersion)) {
    sync_instagram_fail('INSTAGRAM_GRAPH_API_VERSION non è configurata correttamente.');
}

$reels = sync_instagram_fetch_yesterdays_reels($userId, $token, $apiVersion, $targetDay);
sync_instagram_log('Reel trovati per il giorno: ' . count($reels) . '.');

require_once __DIR__ . '/../../includi/db.php';
$stmt = $conn->prepare("SELECT p.id, p.torneo, COALESCE(t.nome, p.torneo) AS torneo_nome,
        p.squadra_casa, p.squadra_ospite, p.link_instagram,
        p.giornata,p.fase_round,p.gol_casa,p.gol_ospite,p.giocata,p.data_partita
    FROM partite p
    LEFT JOIN tornei t ON t.id = (
        SELECT t2.id FROM tornei t2
        WHERE t2.filetorneo = p.torneo
           OR t2.filetorneo = CONCAT(p.torneo, '.php')
           OR t2.nome = p.torneo
        ORDER BY (t2.filetorneo = p.torneo) DESC,
                 (t2.filetorneo = CONCAT(p.torneo, '.php')) DESC,
                 t2.id ASC LIMIT 1
    )
    WHERE p.data_partita >= ? AND p.data_partita < ? AND p.giocata=1
    ORDER BY p.id ASC");
if (!$stmt) {
    sync_instagram_fail('Query delle partite non disponibile.');
}
$matchStart = $targetDay->modify('-1 day')->format('Y-m-d');
$matchEnd = $targetDay->modify('+1 day')->format('Y-m-d');
$stmt->bind_param('ss', $matchStart,$matchEnd);
if (!$stmt->execute()) {
    sync_instagram_fail('Lettura delle partite non riuscita.');
}
$result = $stmt->get_result();
$matches = [];
while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['_tournament_aliases'] = sync_instagram_tournament_aliases($row);
    $matches[] = $row;
}
$stmt->close();
sync_instagram_log('Partite nel database per il giorno: ' . count($matches) . '.');

$candidatesByReel = [];
foreach ($reels as $reel) {
    $caption = (string)$reel['caption'];
    $identity = video_sync_parse_result($caption);
    $candidateIds = [];
    foreach ($matches as $match) {
        if ($identity !== null) {
            if (video_sync_result_match($identity,$match,$targetDate)) $candidateIds[] = (int)$match['id'];
            continue;
        }
    }
    $candidatesByReel[(string)$reel['id']] = [
        'reel' => $reel,
        'match_ids' => array_values(array_unique($candidateIds)),
    ];
}

$linksByMatch = [];
$unmatched = 0;
$ambiguousReels = 0;
foreach ($candidatesByReel as $candidate) {
    $ids = $candidate['match_ids'];
    if (count($ids) === 0) {
        $unmatched++;
        continue;
    }
    if (count($ids) !== 1) {
        $ambiguousReels++;
        sync_instagram_log('Da verificare: un Reel cita più partite; nessun link scritto.');
        continue;
    }
    $matchId = $ids[0];
    $linksByMatch[$matchId][] = [
        'permalink' => (string)$candidate['reel']['permalink'],
        'caption' => (string)$candidate['reel']['caption'],
        'media_id' => (string)$candidate['reel']['id'],
    ];
}

$update = $conn->prepare("UPDATE partite SET link_instagram = ? WHERE id = ? AND (link_instagram IS NULL OR TRIM(link_instagram) = '')");
if (!$update) {
    sync_instagram_fail('Aggiornamento dei link non disponibile.');
}
$saved = 0;
$alreadyLinked = 0;
$ambiguousMatches = 0;
foreach ($linksByMatch as $matchId => $links) {
    if (count($links) !== 1) {
        $ambiguousMatches++;
        sync_instagram_log('Da verificare: più Reel corrispondono alla stessa partita ID ' . (int)$matchId . '; nessun link scritto.');
        continue;
    }
    $permalink = trim((string)$links[0]['permalink']);
    if (!preg_match('~^https://(?:www\.)?instagram\.com/(?:reel|reels|p)/[^/?#]+/?$~i', $permalink)) {
        $unmatched++;
        continue;
    }
    if ($dryRun) {
        sync_instagram_log('Corrispondenza: partita ID ' . (int)$matchId . ' ← ' . $permalink);
        $saved++;
        continue;
    }
    $update->bind_param('si', $permalink, $matchId);
    if (!$update->execute()) {
        sync_instagram_log('Errore scrittura partita ID ' . (int)$matchId . '.');
        continue;
    }
    if ($update->affected_rows === 1) {
        sync_instagram_log('Link salvato sulla partita ID ' . (int)$matchId . ': ' . $permalink);
        $saved++;
    } else {
        $alreadyLinked++;
    }
}
$update->close();

sync_instagram_log('Riepilogo: ' . $saved . ' ' . ($dryRun ? 'corrispondenze' : 'link salvati')
    . ', ' . $unmatched . ' Reel senza abbinamento, ' . $ambiguousReels . ' Reel ambigui, '
    . $ambiguousMatches . ' partite con più Reel, ' . $alreadyLinked . ' partite già collegate.');
