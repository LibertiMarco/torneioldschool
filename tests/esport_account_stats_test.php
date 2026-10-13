<?php
require_once __DIR__ . '/../includi/esport_account_stats.php';
require_once __DIR__ . '/../includi/player_awards.php';
function esport_expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$user = ['nome' => 'Mario', 'cognome' => 'Rossi'];
$teams = [
    ['id' => 1, 'nome' => '  MARIO   ROSSI ', 'torneo' => 'cup', 'sezione' => 'esport'],
    ['id' => 2, 'nome' => 'Mario Rossi', 'torneo' => 'league', 'sezione' => 'esport'],
    ['id' => 3, 'nome' => 'Mario Rossi', 'torneo' => 'football', 'sezione' => 'calcio'],
    ['id' => 4, 'nome' => 'Mario Rossini', 'torneo' => 'cup', 'sezione' => 'esport'],
];
$accounts = [$user, ['nome' => 'Luca', 'cognome' => 'Bianchi']];
esport_expect(esport_account_has_unique_name($user, $accounts), 'Unique account can be associated');
$result = esport_account_profile($user, $teams, true);
esport_expect(count($result['teams']) === 2, 'Only full names in esport across tournaments');
esport_expect(!isset($result['player']['reti'], $result['player']['presenze']), 'No goals or appearances');
esport_expect(!isset($result['teams'][0]['reti'], $result['teams'][0]['presenze']), 'No tournament counters');
$duplicate = ['nome' => '  MARIO ', 'cognome' => ' ROSSI  '];
$accounts[] = $duplicate;
esport_expect(!esport_account_has_unique_name($user, $accounts), 'First namesake is blocked');
esport_expect(!esport_account_has_unique_name($duplicate, $accounts), 'Second namesake is blocked too');
$blocked = esport_account_profile($user, $teams, false);
esport_expect($blocked['teams'] === [] && $blocked['awards'] === [] && $blocked['association_ambiguous'], 'Ambiguous accounts see no associations or awards');
esport_expect(!esport_account_has_unique_name(['nome' => '', 'cognome' => 'Rossi'], $accounts), 'Incomplete identity is blocked');
esport_expect(esport_account_name_key('  Nicolò   D’Angelo ') === esport_account_name_key('nicolò d’angelo'), 'Unicode and spaces normalize');
$award = ['tipo' => 'Squadra', 'squadra_id' => 1, 'members' => []];
esport_expect(player_award_recipient_type($award, $result['player'], $result['teams']) === 'Squadra', 'Esport team awards do not require a football player');
esport_expect(player_award_recipient_type(['tipo' => 'Individuale', 'giocatore_id' => 10], $result['player'], $result['teams']) === null, 'Other players individual awards stay excluded');
echo "Esport tournaments, name collisions and awards tests passed.\n";
