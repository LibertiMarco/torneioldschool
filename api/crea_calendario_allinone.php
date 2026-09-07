<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/auto_matchday.php';
require_once __DIR__ . '/../includi/all_in_one_calendar.php';
require_once __DIR__ . '/../includi/api_cache.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function aio_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$tournaments = array_values(array_filter(auto_matchday_fetch_tournaments($conn), static function ($t) use ($adminSection) {
    return $t['sezione'] === $adminSection &&
        (($t['config']['formato'] ?? '') === 'girone' || stripos($t['slug'], 'AllInOneNight') !== false);
}));
$fields = auto_matchday_fetch_fields($conn);
$selectedId = (int)($_POST['torneo'] ?? 0);
$selectedFields = array_values(array_unique(array_filter((array)($_POST['campi'] ?? []), 'is_string')));
$capacityInput = is_array($_POST['capacita'] ?? null) ? $_POST['capacita'] : [];
$capacities = [];
foreach ($fields as $index => $field) {
    $capacities[$field] = is_scalar($capacityInput[$index] ?? 1) ? ($capacityInput[$index] ?? 1) : '';
}
$date = is_string($_POST['data'] ?? null) ? $_POST['data'] : '';
$time = is_string($_POST['ora'] ?? null) ? $_POST['ora'] : '';
$rows = [];
$error = $success = '';
$locked = $transaction = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('admin_allinone');
    try {
        $tournament = null;
        foreach ($tournaments as $item) if ($item['id'] === $selectedId) $tournament = $item;
        if (!$tournament) throw new RuntimeException('Seleziona una competizione a gironi valida.');
        if (!$selectedFields || array_diff($selectedFields, $fields)) throw new RuntimeException('Seleziona almeno un campo valido.');
        $fieldSlots = all_in_one_field_slots($selectedFields, $capacities);
        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$date $time", new DateTimeZone('Europe/Rome'));
        if (!$start || $start->format('Y-m-d H:i') !== "$date $time") throw new RuntimeException('Inserisci data e ora valide.');
        $save = ($_POST['azione'] ?? '') === 'salva';
        if ($save) {
            // Serializza le generazioni, anche tra tornei che condividono gli stessi campi.
            $lock = $conn->query("SELECT GET_LOCK('all_in_one_calendar', 10) AS acquired");
            $locked = $lock && (int)$lock->fetch_assoc()['acquired'] === 1;
            if (!$locked) throw new RuntimeException('Generazione in corso. Riprova tra qualche secondo.');
            if (!$conn->begin_transaction()) throw new RuntimeException('Impossibile iniziare il salvataggio.');
            $transaction = true;
        }
        $slug = $tournament['slug'];
        if (auto_matchday_fetch_regular_matches($conn, $slug)) throw new RuntimeException('Esistono già partite dei gironi per questa competizione. Il calendario non è stato modificato.');
        $teams = auto_matchday_fetch_teams($conn, $slug);
        $rows = all_in_one_calendar($teams, $fieldSlots, $start);
        $occupied = auto_matchday_fetch_global_occupied_slots($conn);
        all_in_one_check_capacity($rows, $occupied, $fieldSlots);
        if ($save) {
            $fingerprint = hash('sha256', json_encode($rows));
            if (!hash_equals($fingerprint, (string)($_POST['anteprima'] ?? ''))) throw new RuntimeException('I dati sono cambiati: genera nuovamente l’anteprima prima di salvare.');
            $stmt = $conn->prepare("INSERT INTO partite (torneo, fase, fase_round, fase_leg, squadra_casa, squadra_ospite, gol_casa, gol_ospite, data_partita, ora_partita, campo, giornata, giocata, link_youtube, link_instagram, created_at) VALUES (?, 'REGULAR', NULL, NULL, ?, ?, 0, 0, ?, ?, ?, ?, 0, NULL, NULL, NOW())");
            if (!$stmt) throw new RuntimeException('Impossibile preparare il salvataggio.');
            foreach ($rows as $row) {
                $stmt->bind_param('ssssssi', $slug, $row['casa'], $row['ospite'], $row['data'], $row['ora'], $row['campo'], $row['giornata']);
                if (!$stmt->execute()) throw new RuntimeException('Impossibile salvare le partite.');
            }
            $stmt->close();
            if (!$conn->commit()) throw new RuntimeException('Impossibile completare il salvataggio.');
            $transaction = false;
            $success = count($rows) . ' partite create correttamente.';
            tos_api_cache_delete_standings($slug);
        }
    } catch (Throwable $e) {
        if ($transaction) $conn->rollback();
        $error = $e instanceof mysqli_sql_exception ? 'Errore database: nessuna partita creata. Riprova.' : $e->getMessage();
        $rows = [];
    } finally {
        if ($locked) $conn->query("SELECT RELEASE_LOCK('all_in_one_calendar')");
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow"><title>Calendario AllInOneNight</title>
  <link rel="stylesheet" href="/style.min.css">
  <style>
    body{background:#f3f5f8}
    .aio,.aio *{box-sizing:border-box}
    .aio{width:min(1120px,calc(100% - 40px));margin:120px auto 64px;color:#15293e;line-height:1.5}
    .aio-back{display:inline-flex;gap:8px;align-items:center;color:#526277;text-decoration:none;font-size:.9rem;font-weight:600;margin-bottom:22px}
    .aio-back:hover{color:#15293e;text-decoration:underline}
    .aio-hero{padding:32px;border-radius:20px;background:#15293e;color:#fff;margin-bottom:24px;border-top:4px solid #d9a441}
    .aio-eyebrow{font-size:.75rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#edc77d;margin:0 0 10px}
    .aio h1{font-size:clamp(1.65rem,4vw,2.35rem);line-height:1.2;margin:0 0 12px;color:inherit;text-align:left}
    .aio-hero>p:last-of-type{color:#d6e0eb;max-width:740px;margin:0;font-size:.98rem}
    .aio-tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:22px}
    .aio-tags span{font-size:.8rem;padding:6px 12px;border:1px solid #486077;border-radius:30px;color:#f0f4f8}
    .aio form{display:grid;gap:22px}
    .aio-panel{background:#fff;border:1px solid #e0e6ed;border-radius:16px;padding:26px;box-shadow:0 4px 16px #15293e05;min-width:0}
    .aio h2{font-size:1.15rem;margin:0 0 6px;color:#15293e;text-align:left}
    .aio-help{font-size:.9rem;color:#64748b;margin:0 0 22px;max-width:800px}
    .aio-form-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:18px;margin-top:22px}
    .aio label{display:block;font-size:.88rem;font-weight:600;color:#33465c;min-width:0}
    .aio input:not([type=checkbox]),.aio select{display:block;width:100%;min-width:0;max-width:100%;height:48px;margin-top:8px;padding:10px 12px;border:1px solid #cdd6e1;border-radius:9px;background:#fff;color:#15293e;font:inherit;color-scheme:light}
    .aio input:focus-visible,.aio select:focus-visible,.aio button:focus-visible,.aio a:focus-visible,.aio summary:focus-visible{outline:3px solid #d9a441;outline-offset:3px}
    .aio-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .aio-field{border:1px solid #dce3ec;border-radius:12px;padding:18px;background:#fafbfd;transition:border-color .15s,background .15s}
    .aio-field:has(input[type=checkbox]:checked){border-color:#b48b40;background:#fffcf4;box-shadow:inset 0 0 0 1px #b48b40}
    .aio .aio-field-name{display:flex;align-items:center;gap:12px;font-size:1rem;font-weight:700;cursor:pointer;overflow-wrap:anywhere}
    .aio input[type=checkbox]{appearance:auto;width:20px;height:20px;flex:0 0 20px;margin:0;accent-color:#15293e;cursor:pointer}
    .aio .aio-capacity{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:16px;padding-top:14px;border-top:1px solid #e3e7ec;font-weight:500;color:#526277}
    .aio .aio-capacity input{width:80px;flex:0 0 80px;height:40px;margin:0;text-align:center}
    .aio-actions{display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;padding:0 2px}
    .aio button{width:auto;padding:13px 22px;min-height:48px;border:1px solid #15293e;border-radius:10px;background:#15293e;color:#fff;font:inherit;font-size:.95rem;font-weight:700;cursor:pointer;transition:background .15s,box-shadow .15s}
    .aio button:hover{background:#254766;box-shadow:0 4px 12px #15293e20}
    .aio button.aio-save{background:#d9a441;border-color:#d9a441;color:#15293e}
    .aio button.aio-save:hover{background:#e7b756}
    .aio-details{margin-top:20px;color:#526277;font-size:.88rem}
    .aio-details summary{cursor:pointer;font-weight:600;width:fit-content}.aio-details p{max-width:820px;margin:10px 0 0}
    .aio-preview{margin-top:28px;padding:0;overflow:hidden}
    .aio-preview-head{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:22px 26px}
    .aio-preview-head h2{margin:0}.aio-count{background:#edf2f7;color:#33465c;border-radius:30px;padding:6px 12px;font-size:.85rem;white-space:nowrap}
    .aio-scroll{overflow:auto}.aio table{width:100%;min-width:720px;border-collapse:collapse;margin:0;background:#fff;font-size:.9rem}
    .aio th,.aio td{text-align:left;padding:15px 20px;border:0;border-bottom:1px solid #e9edf2;color:#33465c}
    .aio th{background:#f6f8fb;font-size:.73rem;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:#62738a;white-space:nowrap}
    .aio tbody tr:hover{background:#fffcf4}.aio tbody tr:last-child td{border-bottom:0}
    .aio td:nth-child(3){font-weight:600;color:#15293e}.aio td:nth-child(5){font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
    .aio-error,.aio-success{padding:16px 20px;border-radius:12px;margin:0 0 22px;border:1px solid}
    .aio-error{background:#fff1f2;color:#9f2335;border-color:#fecdd3}.aio-success{background:#edfdf3;color:#166534;border-color:#bbf7d0}
    @media(max-width:720px){.aio{width:calc(100% - 24px);margin-top:100px}.aio-hero{padding:24px}.aio-panel{padding:20px}.aio-form-grid{grid-template-columns:1fr 1fr}.aio-form-grid>label:first-child{grid-column:1/-1}.aio-fields{grid-template-columns:1fr}.aio-preview{padding:0}.aio-preview-head{padding:20px}}
    @media(max-width:420px){.aio-form-grid{grid-template-columns:1fr}.aio-actions{flex-direction:column}.aio button{width:100%}.aio-hero{padding:20px}.aio-preview-head{align-items:flex-start;flex-direction:column;gap:8px}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includi/header.php'; ?>
<main class="aio">
  <a class="aio-back" href="/api/gestione_partite.php"><span aria-hidden="true">←</span> Gestione Partite</a>
  <header class="aio-hero">
    <p class="aio-eyebrow">AllInOneNight · Organizzazione torneo</p>
    <h1>Una serata, tutte le partite.</h1>
    <p>Configura i campi e l’orario di partenza. Il calendario dei gironi è pronto in pochi passaggi, con un’anteprima da controllare prima di creare le partite.</p>
    <div class="aio-tags"><span>Turni da 15 minuti</span><span>Gironi alternati</span><span>Più campi in parallelo</span></div>
  </header>
  <?php if ($error): ?><p class="aio-error" role="alert"><?= aio_h($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p class="aio-success" role="status"><?= aio_h($success) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field('admin_allinone') ?>
    <section class="aio-panel" aria-labelledby="aio-settings-title">
    <h2 id="aio-settings-title">1. Imposta la serata</h2>
    <p class="aio-help">Scegli la competizione e quando dare il calcio d’inizio.</p>
    <div class="aio-form-grid">
    <label>Competizione <select name="torneo" required><option value="">Seleziona competizione</option>
      <?php foreach ($tournaments as $t): ?><option value="<?= $t['id'] ?>" <?= $selectedId === $t['id'] ? 'selected' : '' ?>><?= aio_h($t['nome']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Data di inizio <input type="date" name="data" value="<?= aio_h($date) ?>" required></label>
    <label>Orario di inizio <input type="time" name="ora" value="<?= aio_h($time) ?>" required></label>
    </div>
    </section>
    <section class="aio-panel" aria-labelledby="aio-fields-title">
      <h2 id="aio-fields-title">2. Scegli dove giocare</h2>
      <p class="aio-help">Seleziona gli impianti e indica i campi disponibili in ciascuno: 2 campi permettono 2 partite contemporanee.</p>
      <div class="aio-fields">
      <?php foreach ($fields as $index => $field): ?>
        <div class="aio-field">
          <label class="aio-field-name"><input type="checkbox" name="campi[]" value="<?= aio_h($field) ?>" <?= in_array($field, $selectedFields, true) ? 'checked' : '' ?>><?= aio_h($field) ?></label>
          <label class="aio-capacity">Campi disponibili <input aria-label="Numero campi per <?= aio_h($field) ?>" type="number" name="capacita[<?= $index ?>]" min="1" max="32" step="1" value="<?= aio_h($capacities[$field]) ?>"></label>
        </div>
      <?php endforeach; ?>
      <?php if (!$fields): ?><p>Aggiungi prima i campi in Gestione Partite → Campi.</p><?php endif; ?>
      </div>
      <details class="aio-details"><summary>Come vengono distribuite le partite?</summary><p>I gironi si alternano per ogni giornata. Le gare dello stesso girone si giocano in parallelo; se i campi non bastano, proseguono nella fascia successiva di 15 minuti. Le partite mantengono il nome dell’impianto. Le squadre seguono l’ordine di creazione: con quattro squadre, 1–2 e 3–4, poi 1–3 e 2–4, infine 1–4 e 2–3.</p></details>
    </section>
    <div class="aio-actions">
    <button type="submit" name="azione" value="anteprima">Genera anteprima</button>
    <?php if ($rows && !$success): ?>
      <input type="hidden" name="anteprima" value="<?= aio_h(hash('sha256', json_encode($rows))) ?>">
      <button class="aio-save" type="submit" name="azione" value="salva">Crea tutte le <?= count($rows) ?> partite</button>
    <?php endif; ?>
    </div>
  </form>
  <?php if ($rows): ?>
    <section class="aio-panel aio-preview" aria-labelledby="aio-preview-title">
    <div class="aio-preview-head"><h2 id="aio-preview-title"><?= $success ? 'Calendario creato' : 'Anteprima del calendario' ?></h2><span class="aio-count"><?= count($rows) ?> partite</span></div>
    <div class="aio-scroll"><table><thead><tr><th>Giornata</th><th>Girone</th><th>Partita</th><th>Data</th><th>Ora</th><th>Campo</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= $row['giornata'] ?></td><td><?= aio_h($row['girone']) ?></td><td><?= aio_h($row['casa']) ?> – <?= aio_h($row['ospite']) ?></td><td><?= aio_h($row['data']) ?></td><td><?= aio_h(substr($row['ora'], 0, 5)) ?></td><td><?= aio_h($row['campo']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    </section>
  <?php endif; ?>
</main>
</body></html>
