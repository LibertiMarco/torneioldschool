<?php
class squadra {
    private $conn;
    private $table = "squadre";
    private $hasGirone = false;

    public function __construct() {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
        $this->hasGirone = $this->ensureGironeColumn();
    }

    private function ensureGironeColumn(): bool {
        if (!$this->conn) {
            return false;
        }

        $check = @$this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'girone'");
        if ($check && $check->num_rows > 0) {
            return true;
        }

        @$this->conn->query("ALTER TABLE {$this->table} ADD COLUMN girone VARCHAR(32) DEFAULT NULL AFTER torneo");
        $check = @$this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'girone'");
        return $check && $check->num_rows > 0;
    }

    private function normalizeGirone(?string $value): ?string {
        $value = strtoupper(trim((string)$value));
        $value = preg_replace('/^GIRONE\s+/u', '', $value);
        $value = preg_replace('/^GRUPPO\s+/u', '', $value);
        if ($value === '') {
            return null;
        }
        return substr($value, 0, 32);
    }

    private function resolvePreferredGironeForTorneo(string $torneo, ?string $selectedGirone = null, ?int $excludeId = null): ?string {
        if (!$this->hasGirone) {
            return null;
        }

        $manualGirone = $this->normalizeGirone($selectedGirone);
        if ($manualGirone !== null) {
            return $manualGirone;
        }

        return $this->resolveAutoGironeForTorneo($torneo, $excludeId);
    }

    private function buildGironeLabels(int $count): array {
        $labels = [];
        for ($i = 0; $i < $count; $i++) {
            $n = $i;
            $label = '';
            do {
                $label = chr(65 + ($n % 26)) . $label;
                $n = intdiv($n, 26) - 1;
            } while ($n >= 0);
            $labels[] = $label;
        }
        return $labels;
    }

    private function getTorneoConfig(string $torneo): array {
        if (!$this->conn || $torneo === '') {
            return [];
        }

        $filetorneo = preg_replace('/\.(php|html)$/i', '', $torneo) . '.php';
        $stmt = $this->conn->prepare("SELECT config FROM tornei WHERE filetorneo = ? LIMIT 1");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("s", $filetorneo);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['config'])) {
            return [];
        }

        $config = json_decode((string)$row['config'], true);
        return is_array($config) ? $config : [];
    }

    private function resolveAutoGironeForTorneo(string $torneo, ?int $excludeId = null): ?string {
        if (!$this->hasGirone || $torneo === '') {
            return null;
        }

        $config = $this->getTorneoConfig($torneo);
        $formato = strtolower(trim((string)($config['formato'] ?? $config['formula_torneo'] ?? '')));
        $numeroGironi = max(0, (int)($config['numero_gironi'] ?? 0));
        $squadrePerGirone = max(0, (int)($config['squadre_per_girone'] ?? 0));

        if ($formato !== 'girone' || $numeroGironi <= 0 || $squadrePerGirone <= 0) {
            return null;
        }

        $labels = $this->buildGironeLabels($numeroGironi);
        $counts = array_fill_keys($labels, 0);

        $sql = "SELECT girone, COUNT(*) AS cnt FROM {$this->table} WHERE torneo = ?";
        $types = "s";
        $params = [$torneo];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id <> ?";
            $types .= "i";
            $params[] = $excludeId;
        }
        $sql .= " GROUP BY girone";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return $labels[0] ?? null;
        }

        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $girone = $this->normalizeGirone($row['girone'] ?? null);
                if ($girone !== null && array_key_exists($girone, $counts)) {
                    $counts[$girone] = (int)$row['cnt'];
                }
            }
        }
        $stmt->close();

        foreach ($labels as $label) {
            if (($counts[$label] ?? 0) < $squadrePerGirone) {
                return $label;
            }
        }

        return $labels[0] ?? null;
    }

    private function resolveGironeForUpdate(array $existing, string $torneo, int $id, ?string $selectedGirone = null): ?string {
        if (!$this->hasGirone) {
            return null;
        }

        $manualGirone = $this->normalizeGirone($selectedGirone);
        if ($manualGirone !== null) {
            return $manualGirone;
        }

        $currentGirone = $this->normalizeGirone($existing['girone'] ?? null);
        $currentTorneo = (string)($existing['torneo'] ?? '');

        if ($currentTorneo === $torneo && $currentGirone !== null) {
            return $currentGirone;
        }

        return $this->resolveAutoGironeForTorneo($torneo, $id);
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

    public function crea($nome, $torneo, $logo = null, $girone = null) {
        if ($this->hasGirone) {
            $resolvedGirone = $this->resolvePreferredGironeForTorneo($torneo, $girone);
            $stmt = $this->conn->prepare("INSERT INTO {$this->table} (nome, torneo, girone, logo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $torneo, $resolvedGirone, $logo);
            return $stmt->execute();
        }

        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (nome, torneo, logo) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nome, $torneo, $logo);
        return $stmt->execute();
    }

    public function aggiorna($id, $nome, $torneo, $punti, $giocate, $vinte, $pareggiate, $perse, $gol_fatti, $gol_subiti, $differenza_reti, $logo = null, $girone = null) {
        $existing = $this->getById($id) ?: [];
        $fields = ["nome=?", "torneo=?"];
        $types = "ss";
        $params = [$nome, $torneo];

        if ($this->hasGirone) {
            $fields[] = "girone=?";
            $types .= "s";
            $params[] = $this->resolveGironeForUpdate($existing, $torneo, $id, $girone);
        }

        if ($logo !== null) {
            $fields[] = "logo=?";
            $types .= "s";
            $params[] = $logo;
        }

        $fields[] = "punti=?";
        $fields[] = "giocate=?";
        $fields[] = "vinte=?";
        $fields[] = "pareggiate=?";
        $fields[] = "perse=?";
        $fields[] = "gol_fatti=?";
        $fields[] = "gol_subiti=?";
        $fields[] = "differenza_reti=?";
        $types .= "iiiiiiii";
        array_push($params, $punti, $giocate, $vinte, $pareggiate, $perse, $gol_fatti, $gol_subiti, $differenza_reti);

        $params[] = $id;

        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET " . implode(", ", $fields) . "
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
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE torneo = ? ORDER BY nome ASC");
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

    public function aggiornaGironiTorneo(string $torneo, array $gironiMap): void {
        if (!$this->hasGirone || $torneo === '') {
            return;
        }

        $stmt = $this->conn->prepare("UPDATE {$this->table} SET girone = ? WHERE id = ? AND torneo = ?");
        if (!$stmt) {
            return;
        }

        foreach ($gironiMap as $id => $girone) {
            $teamId = (int)$id;
            if ($teamId <= 0) {
                continue;
            }

            $normalizedGirone = $this->normalizeGirone(is_scalar($girone) ? (string)$girone : null) ?? '';
            $stmt->bind_param("sis", $normalizedGirone, $teamId, $torneo);
            $stmt->execute();
        }

        $stmt->close();
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
