<?php
require_once __DIR__ . '/../includi/admin_guard.php';

require_once __DIR__ . '/../includi/db.php';

$messages = [];
$errors = [];
$utentiDisponibili = [];
$giocatoriDisponibili = [];
$associazioni = [];
$csrfKey = 'assoc_account_giocatore';
$csrfToken = csrf_get_token($csrfKey);
$columnReady = false;

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ensureUtenteColumn(mysqli $conn, array &$errors): bool {
    $check = $conn->query("SHOW COLUMNS FROM giocatori LIKE 'utente_id'");
    if ($check && $check->num_rows > 0) {
        return true;
    }
    if (!$check) {
        $errors[] = "Impossibile verificare la tabella giocatori.";
        return false;
    }
    $sql = "
        ALTER TABLE giocatori
        ADD COLUMN utente_id INT UNSIGNED DEFAULT NULL,
        ADD UNIQUE KEY uq_giocatore_utente (utente_id),
        ADD CONSTRAINT fk_giocatore_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL
    ";
    if ($conn->query($sql)) {
        return true;
    }
    $errors[] = "Non riesco ad aggiungere il campo per l'associazione account. Errore: " . $conn->error;
    return false;
}

if (!$conn || $conn->connect_error) {
    $errors[] = "Connessione al database non disponibile.";
} else {
    $conn->set_charset('utf8mb4');
    $columnReady = ensureUtenteColumn($conn, $errors);

    if ($columnReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_require($csrfKey);
        $azione = $_POST['azione'] ?? '';

        if ($azione === 'associa') {
            $utenteId = (int)($_POST['utente_id'] ?? 0);
            $giocatoreId = (int)($_POST['giocatore_id'] ?? 0);

            if ($utenteId <= 0 || $giocatoreId <= 0) {
                $errors[] = "Seleziona sia l'account sia il giocatore.";
            } else {
                $stmtUser = $conn->prepare("SELECT id, nome, cognome, email FROM utenti WHERE id = ?");
                $stmtUser->bind_param("i", $utenteId);
                $stmtUser->execute();
                $user = $stmtUser->get_result()->fetch_assoc();
                $stmtUser->close();

                if (!$user) {
                    $errors[] = "Account selezionato non trovato.";
                } else {
                    $stmtPlayer = $conn->prepare("SELECT id, nome, cognome, utente_id FROM giocatori WHERE id = ?");
                    $stmtPlayer->bind_param("i", $giocatoreId);
                    $stmtPlayer->execute();
                    $player = $stmtPlayer->get_result()->fetch_assoc();
                    $stmtPlayer->close();

                    if (!$player) {
                        $errors[] = "Giocatore selezionato non trovato.";
                    } elseif (!empty($player['utente_id'])) {
                        $errors[] = "Questo giocatore ha giÃ  un account associato.";
                    } else {
                        $stmtAlready = $conn->prepare("
                            SELECT id, nome, cognome
                            FROM giocatori
                            WHERE utente_id = ?
                            LIMIT 1
                        ");
                        $stmtAlready->bind_param("i", $utenteId);
                        $stmtAlready->execute();
                        $already = $stmtAlready->get_result()->fetch_assoc();
                        $stmtAlready->close();

                        if ($already) {
                            $errors[] = "L'account Ã¨ giÃ  associato a {$already['nome']} {$already['cognome']}.";
                        } else {
                            $stmtUpdate = $conn->prepare("UPDATE giocatori SET utente_id = ? WHERE id = ?");
                            $stmtUpdate->bind_param("ii", $utenteId, $giocatoreId);
                            if ($stmtUpdate->execute()) {
                                $messages[] = "Associazione salvata correttamente.";
                            } else {
                                $errors[] = "Impossibile salvare l'associazione.";
                            }
                            $stmtUpdate->close();
                        }
                    }
                }
            }
        } elseif ($azione === 'rimuovi') {
            $giocatoreId = (int)($_POST['giocatore_rimuovi'] ?? 0);
            if ($giocatoreId <= 0) {
                $errors[] = "Seleziona l'associazione da rimuovere.";
            } else {
                $stmtCheck = $conn->prepare("SELECT utente_id FROM giocatori WHERE id = ? AND utente_id IS NOT NULL");
                $stmtCheck->bind_param("i", $giocatoreId);
                $stmtCheck->execute();
                $assocRow = $stmtCheck->get_result()->fetch_assoc();
                $stmtCheck->close();

                if (!$assocRow) {
                    $errors[] = "Associazione non trovata.";
                } else {
                    $stmtRemove = $conn->prepare("UPDATE giocatori SET utente_id = NULL WHERE id = ?");
                    $stmtRemove->bind_param("i", $giocatoreId);
                    if ($stmtRemove->execute()) {
                        $messages[] = "Associazione rimossa.";
                    } else {
                        $errors[] = "Impossibile rimuovere l'associazione.";
                    }
                    $stmtRemove->close();
                }
            }
        }
    }

    if ($columnReady) {
        $resUtenti = $conn->query("
            SELECT u.id, u.nome, u.cognome, u.email
            FROM utenti u
            WHERE u.id NOT IN (
                SELECT utente_id FROM giocatori WHERE utente_id IS NOT NULL
            )
            ORDER BY u.cognome, u.nome
        ");
        if ($resUtenti) {
            while ($row = $resUtenti->fetch_assoc()) {
                $utentiDisponibili[] = $row;
            }
        }

        $resGiocatori = $conn->query("
            SELECT id, nome, cognome, ruolo
            FROM giocatori
            WHERE utente_id IS NULL
            ORDER BY cognome, nome
        ");
        if ($resGiocatori) {
            while ($row = $resGiocatori->fetch_assoc()) {
                $giocatoriDisponibili[] = $row;
            }
        }

        $resAssoc = $conn->query("
            SELECT g.id AS giocatore_id, g.nome AS giocatore_nome, g.cognome AS giocatore_cognome, g.ruolo,
                   u.id AS utente_id, u.nome AS utente_nome, u.cognome AS utente_cognome, u.email
            FROM giocatori g
            INNER JOIN utenti u ON u.id = g.utente_id
            ORDER BY u.cognome, u.nome
        ");
        if ($resAssoc) {
            while ($row = $resAssoc->fetch_assoc()) {
                $associazioni[] = $row;
            }
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
  <title>Associa account a giocatore</title>
  <link rel="stylesheet" href="/style.min.css?v=20251204">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link rel="apple-touch-icon" href="/img/logo_old_school.png">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; background: #f6f8fb; }
    main.admin-wrapper { max-width: 1100px; margin: 0 auto; padding: 36px 16px 70px; flex: 1 0 auto; }
    .panel-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 18px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
    .form-grid label { font-weight: 700; color: #15293e; display: flex; flex-direction: column; gap: 6px; }
    .form-grid select { padding: 10px 12px; border: 1px solid #d7dce5; border-radius: 10px; background: #fff; }
    .msg { padding: 10px 12px; border-radius: 10px; margin-bottom: 10px; font-weight: 700; }
    .msg.ok { background: #e8f6ef; color: #065f46; border: 1px solid #34d399; }
    .msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
    table.assoc-table { width: 100%; border-collapse: collapse; }
    table.assoc-table th, table.assoc-table td { padding: 10px; border-bottom: 1px solid #e5eaf0; text-align: left; }
    table.assoc-table th { background: #f8fafc; color: #15293e; }
    .actions { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
    .small-note { color: #475569; font-size: 0.95rem; margin-top: 6px; }
    @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
    <h1>Associa account a giocatore</h1>
    <p>Solo gli admin possono collegare un profilo utente a un giocatore del database.</p>

    <?php foreach ($messages as $m): ?>
      <div class="msg ok"><?= h($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
      <div class="msg err"><?= h($e) ?></div>
    <?php endforeach; ?>

    <?php if (!$columnReady): ?>
      <div class="panel-card">
        <p>Non riesco a preparare il campo necessario nella tabella dei giocatori. Controlla i permessi del database e riprova.</p>
      </div>
    <?php else: ?>
      <div class="panel-card">
        <h3>Crea una nuova associazione</h3>
        <form method="POST" class="form-grid" autocomplete="off">
          <?= csrf_field($csrfKey) ?>
          <input type="hidden" name="azione" value="associa">
          <label>Account da associare
            <select name="utente_id" required <?= empty($utentiDisponibili) ? 'disabled' : '' ?>>
              <option value="">-- scegli un account --</option>
              <?php foreach ($utentiDisponibili as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= h(trim(($u['cognome'] ?? '') . ' ' . ($u['nome'] ?? ''))) ?> - <?= h($u['email'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($utentiDisponibili)): ?>
              <span class="small-note">Nessun account libero: tutti gli account risultano giÃ  associati a un giocatore.</span>
            <?php endif; ?>
          </label>
          <label>Giocatore
            <select name="giocatore_id" required <?= empty($giocatoriDisponibili) ? 'disabled' : '' ?>>
              <option value="">-- scegli un giocatore --</option>
              <?php foreach ($giocatoriDisponibili as $g): ?>
                <option value="<?= (int)$g['id'] ?>"><?= h(trim(($g['cognome'] ?? '') . ' ' . ($g['nome'] ?? ''))) ?><?= $g['ruolo'] ? ' - ' . h($g['ruolo']) : '' ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($giocatoriDisponibili)): ?>
              <span class="small-note">Non ci sono giocatori liberi da associare.</span>
            <?php endif; ?>
          </label>
          <div class="actions" style="grid-column: 1 / -1;">
            <button class="btn-primary" type="submit" <?= (empty($utentiDisponibili) || empty($giocatoriDisponibili)) ? 'disabled' : '' ?>>Associa</button>
            <p class="small-note">Un account puÃ² essere collegato a un solo giocatore e viceversa.</p>
          </div>
        </form>
      </div>

      <div class="panel-card">
        <h3>Associazioni attive</h3>
        <?php if (empty($associazioni)): ?>
          <p>Nessuna associazione presente.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="assoc-table">
              <thead>
                <tr>
                  <th>Giocatore</th>
                  <th>Ruolo</th>
                  <th>Account</th>
                  <th>Email</th>
                  <th>Azioni</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($associazioni as $row): ?>
                  <tr>
                    <td><?= h(trim(($row['giocatore_cognome'] ?? '') . ' ' . ($row['giocatore_nome'] ?? ''))) ?></td>
                    <td><?= h($row['ruolo'] ?? '') ?></td>
                    <td><?= h(trim(($row['utente_nome'] ?? '') . ' ' . ($row['utente_cognome'] ?? ''))) ?></td>
                    <td><?= h($row['email'] ?? '') ?></td>
                    <td>
                      <form method="POST" onsubmit="return confirm('Rimuovere questa associazione?');">
                        <?= csrf_field($csrfKey) ?>
                        <input type="hidden" name="azione" value="rimuovi">
                        <input type="hidden" name="giocatore_rimuovi" value="<?= (int)$row['giocatore_id'] ?>">
                        <button type="submit" class="btn-danger">Rimuovi</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>

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
