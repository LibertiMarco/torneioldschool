<?php
/** Validate the entire request before writing any of its matches. */
function match_batch_inputs(array $input, array $tournaments, array $teams, array $fields): array
{
    $text = static function ($value): string {
        if (!is_string($value)) throw new InvalidArgumentException('Formato dei campi non valido.');
        return trim($value);
    };
    $tournament = $text($input['torneo'] ?? '');
    $phase = $text($input['fase'] ?? '');
    $field = $text($input['campo'] ?? '');
    $date = $text($input['data_partita'] ?? '');
    if (!in_array($tournament, array_column($tournaments, 'slug'), true)) {
        throw new InvalidArgumentException('Seleziona un torneo disponibile.');
    }
    if (!in_array($phase, ['REGULAR', 'SPAREGGIO', 'GOLD', 'SILVER', 'BRONZO'], true)) {
        throw new InvalidArgumentException('Seleziona una fase valida.');
    }
    $meta = array_values(array_filter($tournaments, static fn($t) => $t['slug'] === $tournament))[0];
    $allowedFields = array_column($fields, 'nome');
    if (($meta['sezione'] ?? 'calcio') === 'esport') $allowedFields[] = 'Online';
    if ($field === '' || !in_array($field, $allowedFields, true)) {
        throw new InvalidArgumentException('Seleziona un campo disponibile.');
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Seleziona una data valida.');
    }
    $rows = $input['partite'] ?? null;
    if (!is_array($rows) || count($rows) < 1 || count($rows) > 50) {
        throw new InvalidArgumentException('Inserisci da 1 a 50 partite per salvataggio.');
    }
    // A PHP max_input_vars truncation must never silently save only some rows.
    if ($text($input['partite_count'] ?? '') !== (string)count($rows)) {
        throw new InvalidArgumentException('Elenco partite incompleto. Ricarica e riprova.');
    }
    $usedTeams = $usedTimes = $result = [];
    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Formato partita non valido.');
        $home = $text($row['squadra_casa'] ?? '');
        $away = $text($row['squadra_ospite'] ?? '');
        $time = $text($row['ora_partita'] ?? '');
        $prefix = 'Partita ' . ($index + 1) . ': ';
        if (!in_array($home, $teams[$tournament] ?? [], true) || !in_array($away, $teams[$tournament] ?? [], true)) {
            throw new InvalidArgumentException($prefix . 'seleziona due squadre del torneo.');
        }
        if ($home === $away) throw new InvalidArgumentException($prefix . 'le squadre non possono coincidere.');
        if (isset($usedTeams[$home]) || isset($usedTeams[$away])) {
            throw new InvalidArgumentException($prefix . 'una squadra compare in piu partite della stessa giornata.');
        }
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) {
            throw new InvalidArgumentException($prefix . 'inserisci un orario valido.');
        }
        if ($field !== 'Online' && isset($usedTimes[$time])) {
            throw new InvalidArgumentException($prefix . 'due partite iniziano alla stessa ora sullo stesso campo.');
        }
        $usedTeams[$home] = $usedTeams[$away] = $usedTimes[$time] = true;
        $result[] = array_merge($input, [
            'torneo' => $tournament, 'fase' => $phase, 'campo' => $field, 'data_partita' => $date,
            'squadra_casa' => $home, 'squadra_ospite' => $away, 'ora_partita' => $time,
            'gol_casa' => '0', 'gol_ospite' => '0', 'giocata' => '0',
        ]);
    }
    return $result;
}

/** One transaction includes every row and any automatic return matches. */
function match_batch_save(object $connection, array $rows, callable $save): void
{
    if (!$connection->begin_transaction()) throw new RuntimeException('Impossibile avviare il salvataggio.');
    try {
        foreach ($rows as $index => $row) {
            $error = $save($row);
            if ($error !== '') throw new InvalidArgumentException('Partita ' . ($index + 1) . ': ' . $error);
        }
        if (!$connection->commit()) throw new RuntimeException('Salvataggio non completato.');
    } catch (Throwable $error) {
        $connection->rollback();
        throw $error;
    }
}
