<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header('Location: /index.php');
    exit;
}
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/crud/Squadra.php';
require_once __DIR__ . '/crud/torneo.php';

$squadra = new Squadra();
$torneoModel = new Torneo();

// flash messages
$errore = $_SESSION['flash_error'] ?? '';
$successo = $_SESSION['flash_success'] ?? '';
$defaultTab = $_SESSION['flash_tab'] ?? 'crea';
unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_tab']);

function sanitizeTorneoSlugValue($value) {
    $value = preg_replace('/\.html$/i', '', $value);
    $value = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    return $value;
}

function salvaScudetto($nomeSquadra, $torneoSlug, $fieldName) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $maxSize = 2 * 1024 * 1024;
    if ($_FILES[$fieldName]['size'] > $maxSize) {
        return null;
    }

    $allowed = [
        'image/jpeg'   => 'jpg',
        'image/png'    => 'png',
        'image/webp'   => 'webp',
        'image/gif'    => 'gif',
        'image/svg+xml'=> 'svg',
        'image/svg'    => 'svg'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES[$fieldName]['tmp_name']);
    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'] ?? '', PATHINFO_EXTENSION));

    if (isset($allowed[$mime])) {
        $extension = $allowed[$mime];
    } elseif ($ext === 'svg') {
        $extension = 'svg'; // alcune configurazioni leggono gli svg come text/plain
    } else {
        return null;
    }

    $baseDir = realpath(__DIR__ . '/../img/scudetti');
    if (!$baseDir) {
        return null;
    }

    $slugSquadra = strtolower(preg_replace('/[^a-z0-9]/i', '', $nomeSquadra));
    if ($slugSquadra === '') {
        $slugSquadra = 'squadra';
    }
    $slugTorneo = strtolower(preg_replace('/[^a-z0-9]/i', '', $torneoSlug));
    if ($slugTorneo === '') {
        $slugTorneo = 'torneo';
    }

    $filename = "{$slugSquadra}-{$slugTorneo}.{$extension}";
    $counter = 2;
    while (file_exists($baseDir . '/' . $filename)) {
        $filename = "{$slugSquadra}-{$slugTorneo}_{$counter}.{$extension}";
        $counter++;
    }

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $baseDir . '/' . $filename)) {
        return null;
    }

    return '/img/scudetti/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'crea') {
        $nome = trim($_POST['nome'] ?? '');
        $torneo = sanitizeTorneoSlugValue(trim($_POST['torneo'] ?? ''));
        if ($nome === '' || $torneo === '') {
            $errore = 'Compila tutti i campi obbligatori.';
        } else {
            $logo = salvaScudetto($nome, $torneo, 'scudetto');
            try {
                $ok = $squadra->crea($nome, $torneo, $logo);
                if ($ok) {
                    $_SESSION['flash_error'] = '';
                    $_SESSION['flash_success'] = 'Squadra creata correttamente.';
                    header('Location: gestione_squadre.php');
                    exit;
                } else {
                    $errore = 'Creazione non riuscita.';
                }
            } catch (Throwable $e) {
                // 1062 = duplicate entry
                if (method_exists($e, 'getCode') && (int)$e->getCode() === 1062) {
                    $errore = 'Squadra già iscritta al torneo.';
                } else {
                    $errore = 'Errore nella creazione: ' . $e->getMessage();
                }
            }
        }
    }

    if ($azione === 'modifica') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $torneo = sanitizeTorneoSlugValue(trim($_POST['torneo'] ?? ''));
        $punti = (int)($_POST['punti'] ?? 0);
        $giocate = (int)($_POST['giocate'] ?? 0);
        $vinte = (int)($_POST['vinte'] ?? 0);
        $pareggiate = (int)($_POST['pareggiate'] ?? 0);
        $perse = (int)($_POST['perse'] ?? 0);
        $gol_fatti = (int)($_POST['gol_fatti'] ?? 0);
        $gol_subiti = (int)($_POST['gol_subiti'] ?? 0);
        $diff = $gol_fatti - $gol_subiti;
        if ($id <= 0 || $nome === '' || $torneo === '') {
            $errore = 'Seleziona una squadra e compila i campi obbligatori.';
        } else {
            $logo = salvaScudetto($nome, $torneo, 'scudetto_mod');
            try {
                $ok = $squadra->aggiorna($id, $nome, $torneo, $punti, $giocate, $vinte, $pareggiate, $perse, $gol_fatti, $gol_subiti, $diff, $logo);
                if ($ok) {
                    $_SESSION['flash_error'] = '';
                    $_SESSION['flash_success'] = 'Squadra aggiornata correttamente.';
                    $_SESSION['flash_tab'] = 'modifica';
                    header('Location: gestione_squadre.php');
                    exit;
                } else {
                    $errore = 'Aggiornamento non riuscito.';
                }
            } catch (Throwable $e) {
                if (method_exists($e, 'getCode') && (int)$e->getCode() === 1062) {
                    $errore = 'Squadra già iscritta al torneo.';
                } else {
                    $errore = 'Errore nell\'aggiornamento: ' . $e->getMessage();
                }
            }
        }
    }

    if ($azione === 'elimina') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $squadra->elimina($id);
            $_SESSION['flash_error'] = '';
            $_SESSION['flash_success'] = 'Squadra eliminata.';
            header('Location: gestione_squadre.php');
            exit;
        } else {
            $errore = 'Seleziona una squadra valida da eliminare.';
        }
    }
}

// dati per le liste (convertiti in array per evitare false/null)
$torneiList = [];
if ($resTornei = $torneoModel->getAll()) {
    while ($r = $resTornei->fetch_assoc()) {
        $torneiList[] = $r;
    }
}

$torneiFiltro = [];
$resFiltro = $squadra->getTornei();
if (is_array($resFiltro)) {
    $torneiFiltro = $resFiltro;
} elseif ($resFiltro) {
    while ($r = $resFiltro->fetch_assoc()) {
        $torneiFiltro[] = $r;
    }
}

$squadreList = [];
if ($resSquadre = $squadra->getAll()) {
    while ($r = $resSquadre->fetch_assoc()) {
        $squadreList[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Gestione Squadre</title>
  <link rel="stylesheet" href="/style.css">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; background: linear-gradient(180deg, #f6f8fb 0%, #eef3f9 100%); }
    main.admin-wrapper { flex: 1 0 auto; }
    .panel-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 18px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .panel-card h2 { margin-top: 0; color: #15293e; font-size: 1.05rem; }
    .tab-buttons { display: flex; gap: 12px; margin: 10px 0 20px; flex-wrap: wrap; }
    .tab-buttons button { padding: 12px 16px; border: 1px solid #cbd5e1; background: #ecf1f7; cursor: pointer; border-radius: 10px; font-weight: 600; color: #1c2a3a; box-shadow: 0 2px 6px rgba(0,0,0,0.04); transition: all .2s; }
    .tab-buttons button:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
    .tab-buttons button.active { background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border-color: #15293e; box-shadow: 0 8px 20px rgba(21,41,62,0.25); }
    @media (max-width: 640px) {
      .tab-buttons { justify-content: center; }
    }
    .hidden { display: none !important; }
    .file-upload { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 10px 14px; border: 1px dashed #d4d9e2; border-radius: 10px; background: #f7f9fc; }
    .file-upload input[type="file"] { display: none; }
    .file-upload .file-btn { background: #15293e; color: #fff; padding: 8px 18px; border-radius: 999px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: transform 0.2s ease, background 0.2s ease; text-transform: uppercase; letter-spacing: 0.04em; border: none; }
    .file-upload .file-btn:hover { background: #0e1d2e; transform: translateY(-1px); }
    .file-upload .file-name { font-size: 0.9rem; color: #5f6b7b; }
    .admin-alert { border-radius: 10px; padding: 12px 18px; font-weight: 600; margin-bottom: 18px; }
    .admin-alert.success { background: #e8f6ef; color: #065f46; border: 1px solid #34d399; }
    .admin-alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
    .search-input { padding: 8px 10px; border-radius: 10px; border: 1px solid #d5dbe4; min-width: 220px; }
    .admin-table-squadre { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .admin-table-squadre th, .admin-table-squadre td { padding: 12px; border-bottom: 1px solid #eef2ff; text-align: left; }
    .admin-table-squadre th { background: #f8fafc; font-size: 0.9rem; color: #15293e; cursor: pointer; }
    .admin-table-squadre th:last-child,
    .admin-table-squadre td:last-child { width: 110px; min-width: 110px; text-align: center; }
    .admin-table-squadre .btn-danger {
      white-space: nowrap;
      padding: 6px 10px;
      font-size: 0.88rem;
      display: inline-block;
    }
    @media (max-width: 640px) {
      .admin-table-squadre .btn-danger {
        padding: 6px 8px;
        font-size: 0.82rem;
      }
    }

    /* Modal elimina */
    .confirm-modal {
      position: fixed; inset: 0; display: none; align-items: center; justify-content: center;
      background: rgba(0,0,0,0.45); backdrop-filter: blur(2px); z-index: 9999;
    }
    .confirm-modal.active { display: flex; }
    .confirm-card {
      background: #fff; border-radius: 14px; padding: 22px; width: min(420px, 90vw);
      box-shadow: 0 18px 34px rgba(0,0,0,0.15); border: 1px solid #e5eaf0;
    }
    .confirm-card h4 { margin: 0 0 8px; color: #15293e; }
    .confirm-card p { margin: 0 0 16px; color: #345; }
    .confirm-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    .confirm-actions button { flex: 1 1 0; min-width: 140px; text-align: center; }
    .btn-ghost {
      border: 1px solid #d5dbe4; background: #f8f9fc; color: #1c2a3a;
      border-radius: 12px; padding: 12px 16px; font-weight: 800; letter-spacing: 0.2px;
      cursor: pointer; transition: transform .15s, box-shadow .15s;
    }
    .btn-ghost:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(0,0,0,0.08); }
    .modern-danger {
      background: linear-gradient(135deg, #d72638, #b1172a); border: none; color: #fff;
      padding: 12px 16px; border-radius: 12px; font-weight: 800; letter-spacing: 0.2px;
      cursor: pointer; box-shadow: 0 12px 26px rgba(183,23,42,0.32); transition: transform .15s, box-shadow .15s;
    }
    .modern-danger:hover { transform: translateY(-1px); box-shadow: 0 16px 30px rgba(183,23,42,0.38); }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../includi/header.php'; ?>
  <main class="admin-wrapper">
    <section class="admin-container">
      <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
      <h1 class="admin-title">Gestione Squadre</h1>
      <p>Usa i tab per creare, modificare o eliminare le squadre. Stile e interazione sono gli stessi della gestione blog.</p>

      <div class="tab-buttons">
        <button type="button" data-tab="crea" class="active">Crea</button>
        <button type="button" data-tab="modifica">Modifica</button>
        <button type="button" data-tab="elimina">Elimina</button>
      </div>

      <?php if ($successo): ?>
        <div class="admin-alert success"><?= htmlspecialchars($successo) ?></div>
      <?php endif; ?>
      <?php if ($errore): ?>
        <div class="admin-alert error"><?= htmlspecialchars($errore) ?></div>
      <?php endif; ?>

      <!-- CREA -->
      <div class="panel-card" data-section="crea">
        <form method="POST" class="admin-form" enctype="multipart/form-data">
          <input type="hidden" name="azione" value="crea">
          <div class="form-group"><label>Nome</label><input type="text" name="nome" required></div>
          <div class="form-group">
            <label>Torneo</label>
            <select name="torneo" required>
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($torneiList as $row): ?>
                <?php $slugValue = sanitizeTorneoSlugValue($row['filetorneo'] ?? $row['nome']); ?>
                <option value="<?= htmlspecialchars($slugValue) ?>"><?= htmlspecialchars($row['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Immagine / Scudetto</label>
            <div class="file-upload">
              <input type="file" name="scudetto" id="scudettoUpload" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg">
              <button type="button" class="file-btn" data-target="scudettoUpload">Scegli immagine</button>
              <span class="file-name" id="scudettoUploadName">Nessun file selezionato</span>
            </div>
            <small>PNG, JPG, WEBP o GIF - max 2MB.</small>
          </div>
          <button type="submit" class="btn-primary">Crea Squadra</button>
        </form>
      </div>

      <!-- MODIFICA -->
      <div class="panel-card hidden" data-section="modifica">
        <form method="POST" class="admin-form" enctype="multipart/form-data" id="formModifica">
          <input type="hidden" name="azione" value="modifica">
          <input type="hidden" name="id" id="mod_id">
          <div class="form-group">
            <label>Seleziona torneo</label>
            <select id="selectTorneoFiltro" required>
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($torneiFiltro as $row): ?>
                <?php $val = $row['id'] ?? ($row['torneo'] ?? ($row['nome'] ?? '')); ?>
                <?php $label = $row['nome'] ?? $row['torneo'] ?? $val; ?>
                <?php if ($val !== ''): ?>
                  <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Seleziona squadra</label>
            <select id="selectSquadraMod" name="id_select" required disabled>
              <option value="">-- Seleziona una squadra --</option>
            </select>
          </div>
          <div class="form-group"><label>Nome</label><input type="text" name="nome" id="mod_nome"></div>
          <div class="form-group"><label>Torneo</label><input type="text" name="torneo" id="mod_torneo" readonly></div>
          <div class="form-group">
            <label>Nuovo scudetto</label>
            <div class="file-upload">
              <input type="file" name="scudetto_mod" id="scudettoUploadMod" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg">
              <button type="button" class="file-btn" data-target="scudettoUploadMod">Scegli immagine</button>
              <span class="file-name" id="scudettoUploadModName">Nessun file selezionato</span>
            </div>
            <small>Lascia vuoto per mantenere l'immagine attuale.</small>
          </div>
          <div class="form-row">
            <div class="form-group half"><label>Punti</label><input type="number" name="punti" id="mod_punti"></div>
            <div class="form-group half"><label>Giocate</label><input type="number" name="giocate" id="mod_giocate"></div>
          </div>
          <div class="form-row">
            <div class="form-group half"><label>Vinte</label><input type="number" name="vinte" id="mod_vinte"></div>
            <div class="form-group half"><label>Pareggiate</label><input type="number" name="pareggiate" id="mod_pareggiate"></div>
          </div>
          <div class="form-row">
            <div class="form-group half"><label>Perse</label><input type="number" name="perse" id="mod_perse"></div>
            <div class="form-group half"><label>Gol Fatti</label><input type="number" name="gol_fatti" id="mod_fatti"></div>
          </div>
          <div class="form-group"><label>Gol Subiti</label><input type="number" name="gol_subiti" id="mod_subiti"></div>
          <button type="submit" class="btn-primary">Salva modifiche</button>
        </form>
      </div>

      <!-- ELIMINA -->
      <div class="panel-card hidden" data-section="elimina">
        <?php if (!empty($torneiList)): ?>
          <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:10px;">
            <select id="selectTorneoElimina" class="search-input" style="min-width:220px;">
              <option value="">-- Seleziona un torneo --</option>
              <?php foreach ($torneiList as $row): ?>
                <?php $slugValue = sanitizeTorneoSlugValue($row['filetorneo'] ?? $row['nome']); ?>
                <option value="<?= htmlspecialchars($slugValue) ?>"><?= htmlspecialchars($row['nome']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="searchSquadra" placeholder="Cerca squadra..." class="search-input" style="flex:1 1 180px;">
          </div>
          <table class="admin-table-squadre" id="tabellaSquadre">
            <thead><tr><th data-col="nome">Nome</th><th data-col="torneo">Torneo</th><th>Azioni</th></tr></thead>
            <tbody>
              <tr><td colspan="3">Seleziona un torneo per vedere le squadre.</td></tr>
            </tbody>
          </table>
        <?php else: ?>
          <p>Nessun torneo disponibile.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <div id="footer-container"></div>
  <!-- Modal conferma eliminazione -->
  <div class="confirm-modal" id="confirmDeleteModal">
    <div class="confirm-card">
      <h4>Conferma eliminazione</h4>
      <p id="confirmDeleteText">Vuoi eliminare questa squadra?</p>
      <div class="confirm-actions">
        <button type="button" class="btn-ghost" id="btnCancelDel">Annulla</button>
        <button type="button" class="modern-danger" id="btnConfirmDel">Elimina</button>
      </div>
    </div>
  </div>

  <script>
    (function() {
      function ready(fn) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
      function qs(sel) { return document.querySelector(sel); }
      function qsa(sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); }

      function initTabs() {
        var tabs = qsa('.tab-buttons button');
        var sections = qsa('[data-section]');
        var alerts = function() { return qsa('.admin-alert'); };

        function clearAlerts() {
          alerts().forEach(function(a) { if (a && a.parentNode) a.parentNode.removeChild(a); });
        }

        function show(name, purge) {
          if (purge) clearAlerts();
          sections.forEach(function(sec) { sec.classList.toggle('hidden', sec.getAttribute('data-section') !== name); });
          tabs.forEach(function(btn) { btn.classList.toggle('active', btn.getAttribute('data-tab') === name); });
        }
        tabs.forEach(function(btn) { btn.addEventListener('click', function() { show(btn.getAttribute('data-tab'), true); }); });
        var defaultTab = '<?php echo addslashes($defaultTab ?? 'crea'); ?>';
        show(defaultTab || 'crea', false);
      }

      function initFileButtons() {
        qsa('.file-btn').forEach(function(btn) {
          var targetId = btn.getAttribute('data-target');
          var input = targetId ? document.getElementById(targetId) : null;
          var label = null;
          if (input) {
            label = qs('#' + targetId + 'Name');
            if (!label) {
              var wrap = input.closest('.file-upload');
              if (wrap) {
                label = wrap.querySelector('.file-name');
              }
            }
            btn.addEventListener('click', function(e) { e.preventDefault(); input.click(); });
            input.addEventListener('change', function() {
              var file = input.files && input.files[0];
              if (label) { label.textContent = file ? file.name : 'Nessun file selezionato'; }
            });
          }
        });
      }

      function initFiltroElenco() {
        var table = document.getElementById('tabellaSquadre');
        if (!table) return;
        var headers = table.querySelectorAll('th[data-col]');
        var search = document.getElementById('searchSquadra');
        var sortDirection = {};
        headers.forEach(function(header) {
          header.addEventListener('click', function() {
            var colIndex = Array.prototype.indexOf.call(header.parentNode.children, header);
            var tbody = table.querySelector('tbody');
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            var col = header.getAttribute('data-col');
            sortDirection[col] = sortDirection[col] === 'asc' ? 'desc' : 'asc';
            rows.sort(function(a, b) {
              var valA = a.children[colIndex].textContent.trim().toLowerCase();
              var valB = b.children[colIndex].textContent.trim().toLowerCase();
              return sortDirection[col] === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
            });
            tbody.innerHTML = '';
            rows.forEach(function(r) { tbody.appendChild(r); });
          });
        });
        if (search) {
          search.addEventListener('input', function() {
            var filtro = search.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function(tr) {
              var testo = tr.textContent.toLowerCase();
              tr.style.display = testo.indexOf(filtro) !== -1 ? '' : 'none';
            });
          });
        }
      }

      function initModificaLoader() {
        var selectTorneoFiltro = document.getElementById('selectTorneoFiltro');
        var selectSquadraMod = document.getElementById('selectSquadraMod');
        var hiddenId = document.getElementById('mod_id');
        var campi = {
          nome: document.getElementById('mod_nome'),
          torneo: document.getElementById('mod_torneo'),
          punti: document.getElementById('mod_punti'),
          giocate: document.getElementById('mod_giocate'),
          vinte: document.getElementById('mod_vinte'),
          pareggiate: document.getElementById('mod_pareggiate'),
          perse: document.getElementById('mod_perse'),
          fatti: document.getElementById('mod_fatti'),
          subiti: document.getElementById('mod_subiti')
        };
        if (!selectTorneoFiltro || !selectSquadraMod) return;

        selectTorneoFiltro.addEventListener('change', function() {
          var torneo = selectTorneoFiltro.value;
          selectSquadraMod.innerHTML = '<option value="">-- Seleziona una squadra --</option>';
          hiddenId.value = '';
          Object.keys(campi).forEach(function(k) { if (campi[k]) campi[k].value = ''; });
          if (!torneo) { selectSquadraMod.disabled = true; return; }
          fetch('/api/get_squadre_torneo.php?torneo=' + encodeURIComponent(torneo))
            .then(function(res) { return res.json(); })
            .then(function(data) {
              if (data && data.length) {
                data.forEach(function(s) { selectSquadraMod.innerHTML += '<option value="' + s.id + '">' + s.nome + '</option>'; });
                selectSquadraMod.disabled = false;
              } else {
                selectSquadraMod.disabled = true;
                alert('Nessuna squadra trovata per questo torneo.');
              }
            })
            .catch(function(err) { console.error('Errore nel caricamento squadre:', err); });
        });

        selectSquadraMod.addEventListener('change', function(e) {
          var id = e.target.value;
          hiddenId.value = id;
          if (!id) { Object.keys(campi).forEach(function(k) { if (campi[k]) campi[k].value = ''; }); return; }
          fetch('/api/get_squadra.php?id=' + encodeURIComponent(id))
            .then(function(res) { return res.json(); })
            .then(function(data) {
              if (data && !data.error) {
                if (campi.nome) campi.nome.value = data.nome || '';
                if (campi.torneo) campi.torneo.value = data.torneo || '';
                if (campi.punti) campi.punti.value = data.punti != null ? data.punti : '';
                if (campi.giocate) campi.giocate.value = data.giocate != null ? data.giocate : '';
                if (campi.vinte) campi.vinte.value = data.vinte != null ? data.vinte : '';
                if (campi.pareggiate) campi.pareggiate.value = data.pareggiate != null ? data.pareggiate : '';
                if (campi.perse) campi.perse.value = data.perse != null ? data.perse : '';
                if (campi.fatti) campi.fatti.value = data.gol_fatti != null ? data.gol_fatti : '';
                if (campi.subiti) campi.subiti.value = data.gol_subiti != null ? data.gol_subiti : '';
              }
            })
            .catch(function(err) { console.error('Errore nel recupero squadra:', err); });
        });
      }

      function initFooter() {
        var footer = document.getElementById('footer-container');
        if (!footer) return;
        fetch('/includi/footer.html')
          .then(function(r) { return r.text(); })
          .then(function(html) { footer.innerHTML = html; })
          .catch(function(err) { console.error('Errore footer:', err); });
      }

      function bindDeleteButtons(modal, textEl) {
        var btnCancel = document.getElementById('btnCancelDel');
        var btnConfirm = document.getElementById('btnConfirmDel');
        var pendingForm = null;

        qsa('.confirm-delete').forEach(function(btn) {
          if (btn.dataset.bound === '1') return;
          btn.dataset.bound = '1';
          btn.addEventListener('click', function() {
            var formId = btn.getAttribute('data-form');
            pendingForm = formId ? document.getElementById(formId) : null;
            if (!pendingForm) return;
            var label = btn.getAttribute('data-label') || 'questa squadra';
            if (textEl) textEl.textContent = 'Vuoi eliminare "' + label + '"?';
            if (modal) modal.classList.add('active');
          });
        });

        if (btnCancel && modal) {
          btnCancel.addEventListener('click', function() {
            pendingForm = null;
            modal.classList.remove('active');
          });
        }
        if (btnConfirm) {
          btnConfirm.addEventListener('click', function() {
            if (pendingForm) pendingForm.submit();
            pendingForm = null;
            if (modal) modal.classList.remove('active');
          });
        }
        if (modal) {
          modal.addEventListener('click', function(e) {
            if (e.target === modal) {
              pendingForm = null;
              modal.classList.remove('active');
            }
          });
        }
      }

      function initEliminaLoader(modal, textEl) {
        var selectTorneoElimina = document.getElementById('selectTorneoElimina');
        var table = document.getElementById('tabellaSquadre');
        if (!selectTorneoElimina || !table) return;
        var tbody = table.querySelector('tbody');

        function esc(str) {
          return String(str || '').replace(/[&<>"']/g, function(ch) {
            return {
              '&': '&amp;',
              '<': '&lt;',
              '>': '&gt;',
              '"': '&quot;',
              "'": '&#39;'
            }[ch];
          });
        }

        function renderRows(data, torneoLabel) {
          if (!tbody) return;
          if (!data || !data.length) {
            tbody.innerHTML = '<tr><td colspan=\"3\">Nessuna squadra trovata per questo torneo.</td></tr>';
            return;
          }
          tbody.innerHTML = '';
          data.forEach(function(s) {
            var id = s.id || '';
            var nome = esc(s.nome || '');
            var torneo = esc(torneoLabel || s.torneo || '');
            var formId = 'del-' + id;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + nome + '</td><td>' + torneo + '</td>' +
              '<td><form method=\"POST\" class=\"delete-form\" style=\"display:inline;\" id=\"' + formId + '\">' +
              '<input type=\"hidden\" name=\"azione\" value=\"elimina\">' +
              '<input type=\"hidden\" name=\"id\" value=\"' + id + '\">' +
              '<button type=\"button\" class=\"btn-danger confirm-delete\" data-form=\"' + formId + '\" data-label=\"' + nome + '\">Elimina</button>' +
              '</form></td>';
            tbody.appendChild(tr);
          });
          bindDeleteButtons(modal, textEl);
        }

        selectTorneoElimina.addEventListener('change', function() {
          var torneo = selectTorneoElimina.value;
          var opt = selectTorneoElimina.options[selectTorneoElimina.selectedIndex];
          var torneoLabel = (opt && opt.text) ? opt.text : torneo;
          tbody.innerHTML = '<tr><td colspan=\"3\">Caricamento...</td></tr>';
          if (!torneo) {
            tbody.innerHTML = '<tr><td colspan=\"3\">Seleziona un torneo per vedere le squadre.</td></tr>';
            return;
          }
          fetch('/api/get_squadre_torneo.php?torneo=' + encodeURIComponent(torneo))
            .then(function(res) { return res.json(); })
            .then(function(data) { renderRows(data || [], torneoLabel); })
            .catch(function(err) {
              console.error('Errore nel caricamento squadre:', err);
              tbody.innerHTML = '<tr><td colspan=\"3\">Errore nel caricamento delle squadre.</td></tr>';
            });
        });
      }

      ready(function() {
        initTabs();
        initFileButtons();
        initFiltroElenco();
        initModificaLoader();
        initFooter();
        var modal = document.getElementById('confirmDeleteModal');
        var text = document.getElementById('confirmDeleteText');
        bindDeleteButtons(modal, text);
        initEliminaLoader(modal, text);
      });
    })();
  </script>
</body>
</html>
