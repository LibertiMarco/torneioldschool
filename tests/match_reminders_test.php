<?php
require_once __DIR__.'/../includi/match_reminders.php';
function check_reminder(bool $ok): void { if (!$ok) throw new RuntimeException('Reminder assertion failed'); }
$match = ['data_partita'=>'2026-10-25','ora_partita'=>'18:00:00','giocata'=>0];
$zone = new DateTimeZone('Europe/Rome');
foreach (['1h'=>'2026-10-25 17:00:00','2h'=>'2026-10-25 16:00:00','1d'=>'2026-10-24 18:00:00'] as $choice=>$time) {
    $now = new DateTimeImmutable($time, $zone);
    check_reminder(reminder_due($match, $choice, $now));
    check_reminder(!reminder_due($match, $choice, $now->modify('-1 second')));
    check_reminder(!reminder_due($match, $choice, $now->modify('+5 minutes')));
    check_reminder(!reminder_due(array_replace($match, ['giocata'=>1]), $choice, $now));
}
check_reminder(!reminder_due($match, 'off', new DateTimeImmutable('2026-10-25 17:00:00', $zone)));
check_reminder(reminder_kickoff(array_replace($match, ['data_partita'=>'2026-02-30'])) === null);
check_reminder(reminder_kickoff(array_replace($match, ['ora_partita'=>''])) === null);
check_reminder((new TosNotificationPreferences())->defaults()['reminder'] === 'off');
foreach (['off','1h','2h','1d'] as $choice) TosNotificationPreferences::validate(['reminder'=>$choice]);
$rejected = false;
try { TosNotificationPreferences::validate(['reminder'=>true]); } catch (InvalidArgumentException $e) { $rejected = true; }
check_reminder($rejected);
$path = sys_get_temp_dir().'/reminder-'.bin2hex(random_bytes(8)).'.json';
try {
    $count = 0;
    try { reminder_dispatch_once($path, function () use (&$count) { $count++; throw new RuntimeException('Simulated interruption'); }); } catch (RuntimeException $e) {}
    check_reminder(!reminder_dispatch_once($path, function () use (&$count) { $count++; }));
    check_reminder($count === 1);
} finally { if (is_file($path)) unlink($path); }
echo "Reminder timing, DST, exclusions, preferences and crash deduplication passed.\n";
