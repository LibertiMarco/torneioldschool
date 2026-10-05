<?php
require_once __DIR__ . '/../includi/player_match_conflicts.php';
$members = [];
foreach ([1 => 'A', 3 => 'C'] as $id => $name) {
    foreach ([10 => 'Attaccante', 11 => 'Portiere'] as $player => $role) {
        $members[] = ['squadra_id' => $id, 'squadra_nome' => $name, 'torneo' => $id === 3 ? 'altro' : 'test',
            'giocatore_id' => $player, 'nome' => 'Mario', 'cognome' => (string)$player, 'ruolo' => $role];
    }
}
$rows = [['home_team_id' => 1, 'away_team_id' => 2, 'home_team_name' => 'A', 'away_team_name' => 'B',
    'data' => '2026-10-05', 'ora' => '20:00', 'warnings' => []]];
foreach ([0, 60, 90, 91, -90] as $distance) {
    $time = date('H:i', strtotime('2026-10-05 20:00') + $distance * 60);
    $matches = [['torneo' => 'altro', 'squadra_casa' => 'C', 'squadra_ospite' => 'D', 'data_partita' => '2026-10-05', 'ora_partita' => $time]];
    $result = player_match_conflict_warnings($rows, $members, $matches);
    if (count($result[0]['warnings']) !== (abs($distance) <= 90 ? 1 : 0)) throw new RuntimeException('Soglia/portiere: ' . $distance);
}
$proposed = $rows;
$proposed[] = ['home_team_id' => 3, 'away_team_id' => 4, 'home_team_name' => 'C', 'away_team_name' => 'D',
    'data' => '2026-10-05', 'ora' => '21:00', 'warnings' => []];
$result = player_match_conflict_warnings($proposed, $members, []);
if (count($result[0]['warnings']) !== 1 || count($result[1]['warnings']) !== 1) throw new RuntimeException('Proposte');
$rows[0]['ora'] = '23:30';
$matches[0]['data_partita'] = '2026-10-06';
$matches[0]['ora_partita'] = '00:30';
$result = player_match_conflict_warnings($rows, $members, $matches);
if (count($result[0]['warnings']) !== 1) throw new RuntimeException('Mezzanotte');
echo "OK: soglie, portieri, altri tornei, proposte, mezzanotte\n";
