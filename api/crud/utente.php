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

    public function crea($email, $nome, $cognome, $password, $ruolo) {
        $check = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            return ['error' => 'Email già registrata'];
        }

        $pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\'":\\|,.<>\/?]).{8,}$/';
        if (!preg_match($pattern, $password)) {
            return ['error' => 'La password deve contenere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.'];
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (nome, cognome, email, password, ruolo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nome, $cognome, $email, $hashed, $ruolo);

        if ($stmt->execute()) {
            return ['success' => true];
        }

        return ['error' => 'Errore durante la creazione dell’utente'];
    }

    public function aggiorna($id, $email, $nome, $cognome, $password, $ruolo) {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE {$this->table} SET email=?, nome=?, cognome=?, password=?, ruolo=? WHERE id=?");
            $stmt->bind_param("sssssi", $email, $nome, $cognome, $hashed, $ruolo, $id);
        } else {
            $stmt = $this->conn->prepare("UPDATE {$this->table} SET email=?, nome=?, cognome=?, ruolo=? WHERE id=?");
            $stmt->bind_param("ssssi", $email, $nome, $cognome, $ruolo, $id);
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
