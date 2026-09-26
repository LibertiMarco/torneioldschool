<?php
require_once __DIR__ . '/../includi/graphics_templates.php';

function expect_template(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function rejects_template(callable $callback): void
{
    try { $callback(); } catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException('Invalid input was accepted');
}
$layout = [];
foreach (['photo', 'homeLogo', 'awayLogo', 'score', 'home', 'away', 'round'] as $key) {
    $layout[$key] = ['x' => 0, 'y' => 0, 'w' => 500, 'h' => 100, 'font' => 40, 'visible' => true, 'color' => '#ffffff'];
}
$clean = graphics_template_layout($layout, 'ft');
expect_template($clean == $layout, 'Valid positions changed');
$invalid = $layout;
$invalid['score']['x'] = '../outside';
rejects_template(fn() => graphics_template_layout($invalid, 'ft'));
$invalid['score']['x'] = -1;
rejects_template(fn() => graphics_template_layout($invalid, 'ft'));
$invalid = $layout;
$invalid['photo']['w'] = 0;
rejects_template(fn() => graphics_template_layout($invalid, 'ft'));
rejects_template(fn() => graphics_template_layout([], 'mvp'));
rejects_template(fn() => graphics_template_image('<svg xmlns="http://www.w3.org/2000/svg"></svg>'));
rejects_template(fn() => graphics_template_image(str_repeat('x', 8 * 1024 * 1024 + 1)));
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF9sAAAAASUVORK5CYII=');
$image = graphics_template_image($png);
expect_template(strpos($image, 'data:image/png;base64,') === 0, 'PNG not accepted');
$dir = sys_get_temp_dir() . '/graphics-test-' . bin2hex(random_bytes(8));
$paths = [$dir . '/1-ft.json', $dir . '/1-mvp.json', $dir . '/2-ft.json'];
try {
    expect_template(graphics_template_read($paths[0]) === null, 'Missing template must be empty');
    graphics_template_write($paths[0], ['image' => $image, 'layout' => $clean]);
    graphics_template_write($paths[1], ['image' => 'mvp', 'layout' => []]);
    graphics_template_write($paths[2], ['image' => 'other tournament', 'layout' => []]);
    $clean['score']['x'] = 350;
    graphics_template_write($paths[0], ['image' => $image, 'layout' => $clean]);
    expect_template(graphics_template_read($paths[0])['layout']['score']['x'] === 350, 'Overwrite did not persist');
    graphics_template_write($paths[0], null);
    expect_template(graphics_template_read($paths[0]) === null, 'Reset did not clear template');
    expect_template(graphics_template_read($paths[1])['image'] === 'mvp', 'Reset affected MVP');
    expect_template(graphics_template_read($paths[2])['image'] === 'other tournament', 'Reset affected another tournament');
} finally {
    foreach ($paths as $path) if (is_file($path)) unlink($path);
    if (is_dir($dir)) rmdir($dir);
}
echo "Graphics template validation and persistence: OK\n";
