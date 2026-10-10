<?php
require_once __DIR__ . '/mobile_auth.php';

final class MobileAccountFeatures
{
    public function __construct(private PDO $db, private MobileAuth|Closure $auth) {}

    private function authorize(string $access): void
    {
        if ($this->auth instanceof MobileAuth) {
            $this->auth->requirePermission($access, 'admin');
        } else {
            // Trusted server-side guard for the cookie-authenticated web preview.
            ($this->auth)();
        }
    }

    private function record(int $id): array
    {
        if ($id <= 0) { throw new MobileAuthError('invalid_request', 'Account non valido.', 400); }
        $stmt = $this->db->prepare('SELECT id, email, nome, cognome, ruolo, avatar, feature_flags FROM utenti WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new MobileAuthError('not_found', 'Account non trovato.', 404); }
        return $row;
    }

    private function response(array $row): array
    {
        $definitions = [];
        foreach (user_feature_definitions() as $key => $config) {
            $definitions[] = ['key' => $key, 'label' => $config['label'], 'description' => $config['description']];
        }
        return ['user' => MobileAuth::publicUser($row), 'definitions' => $definitions,
            'revision' => hash('sha256', (string)($row['feature_flags'] ?? ''))];
    }

    public function get(string $access, int $id): array
    {
        $this->authorize($access);
        return $this->response($this->record($id));
    }

    public function save(string $access, int $id, array $flags, string $revision): array
    {
        $this->authorize($access);
        $keys = array_keys(user_feature_definitions());
        if (array_diff(array_keys($flags), $keys) || array_diff($keys, array_keys($flags))) {
            throw new MobileAuthError('invalid_request', 'Funzioni account non valide.', 400);
        }
        foreach ($flags as $enabled) {
            if (!is_bool($enabled)) { throw new MobileAuthError('invalid_request', 'Le abilitazioni devono essere booleane.', 400); }
        }
        $row = $this->record($id);
        $raw = (string)($row['feature_flags'] ?? '');
        if (!hash_equals(hash('sha256', $raw), $revision)) {
            throw new MobileAuthError('conflict', 'Le funzioni sono state modificate. Ricarica prima di salvare.', 409);
        }
        $encoded = encode_user_feature_flags($flags);
        if ($raw !== $encoded) {
            // Compare-and-swap: never overwrite a newer web/mobile change silently.
            $stmt = $this->db->prepare("UPDATE utenti SET feature_flags = ? WHERE id = ? AND COALESCE(feature_flags, '') = ?");
            $stmt->execute([$encoded, $id, $raw]);
            if ($stmt->rowCount() !== 1) {
                throw new MobileAuthError('conflict', 'Account modificato. Ricarica prima di salvare.', 409);
            }
        }
        return $this->response($this->record($id));
    }
}
