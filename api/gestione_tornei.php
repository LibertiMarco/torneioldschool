<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /torneioldschool/index.php");
    exit;
}

require_once __DIR__ . '/crud/Torneo.php';
$torneo = new Torneo();
require_once __DIR__ . '/crud/Squadra.php';
$squadraModel = new Squadra();

function sanitizeTorneoSlug($value) {
    $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    if ($slug === '') {
        $slug = 'torneo' . time();
    }
    return $slug;
}

function creaFileTorneoDaTemplate($nomeTorneo, $slug) {
    $baseDir = realpath(__DIR__ . '/../tornei');
    if (!$baseDir) {
        return;
    }

    $htmlTemplate = $baseDir . '/SerieA.html';
    $jsTemplate = $baseDir . '/script-serieA.js';
    if (!file_exists($htmlTemplate) || !file_exists($jsTemplate)) {
        return;
    }

    $htmlContent = file_get_contents($htmlTemplate);
    $jsContent = file_get_contents($jsTemplate);
    if ($htmlContent === false || $jsContent === false) {
        return;
    }

    $newScriptName = 'script-' . $slug . '.js';
    $htmlContent = str_replace(
        ['Serie A', 'script-serieA.js'],
        [$nomeTorneo, $newScriptName],
        $htmlContent
    );

    $jsContent = str_replace('SerieA', $slug, $jsContent);

    @file_put_contents($baseDir . '/' . $slug . '.html', $htmlContent);
    @file_put_contents($baseDir . '/' . $newScriptName, $jsContent);
}

function eliminaFileTorneo($fileName) {
    if (!$fileName) {
        return;
    }
    $baseDir = realpath(__DIR__ . '/../tornei');
    if (!$baseDir) {
        return;
    }
    $slug = preg_replace('/\.html$/i', '', $fileName);
    if ($slug === '') {
        return;
    }
    $htmlPath = $baseDir . '/' . $slug . '.html';
    $jsPath = $baseDir . '/script-' . $slug . '.js';
    if (file_exists($htmlPath)) {
        @unlink($htmlPath);
    }
    if (file_exists($jsPath)) {
        @unlink($jsPath);
    }
}

function salvaImmagineTorneo($nomeTorneo, $fileField) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$fileField]['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
        return null;
    }

    $baseDir = realpath(__DIR__ . '/../img/tornei');
    if (!$baseDir) {
        return null;
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]/', '', $nomeTorneo));
    if ($slug === '') {
        $slug = 'torneo';
    }

    $extension = $allowed[$mime];
    $filename = $slug . '.' . $extension;
    $counter = 2;
    while (file_exists($baseDir . '/' . $filename)) {
        $filename = $slug . '_' . $counter . '.' . $extension;
        $counter++;
    }

    if (!move_uploaded_file($_FILES[$fileField]['tmp_name'], $baseDir . '/' . $filename)) {
        return null;
    }

    return '/torneioldschool/img/tornei/' . $filename;
}

function eliminaImmagineTorneo($imgPath) {
    if (!$imgPath) {
        return;
    }
    $default = '/torneioldschool/img/tornei/pallone.png';
    $basename = basename($imgPath);
    if ($basename === basename($default)) {
        return;
    }
    $imgDir = realpath(__DIR__ . '/../img/tornei');
    if (!$imgDir) {
        return;
    }
    $fullPath = $imgDir . '/' . $basename;
    if (!file_exists($fullPath)) {
        return;
    }
    @unlink($fullPath);
}

// --- CREA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
    $nome = trim($_POST['nome']);
    $stato = $_POST['stato'];
    $data_inizio = $_POST['data_inizio'];
    $data_fine = $_POST['data_fine'];
    $rawFile = preg_replace('/\.html$/i', '', trim($_POST['filetorneo']));
    $slug = sanitizeTorneoSlug($rawFile);
    $filetorneo = $slug . '.html';
    $categoria = trim($_POST['categoria']);
    $img = salvaImmagineTorneo($nome, 'img_upload');

    if ($torneo->crea($nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria)) {
        creaFileTorneoDaTemplate($nome, $slug);
    }
    header("Location: gestione_tornei.php");
    exit;
}

// --- AGGIORNA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
    $id = (int)$_POST['id'];
    $nome = trim($_POST['nome']);
    $stato = $_POST['stato'];
    $data_inizio = $_POST['data_inizio'];
    $data_fine = $_POST['data_fine'];
    $rawFile = preg_replace('/\.html$/i', '', trim($_POST['filetorneo']));
    $slug = sanitizeTorneoSlug($rawFile);
    $filetorneo = $slug . '.html';
    $categoria = trim($_POST['categoria']);
    $record = $torneo->getById($id);
    $img = salvaImmagineTorneo($nome, 'img_upload_mod');
    if (!$img && $record && !empty($record['img'])) {
        $img = $record['img'];
    }
    $torneo->aggiorna($id, $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria);
    header("Location: gestione_tornei.php");
    exit;
}

// --- ELIMINA ---
if (isset($_GET['elimina'])) {
    $id = (int)$_GET['elimina'];
    $record = $torneo->getById($id);

    if ($record) {
        eliminaFileTorneo($record['filetorneo'] ?? null);
        eliminaImmagineTorneo($record['img'] ?? null);

        $torneoSlug = '';
        if (!empty($record['filetorneo'])) {
            $torneoSlug = sanitizeTorneoSlug(preg_replace('/\.html$/i', '', $record['filetorneo']));
        }
        $squadraModel->eliminaByTorneo($torneoSlug);
    }

    $torneo->elimina($id);
    header("Location: gestione_tornei.php");
    exit;
}

// --- LISTA TORNEI ---
$lista = $torneo->getAll();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Tornei</title>
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
        .admin-container {
            padding-bottom: 80px;
        }
        #footer-container {
            margin-top: 40px;
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
            <h1 class="admin-title">Gestione Tornei</h1>

            <!-- PICKLIST -->
            <div class="admin-select-action">
                <label for="azione">Seleziona azione:</label>
                <select id="azione" class="operation-picker">
                    <option value="crea" selected>Aggiungi Torneo</option>
                    <option value="modifica">Modifica Torneo</option>
                    <option value="elimina">Elimina Torneo</option>
                </select>
            </div>


            <!-- FORM CREAZIONE -->
            <form method="POST" class="admin-form form-crea" enctype="multipart/form-data">
                <h2>Aggiungi Torneo</h2>
                <div class="form-group"><label>Nome</label><input type="text" name="nome" required></div>
                <div class="form-group"><label>Stato</label>
                    <select name="stato">
                        <option value="programmato">Programmato</option>
                        <option value="in corso">In Corso</option>
                        <option value="terminato">Terminato</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group half"><label>Data Inizio</label><input type="date" name="data_inizio" required></div>
                    <div class="form-group half"><label>Data Fine</label><input type="date" name="data_fine" required></div>
                </div>
                <div class="form-group">
                    <label>Immagine Torneo</label>
                    <div class="file-upload">
                        <input type="file" name="img_upload" id="img_upload" accept="image/*">
                        <button type="button" class="file-btn" data-target="img_upload">Scegli immagine</button>
                        <span class="file-name" id="img_upload_name">Nessun file selezionato</span>
                    </div>
                    <small>Se non carichi un file verrà usata l'immagine predefinita.</small>
                </div>
                <div class="form-group"><label>File Torneo</label><input type="text" name="filetorneo" required></div>
                <div class="form-group"><label>Categoria</label><input type="text" name="categoria" required></div>
                <button type="submit" name="crea" class="btn-primary">Crea Torneo</button>
            </form>

            <!-- FORM MODIFICA -->
            <form method="POST" class="admin-form form-modifica hidden" id="formModifica" enctype="multipart/form-data">
                <h2>Modifica Torneo</h2>

                <div class="form-group">
                    <label>Seleziona Torneo</label>
                    <select name="id" id="selectTorneo" required>
                        <option value="">-- Seleziona un torneo --</option>
                        <?php
                        $lista2 = $torneo->getAll();
                        while ($row = $lista2->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nome']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                        
                <div class="form-group"><label>Nome</label><input type="text" name="nome" id="mod_nome"></div>
                <div class="form-group"><label>Stato</label>
                    <select name="stato" id="mod_stato">
                        <option value="programmato">Programmato</option>
                        <option value="in corso">In Corso</option>
                        <option value="terminato">Terminato</option>
                    </select>
                </div>
                <div class="form-group"><label>Data Inizio</label><input type="date" name="data_inizio" id="mod_inizio"></div>
                <div class="form-group"><label>Data Fine</label><input type="date" name="data_fine" id="mod_fine"></div>
                <div class="form-group">
                    <label>Nuova immagine</label>
                    <div class="file-upload">
                        <input type="file" name="img_upload_mod" id="img_upload_mod" accept="image/*">
                        <button type="button" class="file-btn" data-target="img_upload_mod">Scegli immagine</button>
                        <span class="file-name" id="img_upload_mod_name">Nessun file selezionato</span>
                    </div>
                    <small>Se non carichi nulla resterà l'immagine attuale.</small>
                </div>
                <div class="form-group"><label>File Torneo</label><input type="text" name="filetorneo" id="mod_file"></div>
                <div class="form-group"><label>Categoria</label><input type="text" name="categoria" id="mod_categoria"></div>
                        
                <button type="submit" name="aggiorna" class="btn-primary">Aggiorna Torneo</button>
            </form>

            <!-- SEZIONE ELIMINA -->
            <section class="admin-table-section form-elimina hidden">
                <h2>Elimina Torneo</h2>
                <?php $lista = $torneo->getAll(); ?>
                <table class="admin-table" id="tabellaTornei">
                    <thead>
                        <tr>
                            <th data-col="nome">Nome</th>
                            <th data-col="stato">Stato</th>
                            <th data-col="categoria">Categoria</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $lista->fetch_assoc()): ?>
                            <tr data-id="<?= $row['id'] ?>"> <!-- memorizziamo l’ID come attributo -->
                                <td><?= htmlspecialchars($row['nome']) ?></td>
                                <td><?= htmlspecialchars($row['stato']) ?></td>
                                <td><?= htmlspecialchars($row['categoria']) ?></td>
                                <td>
                                    <a href="?elimina=<?= $row['id'] ?>" class="btn-danger" onclick="return confirm('Eliminare questo torneo?')">
                                        Elimina
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>


        </section>
    </main>

    <div id="footer-container"></div>

    <script>
        const selectAzione = document.getElementById('azione');
        const formCrea = document.querySelector('.form-crea');
        const formModifica = document.querySelector('.form-modifica');
        const formElimina = document.querySelector('.form-elimina');

        function mostraSezione(valore) {
            formCrea.classList.add('hidden');
            formModifica.classList.add('hidden');
            formElimina.classList.add('hidden');

            if (valore === 'crea') formCrea.classList.remove('hidden');
            if (valore === 'modifica') formModifica.classList.remove('hidden');
            if (valore === 'elimina') formElimina.classList.remove('hidden');
        }

        selectAzione.addEventListener('change', (e) => mostraSezione(e.target.value));
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.file-upload').forEach(wrapper => {
                const input = wrapper.querySelector('input[type="file"]');
                const btn = wrapper.querySelector('.file-btn');
                const nameLabel = wrapper.querySelector('.file-name');
                if (btn && input) {
                    btn.addEventListener('click', (e) => {
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
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.file-upload input[type="file"]').forEach(input => {
                const label = input.parentElement.querySelector('.file-name');
                input.addEventListener('change', () => {
                    const file = input.files && input.files[0];
                    label.textContent = file ? file.name : 'Nessun file selezionato';
                });
            });
        });
    </script>
    <script>
const selectTorneo = document.getElementById('selectTorneo');
const campi = {
    nome: document.getElementById('mod_nome'),
    stato: document.getElementById('mod_stato'),
    inizio: document.getElementById('mod_inizio'),
    fine: document.getElementById('mod_fine'),
    file: document.getElementById('mod_file'),
    categoria: document.getElementById('mod_categoria')
};

selectTorneo.addEventListener('change', async (e) => {
    const id = e.target.value;
    if (!id) {
        Object.values(campi).forEach(c => c.value = '');
        return;
    }

    try {
        const res = await fetch(`/torneioldschool/api/get_torneo.php?id=${id}`);
        const data = await res.json();

        if (data && !data.error) {
            campi.nome.value = data.nome || '';
            campi.stato.value = data.stato || 'programmato';
            campi.inizio.value = data.data_inizio || '';
            campi.fine.value = data.data_fine || '';
            campi.file.value = data.filetorneo || '';
            campi.categoria.value = data.categoria || '';
        } else {
            alert('Errore: ' + (data.error || 'Dati non trovati'));
        }
    } catch (err) {
        console.error('Errore nel recupero del torneo:', err);
    }
});
</script>
<script>
// ============ ORDINAMENTO TABELLA ============ //
document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("tabellaTornei");
  const headers = table.querySelectorAll("th[data-col]");
  let sortDirection = {}; // tiene traccia della direzione per ogni colonna

  headers.forEach(header => {
    header.style.cursor = "pointer";
    header.addEventListener("click", () => {
      const columnIndex = Array.from(header.parentNode.children).indexOf(header);
      const tbody = table.querySelector("tbody");
      const rows = Array.from(tbody.querySelectorAll("tr"));
      const col = header.getAttribute("data-col");

      // Alterna direzione (asc <-> desc)
      sortDirection[col] = sortDirection[col] === "asc" ? "desc" : "asc";

      // Ordina righe
      rows.sort((a, b) => {
        const valA = a.children[columnIndex].textContent.trim().toLowerCase();
        const valB = b.children[columnIndex].textContent.trim().toLowerCase();

        if (!isNaN(valA) && !isNaN(valB)) {
          return sortDirection[col] === "asc" ? valA - valB : valB - valA;
        } else {
          return sortDirection[col] === "asc"
            ? valA.localeCompare(valB)
            : valB.localeCompare(valA);
        }
      });

      // Rimuovi vecchie righe e aggiungi nuove ordinate
      tbody.innerHTML = "";
      rows.forEach(r => tbody.appendChild(r));

      // Aggiorna indicatori ↑↓
      headers.forEach(h => h.classList.remove("sort-asc", "sort-desc"));
      header.classList.add(sortDirection[col] === "asc" ? "sort-asc" : "sort-desc");
    });
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const footer = document.getElementById('footer-container');
  if (!footer) return;
  fetch('/torneioldschool/includi/footer.html')
    .then(response => response.text())
    .then(html => footer.innerHTML = html)
    .catch(err => console.error('Errore caricamento footer:', err));
});
</script>

</body>
</html>
