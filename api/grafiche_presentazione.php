<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/presentation_templates.php';
$embedded = ($_GET['embed'] ?? '') === '1';
$tournaments = [];
$error = '';
try {
    $assignments = graphics_template_read(__DIR__ . '/../includi/presentation_template_assignments.json') ?? [];
    $result = $conn->query('SELECT id, nome, img, filetorneo, config FROM tornei ORDER BY id DESC');
    if (!$result) throw new RuntimeException('Impossibile caricare i campionati.');
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $template = presentation_template_for($row, tos_runtime_path('graphics-templates'), __DIR__ . '/../cache/graphics-templates', $assignments);
        $tournaments[(int)$row['id']] = ['id' => (int)$row['id'], 'nome' => $row['nome'], 'img' => $row['img'], 'template' => $template, 'squadre' => []];
    }
    $result = $conn->query("SELECT id, nome, torneo, logo FROM squadre WHERE TRIM(torneo) <> '' ORDER BY nome");
    if (!$result) throw new RuntimeException('Impossibile caricare le squadre.');
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $team) {
        $id = presentation_team_tournament_id($team['torneo'], $rows);
        if ($id !== null) $tournaments[$id]['squadre'][] = ['id' => (int)$team['id'], 'nome' => $team['nome'], 'logo' => $team['logo']];
    }
    $tournaments = array_values($tournaments);
    usort($tournaments, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));
} catch (Throwable $e) {
    $tournaments = [];
    $error = 'Impossibile preparare le presentazioni. Verifica il database e i permessi della cartella delle basi, poi ricarica.';
    error_log('graphics presentation: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Presentazione Squadra</title>
<link rel="stylesheet" href="/style.min.css?v=20251126">
<link rel="stylesheet" href="/api/grafiche_presentazione.css?v=20261009">
</head><body class="<?= $embedded ? 'is-embedded' : 'with-site-header' ?>">
<?php if (!$embedded) include __DIR__ . '/../includi/header.php'; ?>
<main>
<?php if (!$embedded): ?><a href="/api/generatore_grafiche.php">Torna al generatore grafiche</a><?php endif; ?>
<h1>Presentazione Squadra</h1>
<p class="intro">La tua squadra, l’identità del campionato. Post Instagram 4:5 · 1080 × 1350 px.</p>
<div class="workspace">
<section class="controls" aria-label="Selezione dati e modifica foto">
<div class="selection">
<h2>Selezione dati</h2>
<label>Campionato<select id="tournament"><option value="">Seleziona un campionato</option></select></label>
<label>Squadra<select id="team" disabled><option value="">Seleziona una squadra</option></select></label>
<label>Fotografia della squadra<input id="photo" type="file" accept="image/jpeg,image/png,image/webp" disabled></label>
<p class="hint">JPG, PNG o WebP · massimo 25 MB. La foto resta sul tuo dispositivo.</p>
</div><div class="editor">
<fieldset id="photoControls" disabled>
<legend>Editor fotografia</legend>
<label>Zoom <output id="zoomValue">100%</output><input id="zoom" type="range" min="50" max="400" value="100" step="1"></label>
<label>Posizione orizzontale<input id="positionX" type="range" min="-100" max="100" value="0"></label>
<label>Posizione verticale<input id="positionY" type="range" min="-100" max="100" value="0"></label>
<div class="actions"><button id="center" type="button">Centra foto</button><button id="fit" type="button">Mostra foto intera</button><button id="fill" type="button">Riempi spazio</button><button id="reset" type="button">Ripristina</button></div>
<label class="check"><input id="edit" type="checkbox" checked> Trascina e usa due dita per lo zoom</label>
</fieldset>
<p class="hint">La foto parte intera, senza tagli. Puoi ingrandirla e scegliere il ritaglio: loghi e testi restano fissi. Disattiva la modifica per scorrere sull’anteprima.</p>
<button id="download" class="primary" type="button" disabled>Scarica grafica PNG</button>
<p id="status" role="status" aria-live="polite"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
</div>
</section>
<section class="preview" aria-label="Anteprima della grafica">
<div class="preview-head"><h2>Anteprima</h2><span>1080 × 1350</span></div>
<canvas id="presentationCanvas" width="1080" height="1350" aria-label="Presentazione squadra; modifica la foto con i controlli o trascinandola"></canvas>
<p class="hint" id="templateInfo">Seleziona il campionato e la squadra per iniziare.</p>
</section>
</div></main>
<script src="/api/matchday-renderer.js?v=20261007-standings"></script>
<script src="/api/presentazione-renderer.js?v=20261009"></script>
<script src="/api/grafiche_downloads.js?v=20261007"></script>
<script src="/api/grafiche_presentazione.js?v=20261009"></script>
<script src="/api/grafiche_frame_height.js?v=20261006"></script>
<script>PresentationPage.init(<?= json_encode($tournaments, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);</script>
</body></html>
