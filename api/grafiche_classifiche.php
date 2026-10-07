<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
require_once __DIR__ . '/../includi/db.php';
$embedded = isset($_GET['embed']) && $_GET['embed'] === '1';
$tournaments = [];
$loadError = false;
try {
    // Keep the exact squadre.torneo code expected by leggiClassifica.php.
    $result = $conn->query("SELECT codes.torneo AS codice, COALESCE(t.nome, codes.torneo) AS nome
        FROM (SELECT DISTINCT torneo FROM squadre WHERE TRIM(torneo) <> '') codes
        LEFT JOIN tornei t ON t.id = (SELECT tx.id FROM tornei tx
            WHERE tx.nome = codes.torneo OR tx.filetorneo = codes.torneo
               OR REPLACE(REPLACE(tx.filetorneo, '.php', ''), '.html', '') = REPLACE(REPLACE(codes.torneo, '.php', ''), '.html', '')
            ORDER BY tx.id DESC LIMIT 1)
        ORDER BY nome, codice");
    if (!$result) throw new RuntimeException('Impossibile recuperare i tornei');
    $tournaments = $result->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $error) {
    $loadError = true;
    error_log('graphics standings: ' . $error->getMessage());
}
?>
<!doctype html>
<html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Generatore classifiche</title><link rel="stylesheet" href="/style.min.css?v=20251126">
<style>
:root{color-scheme:dark;font-family:Arial,sans-serif}*{box-sizing:border-box}html,body{height:auto}body{display:block;margin:0;background:#08111f;color:#fff}body.is-embedded{display:flow-root;min-height:0}body.with-site-header>main{margin-top:110px}main{width:min(1180px,calc(100% - 32px));margin:32px auto 60px}a{color:#8fc7ff}h1{margin-bottom:8px}.toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin:24px 0;padding:18px;background:#111e31;border-radius:14px}label{display:grid;gap:6px;font-weight:700}select,button{border:1px solid #ffffff24;border-radius:9px;padding:11px 14px;font:inherit}select{background:#081522;color:#fff;max-width:100%}button{cursor:pointer;background:#f2c94c;color:#101722;font-weight:800}button:disabled{opacity:.55;cursor:wait}.status{min-height:24px;color:#bfd0e5}.grid{display:grid;gap:28px}.card{padding:18px;background:#111e31;border-radius:16px;min-width:0}.card-head{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:14px}canvas{display:block;width:min(100%,540px);height:auto;margin:auto;background:#0d1b2d;box-shadow:0 10px 35px #0008;touch-action:pan-y pinch-zoom}@media(max-width:650px){.toolbar>*{width:100%}.card-head{align-items:start;flex-direction:column}.card-head button{width:100%}}
</style></head>
<body class="<?= $embedded ? 'is-embedded' : 'with-site-header' ?>">
<?php if (!$embedded): ?><?php include __DIR__ . '/../includi/header.php'; ?><?php endif; ?>
<main>
<?php if (!$embedded): ?><a href="/api/generatore_grafiche.php">Torna al generatore grafiche</a><?php endif; ?>
<h1>Classifiche</h1><p>Genera le classifiche aggiornate dei tornei con stemmi e colori della competizione. Ogni girone ha la propria grafica.</p>
<div class="toolbar">
<label>Torneo<select id="tournament"><option value="">Tutti i tornei</option>
<?php foreach ($tournaments as $tournament): ?><option value="<?= htmlspecialchars($tournament['codice'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tournament['nome'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
</select></label>
<label>Formato<select id="format"><option value="story">Storia Instagram · 1080 × 1920</option><option value="post">Post Instagram · 1080 × 1350</option></select></label>
<button id="generate" type="button">Genera grafiche</button><button id="downloadAll" type="button" hidden>Scarica tutte</button>
</div><div class="status" id="status" role="status" aria-live="polite"><?= $loadError ? 'Impossibile recuperare i tornei. Ricarica la pagina.' : '' ?></div><div class="grid" id="grid"></div>
</main>
<?php if (!$embedded): ?><div id="footer-container"></div><?php endif; ?>
<script src="/api/matchday-renderer.js?v=20261007-standings"></script>
<script src="/api/classifiche-renderer.js?v=20261007"></script>
<script src="/api/grafiche_downloads.js?v=20261007"></script>
<script src="/api/grafiche_frame_height.js?v=20261006"></script>
<script src="/api/grafiche_classifiche.js?v=20261007"></script>
<script>window.StandingsPage.init(<?= json_encode($tournaments, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);</script>
<?php if (!$embedded): ?><script>fetch('/includi/footer.html').then(response=>response.text()).then(html=>{document.getElementById('footer-container').innerHTML=html;}).catch(()=>{});</script><?php endif; ?>
</body></html>
