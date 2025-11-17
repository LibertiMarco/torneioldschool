<?php
require_once __DIR__ . '/../../includi/db.php';

class partitagiocatore {

    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    /* ==========================================================
       GET ALL STATISTICHE DI UNA PARTITA
    ========================================================== */
    public function getByPartita($partita_id) {
        $sql = "
            SELECT 
                pg.id,
                pg.partita_id,
                pg.giocatore_id,
                g.nome,
                g.cognome,
                s.nome AS squadra,
                pg.presenza,
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
            ORDER BY g.cognome, g.nome
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $partita_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    /* ==========================================================
       GET SINGOLO RECORD
    ========================================================== */
    public function getById($id) {
        $sql = "SELECT * FROM partita_giocatore WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /* ==========================================================
       CREA NUOVO RECORD
    ========================================================== */
    public function create($partita_id, $giocatore_id, $goal, $assist, $giallo, $rosso, $voto) {

        $sql = "
            INSERT INTO partita_giocatore
            (partita_id, giocatore_id, presenza, goal, assist, cartellino_giallo, cartellino_rosso, voto)
            VALUES (?, ?, 1, ?, ?, ?, ?, ?)
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iiiiiid", $partita_id, $giocatore_id, $goal, $assist, $giallo, $rosso, $voto);
        $stmt->execute();

        $this->aggiornaStatisticheGiocatore($giocatore_id, $goal, $assist, $giallo, $rosso, +1);
        $this->ricalcolaMediaVoto($giocatore_id);

        return true;
    }

    /* ==========================================================
       AGGIORNA RECORD ESISTENTE
    ========================================================== */
    public function update($id, $goal, $assist, $giallo, $rosso, $voto) {

        // Dati vecchi
        $old = $this->getById($id);
        if (!$old) return false;

        // Aggiorna record
        if ($voto === null || $voto === '') {
            $sql = "
                UPDATE partita_giocatore
                SET goal=?, assist=?, cartellino_giallo=?, cartellino_rosso=?, voto=NULL
                WHERE id=?
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iiiii", $goal, $assist, $giallo, $rosso, $id);
        } else {
            $sql = "
                UPDATE partita_giocatore
                SET goal=?, assist=?, cartellino_giallo=?, cartellino_rosso=?, voto=?
                WHERE id=?
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iiiidi", $goal, $assist, $giallo, $rosso, $voto, $id);
        }
        $stmt->execute();

        // Differenze
        $dGoal   = $goal   - (int)$old['goal'];
        $dAssist = $assist - (int)$old['assist'];
        $dGiallo = $giallo - (int)$old['cartellino_giallo'];
        $dRosso  = $rosso  - (int)$old['cartellino_rosso'];

        $this->aggiornaStatisticheGiocatore($old['giocatore_id'], $dGoal, $dAssist, $dGiallo, $dRosso, 0);
        $this->ricalcolaMediaVoto($old['giocatore_id']);

        return true;
    }

    /* ==========================================================
       ELIMINA RECORD
    ========================================================== */
    public function delete($id) {

        $old = $this->getById($id);
        if (!$old) return false;

        $sql = "DELETE FROM partita_giocatore WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Rimuovi statistiche dal giocatore
        $this->aggiornaStatisticheGiocatore(
            $old['giocatore_id'],
            -$old['goal'],
            -$old['assist'],
            -$old['cartellino_giallo'],
            -$old['cartellino_rosso'],
            -1
        );

        $this->ricalcolaMediaVoto($old['giocatore_id']);

        return true;
    }

    /* ==========================================================
       AGGIORNA STATISTICHE DEL GIOCATORE
    ========================================================== */
    private function aggiornaStatisticheGiocatore($id, $goal, $assist, $gialli, $rossi, $presenza) {
        $sql = "
            UPDATE giocatori
            SET 
                presenze = GREATEST(COALESCE(presenze,0) + ?, 0),
                reti     = GREATEST(COALESCE(reti,0) + ?, 0),
                assist   = GREATEST(COALESCE(assist,0) + ?, 0),
                gialli   = GREATEST(COALESCE(gialli,0) + ?, 0),
                rossi    = GREATEST(COALESCE(rossi,0) + ?, 0)
            WHERE id=?
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iiiiii", $presenza, $goal, $assist, $gialli, $rossi, $id);
        $stmt->execute();
    }

    /* ==========================================================
       RICALCOLA MEDIA VOTO
    ========================================================== */
    private function ricalcolaMediaVoto($giocatore_id) {
        $sql = "
            SELECT AVG(voto) AS media
            FROM partita_giocatore
            WHERE giocatore_id = ? AND voto IS NOT NULL
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $giocatore_id);
        $stmt->execute();
        $media = $stmt->get_result()->fetch_assoc()['media'];

        if ($media === null) {
            $sql2 = "UPDATE giocatori SET media_voti=NULL WHERE id=?";
            $stmt2 = $this->conn->prepare($sql2);
            $stmt2->bind_param("i", $giocatore_id);
        } else {
            $sql2 = "UPDATE giocatori SET media_voti=? WHERE id=?";
            $stmt2 = $this->conn->prepare($sql2);
            $stmt2->bind_param("di", $media, $giocatore_id);
        }
        $stmt2->execute();
    }
}
