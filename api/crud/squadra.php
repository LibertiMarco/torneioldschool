<?php
class squadra {
    private $conn;
    private $table = "squadre";

    public function __construct() {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM {$this->table} ORDER BY nome ASC");
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function crea($nome, $torneo, $logo = null) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (nome, torneo, logo) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nome, $torneo, $logo);
        return $stmt->execute();
    }

    public function aggiorna($id, $nome, $torneo, $punti, $giocate, $vinte, $pareggiate, $perse, $gol_fatti, $gol_subiti, $differenza_reti, $logo = null) {
        $fields = "nome=?, torneo=?, punti=?, giocate=?, vinte=?, pareggiate=?, perse=?, gol_fatti=?, gol_subiti=?, differenza_reti=?";
        $types = "ssiiiiiiii";
        $params = [$nome, $torneo, $punti, $giocate, $vinte, $pareggiate, $perse, $gol_fatti, $gol_subiti, $differenza_reti];

        if ($logo !== null) {
            $fields = "nome=?, torneo=?, logo=?, punti=?, giocate=?, vinte=?, pareggiate=?, perse=?, gol_fatti=?, gol_subiti=?, differenza_reti=?";
            $types = "sssiiiiiiii";
            array_splice($params, 2, 0, $logo);
        }

        $params[] = $id;

        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET $fields
            WHERE id=?
        ");
        $stmt->bind_param($types . "i", ...$params);
        return $stmt->execute();
    }

    public function elimina($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id=?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getByTorneo($torneo) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE torneo = ?");
        $stmt->bind_param("s", $torneo);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getTornei() {
        $result = $this->conn->query("SELECT DISTINCT torneo FROM {$this->table} WHERE torneo <> '' ORDER BY torneo ASC");
        if (!$result) {
            return false;
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = ['id' => $row['torneo'], 'nome' => $row['torneo']];
        }
        return $rows;
    }

    public function getByNomeETorneo($nome, $torneo) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE nome = ? AND torneo = ? LIMIT 1");
        $stmt->bind_param("ss", $nome, $torneo);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function eliminaByTorneo($torneo) {
        if ($torneo === '') {
            return;
        }
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE torneo = ?");
        $stmt->bind_param("s", $torneo);
        $stmt->execute();
    }
}
?>
