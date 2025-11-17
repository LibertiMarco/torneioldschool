<?php
require_once __DIR__ . '/../includi/db.php';
header('Content-Type: application/json; charset=utf-8');

$azione = $_GET['azione'] ?? $_POST['azione'] ?? '';

/* ==========================================================
   LISTA STATISTICHE DELLA PARTITA
========================================================== */
if ($azione === 'list') {

    if (empty($_GET['partita_id'])) { echo json_encode([]); exit; }

    $partita_id = (int)$_GET['partita_id'];

    $sql = "SELECT 
                pg.id,
                pg.partita_id,
                pg.giocatore_id,
                g.nome,
                g.cognome,
                s.nome AS squadra,
                pg.goal,
                pg.assist,
                pg.cartellino_giallo,
                pg.cartellino_rosso,
                pg.voto
            FROM partita_giocatore pg
            JOIN giocatori g ON g.id = pg.giocatore_id
            JOIN partite p ON p.id = pg.partita_id
            JOIN squadre s ON s.torneo = p.torneo AND s.nome IN (p.squadra_casa, p.squadra_ospite)
            JOIN squadre_giocatori sg ON sg.squadra_id = s.id AND sg.giocatore_id = g.id
            WHERE pg.partita_id = ?
            ORDER BY g.cognome, g.nome";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $partita_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $out = [];

    while ($row = $res->fetch_assoc()) { $out[] = $row; }

    echo json_encode($out);
    exit;
}

/* ==========================================================
   LISTA GIOCATORI DISPONIBILI PER LA PARTITA
   (solo squadre in campo E NON già inseriti)
========================================================== */
if ($azione === 'list_giocatori') {

    if (empty($_GET['partita_id'])) { echo json_encode([]); exit; }

    $partita_id = (int)$_GET['partita_id'];

    // squadre della partita
    $q = $conn->prepare("SELECT squadra_casa, squadra_ospite FROM partite WHERE id=?");
    $q->bind_param("i", $partita_id);
    $q->execute();
    $p = $q->get_result()->fetch_assoc();

    if (!$p) { echo json_encode([]); exit; }

    // giocatori NON ancora inseriti
    $sql = "SELECT DISTINCT g.id, g.nome, g.cognome, s.nome AS squadra
            FROM partite p
            JOIN squadre s ON s.torneo = p.torneo AND s.nome IN (p.squadra_casa, p.squadra_ospite)
            JOIN squadre_giocatori sg ON sg.squadra_id = s.id
            JOIN giocatori g ON g.id = sg.giocatore_id
            WHERE p.id = ?
            AND g.id NOT IN (
                SELECT giocatore_id FROM partita_giocatore WHERE partita_id = ?
            )
            ORDER BY g.cognome, g.nome";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $partita_id, $partita_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $out = [];

    while ($row = $res->fetch_assoc()) { $out[] = $row; }

    echo json_encode($out);
    exit;
}

/* ==========================================================
   AGGIUNTA STATISTICA
========================================================== */
if ($azione === 'add') {

    $partita_id = (int)$_POST['partita_id'];
    $giocatore  = (int)$_POST['giocatore_id'];
    $goal       = (int)$_POST['goal'];
    $assist     = (int)$_POST['assist'];
    $giallo     = (int)$_POST['cartellino_giallo'];
    $rosso      = (int)$_POST['cartellino_rosso'];
    $voto       = $_POST['voto'] === "" ? null : (float)$_POST['voto'];

    /* 🔥 CONTROLLO DUPLICATO */
    $check = $conn->prepare("SELECT id FROM partita_giocatore WHERE partita_id = ? AND giocatore_id = ?");
    $check->bind_param("ii", $partita_id, $giocatore);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();

    if ($exists) {
        echo json_encode(["error" => "exists"]);
        exit;
    }

    /* ➕ INSERIMENTO */
    $sql = "INSERT INTO partita_giocatore
            (partita_id, giocatore_id, presenza, goal, assist, cartellino_giallo, cartellino_rosso, voto)
            VALUES (?, ?, 1, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiiid", $partita_id, $giocatore, $goal, $assist, $giallo, $rosso, $voto);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Statistica aggiunta"]);
    exit;
}

/* ==========================================================
   MODIFICA
========================================================== */
if ($azione === 'edit') {

    $id     = (int)$_POST['id'];
    $goal   = (int)$_POST['goal'];
    $assist = (int)$_POST['assist'];
    $giallo = (int)$_POST['cartellino_giallo'];
    $rosso  = (int)$_POST['cartellino_rosso'];
    $voto   = $_POST['voto'] === "" ? null : (float)$_POST['voto'];

    if ($voto === null) {
        $sql = "UPDATE partita_giocatore 
                SET goal=?, assist=?, cartellino_giallo=?, cartellino_rosso=?, voto=NULL
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $goal, $assist, $giallo, $rosso, $id);
    } else {
        $sql = "UPDATE partita_giocatore 
                SET goal=?, assist=?, cartellino_giallo=?, cartellino_rosso=?, voto=?
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiidi", $goal, $assist, $giallo, $rosso, $voto, $id);
    }

    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Statistica aggiornata"]);
    exit;
}

/* ==========================================================
   ELIMINA
========================================================== */
if ($azione === 'delete') {

    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("DELETE FROM partita_giocatore WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Statistica eliminata"]);
    exit;
}

/* ========================================================== */
echo json_encode(["error" => "Azione non valida"]);
