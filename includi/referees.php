<?php
/** Matches retain their original referee name when the directory is edited. */
final class RefereeDirectory {
    public const INITIAL = ['Vincenzo Amoroso','Alessio Scognamiglio','Francesco Capuozzo',
        'Alessandro Cernicchiaro','Giuseppe Benucci','Lucio Nugnes'];
    public function __construct(private PDO $db) {
        $this->db->exec("CREATE TABLE IF NOT EXISTS referee_directory_exclusions (
            name_key VARCHAR(150) NOT NULL PRIMARY KEY,
            display_name VARCHAR(150) NULL
        )");
    }
    public static function normalize(string $name): string {
        return mb_strtolower(trim(preg_replace('/\\s+/u', ' ', $name) ?? ''), 'UTF-8');
    }
    private static function validate(string $name): string {
        $name = trim(preg_replace('/\\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name) > 150 || !preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M}'’.-]*(?: [\\p{L}\\p{M}][\\p{L}\\p{M}'’.-]*)+$/u", $name)) {
            throw new InvalidArgumentException('Inserisci nome e cognome validi (massimo 150 caratteri).');
        }
        return $name;
    }
    public function names(): array {
        $names = self::INITIAL;
        foreach ($this->db->query("SELECT nome FROM staff WHERE categoria = 'arbitro'")->fetchAll(PDO::FETCH_COLUMN) as $name) $names[] = $name;
        foreach ($this->db->query("SELECT DISTINCT arbitro FROM partite WHERE arbitro IS NOT NULL AND arbitro <> ''")->fetchAll(PDO::FETCH_COLUMN) as $name) $names[] = $name;
        $overrides = $this->db->query("SELECT name_key, display_name FROM referee_directory_exclusions")->fetchAll(PDO::FETCH_KEY_PAIR);
        $result = [];
        foreach ($names as $name) {
            $key = self::normalize((string)$name);
            if ($key === '' || isset($result[$key])) continue;
            if (array_key_exists($key, $overrides)) {
                if ($overrides[$key] !== null) $result[$key] = $overrides[$key];
            } else {
                $result[$key] = trim(preg_replace('/\\s+/u',' ',(string)$name));
            }
        }
        natcasesort($result);
        return array_values($result);
    }
    private function write(callable $operation): mixed {
        $mysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql && (int)$this->db->query("SELECT GET_LOCK('tos_referee_directory', 5)")->fetchColumn() !== 1) {
            throw new RuntimeException('Selezione occupata. Riprova.');
        }
        try {
            $this->db->beginTransaction();
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        } finally {
            if ($mysql) $this->db->query("SELECT RELEASE_LOCK('tos_referee_directory')");
        }
    }
    private function add(string $name): string {
        foreach ($this->names() as $existing) if (self::normalize($existing) === self::normalize($name)) return $existing;
        $stmt = $this->db->prepare("DELETE FROM referee_directory_exclusions WHERE name_key = ?");
        $stmt->execute([self::normalize($name)]);
        foreach ($this->names() as $existing) if (self::normalize($existing) === self::normalize($name)) return $existing;
        $stmt = $this->db->prepare("INSERT INTO staff (nome, ruolo, categoria) VALUES (?, 'Arbitro', 'arbitro')");
        $stmt->execute([$name]);
        return $name;
    }
    private function exclude(string $name): void {
        $key = self::normalize($name);
        $stmt = $this->db->prepare("DELETE FROM referee_directory_exclusions WHERE name_key = ?");
        $stmt->execute([$key]);
        $stmt = $this->db->prepare("INSERT INTO referee_directory_exclusions (name_key) VALUES (?)");
        $stmt->execute([$key]);
    }
    public function create(string $name): string {
        $name = self::validate($name);
        return $this->write(fn() => $this->add($name));
    }
    public function rename(string $oldName, string $name): string {
        $name = self::validate($name);
        return $this->write(function () use ($oldName, $name) {
            $current = $this->names();
            $oldKey = self::normalize($oldName);
            if (!in_array($oldKey, array_map([self::class, 'normalize'], $current), true)) {
                throw new InvalidArgumentException('Arbitro non più disponibile. Ricarica la lista.');
            }
            if ($oldName === $name) return $name;
            foreach ($current as $existing) {
                if (self::normalize($existing) === self::normalize($name) && self::normalize($existing) !== $oldKey) {
                    throw new InvalidArgumentException('Questo arbitro è già presente.');
                }
            }
            // Replace the displayed spelling, including changes in capitalization.
            $this->exclude($oldName);
            if (self::normalize($name) === $oldKey) {
                $stmt = $this->db->prepare("UPDATE referee_directory_exclusions SET display_name = ? WHERE name_key = ?");
                $stmt->execute([$name, $oldKey]);
            } else {
                $this->add($name);
            }
            return $name;
        });
    }
    public function remove(string $name): void {
        if (mb_strlen($name) > 150 || self::normalize($name) === '') throw new InvalidArgumentException('Seleziona un arbitro valido.');
        $this->write(function () use ($name) { $this->exclude($name); });
    }
}
