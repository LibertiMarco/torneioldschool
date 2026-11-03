<?php
declare(strict_types=1);

require_once __DIR__ . '/../includi/security.php';
require_once __DIR__ . '/../includi/user_features.php';
require_once __DIR__ . '/../includi/env_loader.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cutout_reply(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!isset($_SESSION['user_id']) || !user_has_graphics_access((string)($_SESSION['ruolo'] ?? ''))) {
    cutout_reply(['error' => 'Accesso alle grafiche richiesto.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    cutout_reply(['error' => 'Metodo non consentito.'], 405);
}
if (!csrf_is_valid((string)($_POST['_csrf'] ?? ''), 'graphics_cutout')) {
    cutout_reply(['error' => 'Sessione scaduta: ricarica la pagina.'], 403);
}
$key = trim((string)getenv('OPENAI_API_KEY'));
if ($key === '') cutout_reply(['error' => 'OPENAI_API_KEY non configurata sul server.'], 503);
if (!function_exists('curl_init')) cutout_reply(['error' => 'Estensione PHP cURL non disponibile sul server.'], 503);
$file = $_FILES['image'] ?? [];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
    cutout_reply(['error' => 'Caricamento non riuscito. Controlla i limiti upload del server.'], 400);
}
if (($file['size'] ?? 0) > 12 * 1024 * 1024) cutout_reply(['error' => 'Foto troppo grande (massimo 12 MB).'], 413);
$info = @getimagesize($file['tmp_name']);
if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)
    || $info[0] * $info[1] > 20000000) {
    cutout_reply(['error' => 'Usa una foto PNG, JPG o WebP fino a 20 megapixel.'], 400);
}
// Keep the session locked during the edit to serialize paid requests from this session.
if (time() - (int)($_SESSION['graphics_cutout_last_request'] ?? 0) < 15) {
    cutout_reply(['error' => 'Attendi qualche secondo prima di elaborare un’altra foto.'], 429);
}
$_SESSION['graphics_cutout_last_request'] = time();
$subjects = ($_POST['subjects'] ?? 'main') === 'all'
    ? 'Keep ALL people in the photograph, including smaller people in the group.'
    : 'Keep only the largest foreground people; remove distant background people.';
$prompt = 'Edit this photograph into a clean photographic cutout on a truly transparent background. '
    . $subjects . ' Remove the entire background, floodlights, lens flare, starbursts, light streaks and unwanted glow or halos. '
    . 'If glare overlaps a subject, reduce it conservatively without inventing obscured details. '
    . 'Preserve identity, facial features, hair, hands, pose, clothing, jersey text, numbers, logos and natural colors as closely as possible. '
    . 'Keep the original composition and aspect ratio; do not crop heads or feet, add people, beautify or stylize. '
    . 'Keep fine hair edges and natural antialiasing. No replacement background, no checkerboard and no cast shadow.';
@set_time_limit(240);
$curl = curl_init('https://api.openai.com/v1/images/edits');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 210,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
    CURLOPT_POSTFIELDS => [
        'model' => trim((string)getenv('OPENAI_IMAGE_MODEL')) ?: 'gpt-image-2.5-sunburst',
        'image' => new CURLFile($file['tmp_name'], $info['mime'], 'photo'),
        'prompt' => $prompt,
        'background' => 'transparent',
        'output_format' => 'png',
        'quality' => 'high',
        'size' => 'auto',
        'n' => '1',
    ],
]);
$raw = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
$connectionError = curl_errno($curl);
curl_close($curl);
if ($raw === false) {
    error_log('graphics_remove_background: cURL error ' . $connectionError);
    cutout_reply(['error' => 'Connessione OpenAI non riuscita (cURL ' . $connectionError . '). Controlla certificati SSL e connessione del server. Foto originale conservata; verifica l’utilizzo API prima di riprovare.'], 502);
}
$result = json_decode($raw, true);
if ($status < 200 || $status >= 300) {
    $detail = substr(str_replace($key, '[chiave nascosta]', (string)($result['error']['message'] ?? '')), 0, 600);
    error_log('graphics_remove_background: HTTP ' . $status . ' ' . $detail);
    $message = match ($status) {
        401 => 'Chiave OpenAI non valida: controlla OPENAI_API_KEY sul server.',
        403 => 'Il progetto OpenAI non ha accesso al modello immagini. Controlla permessi e verifica organizzazione.',
        429 => 'Credito o limite OpenAI esaurito: controlla Billing e limiti del progetto.',
        400 => 'OpenAI non può elaborare questa foto o configurazione. Controlla il modello e prova un’altra foto.',
        default => 'Elaborazione OpenAI non riuscita. Riprova più tardi.',
    };
    if ($status === 400 && $detail !== '') $message .= ' Dettaglio: ' . $detail;
    cutout_reply(['error' => $message . ' (HTTP OpenAI ' . $status . ')'], 502);
}
$image = $result['data'][0]['b64_json'] ?? null;
$bytes = is_string($image) ? base64_decode($image, true) : false;
if (!$bytes || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
    cutout_reply(['error' => 'OpenAI non ha restituito una foto PNG valida.'], 502);
}
cutout_reply(['image' => 'data:image/png;base64,' . $image]);
