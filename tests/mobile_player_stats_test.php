<?php
require_once __DIR__ . '/../includi/mobile_auth.php';
require_once __DIR__ . '/../includi/mobile_player_stats.php';
function player_expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
try {
    mobile_player_stats_handle(0, []);
    throw new RuntimeException('Unauthenticated account accepted');
} catch (MobileAuthError $error) { player_expect($error->status === 401, 'Authentication required before database access'); }
foreach ([['giocatore_id' => 9], ['user_id' => 3], ['section' => 'invalid'], ['type' => 'invalid'], ['page' => 0], ['page' => 100001], ['section' => []]] as $input) {
    try {
        mobile_player_stats_query($input);
        throw new RuntimeException('Invalid or identity-changing query accepted');
    } catch (MobileAuthError $error) { player_expect($error->status === 400, 'Reject identity overrides and malformed filters'); }
}
player_expect(mobile_player_stats_query([]) === ['calcio', null, 1], 'Defaults');
player_expect(mobile_player_stats_query(['section' => 'esport', 'type' => 'presenze', 'page' => '2', 'action' => 'player_stats']) === ['esport', 'presenze', 2], 'Preview pagination and section');
echo "Personal player stats input and identity: all checks passed.\n";
