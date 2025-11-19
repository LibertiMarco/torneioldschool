<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
  header("Location: /torneioldschool/index.php");
  exit;
}

require_once __DIR__ . '/../includi/db.php';

$errore = '';
$successo = '';
$titolo = '';
$contenuto = '';

$mediaDir = __DIR__ . '/../img/blog_media/';
$allowedImages = ['jpg', 'jpeg', 'png', 'webp'];
$allowedVideos = ['mp4', 'webm', 'ogg'];

function sanitizeText(?string $value): string {
  return trim((string)$value);
}

function detectMediaType(string $filename, array $allowedImages, array $allowedVideos): ?string {
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  if (in_array($ext, $allowedImages, true)) {
    return 'image';
  }
  if (in_array($ext, $allowedVideos, true)) {
    return 'video';
  }
  return null;
}

function saveMediaFile(array $file, string $uploadDir, array $allowedImages, array $allowedVideos, string &$errore): ?array {
  if (empty($file) || !array_key_exists('error', $file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    return null;
  }

  if ($file['error'] !== UPLOAD_ERR_OK) {
    $errore = 'Errore nel caricamento dei file multimediali.';
    return null;
  }

  $tipo = detectMediaType($file['name'] ?? '', $allowedImages, $allowedVideos);
  if (!$tipo) {
    $errore = 'Formato non supportato. Carica immagini JPG/PNG/WEBP o video MP4/WEBM/OGG.';
    return null;
  }

  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    $errore = 'Impossibile creare la cartella per i media.';
    return null;
  }

  try {
    $random = bin2hex(random_bytes(4));
  } catch (Throwable $th) {
    $random = bin2hex(openssl_random_pseudo_bytes(4));
  }

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $nomeFile = 'media_' . date('Ymd_His') . '_' . $random . '.' . $ext;
  $dest = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nomeFile;

  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $errore = 'Salvataggio del file non riuscito.';
    return null;
  }

  return ['path' => $nomeFile, 'tipo' => $tipo];
}

function getNextMediaOrder(mysqli $conn, int $postId): int {
  $stmt = $conn->prepare("SELECT COALESCE(MAX(ordine), 0) AS max_ordine FROM blog_media WHERE post_id = ?");
  if (!$stmt) {
    return 1;
  }
  $stmt->bind_param('i', $postId);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return ((int)($res['max_ordine'] ?? 0)) + 1;
}

function addMediaToPost(mysqli $conn, int $postId, array $files, string $uploadDir, array $allowedImages, array $allowedVideos, string &$errore): bool {
  if (empty($files) || !isset($files['name'])) {
    return true;
  }

  $isMulti = is_array($files['name']);
  $count = $isMulti ? count($files['name']) : 1;
  $ordine = getNextMediaOrder($conn, $postId);

  for ($i = 0; $i < $count; $i++) {
    $file = [
      'name' => $isMulti ? ($files['name'][$i] ?? null) : ($files['name'] ?? null),
      'type' => $isMulti ? ($files['type'][$i] ?? null) : ($files['type'] ?? null),
      'tmp_name' => $isMulti ? ($files['tmp_name'][$i] ?? null) : ($files['tmp_name'] ?? null),
      'error' => $isMulti ? ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) : ($files['error'] ?? UPLOAD_ERR_NO_FILE),
      'size' => $isMulti ? ($files['size'][$i] ?? 0) : ($files['size'] ?? 0),
    ];

    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
      continue;
    }

    $result = saveMediaFile($file, $uploadDir, $allowedImages, $allowedVideos, $errore);
    if (!$result) {
      return false;
    }

    $stmt = $conn->prepare("INSERT INTO blog_media (post_id, tipo, file_path, ordine) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
      $errore = 'Errore interno durante il salvataggio dei media.';
      return false;
    }
    $stmt->bind_param('issi', $postId, $result['tipo'], $result['path'], $ordine);
    if (!$stmt->execute()) {
      $errore = 'Impossibile associare il file caricato.';
      $stmt->close();
      return false;
    }
    $stmt->close();
    $ordine++;
  }

  return true;
}

function removeMediaFile(string $dir, ?string $file): void {
  if (!$file) {
    return;
  }
  $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR);
  if (is_file($path)) {
    @unlink($path);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $azioneForm = $_POST['azione'] ?? 'crea';

  if ($azioneForm === 'crea') {
    $titolo = sanitizeText($_POST['titolo'] ?? '');
    $contenuto = sanitizeText($_POST['contenuto'] ?? '');

    if ($titolo === '' || $contenuto === '') {
      $errore = 'Compila titolo e contenuto per pubblicare un articolo.';
    } else {
      $stmt = $conn->prepare(
        "INSERT INTO blog_post (titolo, contenuto, immagine, data_pubblicazione)
         VALUES (?, ?, NULL, NOW())"
      );
      if ($stmt) {
        $stmt->bind_param('ss', $titolo, $contenuto);
        if ($stmt->execute()) {
          $postId = $stmt->insert_id;
          $titolo = '';
          $contenuto = '';
          if (addMediaToPost($conn, $postId, $_FILES['media'] ?? [], $mediaDir, $allowedImages, $allowedVideos, $errore)) {
            $successo = 'Articolo pubblicato correttamente!';
          }
        } else {
          $errore = 'Impossibile salvare l\'articolo. Riprova.';
        }
        $stmt->close();
      } else {
        $errore = 'Errore interno: operazione non disponibile.';
      }
    }
  }

  if ($azioneForm === 'modifica') {
    $id = (int)($_POST['articolo_id'] ?? 0);
    $nuovoTitolo = sanitizeText($_POST['titolo_mod'] ?? '');
    $nuovoContenuto = sanitizeText($_POST['contenuto_mod'] ?? '');

    if ($id <= 0 || $nuovoTitolo === '' || $nuovoContenuto === '') {
      $errore = 'Seleziona un articolo valido e compila tutti i campi.';
    } else {
      $stmt = $conn->prepare("UPDATE blog_post SET titolo = ?, contenuto = ? WHERE id = ?");
      if ($stmt) {
        $stmt->bind_param('ssi', $nuovoTitolo, $nuovoContenuto, $id);
        if ($stmt->execute()) {
          if (addMediaToPost($conn, $id, $_FILES['media_mod'] ?? [], $mediaDir, $allowedImages, $allowedVideos, $errore)) {
            $successo = 'Articolo aggiornato con successo.';
          }
        } else {
          $errore = 'Aggiornamento non riuscito.';
        }
        $stmt->close();
      } else {
        $errore = 'Errore interno durante l\'aggiornamento.';
      }
    }
  }

  if ($azioneForm === 'elimina') {
    $id = (int)($_POST['articolo_id'] ?? 0);
    if ($id <= 0) {
      $errore = 'Articolo non valido.';
    } else {
      $mediaStmt = $conn->prepare("SELECT file_path FROM blog_media WHERE post_id = ?");
      if ($mediaStmt) {
        $mediaStmt->bind_param('i', $id);
        $mediaStmt->execute();
        $mediaRes = $mediaStmt->get_result();
        while ($row = $mediaRes->fetch_assoc()) {
          removeMediaFile($mediaDir, $row['file_path']);
        }
        $mediaStmt->close();
      }

      $del = $conn->prepare("DELETE FROM blog_post WHERE id = ?");
      if ($del) {
        $del->bind_param('i', $id);
        if ($del->execute()) {
          $successo = 'Articolo eliminato correttamente.';
        } else {
          $errore = 'Impossibile eliminare l\'articolo.';
        }
        $del->close();
      } else {
        $errore = 'Errore interno durante l\'eliminazione.';
      }
    }
  }

  if ($azioneForm === 'elimina_media') {
    $mediaId = (int)($_POST['media_id'] ?? 0);
    if ($mediaId <= 0) {
      $errore = 'Media non valido.';
    } else {
      $stmt = $conn->prepare("SELECT file_path FROM blog_media WHERE id = ?");
      if ($stmt) {
        $stmt->bind_param('i', $mediaId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
          $del = $conn->prepare("DELETE FROM blog_media WHERE id = ?");
          if ($del) {
            $del->bind_param('i', $mediaId);
            if ($del->execute()) {
              removeMediaFile($mediaDir, $res['file_path'] ?? null);
              $successo = 'Elemento multimediale rimosso.';
            } else {
              $errore = 'Impossibile rimuovere il file selezionato.';
            }
            $del->close();
          } else {
            $errore = 'Errore interno durante la rimozione.';
          }
        } else {
          $errore = 'Elemento non trovato.';
        }
      } else {
        $errore = 'Errore interno durante la ricerca del file.';
      }
    }
  }
}

$articoli = [];
$res = $conn->query(
  "SELECT id, titolo, contenuto,
          DATE_FORMAT(data_pubblicazione, '%d/%m/%Y %H:%i') AS data_pubblicazione
   FROM blog_post
   ORDER BY data_pubblicazione DESC"
);
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $articoli[] = $row;
  }
}

$mediaByPost = [];
$mediaRes = $conn->query(
  "SELECT id, post_id, tipo, file_path, ordine
   FROM blog_media
   ORDER BY post_id ASC, ordine ASC, id ASC"
);
if ($mediaRes) {
  while ($row = $mediaRes->fetch_assoc()) {
    $mediaByPost[(int)$row['post_id']][] = [
      'id' => (int)$row['id'],
      'tipo' => $row['tipo'],
      'file_path' => $row['file_path'],
      'ordine' => (int)$row['ordine']
    ];
  }
}

foreach ($articoli as &$articolo) {
  $articolo['media'] = $mediaByPost[$articolo['id']] ?? [];
}
unset($articolo);

$articoliJson = json_encode($articoli, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestione Blog</title>
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
    .admin-select-action {
      margin: 30px 0 20px;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
    }
    .admin-select-action select {
      padding: 10px 14px;
      border-radius: 8px;
      border: 1px solid #cfd8e3;
    }
    .hidden {
      display: none !important;
    }
    .blog-form {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    .blog-form textarea {
      min-height: 220px;
      resize: vertical;
    }
    .admin-alert {
      border-radius: 10px;
      padding: 12px 18px;
      font-weight: 600;
      margin-bottom: 18px;
    }
    .admin-alert.success {
      background: #e8f6ef;
      color: #065f46;
      border: 1px solid #34d399;
    }
    .admin-alert.error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #f87171;
    }
    .file-upload {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
    }
    .file-upload input[type="file"] {
      display: none;
    }
    .file-upload-label {
      background: #15293e;
      color: #fff;
      padding: 10px 20px;
      border-radius: 999px;
      cursor: pointer;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .file-upload-label:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 20px rgba(21, 41, 62, 0.2);
    }
    .file-upload-filename {
      color: #475467;
      font-size: 0.95rem;
    }
    .file-upload small {
      width: 100%;
      color: #6b7280;
    }
    .form-section {
      margin-top: 25px;
    }
    .blog-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    .blog-table th,
    .blog-table td {
      padding: 12px;
      border-bottom: 1px solid #eef2ff;
      text-align: left;
    }
    .blog-table th {
      background: #f8fafc;
      font-size: 0.9rem;
    }
    .blog-table td:last-child {
      text-align: right;
    }
    .media-list {
      margin-top: 20px;
      border: 1px solid #e4e9f7;
      border-radius: 12px;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      background: #f9fbff;
    }
    .media-item {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      align-items: center;
      border-bottom: 1px solid #e4e9f7;
      padding-bottom: 10px;
    }
    .media-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .media-item-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .media-item strong {
      font-size: 0.9rem;
    }
    .media-item span {
      font-size: 0.85rem;
      color: #6b7280;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../includi/header.php'; ?>

<main class="admin-wrapper">
  <section class="admin-container">
    <a class="admin-back-link" href="/torneioldschool/admin_dashboard.php">Torna alla dashboard</a>
    <h1 class="admin-title">Gestione articoli del blog</h1>
    <p>Utilizza il selettore per creare nuovi articoli, modificarli o eliminarli. Puoi caricare più immagini e video per creare caroselli accattivanti.</p>

    <div class="admin-select-action">
      <label for="azioneBlog">Seleziona azione:</label>
      <select id="azioneBlog">
        <option value="crea" selected>Crea articolo</option>
        <option value="modifica">Modifica articolo</option>
        <option value="elimina">Elimina articolo</option>
      </select>
    </div>

    <?php if ($successo): ?>
      <div class="admin-alert success"><?= htmlspecialchars($successo) ?></div>
    <?php endif; ?>

    <?php if ($errore): ?>
      <div class="admin-alert error"><?= htmlspecialchars($errore) ?></div>
    <?php endif; ?>

    <form method="POST" class="admin-form blog-form form-section" data-section="crea" enctype="multipart/form-data">
      <input type="hidden" name="azione" value="crea">
      <label for="titolo">Titolo</label>
      <input type="text" id="titolo" name="titolo" value="<?= htmlspecialchars($titolo) ?>" required>

      <label for="contenuto">Contenuto</label>
      <textarea id="contenuto" name="contenuto" required><?= htmlspecialchars($contenuto) ?></textarea>

      <div>
        <label>Media (immagini o video per il carosello)</label>
        <div class="file-upload">
          <label for="media" class="file-upload-label">
            <span>📁 Scegli file</span>
          </label>
          <input type="file" id="media" name="media[]" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.ogg" multiple>
          <span class="file-upload-filename" data-default="Nessun file selezionato">Nessun file selezionato</span>
          <small>I file verranno mostrati nell'ordine di caricamento.</small>
        </div>
      </div>

      <button type="submit" class="btn-primary">Pubblica articolo</button>
    </form>

    <form method="POST" class="admin-form blog-form form-section hidden" data-section="modifica" enctype="multipart/form-data">
      <input type="hidden" name="azione" value="modifica">
      <input type="hidden" name="articolo_id" id="articolo_id_mod">

      <label for="modSelectArticolo">Seleziona articolo</label>
      <select id="modSelectArticolo" required <?= empty($articoli) ? 'disabled' : '' ?>>
        <option value="">-- Scegli un articolo --</option>
        <?php foreach ($articoli as $articolo): ?>
          <option value="<?= (int)$articolo['id'] ?>"><?= htmlspecialchars($articolo['titolo']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($articoli)): ?>
        <small>Non ci sono articoli da modificare.</small>
      <?php endif; ?>

      <label for="titolo_mod">Titolo</label>
      <input type="text" id="titolo_mod" name="titolo_mod" required>

      <label for="contenuto_mod">Contenuto</label>
      <textarea id="contenuto_mod" name="contenuto_mod" required></textarea>

      <div>
        <label>Aggiungi nuovi media</label>
        <div class="file-upload">
          <label for="media_mod" class="file-upload-label">
            <span>📁 Carica file</span>
          </label>
          <input type="file" id="media_mod" name="media_mod[]" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.ogg" multiple>
          <span class="file-upload-filename" data-default="Nessun file selezionato">Nessun file selezionato</span>
          <small>I file si aggiungeranno al carosello esistente.</small>
        </div>
      </div>

      <div class="media-list" id="mediaList">
        <p>Nessun media associato.</p>
      </div>

      <button type="submit" class="btn-primary" <?= empty($articoli) ? 'disabled' : '' ?>>Salva modifiche</button>
    </form>

    <section class="form-section hidden" data-section="elimina">
      <?php if (empty($articoli)): ?>
        <p>Non ci sono articoli da eliminare.</p>
      <?php else: ?>
        <table class="blog-table">
          <thead>
            <tr>
              <th>Titolo</th>
              <th>Pubblicato il</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($articoli as $articolo): ?>
              <tr>
                <td><?= htmlspecialchars($articolo['titolo']) ?></td>
                <td><?= htmlspecialchars($articolo['data_pubblicazione']) ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('Vuoi eliminare questo articolo e tutti i suoi media?');">
                    <input type="hidden" name="azione" value="elimina">
                    <input type="hidden" name="articolo_id" value="<?= (int)$articolo['id'] ?>">
                    <button type="submit" class="btn-danger">Elimina</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </section>
</main>

<div id="footer-container"></div>
<script>
const sezioni = document.querySelectorAll('[data-section]');
const selettoreAzioni = document.getElementById('azioneBlog');
const postsData = <?= $articoliJson ?: '[]' ?>;

function mostraSezione(nome) {
  sezioni.forEach(section => {
    section.classList.toggle('hidden', section.dataset.section !== nome);
  });
}

selettoreAzioni?.addEventListener('change', e => mostraSezione(e.target.value));
mostraSezione(selettoreAzioni?.value || 'crea');

const moduloSelect = document.getElementById('modSelectArticolo');
const idField = document.getElementById('articolo_id_mod');
const titoloField = document.getElementById('titolo_mod');
const contenutoField = document.getElementById('contenuto_mod');
const mediaList = document.getElementById('mediaList');

function renderMediaList(items = []) {
  if (!mediaList) return;
  if (!items.length) {
    mediaList.innerHTML = '<p>Ancora nessun media associato.</p>';
    return;
  }

  mediaList.innerHTML = items.map(item => `
    <div class="media-item">
      <div class="media-item-info">
        <strong>${item.tipo === 'video' ? 'Video' : 'Immagine'}</strong>
        <span>${item.file_path || ''}</span>
      </div>
      <form method="POST" onsubmit="return confirm('Rimuovere questo file dal carosello?');">
        <input type="hidden" name="azione" value="elimina_media">
        <input type="hidden" name="media_id" value="${item.id}">
        <button type="submit" class="btn-danger">Rimuovi</button>
      </form>
    </div>
  `).join('');
}

moduloSelect?.addEventListener('change', e => {
  const id = Number(e.target.value);
  const articolo = postsData.find(post => Number(post.id) === id);

  if (!articolo) {
    idField.value = '';
    titoloField.value = '';
    contenutoField.value = '';
    renderMediaList([]);
    return;
  }

  idField.value = articolo.id;
  titoloField.value = articolo.titolo || '';
  contenutoField.value = articolo.contenuto || '';
  renderMediaList(Array.isArray(articolo.media) ? articolo.media : []);
});

document.querySelectorAll('.file-upload input[type="file"]').forEach(input => {
  input.addEventListener('change', () => {
    const label = input.closest('.file-upload').querySelector('.file-upload-filename');
    if (!label) return;
    if (input.files && input.files.length) {
      const names = Array.from(input.files).map(file => file.name);
      label.textContent = names.join(', ');
    } else {
      label.textContent = label.dataset.default || 'Nessun file selezionato';
    }
  });
});

document.addEventListener("DOMContentLoaded", () => {
  fetch("/torneioldschool/includi/footer.html")
    .then(r => r.text())
    .then(html => {
      const footer = document.getElementById("footer-container");
      if (footer) footer.innerHTML = html;
    })
    .catch(err => console.error("Errore nel caricamento del footer:", err));
});
</script>
</body>
</html>
