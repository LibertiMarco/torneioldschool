<?php
class SquadraGiocatore {
    private $conn;
    private $table = "squadre_giocatori";

    public function __construct() {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
    }

    public function assegna($giocatoreId, $squadraId, $foto = null) {
        $sql = "
            INSERT INTO {$this->table} (squadra_id, giocatore_id, foto)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE foto = VALUES(foto)
        ";

        $stmt = $this->conn->prepare($sql);
        $foto = $foto !== '' ? $foto : null;
        $stmt->bind_param("iis", $squadraId, $giocatoreId, $foto);
        return $stmt->execute();
    }

    public function dissocia($giocatoreId, $squadraId) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE giocatore_id = ? AND squadra_id = ?");
        $stmt->bind_param("ii", $giocatoreId, $squadraId);
        return $stmt->execute();
    }

    public function getSquadrePerGiocatore($giocatoreId) {
        $sql = "
            SELECT s.id, s.nome, s.torneo, sg.foto
            FROM {$this->table} sg
            JOIN squadre s ON s.id = sg.squadra_id
            WHERE sg.giocatore_id = ?
            ORDER BY s.torneo, s.nome
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $giocatoreId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getGiocatoriPerSquadraId($squadraId) {
        $sql = "
            SELECT g.*, s.nome AS squadra_nome, s.torneo,
                   COALESCE(sg.foto, g.foto) AS foto_squadra
            FROM {$this->table} sg
            JOIN giocatori g ON g.id = sg.giocatore_id
            JOIN squadre s ON s.id = sg.squadra_id
            WHERE s.id = ?
            ORDER BY g.cognome, g.nome
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $squadraId);
        $stmt->execute();
        return $stmt->get_result();
    }
}
