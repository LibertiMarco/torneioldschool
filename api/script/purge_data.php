<?php
// Uso: php api/script/purge_data.php
require_once __DIR__ . '/../../includi/db.php';

$RETENTION_EVENTS_DAYS = 45;     // eventi_utente (tracking)
$RETENTION_LOG_DAYS = 365;       // consensi_log, newsletter_log
$RETENTION_ANON_DAYS = 365;      // consensi_anonimi (anonimi)

function purge(mysqli $conn, string $table, string $column, int $days): int
{
    $stmt = $conn->prepare("DELETE FROM {$table} WHERE {$column} < (NOW() - INTERVAL ? DAY)");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

$deleted = [
    'eventi_utente' => purge($conn, 'eventi_utente', 'created_at', $RETENTION_EVENTS_DAYS),
    'consensi_log' => purge($conn, 'consensi_log', 'created_at', $RETENTION_LOG_DAYS),
    'newsletter_log' => purge($conn, 'newsletter_log', 'sent_at', $RETENTION_LOG_DAYS),
    'consensi_anonimi' => purge($conn, 'consensi_anonimi', 'updated_at', $RETENTION_ANON_DAYS),
];

foreach ($deleted as $table => $count) {
    echo "{$table}: {$count} record eliminati\n";
}
