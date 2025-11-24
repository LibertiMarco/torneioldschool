<?php
class Giocatore {
    private $conn;
    private $table = "giocatori";

    public function __construct() {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
    }

    // ✅ GET ALL
    public function getAll() {
        $sql = "
            SELECT g.*, sg_data.squadre_assoc, sg_data.tornei_assoc
            FROM {$this->table} g
            LEFT JOIN (
                SELECT 
                    sg.giocatore_id,
                    GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS squadre_assoc,
                    GROUP_CONCAT(DISTINCT s.torneo ORDER BY s.torneo SEPARATOR ', ') AS tornei_assoc
                FROM squadre_giocatori sg
                JOIN squadre s ON s.id = sg.squadra_id
                GROUP BY sg.giocatore_id
            ) AS sg_data ON sg_data.giocatore_id = g.id
            ORDER BY g.cognome, g.nome
        ";
        return $this->conn->query($sql);
    }

    public function getLastCreated($limit = 10) {
        $limit = (int)$limit;
        if ($limit <= 0) { $limit = 10; }
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result();
    }

    // ✅ GET BY ID
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ✅ CREA GIOCATORE
    public function crea($nome, $cognome, $ruolo, $presenze, $reti, $gialli, $rossi, $media_voti, $foto) {
        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table}
            (nome, cognome, ruolo, presenze, reti, gialli, rossi, media_voti, foto)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $media_voti = $media_voti === '' ? null : $media_voti;

        $stmt->bind_param(
            "sssiiiids",
            $nome,
            $cognome,
            $ruolo,
            $presenze,
            $reti,
            $gialli,
            $rossi,
            $media_voti,
            $foto
        );

        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }

        return false;
    }

    // ✅ AGGIORNA GIOCATORE
    public function aggiorna($id, $nome, $cognome, $ruolo, $presenze, $reti, $gialli, $rossi, $media_voti, $foto) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET nome = ?, cognome = ?, ruolo = ?,
                presenze = ?, reti = ?, gialli = ?, rossi = ?, media_voti = ?, foto = ?
            WHERE id = ?
        ");

        $media_voti = $media_voti === '' ? null : $media_voti;

        $stmt->bind_param(
            "sssiiiidsi",
            $nome,
            $cognome,
            $ruolo,
            $presenze,
            $reti,
            $gialli,
            $rossi,
            $media_voti,
            $foto,
            $id
        );

        return $stmt->execute();
    }

    // ✅ ELIMINA GIOCATORE
    public function elimina($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    // ✅ TORNEI ESISTENTI
    public function getTornei() {
        return $this->conn->query("SELECT DISTINCT torneo FROM squadre WHERE torneo <> '' ORDER BY torneo");
    }

    // ✅ SQUADRE PER TORNEO
    public function getSquadre($torneo) {
        $stmt = $this->conn->prepare("SELECT nome AS squadra FROM squadre WHERE torneo = ? ORDER BY nome");
        $stmt->bind_param("s", $torneo);
        $stmt->execute();
        return $stmt->get_result();
    }

    // ✅ GIOCATORI PER SQUADRA E TORNEO
    public function getGiocatoriBySquadra($squadra = null, $torneo = null, $squadraId = null) {
        if ($squadraId) {
            $sql = "
                SELECT g.*, s.nome AS squadra, s.torneo,
                       COALESCE(sg.foto, g.foto) AS foto_squadra,
                       sg.ruolo AS ruolo_squadra,
                       sg.presenze AS presenze_squadra,
                       sg.reti AS reti_squadra,
                       sg.assist AS assist_squadra,
                       sg.gialli AS gialli_squadra,
                       sg.rossi AS rossi_squadra,
                       sg.media_voti AS media_squadra,
                       sg.is_captain
                FROM squadre_giocatori sg
                JOIN giocatori g ON g.id = sg.giocatore_id
                JOIN squadre s ON s.id = sg.squadra_id
                WHERE s.id = ?
                ORDER BY g.cognome, g.nome
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $squadraId);
        } else {
            $sql = "
                SELECT g.*, s.nome AS squadra, s.torneo,
                       COALESCE(sg.foto, g.foto) AS foto_squadra,
                       sg.ruolo AS ruolo_squadra,
                       sg.presenze AS presenze_squadra,
                       sg.reti AS reti_squadra,
                       sg.assist AS assist_squadra,
                       sg.gialli AS gialli_squadra,
                       sg.rossi AS rossi_squadra,
                       sg.media_voti AS media_squadra,
                       sg.is_captain
                FROM squadre_giocatori sg
                JOIN giocatori g ON g.id = sg.giocatore_id
                JOIN squadre s ON s.id = sg.squadra_id
                WHERE s.nome = ? AND s.torneo = ?
                ORDER BY g.cognome, g.nome
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ss", $squadra, $torneo);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    // �o. CHECK ESISTENZA PER NOME E COGNOME
    public function esistePerNomeCognome($nome, $cognome) {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM {$this->table}
            WHERE LOWER(nome) = LOWER(?) AND LOWER(cognome) = LOWER(?)
            LIMIT 1
        ");
        $stmt->bind_param("ss", $nome, $cognome);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}
?>
