<?php
class Utente {
    private $conn;
    private $table = "utenti";

    public function __construct() {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
    }

    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY id DESC";
        return $this->conn->query($sql);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function crea($email, $username, $password, $ruolo) {
        // 🔹 Controlla se email o username esistono già
        $check = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE email = ? OR username = ?");
        $check->bind_param("ss", $email, $username);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();
    
        if ($count > 0) {
            return ['error' => 'Email o username già registrati'];
        }
    
        // 🔹 Controlla complessità password
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}$/', $password)) {
            return ['error' => 'La password deve contenere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale (può includere anche il punto).'];
        }
    
        // 🔹 Crea nuovo utente
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (email, username, password, ruolo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $email, $username, $hashed, $ruolo);
    
        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['error' => 'Errore durante la creazione dell’utente'];
        }
    }



    public function aggiorna($id, $email, $username, $password, $ruolo) {
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE {$this->table} SET email=?, username=?, password=?, ruolo=? WHERE id=?");
            $stmt->bind_param("ssssi", $email, $username, $hashed, $ruolo, $id);
        } else {
            $stmt = $this->conn->prepare("UPDATE {$this->table} SET email=?, username=?, ruolo=? WHERE id=?");
            $stmt->bind_param("sssi", $email, $username, $ruolo, $id);
        }
        return $stmt->execute();
    }

    public function elimina($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id=?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
?>
