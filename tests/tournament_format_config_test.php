<?php
require_once __DIR__ . '/../includi/tournament_format_config.php';
require_once __DIR__ . '/../includi/tournament_rules_render.php';
function format_expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$base = ['formato'=>'campionato','fase_finale'=>'coppe','campionato_squadre'=>10,'numero_gironi'=>4,
    'squadre_per_girone'=>4,'totale_squadre'=>16,'qualificati_gold'=>8,'qualificati_silver'=>4,
    'qualificati_bronzo'=>2,'eliminate'=>2,'regole_html'=>'Regole personalizzate'];
$camp = tournament_format_config($base, []);
format_expect($camp['totale_squadre'] === 10 && $camp['numero_gironi'] === 0 && $camp['squadre_per_girone'] === 0, 'League must not inherit groups');
$group = tournament_format_config([...$base,'formato'=>'girone','fase_finale'=>'eliminazione_diretta'], []);
format_expect($group['totale_squadre'] === 16 && $group['campionato_squadre'] === 0 && $group['qualificati_silver'] === 0, 'Groups use their own count and final phase');
$ko = tournament_format_config([...$base,'formato'=>'eliminazione'], ['eliminazione_squadre'=>'8']);
format_expect($ko['totale_squadre'] === 8 && $ko['numero_gironi'] === 0 && $ko['campionato_squadre'] === 0 && $ko['fase_finale'] === '' && $ko['qualificati_gold'] === 0, 'Knockout has no league, groups or qualifications');
$saved = tournament_format_config($base, ['regole_sezioni'=>json_encode([['key'=>'game-rules','title'=>'Regole di gioco','body'=>'Tempi da 30 minuti','auto'=>false]])]);
format_expect($saved['regole_sezioni'][0]['body'] === 'Tempi da 30 minuti' && $saved['regole_html'] === $base['regole_html'], 'Section edits and legacy rules are retained');
$markup = tournament_rules_sections_markup($saved);
format_expect(str_contains($markup, '<h3>Regole di gioco</h3>') && str_contains($markup, 'Tempi da 30 minuti'), 'Custom section headings render separately');
format_expect(tournament_rules_sections_markup([...$saved, 'regole_html'=>'<p>Regole precedenti</p>']) === null, 'Legacy HTML uses its existing renderer');
echo "Tournament format config: all checks passed.\n";
