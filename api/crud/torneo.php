<?php
class torneo {
    private $conn;
    private $table = "tornei";
    private $hasConfig = false;
    private $hasSection = false;

    public function __construct() {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
        $this->hasConfig = $this->ensureConfigColumn();
        $this->hasSection = $this->ensureSectionColumn();
    }

    private function hasColumn(string $column): bool {
        if (!$this->conn) {
            return false;
        }

        $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', $column);
        if ($safeColumn === '') {
            return false;
        }

        $check = @$this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE '{$safeColumn}'");
        return $check && $check->num_rows > 0;
    }

    private function normalizeSezione(?string $value): string {
        return strtolower(trim((string)$value)) === 'esport' ? 'esport' : 'calcio';
    }

    /**
     * Garantisce la presenza della colonna config (JSON/LONGTEXT) per salvare impostazioni torneo.
     */
    private function ensureConfigColumn(): bool {
        if (!$this->conn) {
            return false;
        }

        if (!$this->hasColumn('config')) {
            @$this->conn->query("ALTER TABLE {$this->table} ADD COLUMN config JSON NULL");
        }
        if (!$this->hasColumn('config')) {
            @$this->conn->query("ALTER TABLE {$this->table} ADD COLUMN config LONGTEXT NULL");
        }

        return $this->hasColumn('config');
    }

    /**
     * Garantisce la presenza della colonna sezione per separare calcio ed esport.
     */
    private function ensureSectionColumn(): bool {
        if (!$this->conn) {
            return false;
        }

        if (!$this->hasColumn('sezione')) {
            @$this->conn->query("ALTER TABLE {$this->table} ADD COLUMN sezione VARCHAR(20) NOT NULL DEFAULT 'calcio' AFTER categoria");
        }

        return $this->hasColumn('sezione');
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
    public function crea($nome, $stato, $data_inizio, $data_fine, $filetorneo, $categoria, $sezione = 'calcio', $img = null, $squadre_complete = 0, $config = null) {
        if (empty($img)) {
            $img = "/img/tornei/pallone.png";
        }

        $configJson = null;
        if ($this->hasConfig && $config !== null) {
            $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        }

        $columns = ['nome', 'stato', 'data_inizio', 'data_fine', 'img', 'filetorneo', 'categoria'];
        $params = [$nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria];
        $types = "sssssss";

        if ($this->hasSection) {
            $columns[] = 'sezione';
            $params[] = $this->normalizeSezione($sezione);
            $types .= "s";
        }

        $columns[] = 'squadre_complete';
        $params[] = $squadre_complete;
        $types .= "i";

        if ($this->hasConfig) {
            $columns[] = 'config';
            $params[] = $configJson;
            $types .= "s";
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table}
            (" . implode(', ', $columns) . ")
            VALUES ({$placeholders})
        ");
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Aggiorna un torneo esistente
     */
    public function aggiorna($id, $nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria, $sezione = 'calcio', $squadre_complete = 0, $config = null) {
        if (empty($img)) {
            $img = "/img/tornei/pallone.png";
        }

        $configJson = null;
        if ($this->hasConfig && $config !== null) {
            $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        }

        $assignments = [
            'nome = ?',
            'stato = ?',
            'data_inizio = ?',
            'data_fine = ?',
            'img = ?',
            'filetorneo = ?',
            'categoria = ?',
        ];
        $params = [$nome, $stato, $data_inizio, $data_fine, $img, $filetorneo, $categoria];
        $types = "sssssss";

        if ($this->hasSection) {
            $assignments[] = 'sezione = ?';
            $params[] = $this->normalizeSezione($sezione);
            $types .= "s";
        }

        $assignments[] = 'squadre_complete = ?';
        $params[] = $squadre_complete;
        $types .= "i";

        if ($this->hasConfig) {
            $assignments[] = 'config = ?';
            $params[] = $configJson;
            $types .= "s";
        }

        $params[] = $id;
        $types .= "i";

        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET " . implode(', ', $assignments) . "
            WHERE id = ?
        ");
        $stmt->bind_param($types, ...$params);
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
     * Filtra tornei per stato, categoria e/o sezione
     */
    public function filtra($stato = null, $categoria = null, $sezione = null) {
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
        if ($this->hasSection && !empty($sezione)) {
            $sql .= " AND sezione = ?";
            $params[] = $this->normalizeSezione($sezione);
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
