<?php
/** Build a frozen historical supplement from previously downloaded public sources. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includi/albo_automation.php';
if ($argc !== 3) { fwrite(STDERR, "Usage: php scripts/albo_backfill.php sources.json output.json\n"); exit(1); }
$input = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$records = []; $report=[]; $editions=[];
foreach ($input['tournaments'] as $source) {
    if (!empty($source['unavailable'])) throw new RuntimeException('Source unavailable: ' . $source['slug']);
    $t = $source['torneo'];
    if (($t['stato'] ?? '') !== 'terminato') continue;
    $result = albo_propose($t,$source['matches'],$source['teams'],$source['scorers']);
    foreach ($result['awards'] as &$award) if ($award['tipo'] === 'Squadra' && isset($source['team_members'][(string)$award['squadra_id']])) $award['members']=$source['team_members'][(string)$award['squadra_id']];
    unset($award);
    $editions[] = array_intersect_key($t,array_flip(['id','nome','sezione','filetorneo','img','data_inizio','data_fine']));
    array_push($records,...$result['awards']);
    $report[] = ['torneo_id'=>(int)$t['id'],'competizione'=>$t['nome'],'slug'=>$source['slug'],
        'premi'=>count($result['awards']),'issues'=>$result['issues'],'blocking'=>$result['blocking']];
}
$output = ['version'=>1,'captured_at'=>$input['captured_at'],'editions'=>$editions,'records'=>$records,'report'=>$report];
file_put_contents($argv[2],json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
echo count($editions)." completed tournaments, ".count($records)." verifiable award proposals.\n";
foreach ($report as $entry) if ($entry['issues'] || $entry['blocking']) echo $entry['competizione'] . ': ' . implode(' ',array_unique(array_merge($entry['issues'],$entry['blocking']))) . "\n";
