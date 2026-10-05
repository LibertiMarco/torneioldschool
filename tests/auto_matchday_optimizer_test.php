<?php
require_once __DIR__ . '/../includi/auto_matchday.php';
function schedule_expect($ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function schedule_teams(int $n): array {
    $teams = [];
    for ($i = 1; $i <= $n; $i++) $teams[$i] = ['id' => $i, 'nome' => 'Team ' . $i, 'posizione' => $i, 'punti' => 20 - $i];
    return $teams;
}
function schedule_slots(array $times): array {
    return auto_matchday_normalize_slots(array_map(static function($time) { return ['data' => '2026-10-12', 'ora' => $time, 'campo' => 'Campo A']; }, $times))['slots'];
}
function schedule_rules(array $times, array $preferred = []): array {
    return [['times' => $times, 'preferred_times' => $preferred]];
}
$t = schedule_teams(4); $slots = schedule_slots(['20:00', '21:00']);
$availability = auto_matchday_normalize_availability_rules([1 => schedule_rules(['20:00']), 2 => schedule_rules(['21:00']), 3 => schedule_rules(['20:00']), 4 => schedule_rules(['21:00'])]);
$result = auto_matchday_optimize_schedule($t, [], [], [], $slots, $availability, [], false);
schedule_expect($result['complete'] && $result['optimal'], 'Giornata completa adattando gli avversari');
$pairs = array_map(static function($r) { return auto_matchday_pair_key($r['home_team_id'], $r['away_team_id']); }, $result['rows']);
schedule_expect(in_array(auto_matchday_pair_key(1, 3), $pairs) && in_array(auto_matchday_pair_key(2, 4), $pairs), 'Compatibilita prima della vicinanza in classifica');
$capacity = auto_matchday_build_slot_capacity_map($slots);
$valid = auto_matchday_validate_preview_rows($result['rows'], $t, [], [], [], false, 1, $availability, $capacity);
schedule_expect($valid['valid'], 'Validazione completa');
schedule_expect(!auto_matchday_validate_preview_rows([$result['rows'][0]], $t, [], [], [], false, 1, $availability, $capacity)['valid'], 'Giornata parziale bloccata');
$changed = $result['rows']; $changed[0]['ora'] = $changed[0]['ora'] === '20:00:00' ? '21:00:00' : '20:00:00';
schedule_expect(!auto_matchday_validate_preview_rows($changed, $t, [], [], [], false, 1, $availability, $capacity)['valid'], 'Modifica incompatibile bloccata');
$occupied = [$slots[0]['public_key'] => ['count' => 1]];
schedule_expect(!auto_matchday_optimize_schedule($t, [], [], [], $slots, [], $occupied, false)['complete'], 'Capienza occupata sottratta prima della ricerca');
schedule_expect(!auto_matchday_optimize_schedule(schedule_teams(3), [], [], [], $slots, [], [], false)['complete'], 'Nessun riposo automatico');
$preferred = auto_matchday_normalize_availability_rules([1 => schedule_rules([], ['21:00']), 2 => schedule_rules([], ['20:00']), 3 => schedule_rules([], ['21:00']), 4 => schedule_rules([], ['20:00'])]);
$result = auto_matchday_optimize_schedule($t, [], [], [], $slots, $preferred, [], false);
schedule_expect($result['complete'] && $result['score'][0] === 0, 'Tutte le preferenze rispettate quando possibile');
$timeout = auto_matchday_optimize_schedule($t, [], [], [], $slots, [], [], false, 0);
schedule_expect(!$timeout['optimal'] && !$timeout['rows'], 'Timeout senza falsa promessa di ottimalita');
$blocked = [];
foreach ([1,2,3] as $id) $blocked[auto_matchday_pair_key($id, 4)] = 1;
schedule_expect(!auto_matchday_optimize_schedule($t, [], $blocked, [], $slots, [], [], false)['complete'], 'Nessun calendario parziale quando gli avversari sono esauriti');
schedule_expect(auto_matchday_optimize_schedule($t, [], $blocked, [], $slots, [], [], true)['complete'], 'Ritorni abilitati sbloccano gli abbinamenti');
$existing = [['giornata' => 1, 'squadra_casa' => 'Team 1', 'squadra_ospite' => 'Altro']];
schedule_expect(!auto_matchday_validate_preview_rows($result['rows'], $t, [], [], [], false, 1, $preferred, $capacity, $existing)['valid'], 'Squadra gia presente nella giornata bloccata');

// Independent exhaustive enumeration of pairings and individual slot assignments.
function schedule_oracle(array $ids, array $slots, array $teams, array $rules, array $counts): ?array {
    if (!$ids) return [0, 0, 0];
    $a = array_shift($ids); $best = null;
    foreach ($ids as $j => $b) {
        $pair = auto_matchday_candidate_pair($teams[$a], $teams[$b], [], $counts, [], false);
        if (!$pair) continue;
        foreach ($slots as $k => $slot) {
            if (!auto_matchday_slot_matches_team_rules($slot, $rules[$a] ?? [])['matched'] || !auto_matchday_slot_matches_team_rules($slot, $rules[$b] ?? [])['matched']) continue;
            $remaining = $ids; unset($remaining[$j]); $free = $slots; unset($free[$k]);
            $branch = schedule_oracle(array_values($remaining), array_values($free), $teams, $rules, $counts);
            if ($branch === null) continue;
            $score = [auto_matchday_slot_preference_penalty($slot, $rules[$a] ?? []) + auto_matchday_slot_preference_penalty($slot, $rules[$b] ?? []) + $branch[0], $pair['score'] + $branch[1], $slot['sort_index'] + $branch[2]];
            if ($best === null || $score < $best) $best = $score;
        }
    }
    return $best;
}
mt_srand(140);
for ($case = 0; $case < 35; $case++) {
    $teams = schedule_teams(6); $slots = schedule_slots(['19:00', '20:00', '21:00', '22:00']); $rules = []; $counts = [];
    foreach ($teams as $id => &$team) {
        $team['punti'] = mt_rand(0, 20); $times = [];
        foreach ($slots as $slot) if (mt_rand(0, 2)) $times[] = $slot['ora'];
        if (!$times) $times[] = '23:00:00';
        $rules[$id] = schedule_rules($times, [$slots[mt_rand(0, 3)]['ora']]);
    }
    unset($team);
    foreach (array_keys($teams) as $a) foreach (array_keys($teams) as $b) if ($a < $b && mt_rand(0, 9) === 0) $counts[auto_matchday_pair_key($a, $b)] = 1;
    $exact = schedule_oracle(array_keys($teams), $slots, $teams, $rules, $counts);
    $actual = auto_matchday_optimize_schedule($teams, [], $counts, [], $slots, $rules, [], false);
    schedule_expect($actual['optimal'] && (($exact === null && !$actual['complete']) || ($actual['complete'] && $actual['score'] === $exact)), 'Ottimo globale contro enumerazione esaustiva, caso ' . $case);
}
$start = microtime(true);
$large = auto_matchday_optimize_schedule(schedule_teams(16), [], [], [], schedule_slots(array_fill(0, 20, '20:00')), [], [], false);
schedule_expect($large['complete'] && count($large['rows']) === 8, 'Risorse equivalenti aggregate, 16 squadre');
echo 'OK: vincoli, preferenze, copertura, timeout, 35 confronti esaustivi; 16 squadre in ' . round(microtime(true) - $start, 3) . " s\n";
