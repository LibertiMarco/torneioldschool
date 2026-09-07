<?php
/** Espande ogni impianto in posti disponibili, mantenendo il nome del campo. */
function all_in_one_field_slots(array $fields, array $capacities): array
{
    $slots = [];
    foreach ($fields as $field) {
        $capacity = filter_var($capacities[$field] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 32]]);
        if ($capacity === false) throw new InvalidArgumentException('Il numero di campi per ' . $field . ' deve essere compreso tra 1 e 32.');
        for ($i = 0; $i < $capacity; $i++) $slots[] = $field;
    }
    return $slots;
}

/** Verifica la capienza nelle fasce di 15 minuti, comprese le gare esistenti. */
function all_in_one_check_capacity(array $rows, array $occupied, array $fieldSlots): void
{
    $capacities = array_count_values(array_map('strtolower', $fieldSlots));
    foreach ($rows as $row) {
        $key = strtolower($row['campo']);
        $start = (new DateTimeImmutable($row['data'] . ' ' . $row['ora'], new DateTimeZone('Europe/Rome')))->getTimestamp();
        $end = $start + 900;
        $events = [];
        foreach ([$rows, $occupied] as $matches) {
            foreach ($matches as $match) {
                if (strtolower($match['campo']) !== $key) continue;
                $other = (new DateTimeImmutable($match['data'] . ' ' . $match['ora'], new DateTimeZone('Europe/Rome')))->getTimestamp();
                if ($other >= $end || $other + 900 <= $start) continue;
                $from = max($start, $other);
                $to = min($end, $other + 900);
                $count = $match['count'] ?? 1;
                $events[$from] = ($events[$from] ?? 0) + $count;
                $events[$to] = ($events[$to] ?? 0) - $count;
            }
        }
        ksort($events, SORT_NUMERIC);
        $used = 0;
        foreach ($events as $delta) {
            $used += $delta;
            if ($used > $capacities[$key]) throw new RuntimeException('Capienza superata per ' . $row['campo'] . ' vicino alle ' . substr($row['ora'], 0, 5) . ' del ' . $row['data'] . '. Scegli altri campi o un altro orario.');
        }
    }
}

/** Round robin, con ordine iniziale 1-2, 3-4 e secondo turno 1-3, 2-4. */
function all_in_one_rounds(array $teams): array
{
    $teams = array_values($teams);
    if (count($teams) < 2) {
        throw new InvalidArgumentException('Ogni girone deve avere almeno due squadre.');
    }
    if (count($teams) % 2) $teams[] = null;
    $n = count($teams);
    $ring = [$teams[0]];
    for ($i = 2; $i < $n; $i += 2) $ring[] = $teams[$i];
    for ($i = $n - 1; $i >= 1; $i -= 2) $ring[] = $teams[$i];
    $rounds = [];
    for ($r = 0; $r < $n - 1; $r++) {
        $pairs = [];
        for ($i = 0; $i < $n / 2; $i++) {
            $a = $ring[$i];
            $b = $ring[$n - 1 - $i];
            if ($a !== null && $b !== null) {
                $pairs[] = (int)$a['id'] < (int)$b['id'] ? [$a, $b] : [$b, $a];
            }
        }
        $rounds[] = $pairs;
        $fixed = array_shift($ring);
        $ring[] = array_shift($ring);
        array_unshift($ring, $fixed);
    }
    return $rounds;
}

function all_in_one_calendar(array $teams, array $fields, DateTimeImmutable $start): array
{
    if (!$fields) throw new InvalidArgumentException('Seleziona almeno un campo.');
    usort($teams, static fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);
    $groups = [];
    foreach ($teams as $team) {
        $group = preg_replace('/^(GIRONE|GRUPPO)\s+/u', '', strtoupper(trim($team['girone'] ?? '')));
        if ($group === '') throw new InvalidArgumentException('Assegna un girone a tutte le squadre prima di generare il calendario.');
        $groups[$group][] = $team;
    }
    if (!$groups) throw new InvalidArgumentException('Il torneo non ha squadre.');
    ksort($groups, SORT_NATURAL);
    $rounds = array_map('all_in_one_rounds', $groups);
    $rows = [];
    $slot = $start;
    for ($r = 0; $r < max(array_map('count', $rounds)); $r++) {
        foreach ($rounds as $group => $groupRounds) {
            foreach (array_chunk($groupRounds[$r] ?? [], count($fields)) as $batch) {
                foreach ($batch as $i => [$home, $away]) {
                    $rows[] = ['girone' => $group, 'giornata' => $r + 1,
                        'casa' => $home['nome'], 'ospite' => $away['nome'],
                        'data' => $slot->format('Y-m-d'), 'ora' => $slot->format('H:i:s'), 'campo' => $fields[$i]];
                }
                $slot = $slot->modify('+15 minutes');
            }
        }
    }
    return $rows;
}
