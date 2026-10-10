<?php
require_once __DIR__ . '/../_bootstrap.php';
mobile_run(static function (): array {
    mobile_method('GET');
    (new MobileAuth(mobile_db()))->requirePermission(mobile_bearer(), 'admin');
    $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    if ($page === false) { throw new MobileAuthError('invalid_request', 'Pagina non valida.', 400); }
    $offset = ($page - 1) * 50;
    $stmt = mobile_db()->prepare('SELECT id, email, nome, cognome, ruolo, avatar, feature_flags FROM utenti ORDER BY id DESC LIMIT 51 OFFSET ?');
    $stmt->bindValue(1, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($users) > 50;
    $users = array_slice($users, 0, 50);
    return ['users' => array_map([MobileAuth::class, 'publicUser'], $users), 'page' => $page, 'has_more' => $hasMore];
});
