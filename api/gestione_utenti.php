<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /torneioldschool/index.php");
    exit;
}

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
  <title>Gestione Utenti</title>
  <link rel="stylesheet" href="/torneioldschool/style.css">
  <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
</head>
<body>
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <section class="admin-container">
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
      <form method="POST" class="admin-form form-crea">
        <h2>Aggiungi Utente</h2>
        <div class="form-group"><label>Nome</label><input type="text" name="nome" required></div>
        <div class="form-group"><label>Cognome</label><input type="text" name="cognome" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" id="password" required>
          <p id="password-hint" class="password-hint">
            La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.
          </p>
        </div>

        <div class="form-group"><label>Ruolo</label>
          <select name="ruolo" required>
            <option value="user">Utente</option>
            <option value="admin">Amministratore</option>
          </select>
        </div>
        <button type="submit" name="crea" class="btn-primary">Crea Utente</button>
        <div id="form-message" class="form-message"></div>
      </form>

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
          <table class="admin-table-utenti" id="tabellaUtenti">
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
                    <a href="?elimina=<?= $row['id'] ?>" class="btn-danger" onclick="return confirm('Eliminare questo utente?')">Elimina</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>

    </section>
  </main>

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
        const res = await fetch(`/torneioldschool/api/get_utente.php?id=${id}`);
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

  <!-- VALIDAZIONE PASSWORD -->
  <script>
    const passwordInput = document.getElementById('password');
    const hint = document.getElementById('password-hint');

    passwordInput.addEventListener('input', () => {
      const value = passwordInput.value;
      const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).{8,}$/;
      if (regex.test(value)) {
        hint.textContent = '✅ Password valida';
        hint.style.color = 'green';
      } else {
        hint.textContent = '❌ Almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.';
        hint.style.color = 'red';
      }
    });
  </script>
</body>
</html>
