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
    .aio{max-width:1100px;margin:120px auto 40px;padding:24px;background:#fff;border-radius:16px;color:#15293e}
    .aio form{display:grid;gap:16px}.aio label{display:block}.aio input,.aio select,.aio button{padding:10px;font:inherit}
    .aio fieldset label{display:inline-flex;align-items:center;gap:8px;margin:8px}.aio button{cursor:pointer}
    .aio-scroll{overflow:auto}.aio table{width:100%;border-collapse:collapse}.aio th,.aio td{text-align:left;padding:12px;border-bottom:1px solid #ddd}
    .aio-error{background:#fee2e2;padding:16px}.aio-success{background:#dcfce7;padding:16px}
    @media(max-width:600px){.aio{margin-top:90px;padding:16px}.aio input,.aio select{max-width:100%;box-sizing:border-box}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includi/header.php'; ?>
<main class="aio">
  <a href="/api/gestione_partite.php">Torna a Gestione Partite</a>
  <h1>Calendario gironi AllInOneNight</h1>
  <p>Tutte le sfide di sola andata, alternando i gironi A, B e successivi per ogni giornata. Ogni fascia dura 15 minuti: le gare dello stesso girone occupano i campi selezionati in parallelo; se i campi non bastano, proseguono nella fascia successiva.</p>
  <p>La numerazione delle squadre segue l’ordine di creazione delle squadre assegnate a ciascun girone. Con quattro squadre: 1–2 e 3–4, poi 1–3 e 2–4, infine 1–4 e 2–3.</p>
  <?php if ($error): ?><p class="aio-error" role="alert"><?= aio_h($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p class="aio-success" role="status"><?= aio_h($success) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field('admin_allinone') ?>
    <label>Competizione <select name="torneo" required><option value="">Seleziona</option>
      <?php foreach ($tournaments as $t): ?><option value="<?= $t['id'] ?>" <?= $selectedId === $t['id'] ? 'selected' : '' ?>><?= aio_h($t['nome']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Data di inizio <input type="date" name="data" value="<?= aio_h($date) ?>" required></label>
    <label>Orario di inizio <input type="time" name="ora" value="<?= aio_h($time) ?>" required></label>
    <fieldset><legend>Campi disponibili</legend>
      <p>Per ogni impianto selezionato indica quanti campi possono ospitare partite contemporaneamente. Esempio: 2 campi = 2 partite allo stesso orario. Le partite mantengono il nome dell’impianto.</p>
      <?php foreach ($fields as $index => $field): ?>
        <div>
          <label><input type="checkbox" name="campi[]" value="<?= aio_h($field) ?>" <?= in_array($field, $selectedFields, true) ? 'checked' : '' ?>><?= aio_h($field) ?></label>
          <label>Numero campi per <?= aio_h($field) ?> <input type="number" name="capacita[<?= $index ?>]" min="1" max="32" step="1" value="<?= aio_h($capacities[$field]) ?>" style="width:90px"></label>
        </div>
      <?php endforeach; ?>
      <?php if (!$fields): ?><p>Aggiungi prima i campi in Gestione Partite → Campi.</p><?php endif; ?>
    </fieldset>
    <button type="submit" name="azione" value="anteprima">Genera anteprima</button>
    <?php if ($rows && !$success): ?>
      <input type="hidden" name="anteprima" value="<?= aio_h(hash('sha256', json_encode($rows))) ?>">
      <button type="submit" name="azione" value="salva">Crea tutte le <?= count($rows) ?> partite</button>
    <?php endif; ?>
  </form>
  <?php if ($rows): ?>
    <h2><?= $success ? 'Calendario creato' : 'Anteprima' ?> · <?= count($rows) ?> partite</h2>
    <div class="aio-scroll"><table><thead><tr><th>Giornata</th><th>Girone</th><th>Partita</th><th>Data</th><th>Ora</th><th>Campo</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= $row['giornata'] ?></td><td><?= aio_h($row['girone']) ?></td><td><?= aio_h($row['casa']) ?> – <?= aio_h($row['ospite']) ?></td><td><?= aio_h($row['data']) ?></td><td><?= aio_h(substr($row['ora'], 0, 5)) ?></td><td><?= aio_h($row['campo']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</main>
</body></html>
