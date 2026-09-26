<?php
require_once __DIR__ . '/../includi/graphics_guard.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/graphics_templates.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'POST'], true)) {
        http_response_code(405);
        header('Allow: GET, POST');
        throw new InvalidArgumentException('Metodo non consentito.');
    }
    if ($method === 'POST' && !csrf_is_valid($_POST['_csrf'] ?? '', 'graphics_templates')) {
        http_response_code(403);
        throw new InvalidArgumentException('Sessione scaduta o file troppo grande: ricarica la pagina e riprova.');
    }
    $input = $method === 'POST' ? $_POST : $_GET;
    $id = filter_var($input['torneo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        throw new InvalidArgumentException('Seleziona un torneo.');
    }
    $stmt = $conn->prepare('SELECT id FROM tornei WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('Torneo non trovato.');
    }
    $stmt->close();
    $directory = tos_runtime_path('graphics-templates');
    if ($method === 'GET') {
        echo json_encode(['ft' => graphics_template_read($directory . '/' . $id . '-ft.json'), 'mvp' => graphics_template_read($directory . '/' . $id . '-mvp.json')], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
    $type = $input['type'] ?? '';
    if (!in_array($type, ['ft', 'mvp'], true)) {
        throw new InvalidArgumentException('Formato non valido.');
    }
    $path = $directory . '/' . $id . '-' . $type . '.json';
    if (($input['action'] ?? '') === 'remove') {
        graphics_template_write($path, null);
    } else {
        $layout = json_decode($input['layout'] ?? '', true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($layout)) {
            throw new InvalidArgumentException('Posizioni non valide.');
        }
        $layout = graphics_template_layout($layout, $type);
        $previous = graphics_template_read($path);
        $image = $previous['image'] ?? null;
        if (isset($_FILES['base']) && $_FILES['base']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['base']['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Caricamento non riuscito: verifica il limite upload del server (massimo 8 MB).');
            }
            if (!is_uploaded_file($_FILES['base']['tmp_name'])) {
                throw new InvalidArgumentException('Caricamento non valido.');
            }
            $image = graphics_template_image(file_get_contents($_FILES['base']['tmp_name']));
        }
        if (!$image) {
            throw new InvalidArgumentException('Carica prima una base.');
        }
        graphics_template_write($path, ['image' => $image, 'layout' => $layout]);
    }
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException | JsonException $error) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('graphics_templates: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Impossibile leggere o salvare le basi. Riprova.']);
}
