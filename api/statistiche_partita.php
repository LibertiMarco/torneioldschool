<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
  header("Location: /torneioldschool/index.php");
  exit;
}

require_once __DIR__ . '/../includi/db.php';

$partita_id = isset($_GET['partitaid']) ? (int)$_GET['partitaid'] : 0;
if (!$partita_id) {
  die("ID partita mancante.");
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statistiche Partita</title>
<link rel="stylesheet" href="/torneioldschool/style.css">
<link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">

<style>

/* === HEADER PAGINA === */
.page-header {
    width: 100%;
    text-align: center;
    margin-bottom: 25px;
    padding-top: 10px;

    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Titolo */
.page-header h1 {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.page-header h1::after {
    content: "";
    display: block;
    width: 140px;
    height: 3px;
    background: #c8102e;
    margin: 10px auto 0;
    border-radius: 10px;
}

/* Pulsante indietro */
.page-header .btn-back {
    align-self: flex-start;
    background: #222;
    border: none;
    padding: 8px 16px;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.page-header .btn-back:hover {
    background: #000;
}

/* === BOX PARTITA === */
#partitaBox {
    background: #f8f8f8;
    border-left: 5px solid #c8102e;
    padding: 12px 15px;
    margin-bottom: 25px;
    border-radius: 6px;
    font-size: 18px;
    color: #333;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

#partitaBox b {
    font-size: 20px;
    color: #111;
}

/* Versione desktop */
@media (min-width: 768px) {
    .page-header {
        flex-direction: row;
        justify-content: center;
        align-items: center;
        position: relative;
    }

    .page-header .btn-back {
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
    }
}

.hidden { display:none; }

/* POPUP */
.msg-box {
  position: fixed;
  bottom: 25px;
  right: 25px;
  padding: 14px 20px;
  background: #333;
  color: #fff;
  border-radius: 8px;
  font-size: 15px;
  opacity: 0;
  transition: opacity .4s;
  box-shadow: 0 4px 14px rgba(0,0,0,.25);
}

.msg-success { background: #28a745 !important; }
.msg-error   { background: #dc3545 !important; }

/* Tabella scroll */
.table-scroll {
    max-height: 350px;
    overflow-y: auto;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    position: sticky;
    top: 0;
    background: #222;
    color: white;
    z-index: 5;
    padding: 10px;
}

.admin-table td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    white-space: nowrap;
}

/* Colonne */
.admin-table th:nth-child(1), .admin-table td:nth-child(1) {
    min-width: 180px;
}

.admin-table th:nth-child(2), .admin-table td:nth-child(2) {
    min-width: 150px;
}

.admin-table th:nth-child(3),
.admin-table th:nth-child(4),
.admin-table th:nth-child(5),
.admin-table th:nth-child(6),
.admin-table th:nth-child(7),
.admin-table td:nth-child(3),
.admin-table td:nth-child(4),
.admin-table td:nth-child(5),
.admin-table td:nth-child(6),
.admin-table td:nth-child(7) {
    min-width: 70px;
}

.admin-table th:nth-child(8), .admin-table td:nth-child(8) {
    min-width: 120px;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 14px;
}

</style>
</head>

<body>

<?php include __DIR__ . '/../includi/header.php'; ?>

<div id="msgBox" class="msg-box"></div>

<main class="admin-wrapper">
<section class="admin-container">

<!-- HEADER FINALE -->
<div class="page-header">
    <button class="btn-back" onclick="window.location.href='gestione_partite.php'">← Torna indietro</button>
    <h1>Statistiche Partita</h1>
</div>

<!-- BOX INFO PARTITA -->
<div id="partitaBox">
    <span id="partitaInfo"></span>
</div>

<!-- Selettore Azione -->
<div class="admin-select-action" style="margin-bottom:20px;">
  <label>Azione:</label>
  <select id="azioneStat">
    <option value="add">Aggiungi Statistica</option>
    <option value="edit">Modifica Statistica</option>
    <option value="delete">Elimina Statistica</option>
  </select>
</div>

<!-- ===================== AGGIUNGI ====================== -->
<form id="formAdd" class="admin-form">
  <h2>Aggiungi Statistica</h2>

  <input type="hidden" name="partita_id" value="<?php echo $partita_id; ?>">

  <div class="form-group">
    <label>Giocatore</label>
    <select name="giocatore_id" id="add_giocatore" required>
      <option>Caricamento...</option>
    </select>
  </div>

  <div class="form-row">
    <div class="form-group half">
      <label>Gol</label>
      <input type="number" name="goal" min="0" value="0" required>
    </div>
    <div class="form-group half">
      <label>Assist</label>
      <input type="number" name="assist" min="0" value="0" required>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group half">
      <label>Giallo</label>
      <input type="number" name="cartellino_giallo" min="0" max="1" value="0" required>
    </div>
    <div class="form-group half">
      <label>Rosso</label>
      <input type="number" name="cartellino_rosso" min="0" max="1" value="0" required>
    </div>
  </div>

  <div class="form-group">
    <label>Voto</label>
    <input type="number" name="voto" min="0" max="10" step="0.5" value="6">
  </div>

  <button class="btn-primary" type="submit">➕ Aggiungi</button>
</form>

<!-- ===================== MODIFICA ====================== -->
<section id="sectionEdit" class="admin-form hidden">
  <h2>Modifica Statistica</h2>

  <div class="table-scroll">
    <table class="admin-table" id="tabellaEdit">
      <thead>
        <tr>
          <th>Giocatore</th>
          <th>Squadra</th>
          <th>Gol</th>
          <th>Assist</th>
          <th>Gialli</th>
          <th>Rossi</th>
          <th>Voto</th>
          <th>Modifica</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <hr>

  <form id="formEdit">
    <input type="hidden" name="id" id="edit_id">
    <input type="hidden" name="partita_id" value="<?php echo $partita_id; ?>">

    <div class="form-row">
      <div class="form-group half">
        <label>Gol</label>
        <input id="edit_goal" type="number" name="goal" min="0" required>
      </div>
      <div class="form-group half">
        <label>Assist</label>
        <input id="edit_assist" type="number" name="assist" min="0" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group half">
        <label>Giallo</label>
        <input id="edit_giallo" type="number" name="cartellino_giallo" min="0" max="1" required>
      </div>
      <div class="form-group half">
        <label>Rosso</label>
        <input id="edit_rosso" type="number" name="cartellino_rosso" min="0" max="1" required>
      </div>
    </div>

    <div class="form-group">
      <label>Voto</label>
      <input id="edit_voto" type="number" name="voto" min="0" max="10" step="0.5">
    </div>

    <button class="btn-primary">💾 Salva Modifiche</button>
  </form>
</section>

<!-- ===================== ELIMINA ====================== -->
<section id="sectionDelete" class="admin-form hidden">
  <h2>Elimina Statistica</h2>

  <div class="table-scroll">
    <table class="admin-table" id="tabellaDelete">
      <thead>
        <tr>
          <th>Giocatore</th>
          <th>Squadra</th>
          <th>Gol</th>
          <th>Assist</th>
          <th>Gialli</th>
          <th>Rossi</th>
          <th>Voto</th>
          <th>Elimina</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</section>

</section>
</main>

<script>
const ID = <?php echo $partita_id; ?>;
const API = "/torneioldschool/api/partita_giocatore.php";

/* Popup elegante */
function showMsg(msg, type="success"){
    const box = document.getElementById("msgBox");
    box.textContent = msg;
    box.className = "msg-box " + (type === "error" ? "msg-error" : "msg-success");
    box.style.opacity = "1";
    setTimeout(() => box.style.opacity = "0", 2500);
}

/* SWITCH AZIONE */
document.getElementById("azioneStat").addEventListener("change", e => {
  document.getElementById("formAdd").classList.add("hidden");
  document.getElementById("sectionEdit").classList.add("hidden");
  document.getElementById("sectionDelete").classList.add("hidden");

  if (e.target.value === "add") document.getElementById("formAdd").classList.remove("hidden");
  if (e.target.value === "edit") document.getElementById("sectionEdit").classList.remove("hidden");
  if (e.target.value === "delete") document.getElementById("sectionDelete").classList.remove("hidden");
});

/* INFO PARTITA */
async function loadPartita(){
  const r = await fetch(`/torneioldschool/api/get_partita.php?id=${ID}`);
  const p = await r.json();
document.getElementById("partitaInfo").innerHTML = `
    <b>${p.squadra_casa} - ${p.squadra_ospite}</b><br>
    ${p.data_partita} | 
    ${p.ora_partita.substring(0,5)}
`;

}

/* CARICA GIOCATORI */
async function loadPlayers(){
  const r = await fetch(`${API}?azione=list_giocatori&partita_id=${ID}`);
  const list = await r.json();
  const sel = document.getElementById("add_giocatore");

  sel.innerHTML = `<option value="">-- Seleziona giocatore --</option>`;
  list.forEach(g => {
    sel.innerHTML += `<option value="${g.id}">${g.cognome} ${g.nome} (${g.squadra})</option>`;
  });
}

/* CARICA STATISTICHE */
async function loadStats(){
  const r = await fetch(`${API}?azione=list&partita_id=${ID}`);
  const stats = await r.json();

  const TE = document.querySelector("#tabellaEdit tbody");
  const TD = document.querySelector("#tabellaDelete tbody");

  TE.innerHTML = "";
  TD.innerHTML = "";

  stats.forEach(s => {
    TE.innerHTML += `
      <tr>
        <td>${s.cognome} ${s.nome}</td>
        <td>${s.squadra}</td>
        <td>${s.goal}</td>
        <td>${s.assist}</td>
        <td>${s.cartellino_giallo}</td>
        <td>${s.cartellino_rosso}</td>
        <td>${s.voto ?? '-'}</td>
        <td><button data-edit="${s.id}" class="btn-primary btn-sm">Modifica</button></td>
      </tr>`;

    TD.innerHTML += `
      <tr>
        <td>${s.cognome} ${s.nome}</td>
        <td>${s.squadra}</td>
        <td>${s.goal}</td>
        <td>${s.assist}</td>
        <td>${s.cartellino_giallo}</td>
        <td>${s.cartellino_rosso}</td>
        <td>${s.voto ?? '-'}</td>
        <td><button data-del="${s.id}" class="btn-danger btn-sm">Elimina</button></td>
      </tr>`;
  });
}

/* Aggiunta */
document.getElementById("formAdd").addEventListener("submit", async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append("azione","add");

  const r = await fetch(API, { method:"POST", body:fd });
  const out = await r.json();

  if(out.error === "exists"){
      showMsg("⚠️ Giocatore già aggiunto", "error");
      return;
  }

  if(out.success){
      showMsg("Statistica creata!", "success");
      e.target.reset();
      await loadStats();
      await loadPlayers();
  }
});

/* Popola modifica */
document.querySelector("#tabellaEdit tbody").addEventListener("click", e => {
  const id = e.target.dataset.edit;
  if (!id) return;

  const tr = e.target.closest("tr").children;

  document.getElementById("edit_id").value = id;
  document.getElementById("edit_goal").value = tr[2].textContent;
  document.getElementById("edit_assist").value = tr[3].textContent;
  document.getElementById("edit_giallo").value = tr[4].textContent;
  document.getElementById("edit_rosso").value = tr[5].textContent;
  document.getElementById("edit_voto").value = tr[6].textContent === "-" ? "" : tr[6].textContent;
});

/* Salva modifica */
document.getElementById("formEdit").addEventListener("submit", async e => {
  e.preventDefault();

  const fd = new FormData(e.target);
  fd.append("azione","edit");

  const r = await fetch(API, { method:"POST", body:fd });
  const out = await r.json();

  if(out.success){
      showMsg("Modifica salvata!", "success");
      await loadStats();
  }
});

/* Eliminazione */
document.querySelector("#tabellaDelete tbody").addEventListener("click", async e => {
  const id = e.target.dataset.del;
  if (!id) return;

  if (!confirm("Eliminare questa statistica?")) return;

  const fd = new FormData();
  fd.append("azione","delete");
  fd.append("id",id);

  const r = await fetch(API, { method:"POST", body:fd });
  const out = await r.json();

  if(out.success){
      showMsg("Statistica eliminata", "success");
      await loadStats();
      await loadPlayers();
  }
});

/* INIT */
(async () => {
  await loadPartita();
  await loadPlayers();
  await loadStats();
})();
</script>

</body>
</html>
