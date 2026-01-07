<?php
class torneo {
    private $conn;
    private $table = "tornei";
    private $hasConfig = false;

    public function __construct() {
        require __DIR__ . '/../../includi/db.php'; // usa la connessione esistente
        $this->conn = $conn; // $conn è definito in db.php
        $this->hasConfig = $this->ensureConfigColumn();
    }

    /**
     * Garantisce la presenza della colonna config (JSON/LONGTEXT) per salvare impostazioni torneo.
     */
    private function ensureConfigColumn(): bool {
        if (!$this->conn) {
            return false;
        }
        $has = false;
        // Verifica presenza
        $check = @$this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'config'");
        if ($check && $check->num_rows > 0) {
            $has = true;
        }

        // Tenta aggiunta se mancante (prima JSON, poi LONGTEXT fallback)
        if (!$has) {
            @$this->conn->query("ALTER TABLE {$this->table} ADD COLUMN config JSON NULL");
            $check = @$this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'config'");
            if ($check && $check->num_rows > 0) {
                $has = true;
            }
        }
        if (!$has) {
            @$this->conn->query("ALTER TABLE {$this->table} ADD COLUMN config LONGTEXT NULL");
            $check = @$this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'config'");
            if ($check && $check->num_rows > 0) {
                $has = true;
            }
        }

        return $has;
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
     * Ottiene un torneo per slug (filetorneo senza estensione)
     */
    public function getBySlug(string $slug) {
        $clean = preg_replace('/\.(php|html)$/i', '', $slug);
        $file = $clean . '.php';
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE filetorneo = ? LIMIT 1");
        $stmt->bind_param("s", $file);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Crea un nuovo torneo
     */
    public function crea($nome, $stato, $data_inizio, $data_fine, $filetorneo, $categoria, $img = null, $squadre_complete = 0, $config = null) {
        if (empty($img)) {
            $img = "/img/tornei/pallone.png";
        }

        $configJson = null;
        if ($this->hasConfig && $config !== null) {
            $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        }

        if ($this->hasConfig) {
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table}
                (nome, stato, data_inizio, data_fine, img, filetorneo, categoria, squadre_complete, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssssis", $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $squadre_complete, $configJson);
        } else {
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table}
                (nome, stato, data_inizio, data_fine, img, filetorneo, categoria, squadre_complete)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssssi", $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $squadre_complete);
        }
        return $stmt->execute();
    }

    /**
     * Aggiorna un torneo esistente
     */
    public function aggiorna($id, $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $squadre_complete = 0, $config = null) {
        if (empty($img)) {
            $img = "/img/tornei/pallone.png";
        }

        $configJson = null;
        if ($this->hasConfig && $config !== null) {
            $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        }

        if ($this->hasConfig) {
            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET nome = ?, stato = ?, data_inizio = ?, data_fine = ?, img = ?, filetorneo = ?, categoria = ?, squadre_complete = ?, config = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssssisi", $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $squadre_complete, $configJson, $id);
        } else {
            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET nome = ?, stato = ?, data_inizio = ?, data_fine = ?, img = ?, filetorneo = ?, categoria = ?, squadre_complete = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssssii", $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $squadre_complete, $id);
        }
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

    /**
     * Restituisce l'ultimo errore MySQL (per logging diagnostico)
     */
    public function getLastError(): string {
        return $this->conn ? (string)$this->conn->error : '';
    }
}
?>
