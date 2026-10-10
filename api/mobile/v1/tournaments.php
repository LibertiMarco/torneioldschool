<?php
require_once __DIR__ . '/_bootstrap.php';
mobile_run(static function (): array {
    mobile_method('GET');
    $section = $_GET['section'] ?? 'calcio';
    if (!in_array($section, ['calcio', 'esport'], true)) {
        throw new MobileAuthError('invalid_request', 'Sezione non valida.', 400);
    }
    $stmt = mobile_db()->prepare('SELECT id, nome, stato, data_inizio, data_fine, img, filetorneo, categoria, sezione FROM tornei WHERE sezione = ? ORDER BY data_inizio DESC');
    $stmt->execute([$section]);
    return ['tournaments' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
});
