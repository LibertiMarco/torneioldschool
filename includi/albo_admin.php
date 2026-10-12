<?php
require_once __DIR__ . '/albo_archive.php';
require_once __DIR__ . '/tournament_scorers.php';
require_once __DIR__ . '/env_loader.php';

function albo_admin_db(): PDO {
    return new PDO('mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';port=' . (int)(getenv('DB_PORT') ?: 3306)
        . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4', getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
}

function albo_admin_rows(PDO $db, string $sql, array $params = []): array {
    $stmt=$db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function albo_admin_plan(PDO $db, mysqli $conn, int $id, string $section, bool $closing): array {
    $t=albo_admin_rows($db,'SELECT * FROM tornei WHERE id=? AND sezione=?',[$id,$section])[0] ?? null;
    if (!$t) throw new RuntimeException('Torneo non disponibile in questa sezione.');
    $slug=preg_replace('/\.(php|html?)$/i','',basename($t['filetorneo']));
    $matches=albo_admin_rows($db,'SELECT * FROM partite WHERE torneo=? ORDER BY id',[$slug]);
    $teams=albo_admin_rows($db,'SELECT * FROM squadre WHERE torneo=? ORDER BY id',[$slug]);
    $scorers=albo_admin_rows($db,tournament_scorers_sql($conn,$slug),[$slug,$slug]);
    $plan=albo_propose($t,$matches,$teams,$scorers,$closing);
    $players=albo_admin_rows($db,'SELECT g.id,g.nome,g.cognome,COALESCE(sg.foto,g.foto) AS foto,sg.ruolo,s.id AS squadra_id,s.nome AS squadra,sg.media_voti
        FROM squadre_giocatori sg JOIN squadre s ON s.id=sg.squadra_id JOIN giocatori g ON g.id=sg.giocatore_id WHERE s.torneo=? ORDER BY g.cognome,g.nome',[$slug]);
    $plan['players']=$players;
    $existing=albo_archive_read($conn,$section);
    $plan['manual_kinds']=array_values(array_unique(array_column(array_filter($existing,static fn($row)=>(int)($row['torneo_id'] ?? 0) === $id && $row['source'] === 'manuale'),'kind')));
    $currentScorers=array_map('intval',array_column(array_filter($plan['awards'],static fn($a)=>$a['kind'] === 'SCORER'),'giocatore_id'));
    $manualScorers=array_values(array_filter($existing,static fn($row)=>(int)($row['torneo_id'] ?? 0) === $id && $row['source'] === 'manuale' && $row['kind'] === 'SCORER'));
    $manualScorerConflict=count(array_filter($manualScorers,static fn($row)=>!in_array((int)($row['giocatore_id'] ?? 0),$currentScorers,true))) > 0;
    if ($manualScorerConflict) $plan['issues'][]='Capocannoniere manuale non correlabile alla classifica attuale: verifica dalla scheda Modifica.';
    foreach ($plan['awards'] as &$award) {
        $award['status']='nuovo';
        foreach ($existing as $published) {
            if ((int)($published['torneo_id'] ?? 0) !== $id || $published['kind'] !== $award['kind']) continue;
            if ($published['source'] === 'manuale') {
                if ($award['kind'] === 'SCORER' && !$manualScorerConflict && (int)$published['giocatore_id'] !== (int)$award['giocatore_id']) continue;
                $award['status']='manuale'; break;
            }
            if (($published['source_key'] ?? '') !== $award['source_key']) continue;
            $award['status']=($published['source_hash'] ?? '') === $award['source_hash'] ? 'invariato' : 'da aggiornare';
        }
    }
    unset($award);
    // Freeze exactly the data shown in the preview; reject stale submissions.
    $plan['revision']=hash('sha256',json_encode([$t,$matches,$teams,$scorers,$players,$existing],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    return $plan;
}

final class AlboAwardStore {
    public function __construct(private PDO $db) { $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    private function admin(int $actor): void {
        $lock=$this->db->inTransaction() && $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $role=albo_admin_rows($this->db,'SELECT ruolo FROM utenti WHERE id=?' . $lock,[$actor])[0]['ruolo'] ?? '';
        if (!in_array($role,['admin','sysadmin'],true)) throw new RuntimeException('Operazione riservata agli amministratori.');
    }
    private function schema(): void {
        $engine=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        $this->db->exec('CREATE TABLE IF NOT EXISTS albo_automatico (source_key VARCHAR(190) PRIMARY KEY,torneo_id INTEGER NOT NULL,kind VARCHAR(32) NOT NULL,data TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1)' . $engine);
        $pk=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $this->db->exec("CREATE TABLE IF NOT EXISTS albo_automatico_log (id $pk,source_key VARCHAR(190) NOT NULL,actor_id INTEGER NOT NULL,previous_data TEXT,new_data TEXT,created_at VARCHAR(32) NOT NULL)" . $engine);
    }
    /** The caller re-derives proposals inside the transaction, using the fresh role. */
    public function publish(int $id,int $actor,string $revision,array $keys,array $individuals,bool $close,callable $derive): int {
        $this->admin($actor); $this->schema();
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $this->db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->db->beginTransaction();
        try {
            $this->admin($actor);
            $mysql=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
            $lock=$mysql ? ' FOR UPDATE' : '';
            $t=albo_admin_rows($this->db,'SELECT * FROM tornei WHERE id=?' . $lock,[$id])[0] ?? null;
            if (!$t) throw new RuntimeException('Torneo non trovato.');
            if ($mysql) {
                $slug=preg_replace('/\.(php|html?)$/i','',basename($t['filetorneo']));
                albo_admin_rows($this->db,'SELECT id FROM partite WHERE torneo=? FOR UPDATE',[$slug]);
                albo_admin_rows($this->db,'SELECT id FROM squadre WHERE torneo=? FOR UPDATE',[$slug]);
                albo_admin_rows($this->db,'SELECT pg.id FROM partita_giocatore pg JOIN partite p ON p.id=pg.partita_id WHERE p.torneo=? FOR UPDATE',[$slug]);
                albo_admin_rows($this->db,'SELECT sg.id FROM squadre_giocatori sg JOIN squadre s ON s.id=sg.squadra_id WHERE s.torneo=? FOR UPDATE',[$slug]);
            }
            $plan=$derive($this->db);
            if (!hash_equals($plan['revision'],$revision)) throw new RuntimeException('Risultati o premi modificati: prepara nuovamente il riepilogo.');
            if ($close && $plan['blocking']) throw new RuntimeException('Non puoi chiudere il torneo: ' . implode(' ',$plan['blocking']));
            $options=array_column($plan['awards'],null,'source_key'); $selected=[];
            foreach ($keys as $key) if (!is_string($key)) throw new RuntimeException('Selezione dei premi non valida.');
            foreach (array_unique($keys) as $key) {
                if (!isset($options[$key]) || $options[$key]['status'] === 'manuale') throw new RuntimeException('Selezione dei premi non valida.');
                $selected[]=$options[$key];
            }
            $cap=array_column(array_filter($plan['awards'],static fn($a)=>$a['kind'] === 'SCORER' && $a['status'] !== 'manuale'),'source_key');
            $selectedCap=array_values(array_intersect($cap,$keys));
            if ($selectedCap && count($selectedCap) !== count($cap)) throw new RuntimeException('Seleziona tutti i capocannonieri a pari merito.');
            foreach ($individuals as $kind=>$playerId) {
                if (!$playerId) continue;
                if (filter_var($playerId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) === false) throw new RuntimeException('Giocatore non valido.');
                if (in_array($kind,$plan['manual_kinds'] ?? [],true)) throw new RuntimeException('Premio gia inserito manualmente: usa Modifica per correggerlo.');
                if (!in_array($kind,['MVP','KEEPER','DEFENDER','ATTACKER'],true)) throw new RuntimeException('Premio individuale non valido.');
                $winner=null; foreach ($plan['players'] as $player) if ((int)$player['id'] === (int)$playerId) { $winner=$player; break; }
                if (!$winner) throw new RuntimeException('Giocatore non presente nelle rose del torneo.');
                $selected[]=albo_award($t,$kind,$winner,'Scelta confermata dall’amministratore','Individuale');
            }
            if (!$selected && !$close) throw new RuntimeException('Seleziona almeno un premio da pubblicare.');
            $changed=0;
            foreach ($selected as $row) {
                unset($row['status']);
                if ($row['tipo'] === 'Squadra') $row['members']=array_values(array_unique(array_map(static fn($p)=>(int)$p['id'],array_filter($plan['players'],static fn($p)=>(int)$p['squadra_id'] === (int)$row['squadra_id']))));
                $old=albo_admin_rows($this->db,'SELECT data,active FROM albo_automatico WHERE source_key=?',[$row['source_key']])[0] ?? null;
                $data=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                if ($old && $old['data'] === $data && (int)$old['active'] === 1) continue;
                if ($old) $stmt=$this->db->prepare('UPDATE albo_automatico SET data=?,active=1 WHERE source_key=?');
                else $stmt=$this->db->prepare('INSERT INTO albo_automatico (data,source_key,torneo_id,kind) VALUES (?,?,?,?)');
                $stmt->execute($old ? [$data,$row['source_key']] : [$data,$row['source_key'],$id,$row['kind']]);
                $this->log($row['source_key'],$actor,$old['data'] ?? null,$data); $changed++;
            }
            if ($selectedCap) foreach (albo_admin_rows($this->db,"SELECT source_key,data FROM albo_automatico WHERE torneo_id=? AND kind='SCORER' AND active=1",[$id]) as $old) {
                $oldData=json_decode((string)$old['data'],true) ?: [];
                if (($oldData['anno'] ?? '') !== ($plan['tournament']['data_fine'] ?? '')) continue;
                if (in_array($old['source_key'],$selectedCap,true)) continue;
                $stmt=$this->db->prepare('UPDATE albo_automatico SET active=0 WHERE source_key=?');$stmt->execute([$old['source_key']]);
                $this->log($old['source_key'],$actor,$old['data'],null);$changed++;
            }
            if ($close) { $stmt=$this->db->prepare("UPDATE tornei SET stato='terminato' WHERE id=?");$stmt->execute([$id]); }
            $this->db->commit(); return $changed;
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    private function log(string $key,int $actor,?string $previous,?string $next): void {
        $stmt=$this->db->prepare('INSERT INTO albo_automatico_log (source_key,actor_id,previous_data,new_data,created_at) VALUES (?,?,?,?,?)');
        $stmt->execute([$key,$actor,$previous,$next,gmdate('c')]);
    }
}
