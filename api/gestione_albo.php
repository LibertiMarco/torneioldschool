<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

require_once __DIR__ . '/../includi/db.php';

$messages = [];
$errors = [];

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

    if ($size > 2 * 1024 * 1024) { // 2MB
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

if (!$conn || $conn->connect_error) {
    $errors[] = "Connessione al database non disponibile";
} else {
    $conn->set_charset('utf8mb4');

    $azione = $_POST['azione'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

                $stmt = $conn->prepare("INSERT INTO albo (competizione, premio, vincitrice, vincitrice_logo, torneo_logo, tabellone_url, inizio_mese, inizio_anno, fine_mese, fine_anno) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $torneo_logo = '/img/logo_old_school.png';
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
                    $torneo_logo,
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
                $fetch = $conn->prepare("SELECT vincitrice_logo FROM albo WHERE id=?");
                $fetch->bind_param("i", $id);
                $fetch->execute();
                $fetch->bind_result($currentLogo);
                $fetch->fetch();
                $fetch->close();

                $logo = handleUpload('vincitrice_logo_file', $currentLogo);

                $im2 = $inizio_mese ?: null;
                $ia2 = $inizio_anno ?: null;
                $fm2 = $fine_mese ?: null;
                $fa2 = $fine_anno ?: null;

                $stmt = $conn->prepare("UPDATE albo SET competizione=?, premio=?, vincitrice=?, vincitrice_logo=?, torneo_logo='/img/logo_old_school.png', tabellone_url='', inizio_mese=?, inizio_anno=?, fine_mese=?, fine_anno=? WHERE id=?");
                $stmt->bind_param(
                    "ssssiiiii",
                    $competizione,
                    $premio,
                    $vincitrice,
                    $logo,
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
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    $albo = [];
    $res = $conn->query("SELECT * FROM albo ORDER BY COALESCE(fine_anno, inizio_anno, YEAR(created_at)) DESC, COALESCE(fine_mese, inizio_mese, MONTH(created_at)) DESC, id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $albo[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestione Albo d'oro</title>
  <link rel="stylesheet" href="/style.min.css?v=20251204">
  <style>
    .admin-wrapper { max-width: 1100px; margin: 0 auto; padding: 30px 16px 60px; }
    .admin-card-inline { background: #fff; border: 1px solid #e5e8f0; border-radius: 12px; padding: 18px; box-shadow: 0 6px 16px rgba(0,0,0,0.08); margin-bottom: 20px; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
    .form-grid label { font-weight: 700; color: #15293e; font-size: 0.95rem; }
    .form-grid input { width: 100%; padding: 8px 10px; border: 1px solid #d7dce5; border-radius: 8px; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
    .btn-primary { background: #15293e; color: #fff; border: none; border-radius: 8px; padding: 10px 16px; cursor: pointer; font-weight: 700; }
    .btn-ghost { background: #eef2f7; color: #15293e; border: 1px solid #d7dce5; border-radius: 8px; padding: 8px 12px; cursor: pointer; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 10px; border-bottom: 1px solid #e5e8f0; text-align: left; }
    .pill { display: inline-block; background: #eef2f7; color: #15293e; padding: 4px 8px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; }
    .msg { padding: 10px 12px; border-radius: 8px; margin-bottom: 10px; }
    .msg.ok { background: #e7f6ec; color: #14532d; border: 1px solid #bbf7d0; }
    .msg.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecdd3; }
    .file-input { display: block; }
    .file-label { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px dashed #cbd5e1; border-radius: 10px; background: #f8fafc; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s; }
    .file-label:hover { border-color: #15293e; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .file-btn { background: #15293e; color: #fff; padding: 8px 12px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; }
    .file-name { color: #475569; font-weight: 600; font-size: 0.95rem; }
    .file-input input[type="file"] { display: none; }
    @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/../includi/header.php'; ?>

    <div class="admin-wrapper">
    <h1>Gestione Albo d'oro</h1>

    <?php foreach ($messages as $m): ?>
      <div class="msg ok"><?= h($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
      <div class="msg err"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="admin-card-inline">
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

    <div class="admin-card-inline">
      <h3>Voci esistenti</h3>
      <?php if (empty($albo)): ?>
        <p>Nessun record presente.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Competizione</th>
              <th>Premio</th>
              <th>Vincitrice</th>
              <th>Periodo</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($albo as $row): ?>
              <tr>
                <td>
                  <strong><?= h($row['competizione']) ?></strong><br>
                  <span class="pill">ID #<?= (int)$row['id'] ?></span>
                </td>
                <td><?= h($row['premio'] ?: 'Vincente') ?></td>
                <td><?= h($row['vincitrice']) ?></td>
                <td>
                  <?php
                    $inizio = ($row['inizio_mese'] ? str_pad($row['inizio_mese'], 2, '0', STR_PAD_LEFT) : '--') . '/' . ($row['inizio_anno'] ?: '--');
                    $fine = ($row['fine_mese'] ? str_pad($row['fine_mese'], 2, '0', STR_PAD_LEFT) : '--') . '/' . ($row['fine_anno'] ?: '--');
                    echo h($inizio . ' - ' . $fine);
                  ?>
                </td>
                <td>
                  <form method="POST" style="display:inline-block;">
                    <input type="hidden" name="azione" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="btn-ghost" type="submit" onclick="return confirm('Eliminare questa voce?')">Elimina</button>
                  </form>
                  <details>
                    <summary>Modifica</summary>
                    <form method="POST" enctype="multipart/form-data" class="form-grid" style="margin-top:8px;">
                      <input type="hidden" name="azione" value="update">
                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                      <label>Competizione<input type="text" name="competizione" value="<?= h($row['competizione']) ?>" required></label>
                      <label>Premio<input type="text" name="premio" value="<?= h($row['premio']) ?>"></label>
                      <label>Vincitrice<input type="text" name="vincitrice" value="<?= h($row['vincitrice']) ?>" required></label>
                      <div class="file-input">
                        <label class="file-label">
                          <span class="file-btn">Scegli file</span>
                          <span class="file-name"><?= h($row['vincitrice_logo'] ?: 'Nessun file selezionato') ?></span>
                          <input type="file" name="vincitrice_logo_file" accept="image/png,image/jpeg,image/webp" onchange="this.parentElement.querySelector('.file-name').textContent = this.files?.[0]?.name || 'Nessun file selezionato';">
                        </label>
                      </div>
                      <label>Inizio mese<input type="number" name="inizio_mese" min="1" max="12" value="<?= h($row['inizio_mese']) ?>"></label>
                      <label>Inizio anno<input type="number" name="inizio_anno" min="2000" max="2100" value="<?= h($row['inizio_anno']) ?>"></label>
                      <label>Fine mese<input type="number" name="fine_mese" min="1" max="12" value="<?= h($row['fine_mese']) ?>"></label>
                      <label>Fine anno<input type="number" name="fine_anno" min="2000" max="2100" value="<?= h($row['fine_anno']) ?>"></label>
                      <div class="actions" style="grid-column: 1/-1;">
                        <button class="btn-primary" type="submit">Aggiorna</button>
                      </div>
                    </form>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div id="footer-container"></div>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
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
