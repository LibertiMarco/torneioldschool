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
