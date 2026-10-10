<?php
declare(strict_types=1);
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/env_loader.php';
require_once __DIR__ . '/../includi/match_video_sync.php';
require_once __DIR__ . '/../includi/match_video_sync_job.php';

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
$jsonRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array($_POST['action'] ?? '',['scan','step'],true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_is_valid((string)($_POST['_csrf'] ?? ''),'match_video_sync')) throw new RuntimeException('Sessione scaduta. Ricarica la pagina.');
        $action = $_POST['action'] ?? '';
        if ($action === 'scan') {
            unset($_SESSION['match_video_sync_plan']);
            $from = video_sync_date($fromValue); $to = video_sync_date($toValue);
            if ($to < $from || $from->diff($to)->days > 366) throw new InvalidArgumentException('Seleziona un intervallo di pubblicazione di massimo un anno.');
            if (!in_array($platform,['both','instagram','youtube'],true)) throw new InvalidArgumentException('Piattaforma non valida.');
            $_SESSION['match_video_sync_job'] = video_sync_job_create($platform,$fromValue,$toValue);
            $reply = ['done'=>false,'job'=>$_SESSION['match_video_sync_job']['id'],'progress'=>'Avvio della ricerca…'];
        } elseif ($action === 'step') {
            $job = $_SESSION['match_video_sync_job'] ?? null;
            if (!is_array($job) || !hash_equals($job['id'],(string)($_POST['job'] ?? '')) || $job['expires'] < time()) throw new RuntimeException('Ricerca scaduta o sostituita. Avviane una nuova.');
            if ($job['index'] < count($job['sources'])) {
                video_sync_job_step($job);
                $_SESSION['match_video_sync_job'] = $job;
                $reply = ['done'=>false,'job'=>$job['id'],'progress'=>video_sync_job_progress($job)];
            } else {
                if ($job['media']) {
                    $conn = video_sync_open_database();
                    $plan = video_sync_plan($job['media'],video_sync_load_matches($conn));
                    $conn->close();
                }
                $message = count($job['media']).' contenuti trovati; '.count(array_filter($plan,fn($row)=>$row['automatic'])).' abbinamenti univoci pronti da salvare.';
                $_SESSION['match_video_sync_plan'] = ['expires'=>time()+1800,'rows'=>$plan,'message'=>$message,'errors'=>$job['errors'],
                    'from'=>$job['from'],'to'=>$job['to'],'platform'=>count($job['sources']) === 2 ? 'both' : $job['sources'][0]];
                unset($_SESSION['match_video_sync_job']);
                $reply = ['done'=>true,'progress'=>$message];
            }
        } elseif ($action === 'save') {
            $snapshot = $_SESSION['match_video_sync_plan'] ?? null;
            if (!is_array($snapshot) || $snapshot['expires'] < time()) throw new RuntimeException('La ricerca è scaduta. Cerca nuovamente i video.');
            $selected = $_POST['selected'] ?? [];
            if (!is_array($selected) || !$selected || count($selected) > min(100,count($snapshot['rows']))) throw new InvalidArgumentException('Seleziona da 1 a 100 contenuti da collegare.');
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
            $conn = video_sync_open_database();
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
            $message = $saved.' link salvati. '.$preserved.' link già presenti conservati.';
            foreach ($selected as $key) unset($snapshot['rows'][$key]);
            $snapshot['message'] = $message;
            $snapshot['expires'] = time()+1800;
            $_SESSION['match_video_sync_plan'] = $snapshot;
            header('Location: '.strtok($_SERVER['REQUEST_URI'] ?? '/api/sincronizza_video_partite.php','?'));
            exit;
        } else throw new InvalidArgumentException('Azione non valida.');
    } catch (Throwable $e) {$errors[] = $e->getMessage();}
}
if ($jsonRequest) {
    header('Content-Type: application/json; charset=utf-8');
    if ($errors) {http_response_code(400);$reply = ['error'=>implode(' ',$errors)];}
    session_write_close();
    echo json_encode($reply,JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $snapshot = $_SESSION['match_video_sync_plan'] ?? null;
    if (is_array($snapshot) && $snapshot['expires'] >= time()) {
        $plan = $snapshot['rows']; $message = $snapshot['message'] ?? ''; $errors = $snapshot['errors'] ?? [];
        $fromValue = $snapshot['from'] ?? $fromValue; $toValue = $snapshot['to'] ?? $toValue; $platform = $snapshot['platform'] ?? $platform;
    }
}
$csrf = csrf_get_token('match_video_sync');
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sincronizza video delle partite</title>
<style>
:root{color-scheme:dark;font-family:Arial,sans-serif}*{box-sizing:border-box}body{margin:0;background:#081522;color:#f4f7fb}main{width:min(1200px,calc(100% - 28px));margin:30px auto}a{color:#99ccff}h1{font-size:clamp(24px,4vw,34px)}p{line-height:1.5}.panel,.result{background:#142235;border:1px solid #ffffff20;border-radius:12px;padding:20px;margin:18px 0}.filters{display:flex;gap:16px;align-items:end;flex-wrap:wrap}label{display:grid;gap:8px;font-weight:700}input,select,button{font:inherit;padding:11px;border-radius:8px;border:1px solid #63758a;max-width:100%}input,select{background:#0b1726;color:#fff}button{background:#e8bd45;color:#101722;font-weight:700;cursor:pointer}.error{padding:12px;background:#512b30;border-radius:8px}.status{padding:12px;background:#183f34;border-radius:8px}.result h2{font-size:17px;margin:0 0 12px;overflow-wrap:anywhere}.meta{font-size:13px;color:#beccdc}.choice{display:flex;gap:12px;align-items:start;margin:12px 0}.choice input{width:22px;height:22px;flex-shrink:0}.result select{width:100%;margin:8px 0}.caption{white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px}.help{color:#beccdc}button:disabled{opacity:.5;cursor:default}@media(max-width:650px){.filters>*{width:100%}.panel,.result{padding:15px}}
label,.filters>*{min-width:0}label{grid-template-columns:minmax(0,1fr)}label input,label select{width:100%;min-width:0}.choice{grid-template-columns:none}.error,.status,.help{overflow-wrap:anywhere}
.cover-preview{display:block;width:min(220px,100%);max-height:260px;object-fit:contain;margin:12px 0;border-radius:8px}
</style></head><body><main>
<a href="/admin_dashboard.php">Torna alla dashboard</a><h1>Sincronizza video delle partite</h1>
<p>Cerca i Reel Instagram e i video pubblici YouTube. Torneo, giornata, squadre e risultato identificano la gara anche se il video è stato pubblicato giorni dopo.</p>
<p class="help">Sono riconosciuti anche torneo e gara su righe separate, oppure CHAMPIONS LEAGUE | Napoli-Sporting Lisbona 5-7. Giornata e hashtag sono facoltativi; nei casi dubbi scegli la gara fra le corrispondenze.</p>
<?php foreach ($errors as $error): ?><p class="error" role="alert"><?= video_sync_escape($error) ?></p><?php endforeach; ?>
<?php if ($message !== ''): ?><p class="status" role="status"><?= video_sync_escape($message) ?></p><?php endif; ?>
<form id="videoSyncSearch" class="panel filters" method="post"><input type="hidden" name="_csrf" value="<?= video_sync_escape($csrf) ?>"><input type="hidden" name="action" value="scan">
<label>Pubblicati dal<input type="date" name="from" value="<?= video_sync_escape($fromValue) ?>" required></label>
<label>Pubblicati fino al<input type="date" name="to" value="<?= video_sync_escape($toValue) ?>" required></label>
<label>Piattaforma<select name="platform"><?php foreach (['both'=>'Instagram e YouTube','instagram'=>'Instagram','youtube'=>'YouTube'] as $value=>$label): ?><option value="<?= $value ?>" <?= $platform === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button type="submit">Cerca video e Reel</button></form>
<p id="videoSyncProgress" class="status" role="status" aria-live="polite" hidden></p>
<noscript><p>Attiva JavaScript per eseguire la ricerca con avanzamento.</p></noscript>
<?php if ($plan):
$pageCount = max(1,(int)ceil(count($plan)/100));
$pageNumber = max(1,min($pageCount,(int)($_GET['page'] ?? 1)));
$pageRows = array_slice($plan,($pageNumber-1)*100,100,true);
?><p>Pagina <?= $pageNumber ?> di <?= $pageCount ?> · <?= count($plan) ?> contenuti ancora da controllare. Il salvataggio riguarda questa pagina.</p>
<nav aria-label="Pagine dei risultati"><?php if ($pageNumber > 1): ?><a href="?page=<?= $pageNumber-1 ?>">Pagina precedente</a> · <?php endif; ?><?php if ($pageNumber < $pageCount): ?><a href="?page=<?= $pageNumber+1 ?>">Pagina successiva</a><?php endif; ?></nav>
<form method="post"><input type="hidden" name="_csrf" value="<?= video_sync_escape($csrf) ?>"><input type="hidden" name="action" value="save">
<p>Gli abbinamenti univoci sono preselezionati. Per i casi dubbi scegli la gara e seleziona il contenuto. I link già presenti vengono conservati.</p>
<?php foreach ($pageRows as $key=>$row): $blocked = !$row['candidates'] || (count($row['candidates']) === 1 && trim((string)($row['candidates'][0]['link_'.$row['media']['platform']] ?? '')) !== ''); ?>
<article class="result"><h2><a href="<?= video_sync_escape($row['media']['url']) ?>" target="_blank" rel="noopener noreferrer"><?= ucfirst($row['media']['platform']) ?> · <?= video_sync_escape($row['media']['date']) ?> · Apri contenuto</a></h2>
<?php $thumbnail = video_sync_thumbnail_url($row['media']['thumbnail'] ?? ''); if ($thumbnail !== ''): ?><a href="<?= video_sync_escape($row['media']['url']) ?>" target="_blank" rel="noopener noreferrer"><img class="cover-preview" src="<?= video_sync_escape($thumbnail) ?>" alt="Copertina del contenuto" loading="lazy" referrerpolicy="no-referrer"></a><?php endif; ?>
<p class="caption"><?= video_sync_escape($row['media']['title'] ?: $row['media']['description']) ?></p><p class="meta"><?= video_sync_escape($row['status']) ?></p>
<?php if ($row['candidates']): ?><label>Partita<select name="match_id[<?= $key ?>]" <?= $blocked ? 'disabled' : '' ?>>
<?php if (count($row['candidates']) > 1): ?><option value="">Scegli la partita</option><?php endif; ?>
<?php foreach ($row['candidates'] as $match): ?><option value="<?= (int)$match['id'] ?>"><?= video_sync_escape(video_sync_label($match)) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<label class="choice"><input type="checkbox" name="selected[]" value="<?= $key ?>" <?= $row['automatic'] ? 'checked' : '' ?> <?= $blocked ? 'disabled' : '' ?>>Collega questo contenuto alla partita</label></article>
<?php endforeach; ?><button type="submit">Salva i link selezionati</button></form><?php endif; ?>
</main><script src="/api/sincronizza_video_partite.js?v=3" defer></script></body></html>
