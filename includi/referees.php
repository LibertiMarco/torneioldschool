<?php
/** Reuses the site's staff registry; matches continue storing the referee name. */
final class RefereeDirectory {
    public const INITIAL = ['Vincenzo Amoroso','Alessio Scognamiglio','Francesco Capuozzo',
        'Alessandro Cernicchiaro','Giuseppe Benucci','Lucio Nugnes'];
    public function __construct(private PDO $db) {}
    public static function normalize(string $name): string {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? ''), 'UTF-8');
    }
    public function names(): array {
        $names = self::INITIAL;
        foreach ($this->db->query("SELECT nome FROM staff WHERE categoria = 'arbitro'")->fetchAll(PDO::FETCH_COLUMN) as $name) $names[] = $name;
        foreach ($this->db->query("SELECT DISTINCT arbitro FROM partite WHERE arbitro IS NOT NULL AND arbitro <> ''")->fetchAll(PDO::FETCH_COLUMN) as $name) $names[] = $name;
        $result = [];
        foreach ($names as $name) {
            $key = self::normalize((string)$name);
            if ($key !== '' && !isset($result[$key])) $result[$key] = trim(preg_replace('/\s+/u',' ',(string)$name));
        }
        natcasesort($result);
        return array_values($result);
    }
    public function create(string $name): string {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name) > 150 || !preg_match("/^[\p{L}\p{M}][\p{L}\p{M}'’.-]*(?: [\p{L}\p{M}][\p{L}\p{M}'’.-]*)+$/u", $name)) {
            throw new InvalidArgumentException('Inserisci nome e cognome validi (massimo 150 caratteri).');
        }
        $mysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql && (int)$this->db->query("SELECT GET_LOCK('tos_referee_directory', 5)")->fetchColumn() !== 1) {
            throw new RuntimeException('Selezione occupata. Riprova.');
        }
        try {
            foreach ($this->names() as $existing) if (self::normalize($existing) === self::normalize($name)) return $existing;
            $stmt = $this->db->prepare("INSERT INTO staff (nome, ruolo, categoria) VALUES (?, 'Arbitro', 'arbitro')");
            $stmt->execute([$name]);
            return $name;
        } finally {
            if ($mysql) $this->db->query("SELECT RELEASE_LOCK('tos_referee_directory')");
        }
    }
}
