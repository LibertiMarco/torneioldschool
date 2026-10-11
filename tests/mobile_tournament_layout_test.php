<?php
require_once __DIR__ . '/../includi/mobile_tournament_layout.php';
function layout_expect(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } }
$champions = mobile_tournament_layout('ChampionsLeagueA');
layout_expect($champions['group_mode'] === 'legacy' && $champions['legacy_gold'] === 14 && $champions['legacy_silver'] === 4, 'Champions historical qualification');
$short = mobile_tournament_layout('shorteditionmondiale');
layout_expect($short['legacy_gold'] === 8 && $short['legacy_group_gold'] === 16, 'Separate single/group thresholds');
$serie = mobile_tournament_layout('SerieC');
layout_expect($serie['gold_per_group_override'] === 8, 'Serie C group override');
$weekend = mobile_tournament_layout('WeekendLeague2');
layout_expect($weekend['legacy_gold'] === 6 && $weekend['legacy_silver'] === 4, 'Weekend qualifications');
$mc = mobile_tournament_layout('McLeague');
layout_expect($mc['default_team_count'] === 7 && $mc['default_gold'] === 4 && $mc['eliminated_color'], 'McLeague defaults');
$brasil = mobile_tournament_layout('Brasilerao');
layout_expect($brasil['config_overrides'] === ['totale_squadre' => 22, 'qualificati_gold' => 16, 'qualificati_silver' => 6], 'Brasilerao public page overrides');
layout_expect($mc['config_overrides']['totale_squadre'] === 7 && $mc['config_overrides']['qualificati_silver'] === 2, 'McLeague fixed formula overrides');
layout_expect($champions['config_overrides'] === [], 'Legacy page without overrides remains unchanged');
$formula = mobile_tournament_layout('Formula1');
layout_expect($formula['spareggio'] && $formula['spareggio_default'] === 16, 'Formula 1 play-in');
foreach (['../env_loader','/tmp/test','Torneo.php'] as $invalid) {
    $rejected = false;
    try { mobile_tournament_layout($invalid); } catch (InvalidArgumentException $e) { $rejected = true; }
    layout_expect($rejected, 'Invalid slug rejected');
}
echo "Mobile tournament presentation: all checks passed.\n";
