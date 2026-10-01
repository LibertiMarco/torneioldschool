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
    $localDirectory = __DIR__ . '/../cache/graphics-templates';
    if ($method === 'GET') {
        $templates = [];
        foreach (['ft', 'mvp'] as $format) {
            $templates[$format] = graphics_template_load($directory . '/' . $id . '-' . $format . '.json', $localDirectory . '/' . $id . '-' . $format . '.json');
        }
        echo json_encode($templates, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
    $type = $input['type'] ?? '';
    if (!in_array($type, ['ft', 'mvp'], true)) {
        throw new InvalidArgumentException('Formato non valido.');
    }
    $path = $directory . '/' . $id . '-' . $type . '.json';
    $localPath = $localDirectory . '/' . $id . '-' . $type . '.json';
    if (($input['action'] ?? '') === 'remove') {
        graphics_template_store($path, $localPath, null);
    } else {
        $layout = json_decode($input['layout'] ?? '', true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($layout)) {
            throw new InvalidArgumentException('Posizioni non valide.');
        }
        $layout = graphics_template_layout($layout, $type);
        $previous = graphics_template_load($path, $localPath);
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
        $overlays = [];
        if ($type === 'ft') {
            $overlayMeta = json_decode($input['overlays'] ?? '[]', true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($overlayMeta) || !array_is_list($overlayMeta)) {
                throw new InvalidArgumentException('Elenco dei livelli immagine non valido.');
            }
            $previousOverlays = $previous['overlays'] ?? [];
            if (!$previousOverlays && !empty($previous['score_overlay'])) {
                $oldRect = $previous['layout']['scoreOverlay'] ?? [];
                $previousOverlays = [[
                    'id' => 'legacy-score-overlay', 'name' => 'Box risultato', 'image' => $previous['score_overlay'],
                    'source_width' => 1080, 'source_height' => 1350,
                    'x' => (int)($oldRect['x'] ?? 0), 'y' => (int)($oldRect['y'] ?? 0),
                    'w' => (int)($oldRect['w'] ?? 1080), 'h' => (int)($oldRect['h'] ?? 1350),
                    'layer' => 'between_photo_content'
                ]];
            }
            $previousById = [];
            foreach ($previousOverlays as $oldOverlay) {
                if (is_array($oldOverlay) && isset($oldOverlay['id'])) $previousById[(string)$oldOverlay['id']] = $oldOverlay;
            }
            $allowedLayers = ['behind_graphic', 'between_graphic_photo', 'between_photo_content', 'front'];
            foreach ($overlayMeta as $index => $meta) {
                if (!is_array($meta)) throw new InvalidArgumentException('Livello immagine non valido.');
                $overlayId = $meta['id'] ?? '';
                if (!is_string($overlayId) || !preg_match('/^[A-Za-z0-9_-]{1,120}$/', $overlayId) || isset($overlays[$overlayId])) {
                    throw new InvalidArgumentException('Identificativo del livello immagine non valido.');
                }
                $name = is_string($meta['name'] ?? null) ? substr($meta['name'], 0, 180) : 'Livello immagine';
                $layer = $meta['layer'] ?? '';
                if (!in_array($layer, $allowedLayers, true)) throw new InvalidArgumentException('Livello di composizione non valido.');
                $values = [];
                foreach (['x' => [-100000, 100000], 'y' => [-100000, 100000], 'w' => [0.01, 100000], 'h' => [0.01, 100000], 'source_width' => [1, 16000000], 'source_height' => [1, 16000000]] as $field => $limits) {
                    $value = $meta[$field] ?? null;
                    if (!is_numeric($value) || !is_finite((float)$value) || $value < $limits[0] || $value > $limits[1]) {
                        throw new InvalidArgumentException('Posizione o misura del livello immagine non valida.');
                    }
                    $values[$field] = in_array($field, ['w', 'h'], true) ? round((float)$value, 6) : (int)round((float)$value);
                }
                $saved = $previousById[$overlayId] ?? null;
                $imageData = is_array($saved) ? ($saved['image'] ?? null) : null;
                $uploaded = isset($_FILES['overlay_files']['error'][$index]) && $_FILES['overlay_files']['error'][$index] !== UPLOAD_ERR_NO_FILE;
                if ($uploaded) {
                    if ($_FILES['overlay_files']['error'][$index] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['overlay_files']['tmp_name'][$index])) {
                        throw new InvalidArgumentException('Caricamento di un livello immagine non riuscito.');
                    }
                    $bytes = file_get_contents($_FILES['overlay_files']['tmp_name'][$index]);
                    $info = @getimagesizefromstring($bytes);
                    if (!$info || ($info[0] ?? 0) !== $values['source_width'] || ($info[1] ?? 0) !== $values['source_height']) {
                        throw new InvalidArgumentException('Le misure del file non corrispondono a quelle dichiarate. Ricaricalo.');
                    }
                    $imageData = graphics_template_image($bytes);
                }
                if (!is_string($imageData) || $imageData === '') throw new InvalidArgumentException('Ricarica il livello immagine selezionato.');
                $aspect = $values['source_width'] / $values['source_height'];
                if (abs(($values['w'] / $values['h']) - $aspect) > 0.001) {
                    throw new InvalidArgumentException('Le immagini possono essere ridimensionate solo mantenendo le proporzioni.');
                }
                $overlays[$overlayId] = [
                    'id' => $overlayId, 'name' => $name, 'image' => $imageData,
                    'source_width' => $values['source_width'], 'source_height' => $values['source_height'],
                    'x' => $values['x'], 'y' => $values['y'], 'w' => $values['w'], 'h' => $values['h'], 'layer' => $layer
                ];
            }
            $overlays = array_values($overlays);
        }
        $record = ['image' => $image, 'layout' => $layout];
        if ($type === 'ft') $record['overlays'] = $overlays;
        graphics_template_store($path, $localPath, $record);
    }
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException | JsonException $error) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('graphics_templates: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $error instanceof GraphicsTemplateStorageException ? $error->getMessage() : 'Impossibile leggere o salvare le basi. Riprova.']);
}
