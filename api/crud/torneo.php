<?php
class torneo {
    private $conn;
    private $table = "tornei";

    public function __construct() {
        require __DIR__ . '/../../includi/db.php'; // usa la connessione esistente
        $this->conn = $conn; // $conn è definito in db.php
    }

    /**
     * Ottiene tutti i tornei
     */
    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY data_inizio DESC";
        return $this->conn->query($sql);
    }

    /**
     * Ottiene un torneo specifico per ID
     */
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Crea un nuovo torneo
     */
    public function crea($nome, $stato, $data_inizio, $data_fine, $img = null, $filetorneo, $categoria) {
        if (empty($img)) {
            $img = "/img/tornei/pallone.png";
        }

        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table}
            (nome, stato, data_inizio, data_fine, img, filetorneo, categoria)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssss", $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria);
        return $stmt->execute();
    }

    /**
     * Aggiorna un torneo esistente
     */
    public function aggiorna($id, $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria) {
        if (empty($img)) {
            $img = "/img/tornei/pallone.png";
        }

        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET nome = ?, stato = ?, data_inizio = ?, data_fine = ?, img = ?, filetorneo = ?, categoria = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssssssi", $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $id);
        return $stmt->execute();
    }

    /**
     * Elimina un torneo
     */
    public function elimina($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Filtra tornei per stato e/o categoria
     */
    public function filtra($stato = null, $categoria = null) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($stato)) {
            $sql .= " AND stato = ?";
            $params[] = $stato;
            $types .= "s";
        }
        if (!empty($categoria)) {
            $sql .= " AND categoria = ?";
            $params[] = $categoria;
            $types .= "s";
        }

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result();
    }
}
?>
