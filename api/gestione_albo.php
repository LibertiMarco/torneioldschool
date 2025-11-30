<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

require_once __DIR__ . '/../includi/db.php';

$messages = [];
$errors = [];
$albo = [];
$competizioniList = [];
$alboDelete = [];
$orderColumnAvailable = false;

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function handleUpload(string $field, ?string $existing = null): ?string {
    if (!isset($_FILES[$field]) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return $existing;
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $name = $_FILES[$field]['name'];
    $size = (int)$_FILES[$field]['size'];

    if ($size > 2 * 1024 * 1024) {
        throw new Exception('Immagine troppo grande (max 2MB).');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new Exception('Formato immagine non valido. Solo JPG, PNG, WEBP.');
    }

    $ext = $allowed[$mime];
    $slug = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($name, PATHINFO_FILENAME));
    $slug = trim($slug, '-');
    $filename = 'albo_' . time() . '_' . ($slug ?: 'logo') . '.' . $ext;

    $destDir = realpath(__DIR__ . '/../img/scudetti');
    if (!$destDir) {
        $destDir = __DIR__ . '/../img/scudetti';
        @mkdir($destDir, 0755, true);
    }
    $destPath = $destDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $destPath)) {
        throw new Exception('Caricamento immagine non riuscito.');
    }

    return '/img/scudetti/' . $filename;
}

function ensureOrdinamentoColumn(mysqli $conn, array &$errors): bool {
    $check = $conn->query("SHOW COLUMNS FROM albo LIKE 'ordinamento'");
    if ($check && $check->num_rows > 0) {
        return true;
    }
    if (!$check) {
        $errors[] = "Impossibile verificare la colonna di ordinamento.";
        return false;
    }
    if ($conn->query("ALTER TABLE albo ADD COLUMN ordinamento INT DEFAULT NULL")) {
        return true;
    }
    $errors[] = "Non riesco ad aggiungere la colonna 'ordinamento' per l'ordinamento manuale.";
    return false;
}

if (!$conn || $conn->connect_error) {
    $errors[] = "Connessione al database non disponibile";
} else {
    $conn->set_charset('utf8mb4');
    $azione = $_POST['azione'] ?? '';
    $orderColumnAvailable = false;

    // Controllo presenza tabella
    $exists = $conn->query("SHOW TABLES LIKE 'albo'");
    if (!$exists || $exists->num_rows === 0) {
        $errors[] = "La tabella 'albo' non esiste. Creala prima di usare questa pagina.";
    } else {
        $orderColumnAvailable = ensureOrdinamentoColumn($conn, $errors);
    }

    if (empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $competizione = trim($_POST['competizione'] ?? '');
        $premio = trim($_POST['premio'] ?? 'Vincente');
        $vincitrice = trim($_POST['vincitrice'] ?? '');
        $inizio_mese = (int)($_POST['inizio_mese'] ?? 0);
        $inizio_anno = (int)($_POST['inizio_anno'] ?? 0);
        $fine_mese = (int)($_POST['fine_mese'] ?? 0);
        $fine_anno = (int)($_POST['fine_anno'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);

        try {
            if ($azione === 'create') {
                if ($competizione === '' || $vincitrice === '') {
                    throw new Exception('Compila almeno competizione e vincitrice.');
                }
                $logo = handleUpload('vincitrice_logo_file', null);
                $torneo_logo_path = handleUpload('torneo_logo_file', null) ?? '/img/logo_old_school.png';

                $stmt = $conn->prepare("INSERT INTO albo (competizione, premio, vincitrice, vincitrice_logo, torneo_logo, tabellone_url, inizio_mese, inizio_anno, fine_mese, fine_anno) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $tabellone_url = '';
                $im = $inizio_mese ?: null;
                $ia = $inizio_anno ?: null;
                $fm = $fine_mese ?: null;
                $fa = $fine_anno ?: null;
                $stmt->bind_param(
                    "ssssssiiii",
                    $competizione,
                    $premio,
                    $vincitrice,
                    $logo,
                    $torneo_logo_path,
                    $tabellone_url,
                    $im,
                    $ia,
                    $fm,
                    $fa
                );
                $stmt->execute();
                $stmt->close();
                $messages[] = "Record inserito correttamente.";
            } elseif ($azione === 'update' && $id > 0) {
                if ($competizione === '' || $vincitrice === '') {
                    throw new Exception('Compila almeno competizione e vincitrice.');
                }
                $currentLogo = null;
                $currentTorneoLogo = null;
                $fetch = $conn->prepare("SELECT vincitrice_logo, torneo_logo FROM albo WHERE id=?");
                $fetch->bind_param("i", $id);
                $fetch->execute();
                $fetch->bind_result($currentLogo, $currentTorneoLogo);
                $fetch->fetch();
                $fetch->close();

                $logo = handleUpload('vincitrice_logo_file', $currentLogo);
                $torneo_logo_path = handleUpload('torneo_logo_file', $currentTorneoLogo ?? '/img/logo_old_school.png');

                $im2 = $inizio_mese ?: null;
                $ia2 = $inizio_anno ?: null;
                $fm2 = $fine_mese ?: null;
                $fa2 = $fine_anno ?: null;

                $stmt = $conn->prepare("UPDATE albo SET competizione=?, premio=?, vincitrice=?, vincitrice_logo=?, torneo_logo=?, tabellone_url='', inizio_mese=?, inizio_anno=?, fine_mese=?, fine_anno=? WHERE id=?");
                $stmt->bind_param(
                    "ssssiiiiii",
                    $competizione,
                    $premio,
                    $vincitrice,
                    $logo,
                    $torneo_logo_path,
                    $im2,
                    $ia2,
                    $fm2,
                    $fa2,
                    $id
                );
                $stmt->execute();
                $stmt->close();
                $messages[] = "Record aggiornato.";
            } elseif ($azione === 'delete' && $id > 0) {
                $stmt = $conn->prepare("DELETE FROM albo WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                $messages[] = "Record eliminato.";
            } elseif ($azione === 'sort' && $orderColumnAvailable) {
                $ordine = json_decode($_POST['ordine'] ?? '[]', true);
                if (!is_array($ordine) || empty($ordine)) {
                    throw new Exception('Nessun ordine ricevuto.');
                }
                $stmt = $conn->prepare("UPDATE albo SET ordinamento=? WHERE competizione=?");
                if (!$stmt) {
                    throw new Exception('Impossibile salvare il nuovo ordine.');
                }
                $pos = 1;
                foreach ($ordine as $comp) {
                    $comp = trim((string)$comp);
                    if ($comp === '') continue;
                    $stmt->bind_param("is", $pos, $comp);
                    $stmt->execute();
                    $pos++;
                }
                $stmt->close();
                $messages[] = "Ordine aggiornato correttamente.";
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    $albo = [];
    if (empty($errors)) {
        $orderPrefix = $orderColumnAvailable ? "COALESCE(ordinamento, 999999)," : "";
        $res = $conn->query("SELECT * FROM albo ORDER BY {$orderPrefix} COALESCE(fine_anno, inizio_anno, YEAR(created_at)) DESC, COALESCE(fine_mese, inizio_mese, MONTH(created_at)) DESC, id DESC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $albo[] = $row;
            }
        }

        // Lista competizioni per ordinamento manuale
        $competizioniList = [];
        $fallbackIndex = 1;
        foreach ($albo as $row) {
            $comp = $row['competizione'] ?? '';
            if ($comp === '') continue;
            if (!isset($competizioniList[$comp])) {
                $competizioniList[$comp] = [
                    'competizione' => $comp,
                    'ordinamento' => isset($row['ordinamento']) ? (int)$row['ordinamento'] : $fallbackIndex++
                ];
            }
        }
        $competizioniList = array_values($competizioniList);
        usort($competizioniList, function($a, $b) {
            return ($a['ordinamento'] ?? PHP_INT_MAX) <=> ($b['ordinamento'] ?? PHP_INT_MAX);
        });

        // Lista per elimina (dal più recente)
        $alboDelete = $albo;
        usort($alboDelete, function($a, $b) {
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        });
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestione Albo d'oro (versione 2)</title>
  <link rel="stylesheet" href="/style.min.css?v=20251204">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; background: linear-gradient(180deg, #f6f8fb 0%, #eef3f9 100%); }
    main.admin-wrapper { max-width: 1100px; margin: 0 auto; padding: 36px 16px 70px; flex: 1 0 auto; }
    .admin-card-inline { background: #fff; border: 1px solid #e5e8f0; border-radius: 14px; padding: 18px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .panel-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 18px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .tab-buttons { display: flex; gap: 12px; margin: 10px 0 18px; flex-wrap: wrap; }
    .tab-buttons button { padding: 12px 16px; border: 1px solid #cbd5e1; background: #ecf1f7; cursor: pointer; border-radius: 10px; font-weight: 700; color: #1c2a3a; box-shadow: 0 2px 6px rgba(0,0,0,0.04); transition: all .2s; }
    .tab-buttons button:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
    .tab-buttons button.active { background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border-color: #15293e; box-shadow: 0 8px 20px rgba(21,41,62,0.25); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
    .form-grid label { font-weight: 700; color: #15293e; font-size: 0.95rem; display: flex; flex-direction: column; gap: 6px; }
    .form-grid input, .form-grid select { width: 100%; padding: 10px 12px; border: 1px solid #d7dce5; border-radius: 10px; background: #fff; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
    .btn-primary { background: linear-gradient(135deg, #15293e, #1f3f63); color: #fff; border: none; border-radius: 10px; padding: 11px 16px; cursor: pointer; font-weight: 800; box-shadow: 0 10px 22px rgba(21,41,62,0.22); transition: transform .15s, box-shadow .15s; }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 14px 26px rgba(21,41,62,0.28); }
    .btn-ghost { background: #f8f9fc; color: #1c2a3a; border: 1px solid #d7dce5; border-radius: 10px; padding: 10px 14px; cursor: pointer; font-weight: 700; }
    .btn-danger { background: linear-gradient(135deg, #d72638, #b1172a); color: #fff; border: none; border-radius: 10px; padding: 11px 16px; cursor: pointer; font-weight: 800; box-shadow: 0 10px 22px rgba(183,23,42,0.25); transition: transform .15s, box-shadow .15s; }
    .btn-danger:hover { transform: translateY(-1px); box-shadow: 0 14px 28px rgba(183,23,42,0.35); }
    .btn-warning { background: linear-gradient(135deg, #f59e0b, #f97316); color: #fff; border: none; border-radius: 10px; padding: 11px 16px; cursor: pointer; font-weight: 800; box-shadow: 0 10px 22px rgba(249,115,22,0.25); }
    .file-input { display: block; }
    .file-label { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s; }
    .file-label:hover { border-color: #15293e; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .file-btn { background: #15293e; color: #fff; padding: 8px 12px; border-radius: 9px; font-weight: 700; font-size: 0.95rem; }
    .file-name { color: #475569; font-weight: 600; font-size: 0.95rem; }
    .file-input input[type="file"] { display: none; }
    .msg { padding: 10px 12px; border-radius: 10px; margin-bottom: 10px; font-weight: 700; }
    .msg.ok { background: #e8f6ef; color: #065f46; border: 1px solid #34d399; }
    .msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
    .hidden { display: none !important; }
    .sort-list { list-style: none; padding: 0; margin: 0 0 12px; display: flex; flex-direction: column; gap: 10px; }
    .sort-list li { background: #f8fafc; border: 1px solid #e5eaf0; border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; cursor: grab; box-shadow: 0 6px 16px rgba(0,0,0,0.05); }
    .sort-list li.dragging { opacity: 0.7; }
    .drag-handle { font-size: 1rem; color: #1f3f63; }
    @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <h1>Gestione Albo d'oro</h1>
    <p>Inserisci, modifica o elimina le voci dell'albo d'oro</p>

    <?php foreach ($messages as $m): ?>
      <div class="msg ok"><?= h($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
      <div class="msg err"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="tab-buttons">
      <button type="button" data-tab="panel-create" class="active">Crea</button>
      <button type="button" data-tab="panel-update">Modifica</button>
      <button type="button" data-tab="panel-sort">Ordina</button>
      <button type="button" data-tab="panel-delete">Elimina</button>
    </div>

    <div class="panel-card" id="panel-create">
      <h3>Nuova voce</h3>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="azione" value="create">
        <div class="form-grid">
          <div>
            <label>Competizione*</label>
            <input type="text" name="competizione" required>
          </div>
          <div>
            <label>Premio (es. Vincente, Vincente Coppa, Miglior marcatore, MVP)</label>
            <input type="text" name="premio" placeholder="Vincente" value="Vincente">
          </div>
          <div>
            <label>Vincitrice*</label>
            <input type="text" name="vincitrice" required>
          </div>
          <div class="file-input">
            <label>Logo vincitrice (upload)</label>
            <label class="file-label">
              <span class="file-btn">Scegli file</span>
              <span class="file-name">Nessun file selezionato</span>
              <input type="file" name="vincitrice_logo_file" accept="image/png,image/jpeg,image/webp" onchange="this.parentElement.querySelector('.file-name').textContent = this.files?.[0]?.name || 'Nessun file selezionato';">
            </label>
          </div>
          <div class="file-input">
            <label>Logo torneo (upload)</label>
            <label class="file-label">
              <span class="file-btn">Scegli file</span>
              <span class="file-name">Nessun file selezionato</span>
              <input type="file" name="torneo_logo_file" accept="image/png,image/jpeg,image/webp" onchange="this.parentElement.querySelector('.file-name').textContent = this.files?.[0]?.name || 'Nessun file selezionato';">
            </label>
          </div>
          <div>
            <label>Inizio (mese)</label>
            <input type="number" name="inizio_mese" min="1" max="12" placeholder="1-12">
          </div>
          <div>
            <label>Inizio (anno)</label>
            <input type="number" name="inizio_anno" min="2000" max="2100" placeholder="2025">
          </div>
          <div>
            <label>Fine (mese)</label>
            <input type="number" name="fine_mese" min="1" max="12" placeholder="1-12">
          </div>
          <div>
            <label>Fine (anno)</label>
            <input type="number" name="fine_anno" min="2000" max="2100" placeholder="2025">
          </div>
        </div>
        <div class="actions">
          <button class="btn-primary" type="submit">Salva</button>
        </div>
      </form>
    </div>

    <div class="panel-card hidden" id="panel-update">
      <h3>Modifica</h3>
      <?php if (empty($albo)): ?>
        <p>Nessun record presente.</p>
      <?php else: ?>
        <div class="form-grid" style="margin-bottom:12px;">
          <label>
            Seleziona torneo
            <select id="selCompetizione">
              <option value="">-- scegli torneo --</option>
            </select>
          </label>
          <label>
            Seleziona premio
            <select id="selRecord">
              <option value="">-- scegli premio --</option>
            </select>
          </label>
        </div>

        <form method="POST" enctype="multipart/form-data" class="form-grid" id="formUpdate">
          <input type="hidden" name="azione" value="update">
          <input type="hidden" name="id" id="upd_id">
          <label>Competizione<input type="text" name="competizione" id="upd_competizione" required></label>
          <label>Premio<input type="text" name="premio" id="upd_premio"></label>
          <label>Vincitrice<input type="text" name="vincitrice" id="upd_vincitrice" required></label>
          <div class="file-input">
            <label class="file-label">
              <span class="file-btn">Logo vincitrice</span>
              <span class="file-name" id="upd_vincitrice_logo_name">Nessun file selezionato</span>
              <input type="file" name="vincitrice_logo_file" accept="image/png,image/jpeg,image/webp" onchange="document.getElementById('upd_vincitrice_logo_name').textContent = this.files?.[0]?.name || (this.dataset.current || 'Nessun file selezionato');" data-current="">
            </label>
          </div>
          <div class="file-input">
            <label class="file-label">
              <span class="file-btn">Logo torneo</span>
              <span class="file-name" id="upd_torneo_logo_name">Nessun file selezionato</span>
              <input type="file" name="torneo_logo_file" accept="image/png,image/jpeg,image/webp" onchange="document.getElementById('upd_torneo_logo_name').textContent = this.files?.[0]?.name || (this.dataset.current || 'Nessun file selezionato');" data-current="">
            </label>
          </div>
          <label>Inizio mese<input type="number" name="inizio_mese" id="upd_inizio_mese" min="1" max="12"></label>
          <label>Inizio anno<input type="number" name="inizio_anno" id="upd_inizio_anno" min="2000" max="2100"></label>
          <label>Fine mese<input type="number" name="fine_mese" id="upd_fine_mese" min="1" max="12"></label>
          <label>Fine anno<input type="number" name="fine_anno" id="upd_fine_anno" min="2000" max="2100"></label>
          <div class="actions" style="grid-column: 1/-1;">
            <button class="btn-primary" type="submit">Aggiorna</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <div class="panel-card hidden" id="panel-sort">
      <h3>Ordina competizioni</h3>
      <?php if (!$orderColumnAvailable): ?>
        <p>Colonna di ordinamento non disponibile. Controlla i permessi del database e riprova.</p>
      <?php elseif (empty($competizioniList)): ?>
        <p>Nessuna competizione da ordinare.</p>
      <?php else: ?>
        <p>Trascina per riordinare le competizioni. Il nuovo ordine verrà usato nella home e nella pagina albo.</p>
        <form method="POST" id="formSort">
          <input type="hidden" name="azione" value="sort">
          <input type="hidden" name="ordine" id="ordineInput">
        </form>
        <ul class="sort-list" id="sortList">
          <?php foreach ($competizioniList as $comp): ?>
            <li draggable="true" data-comp="<?= h($comp['competizione']) ?>">
              <span class="drag-handle">&#9776;</span>
              <span><?= h($comp['competizione']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="actions">
          <button class="btn-primary" type="button" id="btnSaveOrder">Salva ordine</button>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel-card hidden" id="panel-delete">
      <h3>Elimina</h3>
      <?php if (empty($albo)): ?>
        <p>Nessun record presente.</p>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="azione" value="delete">
          <div class="form-grid">
            <div>
              <label>Seleziona da eliminare</label>
              <select name="id" required>
                <option value="">--</option>
                <?php foreach ($alboDelete as $row): ?>
                  <option value="<?= (int)$row['id'] ?>"><?= h($row['competizione']) ?><?= $row['premio'] ? ' - ' . h($row['premio']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="actions">
            <button class="btn-danger" type="submit" onclick="return confirm('Eliminare questa voce?')">Elimina</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </main>

  <div id="footer-container"></div>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const panels = ["panel-create", "panel-update", "panel-sort", "panel-delete"];
      const tabButtons = document.querySelectorAll('.tab-buttons button');
      function showPanel(id) {
        panels.forEach(pid => {
          const el = document.getElementById(pid);
          if (el) el.classList.toggle('hidden', pid !== id);
        });
        tabButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.tab === id));
      }
      tabButtons.forEach(btn => btn.addEventListener('click', () => showPanel(btn.dataset.tab)));
      showPanel('panel-create');

      // Gestione selezione per modifica
      const alboData = <?= json_encode($albo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const selCompetizione = document.getElementById('selCompetizione');
      const selRecord = document.getElementById('selRecord');
      const formUpd = document.getElementById('formUpdate');
      const fields = {
        id: document.getElementById('upd_id'),
        competizione: document.getElementById('upd_competizione'),
        premio: document.getElementById('upd_premio'),
        vincitrice: document.getElementById('upd_vincitrice'),
        inizio_mese: document.getElementById('upd_inizio_mese'),
        inizio_anno: document.getElementById('upd_inizio_anno'),
        fine_mese: document.getElementById('upd_fine_mese'),
        fine_anno: document.getElementById('upd_fine_anno'),
        vincitrice_logo: document.querySelector('input[name="vincitrice_logo_file"]'),
        torneo_logo: document.querySelector('input[name="torneo_logo_file"]'),
        vincitrice_logo_name: document.getElementById('upd_vincitrice_logo_name'),
        torneo_logo_name: document.getElementById('upd_torneo_logo_name'),
      };

      function populateCompetizioni() {
        if (!selCompetizione) return;
        const comps = Array.from(new Set(alboData.map(r => r.competizione).filter(Boolean))).sort();
        selCompetizione.innerHTML = '<option value="">-- scegli torneo --</option>' +
          comps.map(c => `<option value="${c}">${c}</option>`).join('');
      }

      function populatePremi(comp) {
        if (!selRecord) return;
        const filtered = alboData.filter(r => r.competizione === comp);
        selRecord.innerHTML = '<option value="">-- scegli premio --</option>' +
          filtered.map(r => `<option value="${r.id}">${r.premio || 'Premio'}</option>`).join('');
      }

      function fillForm(id) {
        const rec = alboData.find(r => Number(r.id) === Number(id));
        if (!rec || !formUpd) return;
        fields.id.value = rec.id || '';
        fields.competizione.value = rec.competizione || '';
        fields.premio.value = rec.premio || '';
        fields.vincitrice.value = rec.vincitrice || '';
        fields.inizio_mese.value = rec.inizio_mese || '';
        fields.inizio_anno.value = rec.inizio_anno || '';
        fields.fine_mese.value = rec.fine_mese || '';
        fields.fine_anno.value = rec.fine_anno || '';
        if (fields.vincitrice_logo) {
          fields.vincitrice_logo.dataset.current = rec.vincitrice_logo || 'Nessun file selezionato';
          fields.vincitrice_logo.value = '';
        }
        if (fields.vincitrice_logo_name) fields.vincitrice_logo_name.textContent = rec.vincitrice_logo || 'Nessun file selezionato';
        if (fields.torneo_logo) {
          fields.torneo_logo.dataset.current = rec.torneo_logo || 'Nessun file selezionato';
          fields.torneo_logo.value = '';
        }
        if (fields.torneo_logo_name) fields.torneo_logo_name.textContent = rec.torneo_logo || 'Nessun file selezionato';
      }

      selCompetizione?.addEventListener('change', () => {
        populatePremi(selCompetizione.value);
        fields.id.value = '';
        selRecord.value = '';
      });
      selRecord?.addEventListener('change', () => fillForm(selRecord.value));

      populateCompetizioni();

      // Drag & drop ordine competizioni
      const sortList = document.getElementById('sortList');
      const ordineInput = document.getElementById('ordineInput');
      const btnSaveOrder = document.getElementById('btnSaveOrder');
      let draggedEl = null;

      function serializeOrder() {
        return Array.from(sortList?.querySelectorAll('li') || []).map(li => li.dataset.comp);
      }

      sortList?.addEventListener('dragstart', (e) => {
        const li = e.target.closest('li');
        if (!li) return;
        draggedEl = li;
        li.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      sortList?.addEventListener('dragend', (e) => {
        if (draggedEl) draggedEl.classList.remove('dragging');
        draggedEl = null;
      });
      sortList?.addEventListener('dragover', (e) => {
        e.preventDefault();
        const li = e.target.closest('li');
        if (!li || li === draggedEl) return;
        const rect = li.getBoundingClientRect();
        const after = (e.clientY - rect.top) > rect.height / 2;
        sortList.insertBefore(draggedEl, after ? li.nextSibling : li);
      });
      sortList?.addEventListener('drop', (e) => e.preventDefault());

      btnSaveOrder?.addEventListener('click', () => {
        if (!sortList || !ordineInput) return;
        const order = serializeOrder();
        ordineInput.value = JSON.stringify(order);
        document.getElementById('formSort')?.submit();
      });

      fetch("/includi/footer.html")
        .then(r => r.text())
        .then(html => {
          const footer = document.getElementById("footer-container");
          if (footer) footer.innerHTML = html;
        })
        .catch(err => console.error("Errore nel caricamento del footer:", err));
      if (typeof initHeaderInteractions === "function") {
        initHeaderInteractions();
      }
    });
  </script>
</body>
</html>
