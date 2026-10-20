<?php
declare(strict_types=1);
// CLI only: no public execution endpoint or tokens in URLs.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../../includi/match_reminders.php';
if (in_array('--help', $argv, true)) {
    echo "Uso: php api/script/send_match_reminders.php --scheduled | --dry-run\n"; exit;
}
$dry = in_array('--dry-run', $argv, true);
if (!$dry && !in_array('--scheduled', $argv, true)) { fwrite(STDERR, "Specificare --scheduled oppure --dry-run\n"); exit(1); }
$lock = null;
try {
    $directory = tos_runtime_path('match-reminders');
    if (!$dry) {
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Runtime non disponibile');
        file_put_contents($directory.'/.htaccess', "Require all denied\n");
        $lock = fopen($directory.'/run.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { echo "busy\n"; exit; }
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), (string)getenv('DB_NAME'));
    $db->set_charset('utf8mb4');
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Rome'));
    $from = $now->format('Y-m-d'); $to = $now->modify('+2 days')->format('Y-m-d');
    $stmt = $db->prepare('SELECT id, torneo, squadra_casa, squadra_ospite, data_partita, ora_partita, giocata FROM partite WHERE giocata=0 AND data_partita BETWEEN ? AND ? ORDER BY data_partita, ora_partita, id');
    $stmt->bind_param('ss', $from, $to); $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    if (!$dry) require_once __DIR__.'/../../includi/push_notifications.php';
    $store = new TosNotificationPreferences(); $due = 0; $attempted = 0;
    $started = microtime(true);
    foreach ($matches as $match) {
        if (!reminder_kickoff($match)) continue;
        foreach (reminder_recipients($db, $match) as $uid) {
            if (microtime(true)-$started > 45) throw new RuntimeException('Tempo esecuzione esaurito');
            $preferences = $store->read($uid);
            if (!reminder_due($match, $preferences['reminder'], $now)) continue;
            $due++;
            if ($dry) continue;
            // Once per account and match, even when the choice or kickoff later changes.
            $path = $directory.'/'.(int)$match['id'].'-'.$uid.'.json';
            $attempted += (int)reminder_dispatch_once($path, static function () use ($db, $uid, $match, $store) {
                // Recheck current match and preference immediately before dispatch.
                $check = $db->prepare('SELECT data_partita, ora_partita, giocata FROM partite WHERE id=?');
                $check->bind_param('i', $match['id']); $check->execute();
                $current = $check->get_result()->fetch_assoc(); $check->close();
                $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Rome'));
                if (!$current || !reminder_due($current, $store->read($uid)['reminder'], $now)) return ['skipped'=>true];
                $date = reminder_kickoff($current);
                $payload = tos_push_build_payload('Promemoria partita',
                    $match['squadra_casa'].' - '.$match['squadra_ospite'].' · '.$date->format('d/m H:i'),
                    '/tornei/partita_eventi.php?id='.(int)$match['id'].'&torneo='.rawurlencode($match['torneo']),
                    ['tag'=>'match-reminder-'.(int)$match['id']]);
                return tos_push_send_to_users($db, [$uid], $payload);
            });
        }
    }
    if (!$dry) {
        $path = $directory.'/heartbeat.json';
        $previous = is_file($path) ? json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR) : [];
        $last = (int)($previous['last_success'] ?? 0); $timestamp = time();
        $runs = $timestamp - $last <= 180 ? (int)($previous['successful_runs'] ?? 0) : 0;
        reminder_write($path, ['last_success'=>$timestamp, 'successful_runs'=>min(2, $runs + ($timestamp-$last >= 30 ? 1 : 0))]);
    }
    echo json_encode(['status'=>'done', 'dry_run'=>$dry, 'due'=>$due, 'attempted'=>$attempted]).PHP_EOL;
} catch (Throwable $error) {
    // Diagnostics stay in the hosting task log, never in a public response.
    fwrite(STDERR, 'Promemoria: '.$error->getMessage().PHP_EOL); exit(1);
} finally {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
