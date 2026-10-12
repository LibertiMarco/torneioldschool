<?php
require_once __DIR__ . '/albo_automation.php';

function albo_backfill(): array {
    static $data;
    if ($data === null) {
        $path = __DIR__ . '/../data/albo-backfill.json';
        $data = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
    }
    return $data;
}

/** Bind legacy text entries to one edition only; ambiguous names remain separate. */
function albo_bind_edition(array $row, array $editions): array {
    $section = $row['sezione'] ?? 'calcio';
    $slug = albo_slug((string)($row['link_torneo'] ?? $row['filetorneo'] ?? ''));
    $name = albo_key(preg_replace('/\([^)]*\)/', '', (string)($row['competizione'] ?? '')) ?? '');
    $year = (string)($row['anno'] ?? ''); $candidates=[];
    foreach ($editions as $edition) {
        if (($edition['sezione'] ?? 'calcio') !== $section) continue;
        $editionYear = substr((string)($edition['data_fine'] ?? ''),0,4);
        if ((!empty($row['torneo_id']) && (int)$row['torneo_id'] === (int)$edition['id'])
            || ($slug !== '' && $slug === albo_slug((string)$edition['filetorneo']) && ($year === '' || $year === $editionYear))
            || ($slug === '' && $name === albo_key((string)$edition['nome']) && ($year === '' || $year === $editionYear))) $candidates[]=$edition;
    }
    if (count($candidates) === 1) {
        $edition=$candidates[0];
        $row['torneo_id']=(int)$edition['id'];
        $row['edition_key']=$section . ':' . (int)$edition['id'] . ':' . ($year ?: substr((string)($edition['data_fine'] ?? ''),0,4));
        $row['link_torneo']=($row['link_torneo'] ?? '') ?: '/tornei/' . basename((string)$edition['filetorneo']);
        if (empty($row['source_key'])) $row['competizione']=$edition['nome'];
        $row['filetorneo']=basename((string)$edition['filetorneo']);
        foreach (['data_inizio','data_fine'] as $field) if (empty($row[$field])) $row[$field]=$edition[$field] ?? '';
        if ($year === '') $row['anno']=substr((string)($edition['data_fine'] ?? ''),0,4);
        if (empty($row['torneo_logo'])) $row['torneo_logo']=$edition['img'] ?? '';
    } else $row['edition_key']=$section . ':legacy:' . $name . ':' . $year;
    $row['kind']=$row['kind'] ?? albo_kind((string)($row['premio'] ?? ''));
    return $row;
}

function albo_manual_rows(array $rows): array {
    return array_map(static function($row) {
        $date = static fn($m,$y) => empty($y) ? '' : sprintf('%04d-%02d-01',(int)$y,max(1,(int)$m));
        return ['record_id'=>(int)$row['id'],'competizione'=>$row['competizione'],'premio'=>$row['premio'] ?? $row['categoria'] ?? 'Premio',
            'vincitrice'=>$row['vincitrice'] ?? '', 'logo_vincitrice'=>$row['vincitrice_logo'] ?? '',
            'torneo_logo'=>$row['torneo_logo'] ?? '', 'link_torneo'=>$row['link_torneo'] ?? '',
            'filetorneo'=>$row['tabellone_url'] ?? '', 'sezione'=>$row['sezione'] ?? 'calcio',
            'anno'=>($row['fine_anno'] ?? null) ?: (($row['inizio_anno'] ?? null) ?: ''),
            'data_inizio'=>$date($row['inizio_mese'] ?? 0,$row['inizio_anno'] ?? 0),
            'data_fine'=>$date($row['fine_mese'] ?? 0,$row['fine_anno'] ?? 0),
            'giornata_unica'=>(int)($row['giornata_unica'] ?? 0),'data_evento'=>$row['data_evento'] ?? '',
            'created_at'=>$row['created_at'] ?? '', 'ordinamento'=>isset($row['ordinamento']) ? (int)$row['ordinamento'] : null,
            'source'=>'manuale'];
    },$rows);
}

function albo_winner_name_matches(string $legacy, string $candidate): bool {
    $legacy=albo_key(preg_replace('/\([^)]*\)/','',$legacy) ?? ''); $candidate=albo_key($candidate);
    if ($legacy === $candidate) return true;
    if (strlen($legacy) >= 5 && str_ends_with($candidate,' ' . $legacy)) return true;
    $a=explode(' ',$legacy); $b=explode(' ',$candidate);
    // A known full name spelling variant is accepted only after unique-candidate checking.
    return count($a) === 2 && count($b) === 2 && $a[1] === $b[1]
        && min(strlen($a[0]),strlen($b[0])) >= 5
        && (str_starts_with($a[0],$b[0]) || str_starts_with($b[0],$a[0]));
}

function albo_team_name_matches(string $legacy,string $candidate): bool {
    $a=albo_key($legacy);$b=albo_key($candidate);
    return $a === $b || (min(strlen($a),strlen($b)) >= 3
        && (str_starts_with($a,$b . ' ') || str_starts_with($b,$a . ' ') || str_ends_with($a,' ' . $b) || str_ends_with($b,' ' . $a)));
}

function albo_archive_merge(array $manual, array $frozen, array $published, array $editions): array {
    $rows=[]; $manualKinds=[]; $publishedKinds=[];
    foreach ($manual as $item) {
        $item=albo_bind_edition($item,$editions);
        $links=[];
        foreach ($frozen as $candidate) {
            $candidate=albo_bind_edition($candidate,$editions);
            if ($candidate['edition_key'] !== $item['edition_key'] || $candidate['kind'] !== $item['kind']) continue;
            $matches=$candidate['tipo'] === 'Squadra'
                ? albo_team_name_matches((string)$item['vincitrice'],(string)$candidate['vincitrice'])
                : albo_winner_name_matches((string)$item['vincitrice'],(string)$candidate['vincitrice']);
            if ($matches && (empty($item['squadra_id']) || $candidate['tipo'] !== 'Squadra' || (int)$item['squadra_id'] === (int)$candidate['squadra_id'])) $links[]=$candidate;
        }
        if (count($links) === 1) foreach (['torneo_id','squadra_id','giocatore_id','members','tipo'] as $field) if (isset($links[0][$field])) $item[$field]=$links[0][$field];
        $rows[]=$item;
        $manualKinds[$item['edition_key'] . ':' . $item['kind']][]=$item;
    }
    foreach ($published as $item) {
        $item=albo_bind_edition($item,$editions);
        $publishedKinds[$item['edition_key'] . ':' . $item['kind']]=true;
        if ((int)($item['active'] ?? 1) !== 1) continue;
        $category=$item['edition_key'] . ':' . $item['kind'];
        if (isset($manualKinds[$category])) {
            $manualScorers=$manualKinds[$category];
            if ($item['kind'] !== 'SCORER' || count(array_filter($manualScorers,static fn($r)=>!empty($r['giocatore_id']))) !== count($manualScorers)) continue;
            if (in_array((int)$item['giocatore_id'],array_map('intval',array_column($manualScorers,'giocatore_id')),true)) continue;
        }
        $item['source']='automatico'; $rows[]=$item;
    }
    foreach ($frozen as $item) {
        $item=albo_bind_edition($item,$editions); $category=$item['edition_key'] . ':' . $item['kind'];
        if (isset($publishedKinds[$category])) continue;
        if (isset($manualKinds[$category])) {
            if ($item['kind'] !== 'SCORER') continue;
            $manualScorers=$manualKinds[$category];
            if (count(array_filter($manualScorers,static fn($r)=>!empty($r['giocatore_id']))) !== count($manualScorers)) continue;
            if (in_array((int)$item['giocatore_id'],array_map('intval',array_column($manualScorers,'giocatore_id')),true)) continue;
        }
        $item['source']='recupero'; $rows[]=$item;
    }
    return $rows;
}

function albo_archive_group(array $rows): array {
    $groups=[];
    foreach ($rows as $row) {
        $key=$row['edition_key'];
        if (!isset($groups[$key])) $groups[$key]=[
            'edition_key'=>$key,'torneo_id'=>$row['torneo_id'] ?? null,
            'competizione'=>$row['competizione'],'sezione'=>$row['sezione'] ?? 'calcio',
            'torneo_logo'=>$row['torneo_logo'] ?? '', 'data_inizio'=>$row['data_inizio'] ?? '', 'data_fine'=>$row['data_fine'] ?? '',
            'anno'=>$row['anno'] ?? '', 'ordinamento'=>$row['ordinamento'] ?? null,
            'filetorneo'=>$row['filetorneo'] ?? '', 'link_torneo'=>$row['link_torneo'] ?? '',
            'giornata_unica'=>$row['giornata_unica'] ?? 0,'data_evento'=>$row['data_evento'] ?? '',
            'latest_record_id'=>0,'latest_sort_time'=>0,'premi'=>[]];
        $group=&$groups[$key];
        // Fill metadata from automatic proposals even when manual entries came first.
        foreach (['filetorneo','link_torneo','anno','torneo_logo','data_inizio','data_fine'] as $field) if (empty($group[$field]) && !empty($row[$field])) $group[$field]=$row[$field];
        $group['latest_record_id']=max($group['latest_record_id'],(int)($row['record_id'] ?? 0));
        if (isset($row['ordinamento'])) $group['ordinamento']=min($group['ordinamento'] ?? PHP_INT_MAX,(int)$row['ordinamento']);
        $group['latest_sort_time']=max($group['latest_sort_time'],strtotime(($row['data_fine'] ?? '') ?: (($row['data_evento'] ?? '') ?: ($row['created_at'] ?? ''))) ?: 0);
        $group['premi'][]=array_intersect_key($row,array_flip(['premio','kind','tipo','vincitrice','logo_vincitrice','squadra_id','giocatore_id','source','source_key','gol','ordinamento']));
        unset($group);
    }
    $order=['REGULAR'=>0,'GOLD'=>1,'SILVER'=>2,'BRONZO'=>3,'SCORER'=>4,'MVP'=>5,'KEEPER'=>6,'DEFENDER'=>7,'ATTACKER'=>8];
    foreach ($groups as &$group) usort($group['premi'],static fn($a,$b)=>(($a['ordinamento'] ?? PHP_INT_MAX)<=>($b['ordinamento'] ?? PHP_INT_MAX)) ?: (($order[$a['kind']] ?? 9)<=>($order[$b['kind']] ?? 9)));
    unset($group);
    $groups=array_values($groups);
    usort($groups,static fn($a,$b)=> (($a['ordinamento'] ?? PHP_INT_MAX)<=>($b['ordinamento'] ?? PHP_INT_MAX)) ?: ($b['latest_sort_time']<=>$a['latest_sort_time']) ?: ($b['latest_record_id']<=>$a['latest_record_id']));
    return $groups;
}

/** Read-only access. Schema creation belongs to the authenticated admin publish. */
function albo_archive_read(mysqli $conn, string $section = ''): array {
    $backfill=albo_backfill();
    $editions=$backfill['editions'] ?? [];
    $res=$conn->query('SELECT id,nome,sezione,filetorneo,img,data_inizio,data_fine FROM tornei');
    if ($res) { $current=$res->fetch_all(MYSQLI_ASSOC); $ids=array_column($current,'id'); $editions=array_merge($current,array_filter($editions,static fn($e)=>!in_array($e['id'],$ids))); }
    $manual=[]; $exists=$conn->query("SHOW TABLES LIKE 'albo'");
    if ($exists && $exists->num_rows) {
        $res=$conn->query('SELECT * FROM albo ORDER BY id');
        if ($res) $manual=albo_manual_rows($res->fetch_all(MYSQLI_ASSOC));
    }
    $teamIndex=[];$res=$conn->query('SELECT id,nome,torneo FROM squadre ORDER BY id');
    if ($res) foreach ($res->fetch_all(MYSQLI_ASSOC) as $team) $teamIndex[albo_slug((string)$team['torneo'])][]=$team;
    foreach ($manual as &$record) {
        $record=albo_bind_edition($record,$editions);
        if (!in_array($record['kind'],['REGULAR','GOLD','SILVER','BRONZO'],true) || empty($record['torneo_id'])) continue;
        $candidates=array_values(array_filter($teamIndex[albo_slug((string)$record['filetorneo'])] ?? [],static fn($team)=>albo_team_name_matches((string)$record['vincitrice'],(string)$team['nome'])));
        if (count($candidates) === 1) { $record['squadra_id']=(int)$candidates[0]['id'];$record['tipo']='Squadra'; }
    }
    unset($record);
    $published=[]; $exists=$conn->query("SHOW TABLES LIKE 'albo_automatico'");
    if ($exists && $exists->num_rows) {
        $res=$conn->query('SELECT data,active FROM albo_automatico ORDER BY source_key');
        if ($res) while ($row=$res->fetch_assoc()) { $item=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR); $item['active']=(int)$row['active']; $published[]=$item; }
    }
    $rows=albo_archive_merge($manual,$backfill['records'] ?? [],$published,$editions);
    return $section === '' ? $rows : array_values(array_filter($rows,static fn($row)=>($row['sezione'] ?? 'calcio') === $section));
}
