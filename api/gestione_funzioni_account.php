<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/user_features.php';

$messages = [];
$errors = [];
$users = [];
$selectedUserId = 0;
$selectedUserFlags = normalize_user_feature_flags([]);
$featureDefinitions = user_feature_definitions();
$csrfKey = 'account_feature_flags';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
    ensure_user_feature_flags_column($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_require($csrfKey);

        $selectedUserId = (int)($_POST['utente_id'] ?? 0);
        $selectedUserFlags = extract_user_feature_flags_from_request($_POST);

        if ($selectedUserId <= 0) {
            $errors[] = 'Seleziona un account da configurare.';
        } else {
            $stmt = $conn->prepare("SELECT id, nome, cognome, email FROM utenti WHERE id = ? LIMIT 1");
            if (!$stmt) {
                $errors[] = 'Impossibile caricare l account selezionato.';
            } else {
                $stmt->bind_param("i", $selectedUserId);
                $stmt->execute();
                $userRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$userRow) {
                    $errors[] = 'Account non trovato.';
                } elseif (!save_user_feature_flags($conn, $selectedUserId, $selectedUserFlags)) {
                    $errors[] = 'Salvataggio non riuscito. Riprova.';
                } else {
                    $displayName = trim(($userRow['nome'] ?? '') . ' ' . ($userRow['cognome'] ?? ''));
                    if ($displayName === '') {
                        $displayName = $userRow['email'] ?? 'Account';
                    }
                    $messages[] = 'Funzioni aggiornate per ' . $displayName . '.';
                    $selectedUserFlags = load_user_feature_flags($conn, $selectedUserId);
                }
            }
        }
    }

    $result = $conn->query("SELECT id, nome, cognome, email, ruolo, feature_flags FROM utenti ORDER BY cognome ASC, nome ASC, email ASC");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $row['feature_flags'] = normalize_user_feature_flags($row['feature_flags'] ?? null);
            $users[] = $row;
        }
        $result->close();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Funzioni Account</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link rel="apple-touch-icon" href="/img/logo_old_school.png">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; background: #f6f8fb; }
    main.admin-wrapper { max-width: 1120px; margin: 0 auto; padding: 36px 16px 70px; flex: 1 0 auto; }
    .panel-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 20px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .msg { padding: 10px 12px; border-radius: 10px; margin-bottom: 10px; font-weight: 700; }
    .msg.ok { background: #e8f6ef; color: #065f46; border: 1px solid #34d399; }
    .msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
    .feature-form { display: grid; gap: 18px; }
    .feature-form label.form-label { display: flex; flex-direction: column; gap: 8px; font-weight: 700; color: #15293e; }
    .feature-form select { padding: 11px 12px; border: 1px solid #d7dce5; border-radius: 10px; background: #fff; }
    .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
    .feature-option {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      border: 1px solid #dce4ef;
      border-radius: 12px;
      padding: 14px;
      background: #f8fafc;
    }
    .feature-option input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; flex: 0 0 auto; }
    .feature-option strong { display: block; color: #15293e; margin-bottom: 4px; }
    .feature-option span { display: block; color: #5c6572; font-size: 0.93rem; line-height: 1.45; }
    .form-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .helper-text { color: #475569; font-size: 0.95rem; }
    .overview-table { width: 100%; border-collapse: collapse; }
    .overview-table th, .overview-table td { padding: 12px 10px; border-bottom: 1px solid #e5eaf0; text-align: left; vertical-align: top; }
    .overview-table th { background: #f8fafc; color: #15293e; }
    .feature-pill-list { display: flex; flex-wrap: wrap; gap: 8px; }
    .feature-pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; background: #e8edf5; color: #15293e; font-size: 0.85rem; font-weight: 700; }
    .feature-pill.is-empty { background: #f1f5f9; color: #64748b; }
    @media (max-width: 720px) {
      .overview-table { display: block; overflow-x: auto; }
    }
  </style>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
    <h1 class="admin-title">Funzioni account</h1>
    <p style="margin: 0 0 18px; color: #475569;">Abilita o disabilita piu funzioni per ogni account. Gli admin vedono comunque sempre tutte le funzioni nascoste.</p>

    <?php foreach ($messages as $message): ?>
      <div class="msg ok"><?= h($message) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
      <div class="msg err"><?= h($error) ?></div>
    <?php endforeach; ?>

    <section class="panel-card">
      <h3 style="margin: 0 0 12px; color: #15293e;">Configura un account</h3>
      <form method="POST" class="feature-form" autocomplete="off">
        <?= csrf_field($csrfKey) ?>
        <label class="form-label">
          Account
          <select name="utente_id" id="featureUserSelect" required>
            <option value="">-- scegli un account --</option>
            <?php foreach ($users as $user): ?>
              <?php
                $userId = (int)($user['id'] ?? 0);
                $displayName = trim(($user['cognome'] ?? '') . ' ' . ($user['nome'] ?? ''));
                if ($displayName === '') {
                    $displayName = $user['email'] ?? 'Account';
                }
              ?>
              <option value="<?= $userId ?>" <?= $userId === $selectedUserId ? 'selected' : '' ?>>
                <?= h($displayName) ?> - <?= h($user['email'] ?? '') ?><?= !empty($user['ruolo']) ? ' [' . h($user['ruolo']) . ']' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <div>
          <div style="font-weight: 700; color: #15293e; margin-bottom: 8px;">Funzioni disponibili</div>
          <div class="feature-grid">
            <?php foreach ($featureDefinitions as $featureKey => $featureConfig): ?>
              <label class="feature-option">
                <input
                  type="checkbox"
                  name="feature_flags[]"
                  value="<?= h($featureKey) ?>"
                  <?= !empty($selectedUserFlags[$featureKey]) ? 'checked' : '' ?>
                >
                <span>
                  <strong><?= h($featureConfig['label'] ?? $featureKey) ?></strong>
                  <span><?= h($featureConfig['description'] ?? '') ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn-primary" type="submit">Salva funzioni</button>
          <span class="helper-text">Puoi attivare piu voci contemporaneamente per lo stesso account.</span>
        </div>
      </form>
    </section>

    <section class="panel-card">
      <h3 style="margin: 0 0 12px; color: #15293e;">Panoramica account</h3>
      <table class="overview-table">
        <thead>
          <tr>
            <th>Account</th>
            <th>Ruolo</th>
            <th>Funzioni attive</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <?php
              $activeLabels = [];
              foreach (($user['feature_flags'] ?? []) as $featureKey => $enabled) {
                  if ($enabled && isset($featureDefinitions[$featureKey])) {
                      $activeLabels[] = $featureDefinitions[$featureKey]['label'];
                  }
              }
              $userName = trim(($user['nome'] ?? '') . ' ' . ($user['cognome'] ?? ''));
              if ($userName === '') {
                  $userName = $user['email'] ?? 'Account';
              }
            ?>
            <tr>
              <td>
                <strong><?= h($userName) ?></strong><br>
                <span style="color: #64748b; font-size: 0.93rem;"><?= h($user['email'] ?? '') ?></span>
              </td>
              <td><?= h($user['ruolo'] ?? 'user') ?></td>
              <td>
                <div class="feature-pill-list">
                  <?php if (!empty($activeLabels)): ?>
                    <?php foreach ($activeLabels as $label): ?>
                      <span class="feature-pill"><?= h($label) ?></span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="feature-pill is-empty">Nessuna funzione attiva</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>

  <div id="footer-container"></div>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const footer = document.getElementById('footer-container');
      if (footer) {
        fetch('/includi/footer.html')
          .then(response => response.text())
          .then(html => { footer.innerHTML = html; })
          .catch(err => console.error('Errore nel caricamento del footer:', err));
      }

      const select = document.getElementById('featureUserSelect');
      const checkboxes = Array.from(document.querySelectorAll('input[name="feature_flags[]"]'));

      const resetFlags = () => {
        checkboxes.forEach((checkbox) => {
          checkbox.checked = false;
        });
      };

      if (select) {
        select.addEventListener('change', async () => {
          const userId = select.value;
          if (!userId) {
            resetFlags();
            return;
          }

          try {
            const response = await fetch(`/api/get_utente.php?id=${encodeURIComponent(userId)}`);
            const data = await response.json();
            if (!data || data.error) {
              resetFlags();
              return;
            }

            const flags = data.feature_flags || {};
            checkboxes.forEach((checkbox) => {
              checkbox.checked = Boolean(flags[checkbox.value]);
            });
          } catch (error) {
            resetFlags();
            console.error('Errore nel caricamento delle funzioni account:', error);
          }
        });
      }
    });
  </script>
</body>
</html>
