<?php if (!isset($alboAutoTournaments,$alboAutoPlan)) { http_response_code(404); exit; } ?>
<div class="panel-card" id="panel-auto">
  <h3>Premi automatici e chiusura torneo</h3>
  <p>Prepara il riepilogo dai risultati, scegli i riconoscimenti individuali e conferma la pubblicazione. I premi inseriti a mano vengono conservati.</p>
  <form method="POST" class="admin-form">
    <?= csrf_field('admin_albo') ?>
    <input type="hidden" name="azione" value="auto_prepare">
    <label for="autoTournament">Torneo</label>
    <select id="autoTournament" name="auto_torneo" required>
      <option value="">Seleziona torneo</option>
      <?php foreach ($alboAutoTournaments as $t): ?>
        <option value="<?= (int)$t['id'] ?>" <?= (int)$alboAutoId === (int)$t['id'] ? 'selected' : '' ?>><?= h($t['nome'] . ' · ' . substr($t['data_fine'],0,4) . ' · ' . $t['stato']) ?></option>
      <?php endforeach; ?>
    </select>
    <label><input type="checkbox" name="auto_close" value="1" <?= $alboAutoClose ? 'checked' : '' ?>> Chiudi il torneo dopo la conferma dei premi</label>
    <button type="submit" class="btn-primary">Prepara premi</button>
  </form>
  <?php if ($alboAutoPlan): ?>
    <h3><?= h($alboAutoPlan['tournament']['nome']) ?>: riepilogo</h3>
    <?php foreach (array_unique(array_merge($alboAutoPlan['issues'],$alboAutoPlan['blocking'])) as $issue): ?>
      <p class="admin-alert"><?= h($issue) ?></p>
    <?php endforeach; ?>
    <form method="POST" onsubmit="return confirm('Pubblicare i premi selezionati<?= $alboAutoClose ? ' e chiudere il torneo' : '' ?>?');">
      <?= csrf_field('admin_albo') ?>
      <input type="hidden" name="azione" value="auto_publish">
      <input type="hidden" name="auto_torneo" value="<?= (int)$alboAutoId ?>">
      <input type="hidden" name="auto_close" value="<?= $alboAutoClose ? '1' : '0' ?>">
      <input type="hidden" name="auto_revision" value="<?= h($alboAutoPlan['revision']) ?>">
      <label for="autoAwards">Premi da pubblicare o aggiornare</label>
      <select id="autoAwards" name="auto_keys[]" multiple size="7">
        <?php foreach ($alboAutoPlan['awards'] as $award): ?>
          <option value="<?= h($award['source_key']) ?>" <?= $award['status'] === 'nuovo' ? 'selected' : '' ?> <?= $award['status'] === 'manuale' ? 'disabled' : '' ?>><?= h($award['premio'] . ' — ' . $award['vincitrice'] . ($award['gol'] !== null ? ' · ' . $award['gol'] . ' gol' : '') . ' · ' . $award['status']) ?></option>
        <?php endforeach; ?>
      </select>
      <p>I premi invariati non richiedono una nuova pubblicazione. Se i risultati cambiano, seleziona esplicitamente i premi da aggiornare.</p>
      <div class="form-grid">
        <?php foreach (['MVP'=>'Miglior giocatore','KEEPER'=>'Miglior portiere','DEFENDER'=>'Miglior difensore','ATTACKER'=>'Miglior attaccante'] as $kind=>$label): ?>
          <label><?= h($label) ?>
            <select name="auto_individuals[<?= h($kind) ?>]" <?= in_array($kind,$alboAutoPlan['manual_kinds'],true) ? 'disabled' : '' ?>>
              <option value="0">Non assegnare o mantieni il premio attuale</option>
              <?php $seen=[]; foreach ($alboAutoPlan['players'] as $p): if (isset($seen[(int)$p['id']])) continue; $seen[(int)$p['id']]=true; ?>
                <option value="<?= (int)$p['id'] ?>"><?= h(trim($p['cognome'] . ' ' . $p['nome']) . ' · ' . $p['squadra'] . (!empty($p['media_voti']) ? ' · media ' . number_format((float)$p['media_voti'],2,',','') : '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn-primary" <?= $alboAutoClose && $alboAutoPlan['blocking'] ? 'disabled' : '' ?>><?= $alboAutoClose ? 'Chiudi torneo e pubblica premi' : 'Pubblica premi selezionati' ?></button>
    </form>
  <?php endif; ?>
  <details>
    <summary>Recupero storico e casi da verificare</summary>
    <p>Lo storico dei tornei terminati è integrato nell’albo di sito e app. Le voci manuali hanno precedenza; i premi automatici non vengono duplicati.</p>
    <?php foreach (albo_backfill()['report'] ?? [] as $report): ?>
      <?php $notes=array_unique(array_merge($report['issues'],$report['blocking'])); if (!$notes) continue; ?>
      <p><?= h($report['competizione'] . ': ' . implode(' ',$notes)) ?></p>
    <?php endforeach; ?>
  </details>
</div>
