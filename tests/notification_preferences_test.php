<?php
require_once __DIR__ . '/../includi/notification_preferences.php';
$directory = sys_get_temp_dir() . '/tos-notifications-' . bin2hex(random_bytes(8));
$store = new TosNotificationPreferences($directory);
function notification_expect(bool $ok): void { if (!$ok) throw new RuntimeException('Notification preferences assertion failed'); }
try {
    notification_expect($store->read(1)['results'] === true);
    $store->update(1, ['results' => false]);
    $store->update(1, ['comments' => false]);
    notification_expect($store->read(1)['results'] === false);
    notification_expect($store->read(2)['results'] === true);
    notification_expect(notification_filter_recipients([1,2], ['tag'=>'match-reminder-99'], $store) === []);
    $store->update(2, ['reminder'=>'1h']);
    notification_expect(notification_filter_recipients([1,2,2], ['tag'=>'match-reminder-99'], $store) === [2]);
    notification_expect(notification_filter_recipients([1, 2, 2], ['tag' => 'match-match_finale'], $store) === [2]);
    notification_expect(notification_filter_recipients([1, 2], ['tag' => 'comment-reply-5'], $store) === [2]);
    notification_expect(notification_filter_recipients([1, 2], ['tag' => 'other'], $store) === [1, 2]);
    foreach ([['results' => 0], ['unknown' => true]] as $invalid) {
        $rejected = false;
        try { $store->update(1, $invalid); } catch (InvalidArgumentException $error) { $rejected = true; }
        notification_expect($rejected);
    }
    echo "Notification preferences: persistence, partial updates, account isolation, delivery filters and validation passed.\n";
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
    if (is_file($directory . '/.htaccess')) unlink($directory . '/.htaccess');
    if (is_dir($directory)) rmdir($directory);
}
