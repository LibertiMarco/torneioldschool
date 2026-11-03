<?php
declare(strict_types=1);
// Retired paid API endpoint. Old clients must reload the browser-only workflow.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
http_response_code(410);
echo json_encode(['error' => 'La rimozione sfondo ora avviene sul dispositivo. Ricarica la pagina delle grafiche.'], JSON_UNESCAPED_UNICODE);
