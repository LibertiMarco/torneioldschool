<?php
require_once __DIR__ . '/../includi/about_content.php';
function about_expect(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
$content = about_content($staffData, $staffCategories);
about_expect(count($content['organizers']) === 2 && $content['organizers'][0]['nome'] === 'Frank', 'Website organizers');
about_expect(substr_count($content['intro_html'], '<p>') === 3 && str_contains($content['intro_html'], '<strong>VAR</strong>'), 'Full formatted website introduction');
about_expect(count($content['facts']) === 4 && $content['facts'][1]['link'] === 'mailto:info@torneioldschool.it', 'Website contact facts');
$groups = about_staff_groups([
    'custom_group' => [['nome' => 'Supporto', 'ruolo' => '']],
    'videomaker' => [['nome' => 'Video', 'ruolo' => 'Regia']],
    'arbitro' => [['nome' => 'Mario', 'ruolo' => '']],
], $staffCategories);
about_expect(array_column($groups, 'label') === ['Arbitri', 'Videomaker', 'Custom Group'], 'Website category order and custom categories');
about_expect($groups[0]['members'][0]['ruolo'] === 'Arbitro' && $groups[1]['members'][0]['ruolo'] === 'Regia', 'Fallback and assigned staff roles');
echo "About website/app content: all checks passed.\n";
