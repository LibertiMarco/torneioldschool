<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /torneioldschool/index.php");
    exit;
}

require_once __DIR__ . '/crud/Squadra.php';
require_once __DIR__ . '/crud/Torneo.php';
$squadra = new Squadra();
$torneoModel = new Torneo();

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
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$fieldName]['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
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

    $extension = $allowed[$mime];
    $filename = "{$slugSquadra}-{$slugTorneo}.{$extension}";
    $counter = 2;
    while (file_exists($baseDir . '/' . $filename)) {
        $filename = "{$slugSquadra}-{$slugTorneo}_{$counter}.{$extension}";
        $counter++;
    }

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $baseDir . '/' . $filename)) {
        return null;
    }

    return '/torneioldschool/img/scudetti/' . $filename;
}

// --- CREA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
    $nome = trim($_POST['nome']);
    $torneo = sanitizeTorneoSlugValue(trim($_POST['torneo']));
    $logo = salvaScudetto($nome, $torneo, 'scudetto');
    $squadra->crea($nome, $torneo, $logo);
    header("Location: gestione_squadre.php");
    exit;
}

// --- AGGIORNA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
    $id = (int)$_POST['id'];
    $nome = trim($_POST['nome']);
    $torneo = sanitizeTorneoSlugValue(trim($_POST['torneo']));
    $punti = (int)$_POST['punti'];
    $giocate = (int)$_POST['giocate'];
    $vinte = (int)$_POST['vinte'];
    $pareggiate = (int)$_POST['pareggiate'];
    $perse = (int)$_POST['perse'];
    $gol_fatti = (int)$_POST['gol_fatti'];
    $gol_subiti = (int)$_POST['gol_subiti'];
    $diff = $gol_fatti - $gol_subiti;
    $logo = salvaScudetto($nome, $torneo, 'scudetto_mod');

    $squadra->aggiorna($id, $nome, $torneo, $punti, $giocate, $vinte, $pareggiate, $perse, $gol_fatti, $gol_subiti, $diff, $logo);
    header("Location: gestione_squadre.php");
    exit;
}

// --- ELIMINA ---
if (isset($_GET['elimina'])) {
    $squadra->elimina((int)$_GET['elimina']);
    header("Location: gestione_squadre.php");
    exit;
}

$lista = $squadra->getAll();
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestione Squadre</title>
  <link rel="stylesheet" href="/torneioldschool/style.css">
  <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
  <style>
    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    main.admin-wrapper {
      flex: 1 0 auto;
    }
    #footer-container {
      margin-top: 20px;
    }

    .file-upload {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
      padding: 10px 14px;
      border: 1px dashed #d4d9e2;
      border-radius: 10px;
      background: #f7f9fc;
    }

    .file-upload input[type="file"] {
      display: none;
    }

    .file-upload .file-btn {
      background: #15293e;
      color: #fff;
      padding: 8px 18px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: transform 0.2s ease, background 0.2s ease;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border: none;
    }

    .file-upload .file-btn:hover {
      background: #0e1d2e;
      transform: translateY(-1px);
    }

    .file-upload .file-name {
      font-size: 0.9rem;
      color: #5f6b7b;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <section class="admin-container">
      <a class="admin-back-link" href="/torneioldschool/admin_dashboard.php">Torna alla dashboard</a>
      <h1 class="admin-title">Gestione Squadre</h1>

      <!-- PICKLIST -->
      <div class="admin-select-action">
        <label for="azione">Seleziona azione:</label>
        <select id="azione" class="operation-picker">
          <option value="crea" selected>Aggiungi Squadra</option>
          <option value="modifica">Modifica Squadra</option>
          <option value="elimina">Elimina Squadra</option>
        </select>
      </div>

      <!-- FORM CREA -->
      <form method="POST" class="admin-form form-crea" enctype="multipart/form-data">
        <h2>Aggiungi Squadra</h2>
        <div class="form-group"><label>Nome</label><input type="text" name="nome" required></div>
        <div class="form-group">
          <label>Torneo</label>
          <select name="torneo" required>
            <option value="">-- Seleziona un torneo --</option>
            <?php
            $torneiCreate = $torneoModel->getAll();
            while ($torneoRow = $torneiCreate->fetch_assoc()): ?>
              <?php
                $slugValue = sanitizeTorneoSlugValue($torneoRow['filetorneo'] ?? $torneoRow['nome']);
                $label = $torneoRow['nome'];
              ?>
              <option value="<?= htmlspecialchars($slugValue) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Immagine / Scudetto</label>
          <div class="file-upload">
            <input type="file" name="scudetto" id="scudettoUpload" accept="image/png,image/jpeg,image/webp,image/gif">
            <button type="button" class="file-btn" data-target="scudettoUpload">Scegli immagine</button>
            <span class="file-name" id="scudettoUploadName">Nessun file selezionato</span>
          </div>
          <small>PNG, JPG, WEBP o GIF - max 2MB.</small>
        </div>
        <button type="submit" name="crea" class="btn-primary">Crea Squadra</button>
      </form>

      <!-- FORM MODIFICA -->
      <form method="POST" class="admin-form form-modifica hidden" id="formModifica" enctype="multipart/form-data">
        <h2>Modifica Squadra</h2>

        <!-- FILTRO TORNEO -->
        <div class="form-group">
          <label>Seleziona Torneo</label>
          <select id="selectTorneoFiltro" required>
            <option value="">-- Seleziona un torneo --</option>
            <?php
            $tornei = $squadra->getTornei();
            while ($row = $tornei->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($row['torneo']) ?>"><?= htmlspecialchars($row['torneo']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- FILTRO SQUADRA -->
        <div class="form-group">
          <label>Seleziona Squadra</label>
          <select name="id" id="selectSquadraMod" required disabled>
            <option value="">-- Seleziona una squadra --</option>
          </select>
        </div>

        <div class="form-group"><label>Nome</label><input type="text" name="nome" id="mod_nome"></div>
        <div class="form-group"><label>Torneo</label><input type="text" name="torneo" id="mod_torneo"></div>
        <div class="form-group">
          <label>Nuovo scudetto</label>
          <div class="file-upload">
            <input type="file" name="scudetto_mod" id="scudettoUploadMod" accept="image/png,image/jpeg,image/webp,image/gif">
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

        <button type="submit" name="aggiorna" class="btn-primary">Aggiorna Squadra</button>
      </form>

      <!-- SEZIONE ELIMINA -->
      <section class="admin-table-section form-elimina hidden">
        <h2>Elimina Squadra</h2>
        <input type="text" id="searchSquadra" placeholder="Cerca squadra..." class="search-input">

        <?php $lista = $squadra->getAll(); ?>
        <table class="admin-table-squadre" id="tabellaSquadre">
          <thead>
            <tr>
              <th data-col="nome">Nome</th>
              <th data-col="torneo">Torneo</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $lista->fetch_assoc()): ?>
              <tr>
                <td data-label="Nome"><?= htmlspecialchars($row['nome']) ?></td>
                <td data-label="Torneo"><?= htmlspecialchars($row['torneo']) ?></td>
                <td data-label="Azioni">
                  <a href="?elimina=<?= $row['id'] ?>" class="btn-danger" onclick="return confirm('Eliminare questa squadra?')">Elimina</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </section>
    </section>
  </main>

  <div id="footer-container"></div>

  <!-- SCRIPT SWITCH SEZIONI -->
  <script>
    const selectAzione = document.getElementById('azione');
    const formCrea = document.querySelector('.form-crea');
    const formModifica = document.querySelector('.form-modifica');
    const formElimina = document.querySelector('.form-elimina');

    function mostraSezione(val) {
      [formCrea, formModifica, formElimina].forEach(f => f.classList.add('hidden'));
      if (val === 'crea') formCrea.classList.remove('hidden');
      if (val === 'modifica') formModifica.classList.remove('hidden');
      if (val === 'elimina') formElimina.classList.remove('hidden');
    }
    selectAzione.addEventListener('change', e => mostraSezione(e.target.value));
  </script>

  <!-- SCRIPT: FILTRO TORNEO → POPOLA SQUADRE -->
  <script>
    const selectTorneoFiltro = document.getElementById('selectTorneoFiltro');
    const selectSquadraMod = document.getElementById('selectSquadraMod');

    selectTorneoFiltro.addEventListener('change', async () => {
      const torneo = selectTorneoFiltro.value;
      if (!torneo) {
        selectSquadraMod.innerHTML = '<option value="">-- Seleziona una squadra --</option>';
        selectSquadraMod.disabled = true;
        return;
      }

      try {
        const res = await fetch(`/torneioldschool/api/get_squadre_torneo.php?torneo=${encodeURIComponent(torneo)}`);
        const data = await res.json();

        selectSquadraMod.innerHTML = '<option value="">-- Seleziona una squadra --</option>';
        if (data.length > 0) {
          data.forEach(s => selectSquadraMod.innerHTML += `<option value="${s.id}">${s.nome}</option>`);
          selectSquadraMod.disabled = false;
        } else {
          selectSquadraMod.disabled = true;
          alert("Nessuna squadra trovata per questo torneo.");
        }
      } catch (err) {
        console.error("Errore nel caricamento squadre:", err);
      }
    });
  </script>

  <!-- SCRIPT: CARICA DATI DELLA SQUADRA -->
  <script>
    const campi = {
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

    selectSquadraMod.addEventListener('change', async e => {
      const id = e.target.value;
      if (!id) {
        Object.values(campi).forEach(c => c.value = '');
        return;
      }

      try {
        const res = await fetch(`/torneioldschool/api/get_squadra.php?id=${id}`);
        const data = await res.json();
        if (data && !data.error) {
          campi.nome.value = data.nome || '';
          campi.torneo.value = data.torneo || '';
          campi.punti.value = data.punti ?? '';
          campi.giocate.value = data.giocate ?? '';
          campi.vinte.value = data.vinte ?? '';
          campi.pareggiate.value = data.pareggiate ?? '';
          campi.perse.value = data.perse ?? '';
          campi.fatti.value = data.gol_fatti ?? '';
          campi.subiti.value = data.gol_subiti ?? '';
        }
      } catch (err) {
        console.error('Errore nel recupero squadra:', err);
      }
    });
  </script>

  <!-- SCRIPT: RICERCA + ORDINAMENTO -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const table = document.getElementById("tabellaSquadre");
      const headers = table.querySelectorAll("th[data-col]");
      const search = document.getElementById("searchSquadra");
      let sortDirection = {};

      headers.forEach(header => {
        header.style.cursor = "pointer";
        header.addEventListener("click", () => {
          const colIndex = Array.from(header.parentNode.children).indexOf(header);
          const tbody = table.querySelector("tbody");
          const rows = Array.from(tbody.querySelectorAll("tr"));
          const col = header.getAttribute("data-col");

          sortDirection[col] = sortDirection[col] === "asc" ? "desc" : "asc";
          rows.sort((a, b) => {
            const valA = a.children[colIndex].textContent.trim().toLowerCase();
            const valB = b.children[colIndex].textContent.trim().toLowerCase();
            return sortDirection[col] === "asc" ? valA.localeCompare(valB) : valB.localeCompare(valA);
          });

          tbody.innerHTML = "";
          rows.forEach(r => tbody.appendChild(r));
        });
      });

      search.addEventListener("input", () => {
        const filtro = search.value.toLowerCase();
        table.querySelectorAll("tbody tr").forEach(tr => {
          const testo = tr.textContent.toLowerCase();
          tr.style.display = testo.includes(filtro) ? "" : "none";
        });
      });
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const footer = document.getElementById('footer-container');
      if (!footer) return;
      fetch('/torneioldschool/includi/footer.html')
        .then(r => r.text())
        .then(html => footer.innerHTML = html)
        .catch(err => console.error('Errore footer:', err));
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.file-upload').forEach(wrapper => {
        const input = wrapper.querySelector('input[type="file"]');
        const button = wrapper.querySelector('.file-btn');
        const nameLabel = wrapper.querySelector('.file-name');
        if (button && input) {
          button.addEventListener('click', (e) => {
            e.preventDefault();
            input.click();
          });
        }
        if (input && nameLabel) {
          input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            nameLabel.textContent = file ? file.name : 'Nessun file selezionato';
          });
        }
      });
    });
  </script>

</body>
</html>
