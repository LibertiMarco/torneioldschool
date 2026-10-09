<?php
require_once __DIR__ . '/../includi/presentation_templates.php';
function presentation_expect($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$dir = sys_get_temp_dir() . '/presentation-' . bin2hex(random_bytes(8));
try {
    $t = ['id' => 21, 'nome' => 'Brasilerao', 'filetorneo' => 'Brasilerao.php', 'config' => null];
    presentation_expect(presentation_template_for($t, $dir.'/runtime', $dir.'/local') === 'brasileirao', 'Brazil identity');
    $renamed = array_replace($t, ['nome' => 'Nuovo nome', 'filetorneo' => 'Nuovo.php']);
    presentation_expect(presentation_template_for($renamed, $dir.'/runtime', $dir.'/local') === 'brasileirao', 'Renaming changed saved ID association');
    $renamed['config'] = '{"presentation_template":"conference"}';
    presentation_expect(presentation_template_for($renamed, $dir.'/runtime', $dir.'/local') === 'conference', 'Explicit config did not override association');
    presentation_expect(presentation_template_for($renamed, $dir.'/runtime', $dir.'/local', ['21'=>'champions']) === 'champions', 'ID manifest did not override config');
    foreach (['ChampionsLeagueA'=>'champions-a', 'ChampionsLeagueB'=>'champions-b', 'ChampionsLeague6'=>'champions', 'AllInOneNightMondiale2'=>'night-world-2', 'MondialeFasciaB'=>'world-b', 'McLeague'=>'mcleague', 'SerieC'=>'serie-c', 'Nuova competizione'=>'editorial'] as $slug=>$expected) {
        presentation_expect(presentation_template_guess(['nome'=>$slug,'filetorneo'=>$slug.'.php']) === $expected, 'Incorrect identity '.$slug);
    }
    $rows = [$t, ['id'=>22,'nome'=>'Serie A','filetorneo'=>'SerieA.php']];
    presentation_expect(presentation_template_guess(['nome'=>'Brasileirão','filetorneo'=>'']) === 'brasileirao', 'Accented competition name');
    foreach (['Brasilerao','Brasilerao.php','Brasilerao.html'] as $code) presentation_expect(presentation_team_tournament_id($code,$rows) === 21, 'Slug association failed');
    presentation_expect(presentation_team_tournament_id('Serie A',$rows) === 22, 'Name association failed');
    presentation_expect(presentation_team_tournament_id('Unknown',$rows) === null, 'Unregistered team attached to tournament');
    $block = $dir.'/blocked'; file_put_contents($block,'not a directory');
    presentation_expect(presentation_template_for(array_replace($t,['id'=>25]), $block, $dir.'/local') === 'brasileirao', 'Local storage fallback');
    echo "PASS: identities, ID persistence, config override, team associations, storage fallback\n";
} finally {
    foreach (glob($dir.'/runtime/*') ?: [] as $file) unlink($file);
    foreach (glob($dir.'/local/*') ?: [] as $file) unlink($file);
    if (is_dir($dir.'/runtime')) rmdir($dir.'/runtime');
    if (is_dir($dir.'/local')) rmdir($dir.'/local');
    if (is_file($dir.'/blocked')) unlink($dir.'/blocked');
    if (is_dir($dir)) rmdir($dir);
}
