<?php
require_once __DIR__ . '/../includi/session_role.php';
require_once __DIR__ . '/../includi/user_features.php';

function expect_role(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Nessuna connessione al database reale: simuliamo cambi tra richieste.
class RoleResult extends mysqli_result
{
    private $row;
    public function __construct(?array $row) { $this->row = $row; }
    public function fetch_assoc(): ?array { return $this->row; }
    public function free(): void {}
}

class RoleStatement extends mysqli_stmt
{
    public $userId;
    public $closed = false;
    private $fixture;
    public function __construct(RoleConnection $fixture) { $this->fixture = $fixture; }
    public function bind_param(string $types, mixed &...$vars): bool
    {
        $this->userId = $vars[0];
        return true;
    }
    public function execute(?array $params = null): bool { return $this->fixture->failure !== 'execute'; }
    public function get_result(): mysqli_result|false
    {
        return $this->fixture->failure === 'result' ? false : new RoleResult($this->fixture->row);
    }
    public function close(): bool { $this->closed = true; return true; }
}

class RoleConnection extends mysqli
{
    public $row = ['ruolo' => 'user'];
    public $failure = '';
    public $statement;
    public $queries = 0;
    public function __construct() {}
    public function prepare(string $query): mysqli_stmt|false
    {
        expect_role($query === 'SELECT ruolo FROM utenti WHERE id = ? LIMIT 1', 'Lettura ruolo per ID');
        $this->queries++;
        return $this->failure === 'prepare' ? false : ($this->statement = new RoleStatement($this));
    }
}

$conn = new RoleConnection();
$session = [];
expect_role(!tos_refresh_session_role($conn, $session) && $conn->queries === 0, 'Ospiti senza query');

$session = ['user_id' => 42, 'ruolo' => 'user', 'remember_me' => true, '_csrf_tokens' => ['test' => 'token']];
foreach (['admin', 'grafico', 'user', 'sysadmin', 'user'] as $role) {
    $conn->row = ['ruolo' => $role];
    expect_role(tos_refresh_session_role($conn, $session), 'Sessione valida');
    expect_role($session['ruolo'] === $role, 'Promozioni e revoche attive alla richiesta successiva');
    expect_role(user_has_admin_access($session['ruolo']) === in_array($role, ['admin', 'sysadmin'], true), 'Permessi admin aggiornati');
    expect_role(user_has_graphics_access($session['ruolo']) === ($role !== 'user'), 'Permessi grafico aggiornati');
    expect_role($conn->statement->userId === 42 && $conn->statement->closed, 'Utente corretto e statement chiuso');
    expect_role($session['remember_me'] && $session['_csrf_tokens']['test'] === 'token', 'Login persistente e CSRF preservati');
}

$conn->row = null;
expect_role(!tos_refresh_session_role($conn, $session) && $session === [], 'Account eliminato disconnesso');

foreach (['prepare', 'execute', 'result'] as $failure) {
    $conn->failure = $failure;
    $session = ['user_id' => 42, 'ruolo' => 'admin'];
    $failed = false;
    try {
        tos_refresh_session_role($conn, $session);
    } catch (RuntimeException $e) {
        $failed = true;
    }
    expect_role($failed && !isset($session['ruolo']), 'Errore DB senza mantenere vecchi privilegi: ' . $failure);
    expect_role($session['user_id'] === 42, 'Sessione recuperabile dopo errore temporaneo');
    if ($failure !== 'prepare') {
        expect_role($conn->statement->closed, 'Statement chiuso anche in caso di errore');
    }
}

$conn->failure = '';
$conn->row = ['ruolo' => 'grafico'];
expect_role(tos_refresh_session_role($conn, $session) && $session['ruolo'] === 'grafico', 'Ripristino dopo errore DB');
echo "OK: aggiornamento ruoli, revoche, login persistente ed errori DB.\n";
