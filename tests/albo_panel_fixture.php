<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includi/albo_archive.php';
function h($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function csrf_field(string $key): string { return '<input type="hidden" name="_csrf" value="fixture-only-csrf">'; }
$alboAutoId=69;$alboAutoClose=false;
$alboAutoTournaments=[['id'=>69,'nome'=>'Coppa prova','data_fine'=>'2026-10-12','stato'=>'terminato']];
$alboAutoPlan=['tournament'=>['nome'=>'Coppa prova'],'revision'=>'fixture-revision','issues'=>[],'blocking'=>[],'manual_kinds'=>[],
    'awards'=>[
        ['source_key'=>'calcio:69:2026:GOLD','premio'=>'Vincente Coppa Gold','vincitrice'=>'Rio','gol'=>null,'status'=>'nuovo'],
        ['source_key'=>'calcio:69:2026:SCORER:100','premio'=>'Capocannoniere','vincitrice'=>'Mario Rossi','gol'=>4,'status'=>'nuovo'],
        ['source_key'=>'calcio:69:2026:SCORER:200','premio'=>'Capocannoniere','vincitrice'=>'Luca Bianchi','gol'=>4,'status'=>'nuovo'],
    ],
    'players'=>[['id'=>100,'nome'=>'Mario','cognome'=>'Rossi','squadra'=>'Rio','media_voti'=>7.5],['id'=>200,'nome'=>'Luca','cognome'=>'Bianchi','squadra'=>'Sharm','media_voti'=>7]],
];
include __DIR__ . '/../includi/albo_admin_panel.php';
