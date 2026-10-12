<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET'); http_response_code(405);
    echo json_encode(['error'=>'Metodo non consentito.']); exit;
}
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/content_sections.php';
require_once __DIR__ . '/../includi/albo_archive.php';
try {
    $section=content_requested_read_section();
    $records=albo_archive_read($conn,$section);
    echo json_encode(['data'=>albo_archive_group($records)],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $error) {
    http_response_code(400); echo json_encode(['error'=>'Sezione non valida.']);
} catch (Throwable $error) {
    error_log('Hall of fame: ' . get_class($error) . ' request failed');
    http_response_code(503); echo json_encode(['error'=>'Albo momentaneamente non disponibile.']);
}
