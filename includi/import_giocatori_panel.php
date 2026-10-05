<?php
require_once __DIR__ . '/import_giocatori.php';
function import_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

// Render within a function so import variables cannot overwrite the other admin sections.
function render_import_giocatori_panel(mysqli $conn, string $adminSection): void {
$previousMode = (new mysqli_driver())->report_mode;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$teams = $conn->query("SELECT s.id, s.nome, s.torneo FROM squadre s JOIN tornei t ON REPLACE(REPLACE(t.filetorneo, '.php', ''), '.html', '') = s.torneo WHERE COALESCE(t.stato, '') <> 'terminato' AND t.sezione = '" . $conn->real_escape_string($adminSection) . "' ORDER BY s.torneo, s.nome")->fetch_all(MYSQLI_ASSOC);
$teamId = (int)($_POST['team'] ?? 0);
$torneo = (string)($_POST['torneo'] ?? '');
$team = null;
foreach ($teams as $t) if ((int)$t['id'] === $teamId && $t['torneo'] === $torneo) $team = $t;
$rows = []; $error = ''; $summary = null;
$players = $conn->query('SELECT id, nome, cognome, foto FROM giocatori')->fetch_all(MYSQLI_ASSOC);
$index = import_player_index($players);
$pivot = new SquadraGiocatore($conn);
$model = new Giocatore($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    try {
        if (!csrf_is_valid($_POST['_csrf'] ?? '', 'import_giocatori')) throw new RuntimeException('Sessione scaduta: ricarica la pagina e riprova.');
        if (!$team) throw new RuntimeException('Seleziona un torneo e una squadra validi.');
        if (isset($_POST['text'])) {
            if (strlen($_POST['text']) > 50000) throw new RuntimeException('Il testo è troppo lungo.');
            $rows = import_player_parse($_POST['text']);
        } else {
            foreach ((array)($_POST['rows'] ?? []) as $r) {
                if (!is_array($r) || isset($r['skip'])) continue;
                $rows[] = ['nome' => import_player_clean((string)($r['nome'] ?? '')), 'cognome' => import_player_clean((string)($r['cognome'] ?? '')), 'portiere' => isset($r['portiere']), 'capitano' => isset($r['capitano']), 'selected' => (int)($r['selected'] ?? 0), 'confirm_new' => isset($r['confirm_new'])];
            }
        }
        if (!$rows || count($rows) > 300) throw new RuntimeException('Inserisci da 1 a 300 giocatori.');
        if (isset($_POST['commit'])) {
            $conn->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $conn->begin_transaction();
            // Full-range locking read: recheck after preview and serialize concurrent creation.
            $index = import_player_index($conn->query('SELECT id, nome, cognome, foto FROM giocatori FOR UPDATE')->fetch_all(MYSQLI_ASSOC));
            $check = $conn->prepare('SELECT id FROM squadre WHERE id = ? AND torneo = ? FOR UPDATE');
            $check->bind_param('is', $teamId, $torneo); $check->execute();
            if (!$check->get_result()->fetch_assoc()) throw new RuntimeException('La squadra non è più disponibile.');
            $summary = ['elaborati' => count($rows), 'esistenti' => 0, 'nuovi' => 0, 'presenti' => 0, 'sospesi' => 0];
            $pending = [];
            foreach ($rows as $r) {
                if (!$r['nome'] || !$r['cognome'] || mb_strlen($r['nome']) > 255 || mb_strlen($r['cognome']) > 255) {
                    $r['issue'] = 'Correggi Nome e Cognome (obbligatori, massimo 255 caratteri).'; $pending[] = $r; $summary['sospesi']++; continue;
                }
                $key = import_player_key($r['nome'], $r['cognome']);
                $matches = $index[$key] ?? [];
                $id = 0;
                if (count($matches) > 1) {
                    foreach ($matches as $m) if ((int)$m['id'] === $r['selected']) $id = (int)$m['id'];
                    if (!$id) { $pending[] = $r; $summary['sospesi']++; continue; }
                } elseif ($matches) $id = (int)$matches[0]['id'];
                elseif ($r['selected']) { $r['issue'] = 'La corrispondenza è cambiata: verifica nuovamente.'; $pending[] = $r; $summary['sospesi']++; continue; }
                $new = !$id;
                if ($new) {
                    if (empty($r['confirm_new']) && import_player_suggestions($r['nome'], $r['cognome'], $index)) {
                        $r['issue'] = 'Possibile errore OCR: verifica i nomi simili oppure conferma che è un nuovo giocatore.';
                        $pending[] = $r; $summary['sospesi']++; continue;
                    }
                    $id = $model->crea($r['nome'], $r['cognome'], '', 0, 0, 0, 0, null, '/img/giocatori/unknown.jpg');
                    if (!$id) throw new RuntimeException('Creazione non riuscita.');
                    $index[$key][] = ['id' => $id, 'nome' => $r['nome'], 'cognome' => $r['cognome'], 'foto' => '/img/giocatori/unknown.jpg'];
                }
                if ($pivot->esisteAssociazione($id, $teamId)) { $summary['presenti']++; continue; }
                if (!$pivot->aggiungiDaImport($id, $teamId, $r['portiere'], $r['capitano'])) throw new RuntimeException('Associazione non riuscita.');
                $summary[$new ? 'nuovi' : 'esistenti']++;
            }
            $conn->commit();
            $rows = $pending;
            try {
                if (function_exists('tos_api_cache_delete')) tos_api_cache_delete(tos_api_cache_build_key('get_rosa', ['torneo' => $torneo, 'squadra' => $team['nome']]));
            } catch (Throwable $cacheError) { error_log('Cache import giocatori: ' . $cacheError->getMessage()); }
        }
    } catch (Throwable $e) {
        if (isset($_POST['commit'])) { $conn->rollback(); $summary = null; }
        error_log('Import giocatori: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Import non riuscito. Nessuna modifica salvata: riprova.';
    }
}

?>
<section class="players-import form-import hidden" aria-label="Import Giocatori"><div class="import-content">
<header class="import-header"><p class="import-eyebrow">Gestione rosa</p><h2 class="import-title">Import Giocatori</h2><p class="import-intro">Dalla foto alla squadra: leggi la lista, controlla i nomi e conferma.</p><ol class="import-steps" aria-label="Fasi importazione"><li><span>1</span> Scegli la squadra</li><li><span>2</span> Leggi la foto</li><li><span>3</span> Controlla e importa</li></ol></header>
<?php if ($error): ?><p class="import-alert" role="alert"><?= import_h($error) ?></p><?php endif; ?>
<?php if ($summary): ?><div class="import-alert" role="status"><h2>Import completato</h2><ul>
<li><?= $summary['elaborati'] ?> giocatori elaborati</li><li><?= $summary['esistenti'] ?> giocatori esistenti aggiunti alla squadra</li><li><?= $summary['nuovi'] ?> nuovi giocatori creati</li><li><?= $summary['presenti'] ?> giocatori già presenti nella squadra</li><li><?= $summary['sospesi'] ?> elementi da verificare qui sotto</li></ul></div><?php endif; ?>
<form method="post" action="/api/gestione_giocatori.php" class="admin-form import-form" id="source-form">
<?= csrf_field('import_giocatori') ?><input type="hidden" name="action" value="import">
<div class="import-team-grid"><label>Torneo <select name="torneo" id="torneo" required><option value="">Seleziona torneo</option><?php foreach (array_unique(array_column($teams, 'torneo')) as $slug): ?><option <?= $torneo === $slug ? 'selected' : '' ?> value="<?= import_h($slug) ?>"><?= import_h($slug) ?></option><?php endforeach; ?></select></label>
<label>Squadra <select name="team" id="team" required><option value="">Seleziona squadra</option><?php foreach ($teams as $t): ?><option data-torneo="<?= import_h($t['torneo']) ?>" value="<?= (int)$t['id'] ?>" <?= $teamId === (int)$t['id'] ? 'selected' : '' ?>><?= import_h($t['nome']) ?></option><?php endforeach; ?></select></label>
</div><fieldset id="image-controls"><legend>Carica la lista giocatori</legend>
<label class="import-upload"><span class="import-upload-title">Scegli una foto della lista</span><span class="import-hint">Foto o screenshot &middot; JPEG, PNG, WebP &middot; max 20 MB</span> <input id="image" type="file" accept="image/jpeg,image/png,image/webp"></label>
<img id="image-preview" hidden alt="Foto della lista giocatori"><label class="import-ocr-option"><input id="enhance-image" type="checkbox" checked> Migliora nitidezza e contrasto per la lettura</label><button id="ocr" type="button">Leggi immagine</button><p id="ocr-status" role="status" aria-live="polite"></p>
<label>Lista giocatori<span class="import-hint">Un giocatore per riga: Nome Cognome, poi P/GK e C/K. Puoi correggere il testo o incollarlo manualmente.</span><textarea name="text" id="text" placeholder="Mario Rossi GK C&#10;Luca Bianchi&#10;Antonio De Luca P" rows="8" maxlength="50000" required></textarea></label>
<p class="import-hint">La prima parola viene proposta come Nome, le successive come Cognome: controlla i nomi composti nella preview. La foto viene letta nel browser.</p>
<div class="import-actions"><button type="submit">Mostra anteprima &rarr;</button></div></fieldset></form>
<?php if ($rows && $team): ?>
<h2>Anteprima — <?= import_h($team['nome']) ?> (<?= import_h($torneo) ?>)</h2><p>Correggi i dati, poi premi “Aggiorna anteprima” per ricontrollare le corrispondenze. Le omonimie senza scelta resteranno in sospeso. I giocatori già presenti non vengono modificati.</p>
<form method="post" action="/api/gestione_giocatori.php" class="admin-form import-form"><?= csrf_field('import_giocatori') ?><input type="hidden" name="action" value="import"><input type="hidden" name="team" value="<?= $teamId ?>"><input type="hidden" name="torneo" value="<?= import_h($torneo) ?>">
<div class="table-wrap"><table><thead><tr><th>Nome</th><th>Cognome</th><th>Portiere</th><th>Capitano</th><th>Stato / scelta</th><th>Escludi</th></tr></thead><tbody>
<?php foreach ($rows as $i => $r): $matches = $index[import_player_key($r['nome'], $r['cognome'])] ?? []; $suggestions = !$matches ? import_player_suggestions($r['nome'], $r['cognome'], $index) : []; ?>
<tr><td><input aria-label="Nome riga <?= $i+1 ?>" type="text" name="rows[<?= $i ?>][nome]" value="<?= import_h($r['nome']) ?>" maxlength="255" required></td><td><input aria-label="Cognome riga <?= $i+1 ?>" type="text" name="rows[<?= $i ?>][cognome]" value="<?= import_h($r['cognome']) ?>" maxlength="255" required></td>
<?php foreach (['portiere','capitano'] as $flag): ?><td><input aria-label="<?= ucfirst($flag) ?> riga <?= $i+1 ?>" type="checkbox" name="rows[<?= $i ?>][<?= $flag ?>]" <?= $r[$flag] ? 'checked' : '' ?>></td><?php endforeach; ?>
<td><?php if (isset($r['issue'])): ?><?= import_h($r['issue']) ?><br><?php endif; ?>
<?php if (count($matches) > 1): ?><strong>ATTENZIONE: trovati più giocatori chiamati <?= import_h($r['nome'].' '.$r['cognome']) ?>.</strong><select aria-label="Scegli giocatore riga <?= $i+1 ?>" name="rows[<?= $i ?>][selected]"><option value="0">Omonimia da verificare</option><?php foreach ($matches as $m): $assocs = $pivot->getSquadrePerGiocatore($m['id'])->fetch_all(MYSQLI_ASSOC); ?><option value="<?= (int)$m['id'] ?>" <?= $r['selected'] === (int)$m['id'] ? 'selected' : '' ?>>#<?= (int)$m['id'] ?> <?= import_h($m['nome'].' '.$m['cognome']) ?> — <?= import_h(implode(', ', array_map(static function($a){return $a['nome'].' ('.$a['torneo'].')';}, $assocs))) ?></option><?php endforeach; ?></select>
<?php elseif ($matches): ?><?= $pivot->esisteAssociazione($matches[0]['id'], $teamId) ? 'Già presente nella squadra' : 'Giocatore esistente' ?><?php elseif ($suggestions): ?><div class="import-suggestions"><strong>Possibile errore di lettura. Intendevi:</strong><?php foreach ($suggestions as $suggestion): ?><button type="button" class="import-suggestion" data-nome="<?= import_h($suggestion['nome']) ?>" data-cognome="<?= import_h($suggestion['cognome']) ?>"><?= import_h($suggestion['nome'].' '.$suggestion['cognome']) ?></button><?php endforeach; ?><label><input type="checkbox" name="rows[<?= $i ?>][confirm_new]" <?= !empty($r['confirm_new']) ? 'checked' : '' ?>> Confermo che è un nuovo giocatore</label></div><?php else: ?>Nuovo giocatore<?php endif; ?></td>
<td><input aria-label="Escludi riga <?= $i+1 ?>" type="checkbox" name="rows[<?= $i ?>][skip]"></td></tr><?php endforeach; ?>
</tbody></table></div><div class="import-actions"><button class="import-secondary" type="submit">Aggiorna anteprima</button><button type="submit" name="commit" value="1">Conferma importazione &rarr;</button></div></form><?php endif; ?>
</div></section>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js"></script>
<script src="/api/import_giocatori.js?v=4"></script>
<?php
mysqli_report($previousMode);
}