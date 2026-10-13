<?php
require_once __DIR__ . '/../includi/player_awards.php';
function award_expect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$player = ['id' => 10];
$teams = [['id' => 5]];
$scorer = ['tipo' => 'Individuale', 'kind' => 'SCORER', 'premio' => 'Capocannoniere',
    'giocatore_id' => 20, 'squadra_id' => 5, 'members' => [10, 20]];
award_expect(player_award_recipient_type($scorer, $player, $teams) === null,
    'A teammate cannot inherit the top scorer award through the roster');
unset($scorer['members']);
award_expect(player_award_recipient_type($scorer, $player, $teams) === null,
    'A teammate cannot inherit the top scorer award through team membership');
award_expect(player_award_recipient_type($scorer, ['id' => 20], $teams) === 'Individuale',
    'The actual top scorer keeps their award');
award_expect(player_award_recipient_type([...$scorer, 'giocatore_id' => null], $player, $teams) === null,
    'An unresolved individual winner cannot become a team award');
award_expect(player_award_recipient_type([...$scorer, 'kind' => 'OTHER', 'premio' => 'Miglior giocatore'], $player, $teams) === null,
    'Other individual prizes also stay exclusive to their winner');
$cup = ['tipo' => 'Squadra', 'kind' => 'GOLD', 'premio' => 'Coppa Gold', 'squadra_id' => 5];
award_expect(player_award_recipient_type($cup, $player, $teams) === 'Squadra',
    'Team trophies remain visible to teammates');
award_expect(player_award_recipient_type([...$cup, 'members' => [20]], $player, $teams) === null,
    'A frozen trophy roster takes precedence over current membership');
award_expect(player_award_recipient_type([...$cup, 'members' => ['10', '20']], $player, []) === 'Squadra',
    'A recorded team winner keeps their trophy even after leaving the team');
echo "Player award ownership: all checks passed.\n";
