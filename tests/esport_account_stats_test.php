<?php
require_once __DIR__ . '/../includi/esport_account_stats.php';
function esport_expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$user = ['nome' => 'Mario', 'cognome' => 'Rossi'];
$teams = [
    ['id' => 1, 'nome' => '  MARIO   ROSSI ', 'torneo' => 'cup', 'sezione' => 'esport'],
    ['id' => 2, 'nome' => 'Mario Rossi', 'torneo' => 'league', 'sezione' => 'esport'],
    ['id' => 3, 'nome' => 'Mario Rossi', 'torneo' => 'football', 'sezione' => 'calcio'],
    ['id' => 4, 'nome' => 'Mario Rossini', 'torneo' => 'cup', 'sezione' => 'esport'],
    ['id' => 5, 'nome' => 'Rossi Mario', 'torneo' => 'cup', 'sezione' => 'esport'],
];
$match = ['id' => 10, 'torneo' => 'cup', 'squadra_casa' => 'Mario Rossi', 'squadra_ospite' => 'Luca Bianchi',
    'gol_casa' => 3, 'gol_ospite' => 1, 'giocata' => 1, 'data_partita' => '2026-10-01', 'ora_partita' => '21:00'];
$matches = [$match, $match,
    array_merge($match, ['id' => 11, 'torneo' => 'league', 'squadra_casa' => 'Luca Bianchi', 'squadra_ospite' => 'Mario Rossi', 'gol_ospite' => 2]),
    array_merge($match, ['id' => 12, 'gol_casa' => 0]),
    array_merge($match, ['id' => 13, 'giocata' => 0]),
    array_merge($match, ['id' => 14, 'gol_casa' => null]),
    array_merge($match, ['id' => 15, 'torneo' => 'football']),
    array_merge($match, ['id' => 16, 'squadra_casa' => 'Mario Rossini']),
];
$result = esport_account_stats_build($user, $teams, $matches);
esport_expect(count($result['teams']) === 2, 'Only exact full names in esport, across all tournaments');
esport_expect($result['player']['nome'] === 'Mario' && $result['player']['id'] === 0, 'An account without a football player has a profile');
esport_expect($result['player']['presenze'] === 3 && $result['player']['reti'] === 5, 'Home and away scores count once; unplayed and unscored excluded');
esport_expect($result['teams'][0]['presenze'] === 2 && $result['teams'][0]['reti'] === 3, 'Per tournament totals exclude duplicate rows');
$history = esport_account_stats_build($user, $teams, $matches, 'gol');
esport_expect(count($history['matches']) === 2 && $history['matches'][0]['partita_id'] === 11, 'Goal history filters zero goals and sorts matches');
esport_expect($history['player']['totali']['gol'] === 5, 'History includes global totals');
$empty = esport_account_stats_build(['nome' => 'Altro', 'cognome' => 'Utente'], $teams, $matches);
esport_expect($empty['teams'] === [] && $empty['player']['reti'] === 0, 'Other users cannot acquire these statistics');
esport_expect(esport_account_matching_teams(['nome' => '', 'cognome' => 'Rossi'], $teams) === [], 'Incomplete names never match');
esport_expect(esport_account_name_key('  Nicolò   D’Angelo ') === esport_account_name_key('nicolò d’angelo'), 'Unicode and spaces normalize');
$many = [];
for ($i = 1; $i <= 101; $i++) $many[] = array_merge($match, ['id' => $i]);
$first = esport_account_stats_build($user, $teams, $many, 'presenze', 1);
$second = esport_account_stats_build($user, $teams, $many, 'presenze', 2);
esport_expect(count($first['matches']) === 100 && $first['pagination']['has_more'], 'History paginates');
esport_expect(count($second['matches']) === 1 && !$second['pagination']['has_more'], 'Last page is correct');
echo "Esport account stats tests passed.\n";
