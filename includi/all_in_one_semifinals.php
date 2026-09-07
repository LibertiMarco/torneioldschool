<?php
require_once __DIR__ . '/auto_matchday.php';
require_once __DIR__ . '/all_in_one_calendar.php';

function aio_next_five_minutes(DateTimeImmutable $now): DateTimeImmutable
{
    $now = $now->setTimezone(new DateTimeZone('Europe/Rome'));
    $timestamp = (int)(ceil($now->getTimestamp() / 300) * 300);
    return $now->setTimestamp($timestamp);
}

/** Classifica completa solo se ogni coppia del girone ha disputato la sua gara. */
function aio_completed_groups(array $teams, array $matches, string $slug): array
{
    $groups = $byName = $stats = [];
    foreach ($teams as $team) {
        $group = preg_replace('/^(GIRONE|GRUPPO)\s+/u', '', strtoupper(trim($team['girone'] ?? '')));
        if ($group === '' || isset($byName[$team['nome']])) return [];
        $groups[$group][] = $team['nome'];
        $byName[$team['nome']] = $group;
        $stats[$team['nome']] = ['points'=>0, 'diff'=>0, 'goals'=>0];
    }
    if (count($groups) !== 2 || min(array_map('count', $groups)) < 2) return [];
    $seen = [];
    foreach ($matches as $match) {
        $a = $match['squadra_casa']; $b = $match['squadra_ospite'];
        if ((int)$match['giocata'] !== 1 || !isset($byName[$a], $byName[$b]) || $a === $b || $byName[$a] !== $byName[$b]) return [];
        $pair = [$a, $b]; sort($pair);
        $key = json_encode($pair);
        if (isset($seen[$key])) return [];
        $seen[$key] = true;
        $gf = (int)$match['gol_casa']; $gs = (int)$match['gol_ospite'];
        foreach ([[$a,$gf,$gs],[$b,$gs,$gf]] as [$name,$for,$against]) {
            $stats[$name]['points'] += $for > $against ? 3 : ($for === $against ? 1 : 0);
            $stats[$name]['diff'] += $for - $against;
            $stats[$name]['goals'] += $for;
        }
    }
    $expected = array_sum(array_map(static fn($g) => count($g) * (count($g)-1)/2, $groups));
    if (count($seen) !== (int)$expected) return [];
    ksort($groups, SORT_NATURAL);
    $ranked = [];
    foreach ($groups as $group => $names) {
        $buckets = [];
        foreach ($names as $name) $buckets[$stats[$name]['points']][] = $name;
        krsort($buckets, SORT_NUMERIC);
        $ranked[$group] = [];
        foreach ($buckets as $tied) {
            $mini = array_fill_keys($tied, ['points'=>0,'diff'=>0,'goals'=>0]);
            foreach ($matches as $match) {
                $a = $match['squadra_casa']; $b = $match['squadra_ospite'];
                if (!isset($mini[$a], $mini[$b])) continue;
                foreach ([[$a,(int)$match['gol_casa'],(int)$match['gol_ospite']],[$b,(int)$match['gol_ospite'],(int)$match['gol_casa']]] as [$name,$for,$against]) {
                    $mini[$name]['points'] += $for > $against ? 3 : ($for === $against ? 1 : 0);
                    $mini[$name]['diff'] += $for - $against;
                    $mini[$name]['goals'] += $for;
                }
            }
            // La versione Mondiale originale usa lo scontro diretto solo tra due pari punti.
            $legacy = strcasecmp($slug, 'AllInOneNightMondiale') === 0;
            usort($tied, static function ($a, $b) use ($mini, $stats, $legacy, $tied) {
                $keys = $legacy ? (count($tied) === 2 ? ['points'] : []) : ['points','diff','goals'];
                foreach ($keys as $key) if ($mini[$a][$key] !== $mini[$b][$key]) return $mini[$b][$key] <=> $mini[$a][$key];
                foreach (['diff','goals'] as $key) if ($stats[$a][$key] !== $stats[$b][$key]) return $stats[$b][$key] <=> $stats[$a][$key];
                if (class_exists('Collator')) {
                    $collator = new Collator('it_IT');
                    $collator->setStrength(Collator::PRIMARY);
                    return $collator->compare($a, $b);
                }
                return strcasecmp($a, $b);
            });
            array_push($ranked[$group], ...$tied);
        }
    }
    return $ranked;
}

/** Ricava i posti effettivamente utilizzati, anche quando condividono lo stesso nome. */
function aio_semifinal_fields(array $matches): array
{
    $counts = $capacities = [];
    foreach ($matches as $match) {
        $field = trim((string)$match['campo']);
        if ($field === '') continue;
        $slot = $match['data_partita'] . ' ' . $match['ora_partita'];
        $counts[$field][$slot] = ($counts[$field][$slot] ?? 0) + 1;
        $capacities[$field] = max($capacities[$field] ?? 0, $counts[$field][$slot]);
    }
    return all_in_one_field_slots(array_keys($capacities), $capacities);
}

function aio_semifinal_rows(array $groups, array $fields, DateTimeImmutable $now): array
{
    if (count($groups) !== 2 || !$fields) return [];
    [$a, $b] = array_values($groups);
    $start = aio_next_five_minutes($now);
    $rows = [];
    foreach ([[$a[0],$b[1]],[$b[0],$a[1]]] as $i => [$home,$away]) {
        $slot = $start->modify('+' . (intdiv($i,count($fields)) * 15) . ' minutes');
        $rows[] = ['casa'=>$home,'ospite'=>$away,'campo'=>$fields[$i % count($fields)],'data'=>$slot->format('Y-m-d'),'ora'=>$slot->format('H:i:s')];
    }
    return $rows;
}

function aio_finalists(array $semifinals): array
{
    if (count($semifinals) !== 2) return [];
    $winners = $participants = [];
    foreach ($semifinals as $match) {
        if ((int)($match['giocata'] ?? 0) !== 1) return [];
        // Questa automazione gestisce due semifinali a gara unica.
        if (trim((string)($match['fase_leg'] ?? '')) !== '') return [];
        $home = trim((string)$match['squadra_casa']);
        $away = trim((string)$match['squadra_ospite']);
        foreach ([$home, $away] as $team) {
            if ($team === '' || isset($participants[$team])) return [];
            $participants[$team] = true;
        }
        $homeGoals = $match['gol_casa'] ?? null;
        $awayGoals = $match['gol_ospite'] ?? null;
        if ($homeGoals === null || $awayGoals === null) return [];
        if ((int)$homeGoals === (int)$awayGoals) {
            if ((int)($match['decisa_rigori'] ?? 0) !== 1) return [];
            $homeGoals = $match['rigori_casa'] ?? null;
            $awayGoals = $match['rigori_ospite'] ?? null;
            if ($homeGoals === null || $awayGoals === null || (int)$homeGoals < 0 || (int)$awayGoals < 0 || (int)$homeGoals === (int)$awayGoals) return [];
        }
        $winners[] = (int)$homeGoals > (int)$awayGoals ? $home : $away;
    }
    return $winners;
}

function aio_semifinal_query(mysqli $conn, string $sql, string $types = '', array $values = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Preparazione semifinali fallita.');
    if ($types !== '') $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Query semifinali fallita.'); }
    return $stmt;
}

/** Da chiamare dopo il commit del risultato, mai durante il salvataggio della distinta. */
function all_in_one_after_result(mysqli $conn, int $matchId): string
{
    $locked = $transaction = false;
    $isFinal = false;
    try {
        $stmt = aio_semifinal_query($conn, 'SELECT torneo, fase, fase_round, giornata, giocata FROM partite WHERE id = ?', 'i', [$matchId]);
        $match = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$match || stripos($match['torneo'], 'AllInOneNight') === false || (int)$match['giocata'] !== 1) return '';
        $phase = strtoupper(trim($match['fase'] ?? ''));
        $round = strtoupper(trim($match['fase_round'] ?? ''));
        $isFinal = $phase === 'GOLD' && ($round === 'SEMIFINALE' || ($round === '' && (int)$match['giornata'] === 2));
        if (!$isFinal && !in_array($phase, ['', 'REGULAR', 'GIRONE'], true)) return '';
        $slug = $match['torneo'];
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Rome'));
        $stmt = aio_semifinal_query($conn, "SELECT GET_LOCK('all_in_one_calendar', 10) AS acquired");
        $locked = (int)$stmt->get_result()->fetch_assoc()['acquired'] === 1; $stmt->close();
        if (!$locked) throw new RuntimeException('Generazione calendario occupata.');
        if (!$conn->begin_transaction()) throw new RuntimeException('Transazione semifinali non disponibile.');
        $transaction = true;
        // Non duplicare né sovrascrivere una fase finale già impostata manualmente.
        $finalFilter = $isFinal ? " AND (UPPER(TRIM(fase_round)) = 'FINALE' OR (TRIM(COALESCE(fase_round, '')) = '' AND giornata = 1))" : '';
        $stmt = aio_semifinal_query($conn, "SELECT id FROM partite WHERE torneo = ? AND UPPER(TRIM(fase)) = 'GOLD'" . $finalFilter . " LIMIT 1", 's', [$slug]);
        $exists = $stmt->get_result()->num_rows > 0; $stmt->close();
        if ($exists) { $conn->rollback(); $transaction = false; return ''; }
        $sourceFilter = $isFinal
            ? "UPPER(TRIM(p.fase)) = 'GOLD' AND (UPPER(TRIM(p.fase_round)) = 'SEMIFINALE' OR (TRIM(COALESCE(p.fase_round, '')) = '' AND p.giornata = 2))"
            : auto_matchday_normalize_phase_expr() . " = 'REGULAR'";
        $stmt = aio_semifinal_query($conn, "SELECT * FROM partite p WHERE torneo = ? AND " . $sourceFilter . " ORDER BY data_partita, ora_partita, id", 's', [$slug]);
        $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        if ($isFinal) {
            $winners = aio_finalists($matches);
            if (!$winners) {
                $conn->rollback(); $transaction = false;
                return count($matches) === 2 && array_sum(array_column($matches, 'giocata')) === 2
                    ? 'Finale non creata: verifica che entrambe le semifinali a gara unica abbiano una vincitrice, indicando i rigori in caso di pareggio.' : '';
            }
            $fields = aio_semifinal_fields($matches);
            if (!$fields) throw new RuntimeException('Campi delle semifinali mancanti.');
            $start = aio_next_five_minutes($now);
            $rows = [['casa'=>$winners[0], 'ospite'=>$winners[1], 'campo'=>$fields[0], 'data'=>$start->format('Y-m-d'), 'ora'=>$start->format('H:i:s')]];
        } else {
        $stmt = aio_semifinal_query($conn, 'SELECT id, nome, girone FROM squadre WHERE torneo = ? ORDER BY id', 's', [$slug]);
        $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $groups = aio_completed_groups($teams, $matches, $slug);
        if (!$groups) { $conn->rollback(); $transaction = false; return ''; }
        $fields = aio_semifinal_fields($matches);
        if (!$fields) throw new RuntimeException('Campi dei gironi mancanti.');
        $rows = aio_semifinal_rows($groups, $fields, $now);
        }
        // Le gare dei gironi risultano terminate: non occupano più i campi.
        $stmt = aio_semifinal_query($conn, "SELECT campo, data_partita AS data, ora_partita AS ora FROM partite WHERE giocata = 0 AND data_partita IS NOT NULL AND ora_partita IS NOT NULL AND campo IS NOT NULL");
        $occupied = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        all_in_one_check_capacity($rows, $occupied, $fields);
        $targetRound = $isFinal ? 'FINALE' : 'SEMIFINALE';
        $matchday = $isFinal ? 1 : 2;
        foreach ($rows as $row) {
            $stmt = aio_semifinal_query($conn, "INSERT INTO partite (torneo, fase, fase_round, fase_leg, squadra_casa, squadra_ospite, gol_casa, gol_ospite, data_partita, ora_partita, campo, giornata, giocata, created_at) VALUES (?, 'GOLD', ?, NULL, ?, ?, 0, 0, ?, ?, ?, ?, 0, NOW())", 'sssssssi', [$slug,$targetRound,$row['casa'],$row['ospite'],$row['data'],$row['ora'],$row['campo'],$matchday]);
            $stmt->close();
        }
        if (!$conn->commit()) throw new RuntimeException('Salvataggio semifinali fallito.');
        $transaction = false;
        return ($isFinal ? 'Finale creata automaticamente alle ' : 'Semifinali create automaticamente dalle ') . substr($rows[0]['ora'], 0, 5) . '.';
    } catch (Throwable $e) {
        if ($transaction) $conn->rollback();
        error_log('Semifinali AllInOneNight: ' . $e->getMessage());
        return 'Risultato salvato, ma ' . ($isFinal ? 'finale non creata' : 'semifinali non create') . ': controlla i campi e salva nuovamente il risultato per riprovare.';
    } finally {
        if ($locked) $conn->query("SELECT RELEASE_LOCK('all_in_one_calendar')");
    }
}
