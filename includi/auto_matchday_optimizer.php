<?php
function auto_matchday_slot_preference_penalty(array $slot, array $rules): int {
    $preferred = [];
    foreach ($rules as $rule) foreach (($rule['preferred_times'] ?? []) as $time) $preferred[$time] = true;
    return $preferred && !isset($preferred[$slot['ora']]) ? 1 : 0;
}
/** Exact joint search: every team plays once, and availability is a hard constraint. */
function auto_matchday_optimize_schedule(
    array $teams, array $history, array $pairCounts, array $pairHistory,
    array $slots, array $availability, array $occupied, bool $allowReturn,
    float $timeBudget = 8.0
): array {
    $start = microtime(true);
    $failure = static function(string $message, bool $optimal = true): array {
        return ['complete' => false, 'optimal' => $optimal, 'rows' => [], 'messages' => [$message]];
    };
    if (count($teams) < 2) return $failure('Servono almeno due squadre.');
    if (count($teams) % 2) return $failure('Numero dispari di squadre: tutte devono giocare una volta, quindi serve un numero pari di partecipanti.');

    // Equivalent copies of a field/time are a single resource with residual capacity.
    $resources = [];
    foreach ($slots as $slot) {
        $key = $slot['public_key'] ?? auto_matchday_slot_key($slot['data'], $slot['ora'], $slot['campo']);
        if (!isset($resources[$key])) $resources[$key] = ['slot' => $slot, 'capacity' => 0];
        $resources[$key]['capacity']++;
    }
    foreach ($resources as $key => &$resource) {
        $resource['capacity'] = max(0, $resource['capacity'] - (int)($occupied[$key]['count'] ?? 0));
    }
    unset($resource);
    $resources = array_values(array_filter($resources, static function($r) { return $r['capacity'] > 0; }));
    $capacities = array_column($resources, 'capacity');
    if (array_sum($capacities) < count($teams) / 2) return $failure('Slot liberi insufficienti per far giocare tutte le squadre: aggiungi orari o capienza.');

    // Collapse resources with identical availability and preference profiles.
    // This avoids exploring equivalent field/time permutations.
    $classes = [];
    foreach ($resources as $resource) {
        $profile = [];
        foreach ($teams as $id => $team) {
            $rules = $availability[$id] ?? [];
            $profile[$id] = [auto_matchday_slot_matches_team_rules($resource['slot'], $rules)['matched'], auto_matchday_slot_preference_penalty($resource['slot'], $rules)];
        }
        $key = json_encode($profile);
        if (!isset($classes[$key])) $classes[$key] = ['slot' => $resource['slot'], 'capacity' => 0, 'profile' => $profile, 'units' => []];
        $classes[$key]['capacity'] += $resource['capacity'];
        for ($i = 0; $i < $resource['capacity']; $i++) $classes[$key]['units'][] = $resource['slot'];
    }
    $resources = array_values($classes);
    foreach ($resources as &$resource) usort($resource['units'], static function($a, $b) { return ($a['sort_index'] ?? 0) <=> ($b['sort_index'] ?? 0); });
    unset($resource);
    $capacities = array_column($resources, 'capacity');
    $compatible = [];
    foreach ($teams as $id => $team) {
        foreach ($resources as $resourceId => $resource) {
            if (auto_matchday_slot_matches_team_rules($resource['slot'], $availability[$id] ?? [])['matched']) {
                $compatible[$id][$resourceId] = true;
            }
        }
        if (empty($compatible[$id])) return $failure('Nessuno slot libero compatibile con le disponibilità di ' . $team['nome'] . '.');
    }
    $options = [];
    $ids = array_keys($teams);
    sort($ids, SORT_NUMERIC);
    foreach ($ids as $i => $a) {
        foreach (array_slice($ids, $i + 1) as $b) {
            $pair = auto_matchday_candidate_pair($teams[$a], $teams[$b], $history, $pairCounts, $pairHistory, $allowReturn);
            if (!$pair) continue;
            foreach (array_intersect_key($compatible[$a], $compatible[$b]) as $resourceId => $_) {
                $option = ['a' => $a, 'b' => $b, 'pair' => $pair, 'resource' => $resourceId, 'preference' => $resources[$resourceId]['profile'][$a][1] + $resources[$resourceId]['profile'][$b][1]];
                $options[$a][] = $option;
                $options[$b][] = $option;
            }
        }
    }
    foreach ($ids as $id) {
        if (empty($options[$id])) return $failure('Nessun avversario con uno slot comune disponibile per ' . $teams[$id]['nome'] . '. Controlla disponibilità e incontri già disputati.');
    }

    $memo = []; $bounds = []; $nodes = 0; $timedOut = false;
    // Optimistic cost bounds discard branches that cannot beat the best schedule.
    $lowerBound = function(array $remaining, array $capacity) use (&$bounds, $options, $resources): ?array {
        if (!$remaining) return [0, 0, 0];
        $key = implode(',', $remaining) . '|' . implode(',', $capacity);
        if (array_key_exists($key, $bounds)) return $bounds[$key];
        $set = array_fill_keys($remaining, true); $preference = 0; $sport = 0;
        foreach ($remaining as $id) {
            $minPreference = $minSport = PHP_INT_MAX;
            foreach ($options[$id] as $option) {
                if ($capacity[$option['resource']] <= 0 || !isset($set[$option['a']], $set[$option['b']])) continue;
                $minPreference = min($minPreference, $option['preference']);
                $minSport = min($minSport, $option['pair']['score']);
            }
            if ($minPreference === PHP_INT_MAX) return $bounds[$key] = null;
            $preference += $minPreference; $sport += $minSport;
        }
        $slotCosts = [];
        foreach ($resources as $id => $resource) {
            for ($i = $resource['capacity'] - $capacity[$id]; $i < $resource['capacity']; $i++) $slotCosts[] = $resource['units'][$i]['sort_index'] ?? 0;
        }
        if (count($slotCosts) < count($remaining) / 2) return $bounds[$key] = null;
        sort($slotCosts, SORT_NUMERIC);
        return $bounds[$key] = [(int)ceil($preference / 2), (int)ceil($sport / 2), array_sum(array_slice($slotCosts, 0, (int)(count($remaining) / 2)))];
    };
    // Score is lexicographic: preferences, sporting balance, configured slot order.
    $search = function(array $remaining, array $capacity) use (&$search, &$memo, &$nodes, &$timedOut, $lowerBound, $options, $resources, $start, $timeBudget): ?array {
        if (microtime(true) - $start >= $timeBudget || $nodes >= 50000) { $timedOut = true; return null; }
        if (!$remaining) return ['score' => [0, 0, 0], 'choices' => []];
        $key = implode(',', $remaining) . '|' . implode(',', $capacity);
        if (array_key_exists($key, $memo)) return $memo[$key];
        $nodes++;
        if (array_sum($capacity) < count($remaining) / 2) return $memo[$key] = null;
        $set = array_fill_keys($remaining, true);
        $pivotOptions = null;
        foreach ($remaining as $id) {
            $feasible = [];
            foreach ($options[$id] as $option) {
                if ($capacity[$option['resource']] > 0 && isset($set[$option['a']], $set[$option['b']])) $feasible[] = $option;
            }
            if (!$feasible) return $memo[$key] = null;
            if ($pivotOptions === null || count($feasible) < count($pivotOptions)) $pivotOptions = $feasible;
        }
        usort($pivotOptions, static function($a, $b) use ($resources) {
            return [$a['preference'], $a['pair']['score'], $resources[$a['resource']]['slot']['sort_index'] ?? 0, $a['a'], $a['b']]
                <=> [$b['preference'], $b['pair']['score'], $resources[$b['resource']]['slot']['sort_index'] ?? 0, $b['a'], $b['b']];
        });
        $best = null;
        foreach ($pivotOptions as $option) {
            // All costs are nonnegative: a branch already worse cannot improve.
            if ($best !== null && [$option['preference'], $option['pair']['score']] > array_slice($best['score'], 0, 2)) continue;
            $resource = $resources[$option['resource']];
            $slot = $resource['units'][$resource['capacity'] - $capacity[$option['resource']]];
            $option['slot'] = $slot;
            $next = array_values(array_filter($remaining, static function($id) use ($option) { return $id !== $option['a'] && $id !== $option['b']; }));
            $nextCapacity = $capacity; $nextCapacity[$option['resource']]--;
            $bound = $lowerBound($next, $nextCapacity);
            if ($bound === null) continue;
            $optimistic = [$option['preference'] + $bound[0], $option['pair']['score'] + $bound[1], ($slot['sort_index'] ?? 0) + $bound[2]];
            if ($best !== null && $optimistic >= $best['score']) continue;
            $branch = $search($next, $nextCapacity);
            if ($timedOut) return null; // Never advertise an unproven optimum.
            if ($branch === null) continue;
            $score = [$option['preference'] + $branch['score'][0], $option['pair']['score'] + $branch['score'][1], ($slot['sort_index'] ?? 0) + $branch['score'][2]];
            if ($best === null || $score < $best['score']) $best = ['score' => $score, 'choices' => array_merge([$option], $branch['choices'])];
        }
        return $memo[$key] = $best;
    };
    $best = $search($ids, $capacities);
    if ($timedOut) return $failure('La ricerca non ha ancora dimostrato la soluzione ottimale entro il limite di calcolo. Specifica meglio le disponibilità e rigenera. Nessuna giornata parziale è stata proposta.', false);
    if ($best === null) return $failure('Non esiste una giornata completa con questi vincoli. Aggiungi slot, amplia le disponibilità oppure verifica il limite degli incontri già disputati.');
    $rows = [];
    foreach ($best['choices'] as $choice) {
        $slot = $choice['slot']; $pair = $choice['pair'];
        $home = (int)$pair['home']['id']; $away = (int)$pair['away']['id'];
        $warnings = $choice['preference'] ? ['Orario preferito non assegnato: migliore combinazione complessiva'] : [];
        $rows[] = ['home_team_id' => $home, 'away_team_id' => $away, 'data' => $slot['data'], 'ora' => $slot['ora'], 'campo' => $slot['campo'], 'generated_signature' => "$home:$away", 'generated_warnings' => $warnings];
    }
    usort($rows, static function($a, $b) { return [$a['data'], $a['ora'], $a['campo'], $a['home_team_id']] <=> [$b['data'], $b['ora'], $b['campo'], $b['home_team_id']]; });
    return ['complete' => true, 'optimal' => true, 'rows' => $rows, 'score' => $best['score'], 'nodes' => $nodes, 'messages' => ['Giornata completa ottimale: tutte le squadre giocano, disponibilità e capienza rispettate.']];
}
