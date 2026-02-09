<?php
require_once __DIR__ . '/../includi/admin_guard.php';

require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/image_optimizer.php';

$errors = [];
$messages = [];
$staffList = [];

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function allowedCategories(): array {
    return [
        'arbitro' => 'Arbitro',
        'videomaker' => 'Videomaker',
        'organizzazione' => 'Organizzazione',
        'staff' => 'Staff',
    ];
}

function normalizeCategory(?string $cat): string {
    $cat = strtolower(trim((string)$cat));
    $categories = allowedCategories();
    return array_key_exists($cat, $categories) ? $cat : 'staff';
}

function ensureStaffTable(mysqli $conn, array &$errors): void {
    $sql = "CREATE TABLE IF NOT EXISTS staff (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        ruolo VARCHAR(150) DEFAULT NULL,
        categoria VARCHAR(60) NOT NULL DEFAULT 'staff',
        foto VARCHAR(255) DEFAULT '/img/giocatori/unknown.jpg',
        ordinamento INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_staff_categoria (categoria),
        KEY idx_staff_ordinamento (ordinamento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        $errors[] = "Impossibile verificare/creare la tabella staff.";
    }
}

function saveStaffPhoto(string $field, ?string $existing, array &$errors): ?string {
    if (!isset($_FILES[$field]) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return $existing;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = "Errore nel caricamento della foto.";
        return $existing;
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $errors[] = "Foto troppo grande (max 5MB).";
        return $existing;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo instanceof finfo) {
        unset($finfo);
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$mime || !isset($allowed[$mime])) {
        $errors[] = "Formato immagine non valido. Usa JPG, PNG o WEBP.";
        return $existing;
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($file['name'], PATHINFO_FILENAME));
    $slug = trim($slug ?: 'staff', '-');
    try {
        $random = bin2hex(random_bytes(3));
    } catch (Throwable $th) {
        $random = (string)mt_rand(1000, 9999);
    }
    $filename = 'staff_' . date('Ymd_His') . '_' . $random . '.' . $allowed[$mime];

    $destDir = realpath(__DIR__ . '/../img/giocatori');
    if (!$destDir) {
        $destDir = __DIR__ . '/../img/giocatori';
        @mkdir($destDir, 0755, true);
    }
    $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $errors[] = "Impossibile salvare la foto sul server.";
        return $existing;
    }

    optimize_image_file($destPath, [
        'maxWidth' => 1200,
        'maxHeight' => 1200,
        'quality' => 82,
        'maxBytes' => 6 * 1024 * 1024,
    ]);

    $default = '/img/giocatori/unknown.jpg';
    $existingBase = $existing ? basename($existing) : '';
    if ($existing && $existing !== $default && $existingBase !== 'unknown.jpg') {
        $oldPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $existingBase;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    return '/img/giocatori/' . $filename;
}

if (!$conn || $conn->connect_error) {
    $errors[] = "Connessione al database non disponibile.";
} else {
    $conn->set_charset('utf8mb4');
    ensureStaffTable($conn, $errors);

    if (empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $azione = $_POST['azione'] ?? '';
        $nome = trim($_POST['nome'] ?? '');
        $ruolo = trim($_POST['ruolo'] ?? '');
        $categoria = normalizeCategory($_POST['categoria'] ?? 'staff');
        $ordinamento = isset($_POST['ordinamento']) && $_POST['ordinamento'] !== '' ? (int)$_POST['ordinamento'] : null;
        $id = (int)($_POST['id'] ?? 0);

        if ($azione === 'create') {
            if ($nome === '') {
                $errors[] = "Inserisci almeno il nome.";
            } else {
                $foto = saveStaffPhoto('foto', '/img/giocatori/unknown.jpg', $errors);
                if (empty($errors)) {
                    if ($ordinamento === null) {
                        $stmt = $conn->prepare("INSERT INTO staff (nome, ruolo, categoria, foto, ordinamento) VALUES (?, ?, ?, ?, NULL)");
                        if ($stmt) {
                            $stmt->bind_param('ssss', $nome, $ruolo, $categoria, $foto);
                        }
                    } else {
                        $stmt = $conn->prepare("INSERT INTO staff (nome, ruolo, categoria, foto, ordinamento) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('ssssi', $nome, $ruolo, $categoria, $foto, $ordinamento);
                        }
                    }
                    if ($stmt) {
                        $stmt->execute();
                        $stmt->close();
                        $messages[] = "Membro staff aggiunto correttamente.";
                    } else {
                        $errors[] = "Errore interno durante il salvataggio.";
                    }
                }
            }
        } elseif ($azione === 'update' && $id > 0) {
            if ($nome === '') {
                $errors[] = "Il nome non puÃ² essere vuoto.";
            } else {
                $currentFoto = '/img/giocatori/unknown.jpg';
                $current = $conn->prepare("SELECT foto FROM staff WHERE id = ?");
                if ($current) {
                    $current->bind_param('i', $id);
                    $current->execute();
                    $current->bind_result($currentFoto);
                    $current->fetch();
                    $current->close();
                }
                $foto = saveStaffPhoto('foto', $currentFoto, $errors);
                if (empty($errors)) {
                    if ($ordinamento === null) {
                        $stmt = $conn->prepare("UPDATE staff SET nome = ?, ruolo = ?, categoria = ?, foto = ?, ordinamento = NULL WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('ssssi', $nome, $ruolo, $categoria, $foto, $id);
                        }
                    } else {
                        $stmt = $conn->prepare("UPDATE staff SET nome = ?, ruolo = ?, categoria = ?, foto = ?, ordinamento = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('ssssii', $nome, $ruolo, $categoria, $foto, $ordinamento, $id);
                        }
                    }
                    if ($stmt) {
                        $stmt->execute();
                        $stmt->close();
                        $messages[] = "Dati aggiornati.";
                    } else {
                        $errors[] = "Impossibile aggiornare il record.";
                    }
                }
            }
        } elseif ($azione === 'delete' && $id > 0) {
            $foto = '/img/giocatori/unknown.jpg';
            $current = $conn->prepare("SELECT foto FROM staff WHERE id = ?");
            if ($current) {
                $current->bind_param('i', $id);
                $current->execute();
                $current->bind_result($foto);
                $current->fetch();
                $current->close();
            }
            $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $messages[] = "Membro eliminato.";

                $default = '/img/giocatori/unknown.jpg';
                $baseFoto = $foto ? basename($foto) : '';
                $destDir = realpath(__DIR__ . '/../img/giocatori');
                if ($foto && $foto !== $default && $baseFoto !== 'unknown.jpg' && $destDir) {
                    $full = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseFoto;
                    if (is_file($full)) {
                        @unlink($full);
                    }
                }
            } else {
                $errors[] = "Errore nell'eliminazione.";
            }
        }
    }

    if (empty($errors)) {
        $res = $conn->query("SELECT id, nome, ruolo, categoria, foto, ordinamento FROM staff ORDER BY categoria, COALESCE(ordinamento, 9999), nome");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $staffList[] = $row;
            }
            $res->free();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-VZ982XSRRN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-VZ982XSRRN');
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Gestione Staff</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link rel="apple-touch-icon" href="/img/logo_old_school.png">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; }
    main.admin-wrapper { flex: 1 0 auto; padding-bottom: 80px; }
    .staff-forms { display: grid; gap: 18px; margin-top: 20px; }
    .staff-card { background: #fff; border-radius: 14px; padding: 20px; border: 1px solid #e4e8f0; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08); }
    .staff-card h2 { margin: 0 0 14px; }
    .staff-form { display: grid; gap: 12px; }
    .staff-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px 16px; align-items: center; }
    .staff-row .actions { display: flex; gap: 10px; align-items: center; }
    .staff-photo { display: flex; align-items: center; gap: 10px; }
    .staff-photo img { width: 64px; height: 64px; object-fit: cover; border-radius: 12px; border: 1px solid #e5e9f2; background: #f6f7fb; }
    .admin-input { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #d0d7e1; }
    .admin-select-action { margin-top: 0; }
    .pill { display: inline-block; background: #15293e; color: #fff; padding: 5px 10px; border-radius: 999px; font-weight: 700; font-size: 0.9rem; }
    .inline-message { padding: 10px 12px; border-radius: 10px; margin: 10px 0; }
    .inline-message.success { background: #e7f6ec; color: #0f5132; border: 1px solid #badbcc; }
    .inline-message.error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
    .admin-title { margin-bottom: 10px; }
    .staff-table-wrapper { display: grid; gap: 12px; }
    .hint { color: #4c5b71; font-size: 0.95rem; }
    @media (max-width: 720px) {
      .staff-row { grid-template-columns: 1fr; }
      .staff-photo { margin-top: 6px; }
      .staff-row .actions { justify-content: flex-start; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <section class="admin-container">
      <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
      <h1 class="admin-title">Gestione Staff</h1>
      <p class="hint">Aggiungi arbitri, videomaker o altri ruoli dello staff visibili nella pagina "Chi siamo".</p>

      <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $m): ?>
          <div class="inline-message success"><?= h($m) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $e): ?>
          <div class="inline-message error"><?= h($e) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="staff-forms">
        <div class="staff-card">
          <h2>Nuovo membro</h2>
          <form class="staff-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="azione" value="create">
            <div>
              <label>Nome e cognome*</label>
              <input class="admin-input" type="text" name="nome" required>
            </div>
            <div>
              <label>Ruolo (es. Arbitro, Videomaker)</label>
              <input class="admin-input" type="text" name="ruolo" placeholder="Arbitro">
            </div>
            <div>
              <label>Categoria</label>
              <select class="admin-input" name="categoria">
                <?php foreach (allowedCategories() as $key => $label): ?>
                  <option value="<?= h($key) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Ordine (opzionale)</label>
              <input class="admin-input" type="number" name="ordinamento" min="1" placeholder="1">
            </div>
            <div>
              <label>Foto (jpg, png, webp - max 5MB)</label>
              <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
            </div>
            <div>
              <button type="submit" class="hero-btn">Salva membro</button>
            </div>
          </form>
        </div>

        <div class="staff-card">
          <div class="staff-header-row" style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <h2 style="margin:0;">Elenco staff</h2>
            <span class="pill"><?= count($staffList) ?> totali</span>
          </div>
          <?php if (empty($staffList)): ?>
            <p class="hint">Non ci sono membri. Aggiungine uno con il modulo sopra.</p>
          <?php else: ?>
            <div class="staff-table-wrapper">
              <?php foreach ($staffList as $member): ?>
                <form class="staff-row" method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
                  <div>
                    <label>Nome</label>
                    <input class="admin-input" type="text" name="nome" required value="<?= h($member['nome'] ?? '') ?>">
                  </div>
                  <div>
                    <label>Ruolo</label>
                    <input class="admin-input" type="text" name="ruolo" value="<?= h($member['ruolo'] ?? '') ?>">
                  </div>
                  <div>
                    <label>Categoria</label>
                    <select class="admin-input" name="categoria">
                      <?php foreach (allowedCategories() as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= ($member['categoria'] ?? '') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label>Ordine</label>
                    <input class="admin-input" type="number" name="ordinamento" min="1" value="<?= h($member['ordinamento'] ?? '') ?>">
                  </div>
                  <div class="staff-photo">
                    <img src="<?= h($member['foto'] ?: '/img/giocatori/unknown.jpg') ?>" alt="Foto <?= h($member['nome'] ?? '') ?>" onerror="this.src='/img/giocatori/unknown.jpg';">
                    <div>
                      <label style="display:block;">Aggiorna foto</label>
                      <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                    </div>
                  </div>
                  <div class="actions">
                    <button type="submit" name="azione" value="update" class="hero-btn hero-btn--small">Aggiorna</button>
                    <button type="submit" name="azione" value="delete" class="hero-btn hero-btn--ghost hero-btn--small" onclick="return confirm('Eliminare <?= h($member['nome'] ?? '') ?>?');">Elimina</button>
                  </div>
                </form>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <div id="footer-container"></div>
  <script src="/includi/app.min.js?v=20251220"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      fetch("/includi/footer.html")
        .then(r => r.text())
        .then(html => {
          const footer = document.getElementById("footer-container");
          if (footer) footer.innerHTML = html;
        })
        .catch(err => console.error("Errore footer:", err));

      if (typeof initHeaderInteractions === "function") {
        initHeaderInteractions();
      }
    });
  </script>
</body>
</html>


