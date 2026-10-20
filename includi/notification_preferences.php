<?php
require_once __DIR__ . '/env_loader.php';

/** Private account preferences, independent of device permission and follows. */
final class TosNotificationPreferences
{
    private string $directory;
    public function __construct(?string $directory = null) {
        $this->directory = $directory ?? tos_runtime_path('notification-preferences');
    }
    public static function defaults(): array {
        return ['results' => true, 'comments' => true];
    }
    private function path(int $userId): string {
        if ($userId < 1) throw new InvalidArgumentException('Invalid account');
        return $this->directory . '/' . $userId . '.json';
    }
    public function read(int $userId): array {
        $path = $this->path($userId);
        if (!is_file($path)) return self::defaults();
        $data = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['preferences'] ?? null)) {
            throw new RuntimeException('Invalid notification preferences');
        }
        return self::validate($data['preferences']) + self::defaults();
    }
    public static function validate(array $patch): array {
        foreach ($patch as $key => $value) {
            if (!array_key_exists($key, self::defaults()) ||
                !is_bool($value)) {
                throw new InvalidArgumentException('Preferenza notifiche non valida.');
            }
        }
        return $patch;
    }
    public function update(int $userId, array $patch): array {
        self::validate($patch);
        $path = $this->path($userId);
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Notification preferences storage unavailable');
        }
        // Also protects an explicitly configured runtime below a local web root.
        if (!is_file($this->directory . '/.htaccess')) file_put_contents($this->directory . '/.htaccess', "Require all denied\n");
        $lock = fopen($path . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Notification preferences lock unavailable');
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            $next = array_replace($this->read($userId), $patch);
            $json = json_encode(['version' => 1, 'preferences' => $next], JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json, LOCK_EX) === false) throw new RuntimeException('Preferences write failed');
            @chmod($temporary, 0600);
            if (!rename($temporary, $path)) throw new RuntimeException('Preferences replace failed');
            return $next;
        } finally {
            if (is_file($temporary)) unlink($temporary);
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}

function notification_preferences_handle(int $userId, ?array $patch, ?TosNotificationPreferences $store = null): array {
    if ($userId < 1) throw new MobileAuthError('invalid_session', 'Accesso richiesto.', 401);
    $store ??= new TosNotificationPreferences();
    try {
        $preferences = $patch === null ? $store->read($userId) : $store->update($userId, $patch);
    } catch (InvalidArgumentException $error) {
        throw new MobileAuthError('invalid_request', 'Preferenza notifiche non valida.', 422);
    }
    return ['preferences' => $preferences];
}

function notification_category(array $payload): ?string {
    $category = $payload['category'] ?? null;
    if (in_array($category, ['results', 'comments'], true)) return $category;
    $tag = (string)($payload['tag'] ?? '');
    if (str_starts_with($tag, 'comment-reply-')) return 'comments';
    if ($tag === 'match-match_finale') return 'results';
    return null;
}

function notification_preference_allows(array $preferences, string $category): bool {
    return ($preferences[$category] ?? true) === true;
}

/** Errors fail closed only for the affected account; unrelated events stay compatible. */
function notification_filter_recipients(array $userIds, array $payload, ?TosNotificationPreferences $store = null): array {
    $category = notification_category($payload);
    if ($category === null) return $userIds;
    $store ??= new TosNotificationPreferences();
    $allowed = [];
    foreach (array_unique(array_map('intval', $userIds)) as $userId) {
        if ($userId < 1) continue;
        try {
            if (notification_preference_allows($store->read($userId), $category)) $allowed[] = $userId;
        } catch (Throwable $error) { error_log('Notification preferences unavailable for recipient'); }
    }
    return $allowed;
}
