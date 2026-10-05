<?php
function import_player_clean(string $value): string {
    return trim(preg_replace('/\s+/u', ' ', $value));
}
function import_player_key(string $nome, string $cognome): string {
    return mb_strtolower(import_player_clean($nome), 'UTF-8') . "\0" . mb_strtolower(import_player_clean($cognome), 'UTF-8');
}
function import_player_parse(string $text): array {
    $rows = [];
    foreach (preg_split('/\R/u', $text) as $line) {
        $line = preg_replace('/^\s*(?:\d+[.)\-]?\s*|[•*\-]\s*)/u', '', $line);
        $tokens = preg_split('/\s+/u', import_player_clean($line), -1, PREG_SPLIT_NO_EMPTY);
        $gk = $captain = false;
        while ($tokens) {
            $last = mb_strtoupper(trim(end($tokens), '()[]{}.,;:'), 'UTF-8');
            if (in_array($last, ['P', 'GK', 'PORTIERE'], true)) $gk = true;
            elseif (in_array($last, ['C', 'K', 'CAPITANO'], true)) $captain = true;
            else break;
            array_pop($tokens);
        }
        if (!$tokens) continue;
        $nome = array_shift($tokens);
        $rows[] = ['nome' => $nome, 'cognome' => implode(' ', $tokens), 'portiere' => $gk, 'capitano' => $captain, 'selected' => 0];
    }
    return $rows;
}
function import_player_index(array $players): array {
    $index = [];
    foreach ($players as $p) $index[import_player_key($p['nome'], $p['cognome'])][] = $p;
    return $index;
}

// Compare Unicode characters, including accented names, rather than UTF-8 bytes.
function import_player_distance(string $a, string $b): int {
    $a = preg_split('//u', mb_strtolower(import_player_clean($a), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    $b = preg_split('//u', mb_strtolower(import_player_clean($b), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    $previous = range(0, count($b));
    foreach ($a as $i => $character) {
        $current = [$i + 1];
        foreach ($b as $j => $other) {
            $current[] = min($current[$j] + 1, $previous[$j + 1] + 1, $previous[$j] + ($character === $other ? 0 : 1));
        }
        $previous = $current;
    }
    return $previous[count($b)];
}

function import_player_suggestions(string $nome, string $cognome, array $index): array {
    if (!$nome || !$cognome || mb_strlen($nome) > 255 || mb_strlen($cognome) > 255) return [];
    $suggestions = [];
    $exactKey = import_player_key($nome, $cognome);
    foreach ($index as $key => $group) {
        if ($key === $exactKey) continue;
        $player = $group[0];
        $sameName = import_player_key($nome, '') === import_player_key($player['nome'], '');
        $sameSurname = import_player_key('', $cognome) === import_player_key('', $player['cognome']);
        if (!$sameName && !$sameSurname) continue;
        $a = $sameName ? $cognome : $nome;
        $b = $sameName ? $player['cognome'] : $player['nome'];
        if (abs(mb_strlen($a) - mb_strlen($b)) > 2) continue;
        // One field must match exactly; short names get a stricter threshold.
        $length = max(mb_strlen($a), mb_strlen($b));
        $distance = import_player_distance($a, $b);
        $limit = $length >= 7 ? 2 : ($length >= 4 ? 1 : 0);
        if ($distance > 0 && $distance <= $limit) {
            $suggestions[] = ['nome' => $player['nome'], 'cognome' => $player['cognome'], 'distance' => $distance];
        }
    }
    usort($suggestions, static function($a, $b) { return $a['distance'] <=> $b['distance']; });
    return array_slice($suggestions, 0, 5);
}
