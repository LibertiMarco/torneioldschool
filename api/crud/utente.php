<?php

require_once __DIR__ . '/../../includi/user_features.php';

class Utente
{
    private $conn;
    private $table = "utenti";

    public function __construct()
    {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
        ensure_user_feature_flags_column($this->conn);
    }

    public function getAll()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY id DESC";
        return $this->conn->query($sql);
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        $row['feature_flags'] = normalize_user_feature_flags($row['feature_flags'] ?? null);
        return $row;
    }

    public function crea($email, $nome, $cognome, $password, $ruolo, array $featureFlags = [])
    {
        $check = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            return ['error' => 'Email gia registrata'];
        }

        $pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\'":\\|,.<>\/?]).{8,}$/';
        if (!preg_match($pattern, $password)) {
            return ['error' => 'La password deve contenere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.'];
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $saveFeatureFlags = ensure_user_feature_flags_column($this->conn);

        if ($saveFeatureFlags) {
            $encodedFeatureFlags = encode_user_feature_flags($featureFlags);
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table} (nome, cognome, email, password, ruolo, feature_flags) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssssss", $nome, $cognome, $email, $hashed, $ruolo, $encodedFeatureFlags);
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table} (nome, cognome, email, password, ruolo) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssss", $nome, $cognome, $email, $hashed, $ruolo);
        }

        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true];
        }

        $stmt->close();
        return ['error' => 'Errore durante la creazione dell\'utente'];
    }

    public function aggiorna($id, $email, $nome, $cognome, $password, $ruolo, array $featureFlags = [])
    {
        $saveFeatureFlags = ensure_user_feature_flags_column($this->conn);
        $encodedFeatureFlags = encode_user_feature_flags($featureFlags);

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            if ($saveFeatureFlags) {
                $stmt = $this->conn->prepare(
                    "UPDATE {$this->table} SET email=?, nome=?, cognome=?, password=?, ruolo=?, feature_flags=? WHERE id=?"
                );
                $stmt->bind_param("ssssssi", $email, $nome, $cognome, $hashed, $ruolo, $encodedFeatureFlags, $id);
            } else {
                $stmt = $this->conn->prepare(
                    "UPDATE {$this->table} SET email=?, nome=?, cognome=?, password=?, ruolo=? WHERE id=?"
                );
                $stmt->bind_param("sssssi", $email, $nome, $cognome, $hashed, $ruolo, $id);
            }
        } else {
            if ($saveFeatureFlags) {
                $stmt = $this->conn->prepare(
                    "UPDATE {$this->table} SET email=?, nome=?, cognome=?, ruolo=?, feature_flags=? WHERE id=?"
                );
                $stmt->bind_param("sssssi", $email, $nome, $cognome, $ruolo, $encodedFeatureFlags, $id);
            } else {
                $stmt = $this->conn->prepare(
                    "UPDATE {$this->table} SET email=?, nome=?, cognome=?, ruolo=? WHERE id=?"
                );
                $stmt->bind_param("ssssi", $email, $nome, $cognome, $ruolo, $id);
            }
        }

        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function elimina($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id=?");
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}

?>
