<?php
/** Unico motore per sito e app. Le impronte invalidano attestazioni dopo correzioni. */
function records_ensure_schema(mysqli $db): void {
    // Come gli altri moduli del sito, prepara la sola tabella aggiuntiva.
    // Il tipo deve coincidere con partite.id anche sui database storici signed.
    $exists=$db->query("SHOW TABLES LIKE 'match_record_verification'");
    if ($exists && $exists->num_rows > 0) return;
    $column=$db->query("SHOW COLUMNS FROM partite LIKE 'id'");
    $type=$column ? ($column->fetch_assoc()['Type']??'') : '';
    if (!preg_match('/^(?:int|bigint|mediumint)(?:\(\d+\))?(?: unsigned)?$/i',$type)) throw new RuntimeException('Tipo identificativo partita non supportato.');
    $sql="CREATE TABLE IF NOT EXISTS match_record_verification (
        partita_id {$type} PRIMARY KEY,
        formato VARCHAR(80) NOT NULL,
        esito ENUM('confermato','annullata','tavolino') NOT NULL,
        risultato_hash CHAR(64) NOT NULL,
        marcature_hash CHAR(64) DEFAULT NULL,
        portieri_hash CHAR(64) DEFAULT NULL,
        portieri_json LONGTEXT NOT NULL,
        verified_by INT UNSIGNED NOT NULL,
        verified_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (partita_id) REFERENCES partite(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$db->query($sql)) throw new RuntimeException('Impossibile preparare la tabella Record.');
}
function records_hash(array $match, array $rows = []): string {
    unset($match['torneo_nome']);
    return hash('sha256', json_encode([$match, $rows], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
}
function records_format(string $value): string {
    $value=trim(preg_replace('/\s+/u',' ',$value));
    if(preg_match('/^(?:calcio\s*(?:a\s*)?|c)?(5|6|7|8|9|11)$/i',$value,$m)) return 'Calcio a '.$m[1];
    return ucfirst(strtolower($value));
}
function records_source(mysqli $db): array {
    records_ensure_schema($db);
    $matches = $db->query("SELECT p.id, p.torneo, p.squadra_casa, p.squadra_ospite, p.gol_casa, p.gol_ospite, p.data_partita, p.ora_partita, p.giocata,
        (SELECT t.nome FROM tornei t WHERE t.filetorneo=p.torneo OR t.filetorneo=CONCAT(p.torneo,'.php') OR t.nome=p.torneo ORDER BY t.id LIMIT 1) AS torneo_nome
        FROM partite p WHERE NOT EXISTS (SELECT 1 FROM tornei t WHERE t.sezione='esport' AND (t.filetorneo=p.torneo OR t.filetorneo=CONCAT(p.torneo,'.php') OR t.nome=p.torneo)) ORDER BY p.id")->fetch_all(MYSQLI_ASSOC);
    $rows = [];
    $columns=[];
    foreach ($db->query('SHOW COLUMNS FROM partita_giocatore')->fetch_all(MYSQLI_ASSOC) as $column) $columns[$column['Field']]=true;
    $team=isset($columns['squadra_id']) ? 'pg.squadra_id' : 'NULL';
    $ownGoal=isset($columns['autogol']) ? 'pg.autogol' : 'NULL';
    foreach ($db->query("SELECT pg.id, pg.partita_id, pg.giocatore_id, {$team} AS squadra_id, pg.presenza, pg.goal, {$ownGoal} AS autogol, g.nome, g.cognome, s.nome AS squadra, s.torneo AS squadra_torneo FROM partita_giocatore pg JOIN giocatori g ON g.id=pg.giocatore_id LEFT JOIN squadre s ON s.id={$team} ORDER BY pg.id")->fetch_all(MYSQLI_ASSOC) as $r) $rows[$r['partita_id']][] = $r;
    $verifications = [];
    $result = $db->query('SELECT * FROM match_record_verification');
    if (!$result) throw new RuntimeException('Applicare la migrazione records_goalkeepers.sql.');
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $v) $verifications[$v['partita_id']] = $v;
    return [$matches, $rows, $verifications];
}
function records_scoring_complete(array $p, array $rows): bool {
    $totals = [$p['squadra_casa'] => 0, $p['squadra_ospite'] => 0];
    foreach ($rows as $r) {
        if ($r['goal'] === null || $r['autogol'] === null) return false;
        if ($r['squadra_torneo'] !== $p['torneo'] || !array_key_exists($r['squadra'], $totals)) return false;
        $other = $r['squadra'] === $p['squadra_casa'] ? $p['squadra_ospite'] : $p['squadra_casa'];
        $totals[$r['squadra']] += (int)$r['goal'];
        $totals[$other] += (int)$r['autogol'];
    }
    return $totals[$p['squadra_casa']] === (int)$p['gol_casa'] && $totals[$p['squadra_ospite']] === (int)$p['gol_ospite'];
}
function records_validate_keepers(array $p, array $rows, array $keepers): void {
    $seen = []; $teams = [];
    foreach ($keepers as $k) {
        if (!is_array($k) || !isset($k['player_id'], $k['goals'], $k['full']) || !is_int($k['goals']) || $k['goals'] < 0 || !is_bool($k['full'])) throw new InvalidArgumentException('Dati portiere non validi.');
        $id = (int)$k['player_id'];
        if (isset($seen[$id])) throw new InvalidArgumentException('Portiere duplicato.');
        $seen[$id] = true; $row = null;
        foreach ($rows as $r) if ((int)$r['giocatore_id'] === $id && (int)$r['presenza'] === 1 && $r['squadra_torneo'] === $p['torneo'] && in_array($r['squadra'], [$p['squadra_casa'], $p['squadra_ospite']], true)) $row = $r;
        if (!$row) throw new InvalidArgumentException('Il portiere deve avere una presenza nel tabellino della squadra.');
        $teams[$row['squadra']][] = $k;
    }
    foreach ([$p['squadra_casa'] => $p['gol_ospite'], $p['squadra_ospite'] => $p['gol_casa']] as $team => $goals) {
        $ks = $teams[$team] ?? [];
        if (!$ks || array_sum(array_column($ks, 'goals')) !== (int)$goals) throw new InvalidArgumentException('Indicare tutti i portieri di entrambe le squadre e attribuire tutti i gol subiti.');
        if (count($ks) > 1 && in_array(true, array_column($ks, 'full'), true)) throw new InvalidArgumentException('Con alternanza nessun portiere può aver giocato tutta la partita.');
        if (count($ks) === 1 && !$ks[0]['full']) throw new InvalidArgumentException('Indicare il portiere che ha completato la partita.');
    }
}
function records_compute(array $matches, array $rows, array $verified, string $tournament = '', string $format = '', int $player = 0): array {
    $labels = ['total'=>'Partita con più gol','margin'=>'Vittoria più larga','team'=>'Squadra con più gol in una partita','player'=>'Giocatore con più gol in una partita','scorers'=>'Più marcatori diversi nella stessa squadra','draw'=>'Pareggio con più gol','shutout'=>'Vittoria più larga senza subire gol'];
    $groups = []; $options = []; $formats = [];
    foreach ($matches as $p) {
        $v = $verified[$p['id']] ?? null;
        if ($v) { $options[$p['torneo']] = $p['torneo']; $formats[$v['formato']] = $v['formato']; }
        if (!$v || ($tournament !== '' && $p['torneo'] !== $tournament) || ($format !== '' && $v['formato'] !== $format)) continue;
        $f = $v['formato'];
        if (!isset($groups[$f])) $groups[$f] = ['records'=>array_map(static fn($label)=>['label'=>$label,'value'=>null,'holders'=>[]], $labels), 'goalkeeper'=>['appearances'=>0,'goals_conceded'=>0,'clean_sheets'=>0,'average'=>null,'clean_sheet_percentage'=>null], 'eligible_matches'=>0, 'verified_keeper_matches'=>0];
        if ($v['esito'] !== 'confermato' || (int)$p['giocata'] !== 1 || $p['gol_casa'] === null || $p['gol_ospite'] === null || $v['risultato_hash'] !== records_hash($p)) continue;
        $g = &$groups[$f]; $g['eligible_matches']++;
        $rs = $rows[$p['id']] ?? []; $h = records_hash($p, $rs);
        $add = static function(string $key, int $value, string $subject, ?int $pid = null) use (&$g, $p): void {
            $record = &$g['records'][$key];
            if ($record['value'] === null || $value > $record['value']) { $record['value'] = $value; $record['holders'] = []; }
            if ($value === $record['value']) $record['holders'][] = ['subject'=>$subject,'player_id'=>$pid,'match'=>$p];
        };
        $a=(int)$p['gol_casa']; $b=(int)$p['gol_ospite'];
        $add('total',$a+$b,$p['squadra_casa'].' / '.$p['squadra_ospite']);
        if ($a === $b) $add('draw',$a+$b,'Pareggio');
        else { $winner=$a>$b?$p['squadra_casa']:$p['squadra_ospite']; $add('margin',abs($a-$b),$winner); if (min($a,$b)===0) $add('shutout',max($a,$b),$winner); }
        $add('team',$a,$p['squadra_casa']); $add('team',$b,$p['squadra_ospite']);
        if ($v['marcature_hash'] === $h && records_scoring_complete($p,$rs)) {
            $scorers=[$p['squadra_casa']=>[], $p['squadra_ospite']=>[]];
            foreach ($rs as $r) if ((int)$r['goal']>0) { $add('player',(int)$r['goal'],$r['nome'].' '.$r['cognome'],(int)$r['giocatore_id']); $scorers[$r['squadra']][$r['giocatore_id']]=true; }
            foreach ($scorers as $team=>$ids) if ($ids) $add('scorers',count($ids),$team);
        }
        if ($v['portieri_hash'] === $h) {
            $ks=json_decode($v['portieri_json'],true) ?: [];
            try { records_validate_keepers($p,$rs,$ks); } catch (InvalidArgumentException $e) { continue; }
            $g['verified_keeper_matches']++;
            foreach ($ks as $k) if ((int)$k['player_id'] === $player) { $g['goalkeeper']['appearances']++; $g['goalkeeper']['goals_conceded']+=$k['goals']; if ($k['full'] && $k['goals']===0) $g['goalkeeper']['clean_sheets']++; }
        }
        unset($g);
    }
    foreach ($groups as &$g) { $k=&$g['goalkeeper']; if ($k['appearances']) { $k['average']=number_format($k['goals_conceded']/$k['appearances'],2,'.',''); $k['clean_sheet_percentage']=number_format(100*$k['clean_sheets']/$k['appearances'],2,'.',''); } unset($k); }
    return ['groups'=>$groups,'tournaments'=>array_values($options),'formats'=>array_values($formats),'coverage'=>'Solo risultati confermati; marcatori e portieri solo con dati completi verificati. Le partite storiche prive di attestazione sono escluse.'];
}
