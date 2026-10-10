<?php
require_once __DIR__ . '/../includi/content_sections.php';
function home_section_expect(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
$_SERVER['HTTP_HOST'] = 'torneioldschool.it';
$_GET = [];
home_section_expect(content_requested_read_section() === 'calcio', 'Default football host');
$_SERVER['HTTP_HOST'] = 'esport.torneioldschool.it';
home_section_expect(content_requested_read_section() === 'esport', 'Default esport host');
foreach (['calcio', 'esport'] as $section) {
    $_GET = ['sezione' => $section];
    home_section_expect(content_requested_read_section() === $section, 'Explicit section');
}
foreach (['', 'other', ['esport']] as $invalid) {
    $_GET = ['sezione' => $invalid];
    $rejected = false;
    try { content_requested_read_section(); } catch (InvalidArgumentException $error) { $rejected = true; }
    home_section_expect($rejected, 'Invalid section must be rejected');
}
$_GET = ['sezione' => 'calcio'];
home_section_expect(content_current_section() === 'esport', 'Write section still follows host');
echo "Mobile homepage sections: all checks passed.\n";
