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
        $sql = "SELECT * FROM {$this->table} ORDER BY cognome, nome";
        return $this->conn->query($sql);
    }

    // ✅ GET BY ID
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ✅ CREA GIOCATORE
    public function crea($nome, $cognome, $ruolo, $squadra, $torneo, $presenze, $reti, $gialli, $rossi, $media_voti, $foto) {
        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table}
            (nome, cognome, ruolo, squadra, torneo, presenze, reti, gialli, rossi, media_voti, foto)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssiiiids",
            $nome,
            $cognome,
            $ruolo,
            $squadra,
            $torneo,
            $presenze,
            $reti,
            $gialli,
            $rossi,
            $media_voti,
            $foto
        );


        return $stmt->execute();
    }

    // ✅ AGGIORNA GIOCATORE
    public function aggiorna($id, $nome, $cognome, $ruolo, $squadra, $torneo, $presenze, $reti, $gialli, $rossi, $media_voti, $foto) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET nome = ?, cognome = ?, ruolo = ?, squadra = ?, torneo = ?,
                presenze = ?, reti = ?, gialli = ?, rossi = ?, media_voti = ?, foto = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssssiiisssi",
            $nome,
            $cognome,
            $ruolo,
            $squadra,
            $torneo,
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
        $sql = "SELECT DISTINCT torneo FROM {$this->table} ORDER BY torneo";
        return $this->conn->query($sql);
    }

    // ✅ SQUADRE PER TORNEO
    public function getSquadre($torneo) {
        $stmt = $this->conn->prepare("SELECT DISTINCT squadra FROM {$this->table} WHERE torneo = ? ORDER BY squadra");
        $stmt->bind_param("s", $torneo);
        $stmt->execute();
        return $stmt->get_result();
    }

    // ✅ GIOCATORI PER SQUADRA E TORNEO
    public function getGiocatoriBySquadra($squadra, $torneo) {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table}
            WHERE squadra = ? AND torneo = ?
            ORDER BY cognome, nome
        ");
        $stmt->bind_param("ss", $squadra, $torneo);
        $stmt->execute();
        return $stmt->get_result();
    }
}
?>
