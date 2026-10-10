<?php
require_once __DIR__ . '/../api/mobile/v1/_bootstrap.php';
require_once __DIR__ . '/../includi/security.php';
require_once __DIR__ . '/../includi/mobile_account_features.php';
require_once __DIR__ . '/../includi/app_preview.php';
require_once __DIR__ . '/../includi/mobile_profile.php';

try {
    if (empty($_SESSION['user_id'])) {
        throw new MobileAuthError('invalid_session', 'Accedi con il tuo account del sito.', 401);
    }
    $db = mobile_db();
    $user = preview_admin($db, $_SESSION);
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $body = null;
    if ($method === 'POST') {
        $multipart = str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
        $body = $action === 'account' && $multipart ? $_POST : mobile_input();
        if (!csrf_is_valid(is_string($body['_csrf'] ?? null) ? $body['_csrf'] : null, 'app_preview')) {
            throw new MobileAuthError('invalid_csrf', 'Sessione scaduta. Ricarica prima di salvare.', 403);
        }
    } elseif ($method !== 'GET') {
        throw new MobileAuthError('method_not_allowed', 'Metodo non consentito.', 405);
    }
    if ($action === 'account') {
        mobile_json(mobile_profile_handle((int)$user['id'], $body, $_FILES, true));
    }
    if ($action === 'features') {
        $service = new MobileAccountFeatures($db, static fn() => preview_admin($db, $_SESSION));
        if ($method === 'GET') {
            $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) { throw new MobileAuthError('invalid_request', 'Account non valido.', 400); }
            mobile_json($service->get('', $id));
        }
        if (!is_int($body['id'] ?? null) || !is_array($body['feature_flags'] ?? null) || !is_string($body['revision'] ?? null)) {
            throw new MobileAuthError('invalid_request', 'Richiesta non valida.', 400);
        }
        mobile_json($service->save('', $body['id'], $body['feature_flags'], $body['revision']));
    }
    mobile_method('GET');
    if ($action === 'session') {
        mobile_json(['user' => $user, 'csrf' => csrf_get_token('app_preview')]);
    }
    if ($action === 'tournaments') {
        $section = $_GET['section'] ?? 'calcio';
        if (!in_array($section, ['calcio', 'esport'], true)) { throw new MobileAuthError('invalid_request', 'Sezione non valida.', 400); }
        $stmt = $db->prepare('SELECT id, nome, stato, data_inizio, data_fine, img, filetorneo, categoria, sezione FROM tornei WHERE sezione = ? ORDER BY data_inizio DESC');
        $stmt->execute([$section]);
        mobile_json(['tournaments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($action === 'users') {
        $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($page === false) { throw new MobileAuthError('invalid_request', 'Pagina non valida.', 400); }
        $stmt = $db->prepare('SELECT id, email, nome, cognome, ruolo, avatar, feature_flags FROM utenti ORDER BY id DESC LIMIT 51 OFFSET ?');
        $stmt->bindValue(1, ($page - 1) * 50, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        mobile_json(['users' => array_map([MobileAuth::class, 'publicUser'], array_slice($rows, 0, 50)), 'page' => $page, 'has_more' => count($rows) > 50]);
    }
    throw new MobileAuthError('not_found', 'Funzione non disponibile.', 404);
} catch (MobileAuthError $error) {
    mobile_json(['error' => ['code' => $error->errorCode, 'message' => $error->getMessage()]], $error->status);
} catch (Throwable $error) {
    error_log('App preview: ' . get_class($error) . ' request failed');
    mobile_json(['error' => ['code' => 'server_error', 'message' => 'Servizio temporaneamente non disponibile.']], 503);
}
