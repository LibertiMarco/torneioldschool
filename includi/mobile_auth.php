<?php
require_once __DIR__ . '/user_features.php';

final class MobileAuthError extends RuntimeException
{
    public function __construct(public string $errorCode, string $message, public int $status = 401)
    {
        parent::__construct($message);
    }
}

/** Opaque credentials: only hashes are persisted; no web-session impersonation. */
final class MobileAuth
{
    public const CALLBACK = 'tosoldschool://auth/callback';
    public const ACCESS_TTL = 900;
    public const REFRESH_TTL = 2592000;
    private Closure $clock;

    public function __construct(private PDO $db, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn(): int => time();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function challenge(string $verifier): string
    {
        if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier)) {
            throw new MobileAuthError('invalid_request', 'Verifica accesso non valida.', 400);
        }
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function validateAuthorization(array $input): array
    {
        if (($input['client_id'] ?? '') !== 'tos-mobile'
            || ($input['redirect_uri'] ?? '') !== self::CALLBACK
            || ($input['response_type'] ?? '') !== 'code'
            || ($input['code_challenge_method'] ?? '') !== 'S256'
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', (string)($input['code_challenge'] ?? ''))
            || !preg_match('/^[A-Za-z0-9_-]{32,128}$/D', (string)($input['state'] ?? ''))) {
            throw new MobileAuthError('invalid_request', 'Richiesta di accesso non valida.', 400);
        }
        return ['challenge' => $input['code_challenge'], 'state' => $input['state']];
    }

    private function now(): int { return ($this->clock)(); }
    private function lock(): string { return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''; }

    private function row(string $sql, array $values): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function execute(string $sql, array $values): void
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    private function account(int $id): array
    {
        $user = $this->row('SELECT id, email, nome, cognome, ruolo, avatar, password, email_verificata, feature_flags FROM utenti WHERE id = ?', [$id]);
        if (!$user || (int)$user['email_verificata'] !== 1) {
            throw new MobileAuthError('invalid_session', 'Accedi nuovamente al tuo account.');
        }
        return $user;
    }

    private function checkAccount(array $session): array
    {
        $user = $this->account((int)$session['user_id']);
        if (!hash_equals($session['password_fingerprint'], hash('sha256', $user['password']))) {
            throw new MobileAuthError('invalid_session', 'Accedi nuovamente al tuo account.');
        }
        return $user;
    }

    public static function publicUser(array $user): array
    {
        $role = trim((string)$user['ruolo']);
        return [
            'id' => (int)$user['id'], 'email' => $user['email'], 'nome' => $user['nome'],
            'cognome' => $user['cognome'], 'avatar' => $user['avatar'], 'ruolo' => $role,
            'feature_flags' => normalize_user_feature_flags($user['feature_flags'] ?? null),
            'permissions' => ['admin' => user_has_admin_access($role), 'graphics' => user_has_graphics_access($role)],
        ];
    }

    public function authorize(int $userId, string $challenge): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge)) {
            throw new MobileAuthError('invalid_request', 'Richiesta non valida.', 400);
        }
        $user = $this->account($userId);
        $code = self::randomToken();
        $this->execute('INSERT INTO mobile_auth_codes (code_hash, user_id, challenge, password_fingerprint, expires_at) VALUES (?, ?, ?, ?, ?)',
            [hash('sha256', $code), $userId, $challenge, hash('sha256', $user['password']), $this->now() + 120]);
        return $code;
    }

    private function response(string $access, string $refresh, array $user): array
    {
        return ['access_token' => $access, 'refresh_token' => $refresh, 'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL, 'user' => self::publicUser($user)];
    }

    public function exchange(string $code, string $verifier): array
    {
        $challenge = self::challenge($verifier);
        $this->db->beginTransaction();
        try {
            $grant = $this->row('SELECT * FROM mobile_auth_codes WHERE code_hash = ?' . $this->lock(), [hash('sha256', $code)]);
            if (!$grant || (int)$grant['expires_at'] <= $this->now() || !hash_equals($grant['challenge'], $challenge)) {
                throw new MobileAuthError('invalid_grant', 'Accesso scaduto o non valido.');
            }
            $user = $this->checkAccount($grant);
            $this->execute('DELETE FROM mobile_auth_codes WHERE code_hash = ?', [$grant['code_hash']]);
            $sessionId = bin2hex(random_bytes(16));
            $access = self::randomToken();
            $refresh = self::randomToken();
            $expires = $this->now() + self::REFRESH_TTL;
            $this->execute('INSERT INTO mobile_sessions (id, user_id, password_fingerprint, access_hash, access_expires_at, refresh_expires_at, revoked_at) VALUES (?, ?, ?, ?, ?, ?, NULL)',
                [$sessionId, $user['id'], $grant['password_fingerprint'], hash('sha256', $access), $this->now() + self::ACCESS_TTL, $expires]);
            $this->execute('INSERT INTO mobile_refresh_tokens (token_hash, session_id, expires_at, used_at) VALUES (?, ?, ?, NULL)', [hash('sha256', $refresh), $sessionId, $expires]);
            $this->db->commit();
            return $this->response($access, $refresh, $user);
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $error;
        }
    }

    public function refresh(string $refresh): array
    {
        $this->db->beginTransaction();
        try {
            $token = $this->row('SELECT * FROM mobile_refresh_tokens WHERE token_hash = ?' . $this->lock(), [hash('sha256', $refresh)]);
            $session = $token ? $this->row('SELECT * FROM mobile_sessions WHERE id = ?' . $this->lock(), [$token['session_id']]) : null;
            if (!$session || $session['revoked_at'] !== null || (int)$session['refresh_expires_at'] <= $this->now()) {
                throw new MobileAuthError('invalid_grant', 'Sessione scaduta. Accedi nuovamente.');
            }
            if ($token['used_at'] !== null) {
                $this->execute('UPDATE mobile_sessions SET revoked_at = ? WHERE id = ?', [$this->now(), $session['id']]);
                $this->db->commit();
                throw new MobileAuthError('invalid_grant', 'Sessione revocata. Accedi nuovamente.');
            }
            $user = $this->checkAccount($session);
            $access = self::randomToken();
            $nextRefresh = self::randomToken();
            $this->execute('UPDATE mobile_refresh_tokens SET used_at = ? WHERE token_hash = ?', [$this->now(), $token['token_hash']]);
            $this->execute('UPDATE mobile_sessions SET access_hash = ?, access_expires_at = ? WHERE id = ?',
                [hash('sha256', $access), $this->now() + self::ACCESS_TTL, $session['id']]);
            $this->execute('INSERT INTO mobile_refresh_tokens (token_hash, session_id, expires_at, used_at) VALUES (?, ?, ?, NULL)',
                [hash('sha256', $nextRefresh), $session['id'], $session['refresh_expires_at']]);
            $this->db->commit();
            return $this->response($access, $nextRefresh, $user);
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $error;
        }
    }

    public function authenticate(string $access): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $access)) {
            throw new MobileAuthError('invalid_session', 'Accesso richiesto.');
        }
        $session = $this->row('SELECT * FROM mobile_sessions WHERE access_hash = ?', [hash('sha256', $access)]);
        if (!$session || $session['revoked_at'] !== null || (int)$session['access_expires_at'] <= $this->now()
            || (int)$session['refresh_expires_at'] <= $this->now()) {
            throw new MobileAuthError('invalid_session', 'Sessione scaduta.');
        }
        return self::publicUser($this->checkAccount($session));
    }

    public function requirePermission(string $access, string $permission): array
    {
        $user = $this->authenticate($access);
        if (empty($user['permissions'][$permission])) {
            throw new MobileAuthError('forbidden', 'Permesso non disponibile.', 403);
        }
        return $user;
    }

    public function logout(string $access): void
    {
        $this->execute('UPDATE mobile_sessions SET revoked_at = ? WHERE access_hash = ?', [$this->now(), hash('sha256', $access)]);
    }
}
