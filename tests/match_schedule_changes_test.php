<?php
require_once __DIR__.'/../includi/match_schedule_changes.php';
function schedule_expect(bool $ok): void { if (!$ok) throw new RuntimeException('Schedule assertion failed'); }
$directory = sys_get_temp_dir().'/tos-schedule-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$now = new DateTimeImmutable('2026-10-20 12:00:00', new DateTimeZone('Europe/Rome'));
$match = ['id'=>7, 'torneo'=>'test', 'squadra_casa'=>'Casa', 'squadra_ospite'=>'Ospite',
    'data_partita'=>'2026-10-25','ora_partita'=>'18:00:00','campo'=>'Campo A','giocata'=>0];
$events = [];
$oldRuntime = getenv('TOS_RUNTIME_DIR');
$emit = function ($match, $changes, $revision) use (&$events) { $events[] = [$changes, $revision]; };
try {
    schedule_observe($directory, $match, $now, true, $emit);
    schedule_expect(glob($directory.'/*') === [] && $events === []);
    schedule_observe($directory, $match, $now, false, $emit);
    schedule_observe($directory, $match, $now, false, $emit);
    schedule_expect($events === []);
    $updated = array_replace($match, ['data_partita'=>'2026-10-26','ora_partita'=>'19:30:00','campo'=>'Campo B']);
    schedule_observe($directory, $updated, $now, true, $emit);
    schedule_expect($events === []);
    schedule_observe($directory, $updated, $now, false, $emit);
    schedule_observe($directory, $updated, $now, false, $emit);
    schedule_expect(count($events) === 1 && count($events[0][0]) === 3 && $events[0][1] === 1);
    schedule_observe($directory, $match, $now, false, $emit);
    schedule_expect(count($events) === 2 && $events[1][1] === 2);
    schedule_expect(schedule_diff(schedule_values($match), array_replace($updated, ['giocata'=>1]), $now) === []);
    schedule_expect(schedule_diff(schedule_values($match), array_replace($updated, ['data_partita'=>'2026-10-19','ora_partita'=>'']), $now) === []);
    schedule_expect(schedule_diff(schedule_values($match), array_replace($match, ['campo'=>'  Campo A  ', 'ora_partita'=>'18:00']), $now) === []);
    $removed = array_replace($match, ['data_partita'=>null, 'ora_partita'=>null, 'campo'=>null]);
    schedule_expect(count(schedule_diff(schedule_values($match), $removed, $now)) === 3);
    schedule_expect(str_contains(schedule_change_body($removed, schedule_diff(schedule_values($match), $removed, $now)), 'da definire'));
    try {
        schedule_observe($directory, $updated, $now, false, function () { throw new RuntimeException('Simulated interruption'); });
    } catch (RuntimeException $e) {}
    $count = count($events);
    schedule_observe($directory, $updated, $now, false, $emit);
    schedule_expect(count($events) === $count);
    schedule_expect(!notification_preference_allows([], 'schedule_changes'));
    schedule_expect(!notification_preference_allows(TosNotificationPreferences::defaults(), 'schedule_changes'));
    schedule_expect(notification_preference_allows(['schedule_changes'=>true], 'schedule_changes'));
    schedule_expect(notification_category(['tag'=>'match-schedule-7-1']) === 'schedule_changes');
    putenv('TOS_RUNTIME_DIR='.$directory);
    mkdir($directory.'/match-reminders', 0700);
    $heartbeat = $directory.'/match-reminders/heartbeat.json';
    reminder_write($heartbeat, ['last_success'=>time(), 'successful_runs'=>2]);
    schedule_expect(notification_reminders_available() && !notification_reminders_available(true));
    reminder_write($heartbeat, ['last_success'=>time(), 'successful_runs'=>2, 'schedule_changes_ready'=>true]);
    schedule_expect(notification_reminders_available(true));
    reminder_write($heartbeat, ['last_success'=>time()-661, 'successful_runs'=>2, 'schedule_changes_ready'=>true]);
    schedule_expect(!notification_reminders_available(true));
    echo "Schedule baseline, combined changes, dry-run, exclusions, revisions, opt-in and crash deduplication passed.\n";
} finally {
    putenv($oldRuntime === false ? 'TOS_RUNTIME_DIR' : 'TOS_RUNTIME_DIR='.$oldRuntime);
    if (is_file($directory.'/match-reminders/heartbeat.json')) unlink($directory.'/match-reminders/heartbeat.json');
    if (is_dir($directory.'/match-reminders')) rmdir($directory.'/match-reminders');
    foreach (glob($directory.'/*') ?: [] as $file) unlink($file);
    rmdir($directory);
}
