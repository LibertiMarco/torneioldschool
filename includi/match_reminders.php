<?php
declare(strict_types=1);
require_once __DIR__.'/notification_preferences.php';

function reminder_kickoff(array $match): ?DateTimeImmutable {
    if ((int)($match['giocata'] ?? 0) !== 0) return null;
    $text = (string)($match['data_partita'] ?? '').' '.(string)($match['ora_partita'] ?? '');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $text, new DateTimeZone('Europe/Rome'));
    return $date && $date->format('Y-m-d H:i:s') === $text ? $date : null;
}

function reminder_due(array $match, string $choice, DateTimeImmutable $now): bool {
    $kickoff = reminder_kickoff($match);
    if (!$kickoff || $kickoff <= $now) return false;
    $due = match ($choice) {
        '1h' => $kickoff->getTimestamp() - 3600,
        '2h' => $kickoff->getTimestamp() - 7200,
        // Same local time on the preceding day, including daylight saving changes.
        '1d' => $kickoff->modify('-1 day')->getTimestamp(),
        default => null,
    };
    return $due !== null && $now->getTimestamp() >= $due && $now->getTimestamp() < $due + 300;
}

function reminder_recipients(mysqli $db, array $match): array {
    $sql = "SELECT DISTINCT g.utente_id AS id FROM squadre s
        JOIN squadre_giocatori sg ON sg.squadra_id=s.id JOIN giocatori g ON g.id=sg.giocatore_id
        WHERE s.torneo=? AND s.nome IN (?,?) AND g.utente_id IS NOT NULL
        UNION SELECT utente_id AS id FROM seguiti WHERE torneo_slug=?
        AND (tipo='torneo' OR (tipo='squadra' AND squadra_nome IN (?,?)))";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssssss', $match['torneo'], $match['squadra_casa'], $match['squadra_ospite'],
        $match['torneo'], $match['squadra_casa'], $match['squadra_ospite']);
    $stmt->execute();
    $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $stmt->close();
    return array_values(array_filter(array_unique(array_map('intval', $ids)), fn($id) => $id > 0));
}

function reminder_write(string $path, array $value): void {
    $tmp = $path.'.tmp';
    if (file_put_contents($tmp, json_encode($value, JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('Scrittura stato fallita');
    @chmod($tmp, 0600);
    if (!rename($tmp, $path)) throw new RuntimeException('Aggiornamento stato fallito');
}

/** Persist before dispatch: a crash can lose an alert, but never repeats an uncertain dispatch. */
function reminder_dispatch_once(string $path, callable $send): bool {
    if (is_file($path)) return false;
    reminder_write($path, ['status'=>'claimed', 'at'=>time()]);
    $result = $send();
    reminder_write($path, ['status'=>'attempted', 'at'=>time(), 'result'=>$result]);
    return true;
}
