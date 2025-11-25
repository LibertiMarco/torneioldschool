<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /index.php");
    exit;
}
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/crud/utente.php';
$utente = new Utente();

// --- CREA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
    $email = trim($_POST['email']);
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $password = trim($_POST['password']);
    $ruolo = trim($_POST['ruolo']);

    $result = $utente->crea($email, $nome, $cognome, $password, $ruolo);
    if (isset($result['error'])) {
        echo "<script>alert('".$result['error']."'); window.history.back();</script>";
        exit;
    }
    header("Location: gestione_utenti.php");
    exit;
}

// --- AGGIORNA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna'])) {
    $id = (int)$_POST['id'];
    $email = trim($_POST['email']);
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $password = !empty($_POST['password']) ? trim($_POST['password']) : null;
    $ruolo = trim($_POST['ruolo']);

    $utente->aggiorna($id, $email, $nome, $cognome, $password, $ruolo);
    header("Location: gestione_utenti.php");
    exit;
}

// --- ELIMINA ---
if (isset($_GET['elimina'])) {
    $utente->elimina((int)$_GET['elimina']);
    header("Location: gestione_utenti.php");
    exit;
}

$lista = $utente->getAll();
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Gestione Utenti</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link rel="apple-touch-icon" href="/img/logo_old_school.png">
  <style>
    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    main.admin-wrapper {
      flex: 1 0 auto;
    }

    .create-register-wrapper {
      width: 100%;
      display: flex;
      justify-content: center;
      margin-bottom: 20px;
    }

    .create-register-box {
      width: 100%;
      max-width: 540px;
      background-color: #ffffff;
      padding: 46px 52px 60px;
      margin-bottom: 0;
      border-radius: 18px;
      box-shadow: 0 35px 70px rgba(15, 23, 42, 0.12);
      border: 1px solid rgba(21, 41, 62, 0.08);
      text-align: left;
      position: relative;
      z-index: 1;
    }

    .create-register-box h2 {
      color: #15293e;
      margin-bottom: 20px;
      font-size: 1.6rem;
      font-weight: 700;
      text-align: center;
    }

    .create-register-form {
      display: flex;
      flex-direction: column;
      gap: 15px;
      width: 100%;
    }

    .create-register-form label {
      font-weight: 600;
      color: #15293e;
      margin-bottom: 3px;
      font-size: 1.05rem;
    }

    .create-register-form input,
    .create-register-form select {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #d0d7e1;
      border-radius: 10px;
      font-size: 1.05rem;
      transition: border-color 0.2s ease;
      background: #fff;
    }

    .create-register-form input:focus,
    .create-register-form select:focus {
      outline: none;
      border-color: #15293e;
    }

    .create-register-form .password-field {
      position: relative;
      display: flex;
      align-items: center;
    }

    .create-register-form .password-field input {
      padding-right: 42px;
    }

    .create-register-form .toggle-password {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      height: 100%;
      color: #5c6572;
    }

    .create-register-form .toggle-password:focus-visible {
      outline: 2px solid #15293e;
      outline-offset: 2px;
    }

    .create-register-form .toggle-password svg {
      width: 22px;
      height: 22px;
    }

    .create-register-form .toggle-password .icon-eye-off {
      display: none;
    }

    .create-register-form .toggle-password.is-visible .icon-eye {
      display: none;
    }

    .create-register-form .toggle-password.is-visible .icon-eye-off {
      display: block;
    }

    .add-user-inline-hint {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
      margin-top: 6px;
      color: #a94442;
    }

    .add-user-inline-hint span {
      font-weight: 700;
      min-width: 20px;
      text-align: center;
    }

    .add-user-submit {
      background-color: #15293e;
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 12px;
      font-size: 1.05rem;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.3s ease, background 0.3s ease;
      margin-top: 10px;
    }

    .add-user-submit:hover {
      background-color: #0e1d2e;
      transform: scale(1.02);
    }

    .admin-container {
      padding-bottom: 80px;
    }

    .admin-wrapper {
      padding-bottom: 60px;
    }

    #footer-container {
      margin-top: auto;
      width: 100%;
    }

    .admin-table-section {
      margin-bottom: 140px;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <section class="admin-container">
      <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
      <h1 class="admin-title">Gestione Utenti</h1>

      <!-- PICKLIST -->
      <div class="admin-select-action">
        <label for="azione">Seleziona azione:</label>
        <select id="azione" class="operation-picker">
          <option value="crea" selected>Aggiungi Utente</option>
          <option value="modifica">Modifica Utente</option>
          <option value="elimina">Elimina Utente</option>
        </select>
      </div>

      <!-- FORM CREA -->
      <div class="create-register-wrapper form-crea">
        <div class="create-register-box">
          <h2>Aggiungi un nuovo utente</h2>
          <form method="POST" class="admin-form create-register-form" id="formCreaUtente" autocomplete="off">
            <label for="crea_nome">Nome</label>
            <input type="text" id="crea_nome" name="nome" required>

            <label for="crea_cognome">Cognome</label>
            <input type="text" id="crea_cognome" name="cognome" required>

            <label for="crea_email">Email</label>
            <input type="email" id="crea_email" name="email" required>

            <label for="crea_password">Password</label>
            <div class="password-field">
              <input type="password" id="crea_password" name="password" required>
              <button type="button" class="toggle-password" data-target="crea_password" data-label-base="password" aria-label="Mostra password">
                <svg class="icon-eye" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
                  <path fill="currentColor" d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7zm0 12c-2.7 0-5-2.3-5-5s2.3-5 5-5 5 2.3 5 5-2.3 5-5 5zm0-8a3 3 0 100 6 3 3 0 000-6z"/>
                </svg>
                <svg class="icon-eye-off" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
                  <path fill="currentColor" d="M2.3 3.7l2 2A12.7 12.7 0 002 12c1 2.5 5 7 10 7 1.7 0 3.3-.5 4.8-1.4l2.2 2.2 1.4-1.4-17-17-1.3 1.3zm7.1 7.1l1.9 1.9a1 1 0 01-1.9-1.9zm3.5 3.5l1.9 1.9a3 3 0 01-3.8-3.8l1.9 1.9zm8.8-.3c.5-.8.8-1.5.8-2.1-1-2.5-5-7-10-7-1.2 0-2.5.3-3.6.8l1.6 1.6a6 6 0 017.4 7.4l1.5 1.5a13.5 13.5 0 002.3-2.2z"/>
                </svg>
              </button>
            </div>
            <div class="add-user-inline-hint">
              <span id="createPasswordCheck"></span>
              <small id="createPasswordMessage">La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.</small>
            </div>

            <label for="crea_confirm_password">Conferma Password</label>
            <div class="password-field">
              <input type="password" id="crea_confirm_password" name="confirm_password" required>
              <button type="button" class="toggle-password" data-target="crea_confirm_password" data-label-base="conferma password" aria-label="Mostra conferma password">
                <svg class="icon-eye" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
                  <path fill="currentColor" d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7zm0 12c-2.7 0-5-2.3-5-5s2.3-5 5-5 5 2.3 5 5-2.3 5-5 5zm0-8a3 3 0 100 6 3 3 0 000-6z"/>
                </svg>
                <svg class="icon-eye-off" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
                  <path fill="currentColor" d="M2.3 3.7l2 2A12.7 12.7 0 002 12c1 2.5 5 7 10 7 1.7 0 3.3-.5 4.8-1.4l2.2 2.2 1.4-1.4-17-17-1.3 1.3zm7.1 7.1l1.9 1.9a1 1 0 01-1.9-1.9zm3.5 3.5l1.9 1.9a3 3 0 01-3.8-3.8l1.9 1.9zm8.8-.3c.5-.8.8-1.5.8-2.1-1-2.5-5-7-10-7-1.2 0-2.5.3-3.6.8l1.6 1.6a6 6 0 017.4 7.4l1.5 1.5a13.5 13.5 0 002.3-2.2z"/>
                </svg>
              </button>
            </div>
            <div class="add-user-inline-hint">
              <span id="createConfirmCheck"></span>
              <small id="createConfirmMessage">Le password devono coincidere.</small>
            </div>

            <label for="crea_ruolo">Ruolo</label>
            <select id="crea_ruolo" name="ruolo" required>
              <option value="user">Utente</option>
              <option value="admin">Amministratore</option>
            </select>

            <button type="submit" name="crea" class="btn-primary add-user-submit">Crea Utente</button>
            <div id="form-message" class="form-message"></div>
          </form>
        </div>
      </div>

      <!-- FORM MODIFICA -->
      <form method="POST" class="admin-form form-modifica hidden" id="formModifica">
        <h2>Modifica Utente</h2>
        <div class="form-group">
          <label>Seleziona Utente</label>
          <select name="id" id="selectUtenteMod" required>
            <option value="">-- Seleziona un utente --</option>
            <?php while ($row = $lista->fetch_assoc()): ?>
              <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nome'].' '.$row['cognome'].' ('.$row['email'].')') ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group"><label>Nome</label><input type="text" name="nome" id="mod_nome" required></div>
        <div class="form-group"><label>Cognome</label><input type="text" name="cognome" id="mod_cognome" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" id="mod_email" required></div>        
        <div class="form-group"><label>Nuova Password (lascia vuoto per mantenere)</label><input type="password" name="password" id="mod_password"></div>
        <div class="form-group"><label>Ruolo</label>
          <select name="ruolo" id="mod_ruolo" required>
            <option value="user">Utente</option>
            <option value="admin">Amministratore</option>
          </select>
        </div>
        <button type="submit" name="aggiorna" class="btn-primary">Aggiorna Utente</button>
      </form>

      <!-- SEZIONE ELIMINA -->
      <section class="admin-table-section form-elimina hidden">
        <h2>Elimina Utente</h2>
        <input type="text" id="searchUtente" placeholder="Cerca utente..." class="search-input">

        <div class="admin-table-utenti-container">
          <table class="admin-table" id="tabellaUtenti">
            <thead>
              <tr>
                <th data-col="nome">Nome</th>
                <th data-col="cognome">Cognome</th>
                <th data-col="email">Email</th>
                <th data-col="ruolo">Ruolo</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $lista2 = $utente->getAll();
              while ($row = $lista2->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['nome']) ?></td>
                  <td><?= htmlspecialchars($row['cognome']) ?></td>
                  <td><?= htmlspecialchars($row['email']) ?></td>
                  <td><?= htmlspecialchars($row['ruolo']) ?></td>
                    <td>
                      <a href="#" class="btn-danger delete-btn" data-id="<?= $row['id'] ?>" data-label="<?= htmlspecialchars($row['nome'] . ' ' . $row['cognome']) ?>" data-type="utente">Elimina</a>
                    </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>

    </section>
    </main>

    <div id="footer-container"></div>

    <?php include __DIR__ . '/../includi/delete_modal.php'; ?>

    <!-- SCRIPT SWITCH SEZIONI -->
    <script>
    const selectAzione = document.getElementById('azione');
    const formCrea = document.querySelector('.form-crea');
    const formModifica = document.querySelector('.form-modifica');
    const formElimina = document.querySelector('.form-elimina');

    function mostraSezione(valore) {
      [formCrea, formModifica, formElimina].forEach(f => f.classList.add('hidden'));
      if (valore === 'crea') formCrea.classList.remove('hidden');
      if (valore === 'modifica') formModifica.classList.remove('hidden');
      if (valore === 'elimina') formElimina.classList.remove('hidden');
    }

    selectAzione.addEventListener('change', (e) => mostraSezione(e.target.value));
  </script>

  <!-- POPOLAMENTO DATI UTENTE -->
  <script>
    const selectUtenteMod = document.getElementById('selectUtenteMod');
    const campi = {
      email: document.getElementById('mod_email'),
      nome: document.getElementById('mod_nome'),
      cognome: document.getElementById('mod_cognome'),
      ruolo: document.getElementById('mod_ruolo')
    };

    selectUtenteMod.addEventListener('change', async (e) => {
      const id = e.target.value;
      if (!id) {
        Object.values(campi).forEach(f => f.value = '');
        campi.ruolo.value = 'user';
        return;
      }
      try {
        const res = await fetch(`/api/get_utente.php?id=${id}`);
        const data = await res.json();
        if (data && !data.error) {
          campi.email.value = data.email || '';
          campi.nome.value = data.nome || '';
          campi.cognome.value = data.cognome || '';
          campi.ruolo.value = data.ruolo || 'user';
        }
      } catch (err) {
        console.error('Errore nel recupero utente:', err);
      }
    });
  </script>

  <!-- FILTRO E ORDINAMENTO -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const table = document.getElementById("tabellaUtenti");
      const headers = table.querySelectorAll("th[data-col]");
      const search = document.getElementById("searchUtente");
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
  <!-- FORM CREAZIONE INTERATTIVA -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const createForm = document.getElementById('formCreaUtente');
      const passwordInput = document.getElementById('crea_password');
      const confirmInput = document.getElementById('crea_confirm_password');
      const passwordMessage = document.getElementById('createPasswordMessage');
      const passwordCheck = document.getElementById('createPasswordCheck');
      const confirmMessage = document.getElementById('createConfirmMessage');
      const confirmCheck = document.getElementById('createConfirmCheck');
      const passwordRegex = /^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]).{8,}$/;

      const updatePasswordStrength = () => {
        if (!passwordInput || !passwordMessage || !passwordCheck) return;
        if (!passwordInput.value) {
          passwordMessage.textContent = 'La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.';
          passwordMessage.style.color = '#a94442';
          passwordCheck.textContent = '';
          return;
        }
        if (passwordRegex.test(passwordInput.value)) {
          passwordMessage.textContent = 'Password valida.';
          passwordMessage.style.color = 'green';
          passwordCheck.textContent = '\u2713';
          passwordCheck.style.color = 'green';
        } else {
          passwordMessage.textContent = 'Almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.';
          passwordMessage.style.color = '#a94442';
          passwordCheck.textContent = '!';
          passwordCheck.style.color = '#a94442';
        }
      };

      const updateConfirmState = () => {
        if (!passwordInput || !confirmInput || !confirmMessage || !confirmCheck) return;
        if (!confirmInput.value) {
          confirmMessage.textContent = 'Le password devono coincidere.';
          confirmMessage.style.color = '#a94442';
          confirmCheck.textContent = '';
          return;
        }
        if (confirmInput.value === passwordInput.value) {
          confirmMessage.textContent = 'Le password coincidono.';
          confirmMessage.style.color = 'green';
          confirmCheck.textContent = '\u2713';
          confirmCheck.style.color = 'green';
        } else {
          confirmMessage.textContent = 'Le password non coincidono.';
          confirmMessage.style.color = '#a94442';
          confirmCheck.textContent = '!';
          confirmCheck.style.color = '#a94442';
        }
      };

      document.querySelectorAll('.form-crea .toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
          const targetId = btn.getAttribute('data-target');
          const baseLabel = btn.getAttribute('data-label-base') || 'password';
          const target = targetId ? document.getElementById(targetId) : null;
          if (!target) return;
          const shouldShow = target.type === 'password';
          target.type = shouldShow ? 'text' : 'password';
          btn.classList.toggle('is-visible', shouldShow);
          btn.setAttribute('aria-label', shouldShow ? `Nascondi ${baseLabel}` : `Mostra ${baseLabel}`);
        });
      });

      if (passwordInput) {
        passwordInput.addEventListener('input', () => {
          updatePasswordStrength();
          updateConfirmState();
        });
      }

      if (confirmInput) {
        confirmInput.addEventListener('input', updateConfirmState);
      }

      if (createForm) {
        createForm.addEventListener('submit', (event) => {
          updatePasswordStrength();
          updateConfirmState();
          const passwordOk = passwordInput ? passwordRegex.test(passwordInput.value) : false;
          const confirmOk = passwordInput && confirmInput && confirmInput.value === passwordInput.value && confirmInput.value !== '';
          if (!passwordOk || !confirmOk) {
            event.preventDefault();
          }
        });
      }
    });
  </script>

  <!-- FOOTER -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const footerContainer = document.getElementById('footer-container');
      if (!footerContainer) return;
  fetch('/includi/footer.html')
    .then(response => response.text())
    .then(html => footerContainer.innerHTML = html)
    .catch(err => console.error('Errore nel caricamento del footer:', err));
});
</script>
<script src="/includi/delete-modal.js"></script>
</body>
</html>
