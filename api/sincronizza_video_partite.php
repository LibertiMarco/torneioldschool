<?php
declare(strict_types=1);
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/env_loader.php';
require_once __DIR__ . '/../includi/match_video_sync.php';

function video_sync_escape($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function video_sync_date(string $value): DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Europe/Rome'));
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Intervallo di pubblicazione non valido.');
    return $date;
}
function video_sync_label(array $match): string {
    return $match['data_partita'].' · '.$match['torneo_nome'].' · '.($match['fase_round'] ?: 'Giornata '.$match['giornata']).' · '.$match['squadra_casa'].' '.$match['gol_casa'].'–'.$match['gol_ospite'].' '.$match['squadra_ospite'].' (ID '.$match['id'].')';
}

$today = (new DateTimeImmutable('today',new DateTimeZone('Europe/Rome')))->format('Y-m-d');
$fromValue = (string)($_POST['from'] ?? (new DateTimeImmutable('-30 days',new DateTimeZone('Europe/Rome')))->format('Y-m-d'));
$toValue = (string)($_POST['to'] ?? $today);
$platform = (string)($_POST['platform'] ?? 'both');
$errors = []; $message = ''; $plan = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_is_valid((string)($_POST['_csrf'] ?? ''),'match_video_sync')) throw new RuntimeException('Sessione scaduta. Ricarica la pagina.');
        $action = $_POST['action'] ?? '';
        if ($action === 'scan') {
            @set_time_limit(300);
            unset($_SESSION['match_video_sync_plan']);
            $from = video_sync_date($fromValue); $to = video_sync_date($toValue);
            if ($to < $from || $from->diff($to)->days > 366) throw new InvalidArgumentException('Seleziona un intervallo di pubblicazione di massimo un anno.');
            if (!in_array($platform,['both','instagram','youtube'],true)) throw new InvalidArgumentException('Piattaforma non valida.');
            $media = [];
            foreach ($platform === 'both' ? ['instagram','youtube'] : [$platform] as $source) {
                try {
                    $items = $source === 'instagram' ? video_sync_instagram($from,$to) : video_sync_youtube(trim((string)getenv('YOUTUBE_API_KEY')),trim((string)getenv('YOUTUBE_CHANNEL_ID')),$from,$to);
                    $media = array_merge($media,$items);
                } catch (Throwable $e) {$errors[] = $e->getMessage();}
            }
            if ($media) {
                require __DIR__ . '/../includi/db.php';
                $plan = video_sync_plan($media,video_sync_load_matches($conn));
                $_SESSION['match_video_sync_plan'] = ['expires'=>time()+1800,'rows'=>$plan];
            }
            $message = count($media).' contenuti trovati; '.count(array_filter($plan,fn($row)=>$row['automatic'])).' abbinamenti univoci pronti da salvare.';
        } elseif ($action === 'save') {
            $snapshot = $_SESSION['match_video_sync_plan'] ?? null;
            if (!is_array($snapshot) || $snapshot['expires'] < time()) throw new RuntimeException('La ricerca è scaduta. Cerca nuovamente i video.');
            $selected = $_POST['selected'] ?? [];
            if (!is_array($selected) || !$selected || count($selected) > count($snapshot['rows'])) throw new InvalidArgumentException('Seleziona almeno un contenuto da collegare.');
            $choices = $_POST['match_id'] ?? [];
            if (!is_array($choices)) throw new InvalidArgumentException('Selezione delle partite non valida.');
            $writes = []; $targets = [];
            foreach (array_unique($selected) as $key) {
                if (!is_string($key) || !isset($snapshot['rows'][$key])) throw new InvalidArgumentException('Contenuto non presente nella ricerca.');
                $row = $snapshot['rows'][$key];
                $id = filter_var($choices[$key] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $candidate = null;
                foreach ($row['candidates'] as $match) if ((int)$match['id'] === $id) $candidate = $match;
                if ($candidate === null) throw new InvalidArgumentException('Scegli una partita fra le corrispondenze trovate.');
                $source = $row['media']['platform']; $target = $source.':'.$id;
                if (isset($targets[$target])) throw new InvalidArgumentException('Hai scelto più video '.$source.' per la stessa gara. Selezionane uno solo.');
                $targets[$target] = true;
                if (!video_sync_valid_url($source,$row['media']['url'])) throw new InvalidArgumentException('Link del contenuto non valido.');
                $writes[] = ['row'=>$row,'match'=>$candidate,'id'=>$id,'field'=>'link_'.$source];
            }
            require __DIR__ . '/../includi/db.php';
            if (!$conn->begin_transaction()) throw new RuntimeException('Impossibile avviare il salvataggio.');
            $saved = 0; $preserved = 0;
            try {
                foreach ($writes as $write) {
                    $lock = $conn->prepare('SELECT * FROM partite WHERE id=? FOR UPDATE');
                    if (!$lock) throw new RuntimeException('Impossibile verificare le partite.');
                    $lock->bind_param('i',$write['id']);
                    if (!$lock->execute()) throw new RuntimeException('Verifica della partita non riuscita.');
                    $current = $lock->get_result()->fetch_assoc(); $lock->close();
                    if (!$current) throw new RuntimeException('Una partita è stata rimossa: ripeti la ricerca.');
                    // Prevent storing a stale match after an administrator changed its metadata.
                    foreach (['torneo','giornata','fase_round','squadra_casa','squadra_ospite','gol_casa','gol_ospite','giocata','data_partita'] as $field) {
                        if ((string)($current[$field] ?? '') !== (string)($write['match'][$field] ?? '')) throw new RuntimeException('Una partita è stata modificata: ripeti la ricerca prima di salvare.');
                    }
                    if (trim((string)($current[$write['field']] ?? '')) !== '') {$preserved++;continue;}
                    $update = $conn->prepare('UPDATE partite SET '.$write['field'].'=? WHERE id=?');
                    if (!$update) throw new RuntimeException('Salvataggio dei link non disponibile.');
                    $update->bind_param('si',$write['row']['media']['url'],$write['id']);
                    if (!$update->execute()) throw new RuntimeException('Salvataggio dei link non riuscito.');
                    $saved += $update->affected_rows; $update->close();
                }
                if (!$conn->commit()) throw new RuntimeException('Conferma del salvataggio non riuscita.');
            } catch (Throwable $e) {$conn->rollback();throw $e;}
            unset($_SESSION['match_video_sync_plan']);
            $message = $saved.' link salvati. '.$preserved.' link già presenti conservati.';
        } else throw new InvalidArgumentException('Azione non valida.');
    } catch (Throwable $e) {$errors[] = $e->getMessage();}
}
$csrf = csrf_get_token('match_video_sync');
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sincronizza video delle partite</title>
<style>
:root{color-scheme:dark;font-family:Arial,sans-serif}*{box-sizing:border-box}body{margin:0;background:#081522;color:#f4f7fb}main{width:min(1200px,calc(100% - 28px));margin:30px auto}a{color:#99ccff}h1{font-size:clamp(24px,4vw,34px)}p{line-height:1.5}.panel,.result{background:#142235;border:1px solid #ffffff20;border-radius:12px;padding:20px;margin:18px 0}.filters{display:flex;gap:16px;align-items:end;flex-wrap:wrap}label{display:grid;gap:8px;font-weight:700}input,select,button{font:inherit;padding:11px;border-radius:8px;border:1px solid #63758a;max-width:100%}input,select{background:#0b1726;color:#fff}button{background:#e8bd45;color:#101722;font-weight:700;cursor:pointer}.error{padding:12px;background:#512b30;border-radius:8px}.status{padding:12px;background:#183f34;border-radius:8px}.result h2{font-size:17px;margin:0 0 12px;overflow-wrap:anywhere}.meta{font-size:13px;color:#beccdc}.choice{display:flex;gap:12px;align-items:start;margin:12px 0}.choice input{width:22px;height:22px;flex-shrink:0}.result select{width:100%;margin:8px 0}.caption{white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px}.help{color:#beccdc}button:disabled{opacity:.5;cursor:default}@media(max-width:650px){.filters>*{width:100%}.panel,.result{padding:15px}}
label,.filters>*{min-width:0}label{grid-template-columns:minmax(0,1fr)}label input,label select{width:100%;min-width:0}.choice{grid-template-columns:none}.error,.status,.help{overflow-wrap:anywhere}
</style></head><body><main>
<a href="/admin_dashboard.php">Torna alla dashboard</a><h1>Sincronizza video delle partite</h1>
<p>Cerca i Reel Instagram e i video pubblici YouTube. Torneo, giornata, squadre e risultato identificano la gara anche se il video è stato pubblicato giorni dopo.</p>
<p class="help">Formato riconosciuto: BRASILERAO | GIORNATA 1 | CEARA 5 - 3 MIRASSOL. Gli hashtag finali sono facoltativi.</p>
<?php foreach ($errors as $error): ?><p class="error" role="alert"><?= video_sync_escape($error) ?></p><?php endforeach; ?>
<?php if ($message !== ''): ?><p class="status" role="status"><?= video_sync_escape($message) ?></p><?php endif; ?>
<form class="panel filters" method="post"><input type="hidden" name="_csrf" value="<?= video_sync_escape($csrf) ?>"><input type="hidden" name="action" value="scan">
<label>Pubblicati dal<input type="date" name="from" value="<?= video_sync_escape($fromValue) ?>" required></label>
<label>Pubblicati fino al<input type="date" name="to" value="<?= video_sync_escape($toValue) ?>" required></label>
<label>Piattaforma<select name="platform"><?php foreach (['both'=>'Instagram e YouTube','instagram'=>'Instagram','youtube'=>'YouTube'] as $value=>$label): ?><option value="<?= $value ?>" <?= $platform === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button type="submit">Cerca video e Reel</button></form>
<?php if ($plan): ?><form method="post"><input type="hidden" name="_csrf" value="<?= video_sync_escape($csrf) ?>"><input type="hidden" name="action" value="save">
<p>Gli abbinamenti univoci sono preselezionati. Per i casi dubbi scegli la gara e seleziona il contenuto. I link già presenti vengono conservati.</p>
<?php foreach ($plan as $key=>$row): $blocked = !$row['candidates'] || (count($row['candidates']) === 1 && trim((string)($row['candidates'][0]['link_'.$row['media']['platform']] ?? '')) !== ''); ?>
<article class="result"><h2><a href="<?= video_sync_escape($row['media']['url']) ?>" target="_blank" rel="noopener noreferrer"><?= ucfirst($row['media']['platform']) ?> · <?= video_sync_escape($row['media']['date']) ?> · Apri contenuto</a></h2>
<p class="caption"><?= video_sync_escape($row['media']['title'] ?: $row['media']['description']) ?></p><p class="meta"><?= video_sync_escape($row['status']) ?></p>
<?php if ($row['candidates']): ?><label>Partita<select name="match_id[<?= $key ?>]" <?= $blocked ? 'disabled' : '' ?>>
<?php if (count($row['candidates']) > 1): ?><option value="">Scegli la partita</option><?php endif; ?>
<?php foreach ($row['candidates'] as $match): ?><option value="<?= (int)$match['id'] ?>"><?= video_sync_escape(video_sync_label($match)) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<label class="choice"><input type="checkbox" name="selected[]" value="<?= $key ?>" <?= $row['automatic'] ? 'checked' : '' ?> <?= $blocked ? 'disabled' : '' ?>>Collega questo contenuto alla partita</label></article>
<?php endforeach; ?><button type="submit">Salva i link selezionati</button></form><?php endif; ?>
</main></body></html>
