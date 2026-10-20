<?php
require_once __DIR__ . '/mobile_auth.php';
require_once __DIR__ . '/env_loader.php';

function player_link_requests_db(): PDO {
    static $db;
    if (!$db) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $name = getenv('DB_NAME') ?: '';
        if (!preg_match('/^[A-Za-z0-9_.-]+$/D', $host) || !preg_match('/^[A-Za-z0-9_-]+$/D', $name)) throw new RuntimeException('Invalid database configuration');
        $port = (int)(getenv('DB_PORT') ?: 3306);
        $db = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
        try { $db->query('SELECT id FROM player_link_requests LIMIT 0'); }
        catch (PDOException $error) {
            if ($error->getCode() !== '42S02') throw $error;
            // Additive initialization only: existing accounts and associations are untouched.
            $db->exec(file_get_contents(__DIR__ . '/../migrations/player_link_requests.sql'));
        }
    }
    return $db;
}

function player_link_requests_notify_admins(array $userIds, string $title, string $text, string $link, int $requestId): void {
    require_once __DIR__ . '/push_notifications.php';
    $conn = new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '',
        getenv('DB_NAME') ?: '', (int)(getenv('DB_PORT') ?: 3306));
    if ($conn->connect_errno) throw new RuntimeException('Notification database unavailable');
    try {
        $conn->set_charset('utf8mb4');
        tos_push_store_notifications_for_users($conn, $userIds, 'player_link_request', $title, $text, $link,
            ['tag' => 'player-link-request-' . $requestId]);
    } finally { $conn->close(); }
}

final class PlayerLinkRequests {
    public function __construct(private PDO $db, private $notifyAdmins = 'player_link_requests_notify_admins') {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    private function lock(): string { return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''; }
    private function one(string $sql, array $params): ?array {
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    private function user(int $id, bool $locked = false): array {
        $row = $id > 0 ? $this->one('SELECT id, ruolo, email_verificata FROM utenti WHERE id = ?' . ($locked ? $this->lock() : ''), [$id]) : null;
        if (!$row || !$row['email_verificata']) throw new MobileAuthError('invalid_session', 'Accedi con un account verificato.', 401);
        return $row;
    }
    private function linked(int $id): bool { return $this->one('SELECT id FROM giocatori WHERE utente_id = ? LIMIT 1', [$id]) !== null; }
    private function admin(int $id): void {
        $user = $this->user($id, true);
        if (!user_has_admin_access((string)$user['ruolo'])) throw new MobileAuthError('forbidden', 'Funzione riservata agli amministratori.', 403);
    }
    public function status(int $userId): array {
        $this->user($userId);
        $row = $this->one('SELECT r.id, r.stato AS status, r.created_at, r.reviewed_at, r.reason,
            g.id AS player_id, g.nome, g.cognome, g.ruolo,
            CASE WHEN g.utente_id IS NULL THEN 0 ELSE 1 END AS already_linked
            FROM player_link_requests r LEFT JOIN giocatori g ON g.id = r.giocatore_id
            WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 1', [$userId]);
        return ['linked'=>$this->linked($userId), 'request'=>$row];
    }
    public function search(int $userId, string $query): array {
        $this->user($userId);
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        if (mb_strlen($query) > 100) throw new MobileAuthError('invalid_request', 'Ricerca troppo lunga.', 400);
        $name = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? "CONCAT(nome, ' ', cognome)" : "nome || ' ' || cognome";
        $reverse = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? "CONCAT(cognome, ' ', nome)" : "cognome || ' ' || nome";
        $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
        $stmt = $this->db->prepare("SELECT g.id, g.nome, g.cognome, g.ruolo, g.foto,
            CASE WHEN g.utente_id IS NULL THEN 0 ELSE 1 END AS already_linked,
            (SELECT s.nome FROM squadre_giocatori sg JOIN squadre s ON s.id = sg.squadra_id
             WHERE sg.giocatore_id = g.id ORDER BY sg.created_at DESC LIMIT 1) AS squadra
            FROM giocatori g
            WHERE ($name LIKE ? ESCAPE '!' OR $reverse LIKE ? ESCAPE '!')
            ORDER BY cognome, nome, id LIMIT 20");
        $stmt->execute([$term, $term]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function submit(int $userId, int $playerId): array {
        if ($playerId <= 0) throw new MobileAuthError('invalid_request', 'Seleziona il tuo giocatore.', 400);
        $this->db->beginTransaction();
        try {
            $this->user($userId, true);
            if ($this->linked($userId)) throw new MobileAuthError('conflict', 'Il tuo account ha già un giocatore collegato.', 409);
            if ($this->one("SELECT id FROM player_link_requests WHERE user_id = ? AND stato = 'pending'", [$userId])) {
                throw new MobileAuthError('conflict', 'Hai già una richiesta in attesa di approvazione.', 409);
            }
            $player = $this->one('SELECT id, utente_id FROM giocatori WHERE id = ?' . $this->lock(), [$playerId]);
            if (!$player) throw new MobileAuthError('conflict', 'Giocatore non disponibile.', 409);
            $stmt = $this->db->prepare("INSERT INTO player_link_requests (user_id, giocatore_id, stato, pending_user_id, created_at) VALUES (?, ?, 'pending', ?, ?)");
            $stmt->execute([$userId, $playerId, $userId, gmdate('Y-m-d H:i:s')]);
            $requestId = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
        // Notify only committed requests; delivery failures must not invite duplicate submissions.
        try {
            $adminIds = [];
            foreach ($this->db->query('SELECT id, ruolo FROM utenti')->fetchAll(PDO::FETCH_ASSOC) as $user) {
                if (user_has_admin_access((string)$user['ruolo'])) $adminIds[] = (int)$user['id'];
            }
            if ($adminIds) {
                $requester = $this->one('SELECT nome, cognome FROM utenti WHERE id = ?', [$userId]);
                $player = $this->one('SELECT nome, cognome FROM giocatori WHERE id = ?', [$playerId]);
                $accountName = trim(($requester['nome'] ?? '') . ' ' . ($requester['cognome'] ?? '')) ?: 'Account #' . $userId;
                $playerName = trim(($player['nome'] ?? '') . ' ' . ($player['cognome'] ?? '')) ?: 'Giocatore #' . $playerId;
                ($this->notifyAdmins)($adminIds, 'Nuova richiesta di associazione giocatore',
                    $accountName . ' ha richiesto di associare il proprio account a ' . $playerName . '.',
                    '/api/gestione_account_giocatore.php', $requestId);
            }
        } catch (Throwable $error) {
            error_log('player_link_requests: notifica admin fallita per richiesta #' . $requestId . ': ' . $error->getMessage());
        }
        return $this->status($userId);
    }
    public function pending(int $adminId): array {
        $this->admin($adminId);
        return $this->db->query("SELECT r.id, r.created_at, u.nome AS user_nome, u.cognome AS user_cognome, u.email,
            g.id AS player_id, g.nome, g.cognome, g.ruolo, g.utente_id
            FROM player_link_requests r JOIN utenti u ON u.id = r.user_id LEFT JOIN giocatori g ON g.id = r.giocatore_id
            WHERE r.stato = 'pending' ORDER BY r.id LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function review(int $adminId, int $requestId, bool $accept, string $reason = ''): void {
        if ($requestId <= 0 || mb_strlen($reason) > 500) throw new MobileAuthError('invalid_request', 'Richiesta o motivo non valido.', 400);
        $this->db->beginTransaction();
        try {
            $this->admin($adminId);
            $request = $this->one('SELECT * FROM player_link_requests WHERE id = ?' . $this->lock(), [$requestId]);
            if (!$request || $request['stato'] !== 'pending') throw new MobileAuthError('conflict', 'La richiesta è già stata gestita o non esiste.', 409);
            if ($accept) {
                $userId = (int)$request['user_id']; $playerId = (int)$request['giocatore_id'];
                $this->user($userId, true);
                if ($this->linked($userId)) throw new MobileAuthError('conflict', 'Questo account è già associato a un giocatore.', 409);
                $player = $this->one('SELECT id, utente_id FROM giocatori WHERE id = ?' . $this->lock(), [$playerId]);
                if (!$player || $player['utente_id'] !== null) throw new MobileAuthError('conflict', 'Il giocatore è già associato a un altro account.', 409);
                $stmt = $this->db->prepare('UPDATE giocatori SET utente_id = ? WHERE id = ? AND utente_id IS NULL');
                $stmt->execute([$userId, $playerId]);
                if ($stmt->rowCount() !== 1) throw new MobileAuthError('conflict', 'Associazione modificata. Ricarica e riprova.', 409);
            }
            $now = gmdate('Y-m-d H:i:s');
            $stmt = $this->db->prepare('UPDATE player_link_requests SET stato = ?, pending_user_id = NULL, reviewed_at = ?, reviewed_by = ?, reason = ? WHERE id = ?');
            $stmt->execute([$accept ? 'accepted' : 'rejected', $now, $adminId, $accept ? '' : trim($reason), $requestId]);
            if ($accept) {
                $stmt = $this->db->prepare("UPDATE player_link_requests SET stato = 'rejected', pending_user_id = NULL, reviewed_at = ?, reviewed_by = ?, reason = 'Giocatore già associato a un altro account.' WHERE giocatore_id = ? AND stato = 'pending'");
                $stmt->execute([$now, $adminId, $request['giocatore_id']]);
            }
            $this->db->commit();
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
}

function player_link_requests_handle(PDO $db, int $userId, array $query, ?array $input): array {
    $service = new PlayerLinkRequests($db);
    if ($input !== null) {
        if (array_diff(array_keys($input), ['giocatore_id', '_csrf']) || !is_int($input['giocatore_id'] ?? null)) throw new MobileAuthError('invalid_request', 'Richiesta non valida.', 400);
        return $service->submit($userId, $input['giocatore_id']);
    }
    if (array_diff(array_keys($query), ['q', 'action']) || !is_string($query['q'] ?? '')) throw new MobileAuthError('invalid_request', 'Ricerca non valida.', 400);
    return [...$service->status($userId), 'players'=>$service->search($userId, $query['q'] ?? '')];
}
