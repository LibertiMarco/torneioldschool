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

    public function crea($nome, $torneo) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (nome, torneo) VALUES (?, ?)");
        $stmt->bind_param("ss", $nome, $torneo);
        return $stmt->execute();
    }

    public function aggiorna($id, $nome, $torneo, $punti, $giocate, $vinte, $pareggiate, $perse, $gol_fatti, $gol_subiti, $differenza_reti) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET nome=?, torneo=?, punti=?, giocate=?, vinte=?, pareggiate=?, perse=?, gol_fatti=?, gol_subiti=?, differenza_reti=?
            WHERE id=?");
        $stmt->bind_param("ssiiiiiiiii", $nome, $torneo, $punti, $giocate, $vinte, $pareggiate, $perse, $gol_fatti, $gol_subiti, $differenza_reti, $id);
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
    return $this->conn->query("SELECT DISTINCT torneo FROM {$this->table} ORDER BY torneo ASC");
}

public function getByNomeETorneo($nome, $torneo) {
    $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE nome = ? AND torneo = ? LIMIT 1");
    $stmt->bind_param("ss", $nome, $torneo);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

}
?>
