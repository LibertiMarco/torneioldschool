<?php
declare(strict_types=1);
require_once __DIR__.'/match_reminders.php';

function schedule_values(array $match): array {
    $date = trim((string)($match['data_partita'] ?? ''));
    $time = trim((string)($match['ora_partita'] ?? ''));
    if ($date === '0000-00-00') $date = '';
    if (preg_match('/^\d{2}:\d{2}$/D', $time)) $time .= ':00';
    return ['data_partita'=>$date, 'ora_partita'=>$time, 'campo'=>trim((string)($match['campo'] ?? ''))];
}

/** New matches establish a baseline; only existing, unplayed future matches produce changes. */
function schedule_diff(?array $before, array $match, DateTimeImmutable $now): array {
    if ($before === null || (int)($match['giocata'] ?? 0) !== 0) return [];
    $kickoff = reminder_kickoff($match);
    if ($kickoff && $kickoff <= $now) return [];
    $values = schedule_values($match);
    if ($values['data_partita'] !== '' && $values['data_partita'] < $now->setTimezone(new DateTimeZone('Europe/Rome'))->format('Y-m-d')) return [];
    $changes = [];
    foreach ($values as $key=>$value) {
        if (($before[$key] ?? '') !== $value) $changes[$key] = ['before'=>$before[$key] ?? '', 'after'=>$value];
    }
    return $changes;
}

function schedule_value_label(string $key, string $value): string {
    if ($value === '') return 'da definire';
    if ($key === 'data_partita') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date->format('d/m/Y') : $value;
    }
    return $key === 'ora_partita' ? substr($value, 0, 5) : $value;
}

function schedule_change_body(array $match, array $changes): string {
    $parts = [$match['squadra_casa'].' - '.$match['squadra_ospite']];
    foreach (['data_partita'=>'Data', 'ora_partita'=>'Ora', 'campo'=>'Campo'] as $key=>$label) {
        if (isset($changes[$key])) $parts[] = $label.': '.schedule_value_label($key, $changes[$key]['after']);
    }
    return implode(' · ', $parts);
}

/** Caller owns the worker lock. Saving before dispatch prevents repeating an uncertain send. */
function schedule_observe(string $directory, array $match, DateTimeImmutable $now, bool $dryRun, callable $emit): array {
    $path = $directory.'/'.(int)$match['id'].'.json';
    $state = null;
    if (is_file($path)) {
        $state = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['version'] ?? null) !== 1 || !is_array($state['values'] ?? null)) {
            throw new RuntimeException('Stato cambi calendario non valido');
        }
    }
    $changes = schedule_diff($state['values'] ?? null, $match, $now);
    $values = schedule_values($match);
    $revision = (int)($state['revision'] ?? 0) + ($changes ? 1 : 0);
    if (!$dryRun && ($state === null || $values !== $state['values'])) {
        reminder_write($path, ['version'=>1, 'values'=>$values, 'revision'=>$revision]);
    }
    if ($changes && !$dryRun) $emit($match, $changes, $revision);
    return $changes;
}

function schedule_changes_run(mysqli $db, string $runtime, bool $dryRun, DateTimeImmutable $now, TosNotificationPreferences $store, float $deadline): array {
    $directory = $runtime.'/schedule-changes';
    if (!$dryRun && !is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Runtime cambi calendario non disponibile');
    // Independent of the reminder lookahead: covers changes to any unplayed fixture.
    $result = $db->query('SELECT id, torneo, squadra_casa, squadra_ospite, data_partita, ora_partita, campo, giocata FROM partite WHERE giocata=0 ORDER BY id');
    $changed = 0; $attempted = 0;
    while ($match = $result->fetch_assoc()) {
        if (microtime(true) >= $deadline) throw new RuntimeException('Tempo controllo calendario esaurito');
        $changes = schedule_observe($directory, $match, $now, $dryRun,
            static function (array $match, array $changes, int $revision) use ($db, $store, &$attempted): void {
                $check = $db->prepare('SELECT id, torneo, squadra_casa, squadra_ospite, data_partita, ora_partita, campo, giocata FROM partite WHERE id=?');
                $check->bind_param('i', $match['id']); $check->execute();
                $current = $check->get_result()->fetch_assoc(); $check->close();
                if (!$current || (int)$current['giocata'] !== 0 || schedule_values($current) !== schedule_values($match)) return;
                $match = $current;
                $payload = tos_push_build_payload('Calendario partita aggiornato', schedule_change_body($match, $changes),
                    '/tornei/partita_eventi.php?id='.(int)$match['id'].'&torneo='.rawurlencode($match['torneo']),
                    ['tag'=>'match-schedule-'.(int)$match['id'].'-'.$revision]);
                $users = notification_filter_recipients(reminder_recipients($db, $match), $payload, $store);
                if ($users) {
                    $sent = tos_push_send_to_users($db, $users, $payload);
                    $attempted += (int)($sent['subscriptions'] ?? 0);
                }
            });
        if ($changes) $changed++;
    }
    return ['changed'=>$changed, 'subscriptions_attempted'=>$attempted];
}
