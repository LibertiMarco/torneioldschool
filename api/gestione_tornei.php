<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: /torneioldschool/index.php");
    exit;
}

require_once __DIR__ . '/crud/Torneo.php';
$torneo = new Torneo();

// --- CREA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crea'])) {
    $nome = trim($_POST['nome']);
    $stato = $_POST['stato'];
    $data_inizio = $_POST['data_inizio'];
    $data_fine = $_POST['data_fine'];
    $filetorneo = trim($_POST['filetorneo']);
    $categoria = trim($_POST['categoria']);
    $img = !empty($_POST['img']) ? $_POST['img'] : null;

    $torneo->crea($nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria);
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
    $filetorneo = trim($_POST['filetorneo']);
    $categoria = trim($_POST['categoria']);
    $img = !empty($_POST['img']) ? $_POST['img'] : null;

    $torneo->aggiorna($id, $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria);
    header("Location: gestione_tornei.php");
    exit;
}

// --- ELIMINA ---
if (isset($_GET['elimina'])) {
    $torneo->elimina((int)$_GET['elimina']);
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
</head>
<body>
    <?php include __DIR__ . '/../includi/header.php'; ?>

    <main class="admin-wrapper">
        <section class="admin-container">
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
            <form method="POST" class="admin-form form-crea">
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
                <div class="form-group"><label>Immagine</label><input type="text" name="img" placeholder="/torneioldschool/img/tornei/pallone.png"></div>
                <div class="form-group"><label>File Torneo</label><input type="text" name="filetorneo" required></div>
                <div class="form-group"><label>Categoria</label><input type="text" name="categoria" required></div>
                <button type="submit" name="crea" class="btn-primary">Crea Torneo</button>
            </form>

            <!-- FORM MODIFICA -->
            <form method="POST" class="admin-form form-modifica hidden" id="formModifica">
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
                <div class="form-group"><label>Immagine</label><input type="text" name="img" id="mod_img"></div>
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
const selectTorneo = document.getElementById('selectTorneo');
const campi = {
    nome: document.getElementById('mod_nome'),
    stato: document.getElementById('mod_stato'),
    inizio: document.getElementById('mod_inizio'),
    fine: document.getElementById('mod_fine'),
    img: document.getElementById('mod_img'),
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
            campi.img.value = data.img || '';
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

</body>
</html>
