<?php
declare(strict_types=1);
require_once __DIR__.'/match_video_sync_job.php';

function video_schedule_window(DateTimeImmutable $end): array
{
    $end = $end->setTimezone(new DateTimeZone('Europe/Rome'));
    // Exactly 24 elapsed hours, including days when daylight saving time changes.
    $start = $end->setTimestamp($end->getTimestamp()-86400);
    return ['start'=>$start->format(DATE_ATOM),'end'=>$end->format(DATE_ATOM)];
}

function video_schedule_game_in_window(array $game,array $window): bool
{
    if ((int)($game['giocata'] ?? 0) !== 1) return false;
    $text = substr((string)($game['data_partita'] ?? ''),0,10).' '.(string)($game['ora_partita'] ?? '');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$text,new DateTimeZone('Europe/Rome'));
    if (!$date || $date->format('Y-m-d H:i:s') !== $text) return false;
    return $date->getTimestamp() >= strtotime($window['start']) && $date->getTimestamp() < strtotime($window['end']);
}

function video_schedule_create(DateTimeImmutable $end,int $attempt = 1): array
{
    $window = video_schedule_window($end);
    return ['version'=>1,'scheduled'=>true,'day'=>$end->setTimezone(new DateTimeZone('Europe/Rome'))->format('Y-m-d'),'window'=>$window,
        'attempt'=>$attempt,'status'=>'running','phase'=>'matches','saved'=>0,'preserved'=>0,'ambiguous'=>0,'errors'=>[],
        'job'=>video_sync_job_create('both',substr($window['start'],0,10),substr($window['end'],0,10))];
}

/** Daily trigger at 13 Rome; later invocations resume/catch up a missed run. */
function video_schedule_due(?array $state,DateTimeImmutable $now): ?array
{
    $now = $now->setTimezone(new DateTimeZone('Europe/Rome'));
    if (($state['status'] ?? '') === 'running') return $state;
    $anchor = $now->setTime(13,0);
    if ($now < $anchor) return null;
    if (($state['scheduled'] ?? true) && ($state['day'] ?? '') === $anchor->format('Y-m-d')) {
        if (($state['status'] ?? '') === 'done' || ($state['attempt'] ?? 1) >= 3 || ($state['retry_after'] ?? 0) > $now->getTimestamp()) return null;
        return video_schedule_create($anchor,(int)$state['attempt']+1);
    }
    return video_schedule_create($anchor);
}

/** Persist after each step: web cron can resume without a browser or administrator session. */
function video_schedule_step(array &$run,bool $dryRun = false,?callable $scan = null,?callable $load = null,?callable $save = null): void
{
    if ($run['status'] !== 'running') return;
    if ($run['phase'] === 'matches') {
        if ($load === null) {
            $conn = video_sync_open_database();
            try {$matches = video_sync_load_matches($conn,$run['job']['from'],$run['job']['to']);} finally {$conn->close();}
        } else $matches = $load($run['window']);
        $run['matches'] = array_values(array_filter($matches,fn($game)=>video_schedule_game_in_window($game,$run['window'])));
        // Avoid social requests when there is nothing to link.
        $missing = array_filter($run['matches'],fn($game)=>trim((string)($game['link_instagram'] ?? '')) === '' || trim((string)($game['link_youtube'] ?? '')) === '');
        $run['phase'] = $missing ? 'scan' : 'finish';return;
    }
    if ($run['phase'] === 'scan') {
        $run['job']['expires'] = time()+1800;
        ($scan ?? 'video_sync_job_step')($run['job']);
        if ($run['job']['index'] < count($run['job']['sources'])) return;
        $plan = video_sync_plan($run['job']['media'],$run['matches']);
        $run['errors'] = $run['job']['errors'];$run['writes'] = [];
        foreach ($plan as $row) {
            if ($row['automatic']) $run['writes'][] = ['row'=>$row,'match'=>$row['candidates'][0],'id'=>(int)$row['candidates'][0]['id']];
            elseif (count($row['candidates']) > 1 || (count($row['candidates']) === 1 && str_starts_with($row['status'],'Più video'))) $run['ambiguous']++;
        }
        $run['phase'] = 'save';return;
    }
    if ($run['phase'] === 'save') {
        $writes = array_slice($run['writes'],0,50);
        if (!$writes) {$run['phase'] = 'finish';return;}
        if ($dryRun) $result = ['saved'=>count($writes),'preserved'=>0];
        elseif ($save !== null) $result = $save($writes);
        else {
            $conn = video_sync_open_database();
            try {$result = video_sync_write_links($conn,$writes);} finally {$conn->close();}
        }
        $run['saved'] += $result['saved'];$run['preserved'] += $result['preserved'];
        $run['writes'] = array_slice($run['writes'],count($writes));return;
    }
    if ($run['phase'] === 'finish') {
        $run['games'] = count($run['matches']);$run['status'] = $run['errors'] ? 'failed' : 'done';
        $run['retry_after'] = time()+300;$run['finished_at'] = date(DATE_ATOM);
        // Credentials and captions are no longer needed in the persisted report.
        unset($run['job'],$run['matches'],$run['writes']);
    }
}
