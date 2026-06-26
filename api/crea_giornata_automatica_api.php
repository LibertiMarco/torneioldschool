<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/auto_matchday.php';

header('Content-Type: application/json; charset=utf-8');

function auto_matchday_api_respond(array $payload, int $defaultStatus = 200): void
{
    $status = isset($payload['status']) ? (int)$payload['status'] : $defaultStatus;
    unset($payload['status']);
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function auto_matchday_api_request_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = trim((string)($_GET['action'] ?? ''));

try {
    if ($method === 'GET') {
        if ($action === 'tournaments') {
            auto_matchday_api_respond([
                'success' => true,
                'data' => auto_matchday_fetch_tournaments($conn),
            ]);
        }

        if ($action === 'context') {
            $tournamentId = (int)($_GET['tournament_id'] ?? 0);
            auto_matchday_api_respond(auto_matchday_fetch_context($conn, $tournamentId));
        }

        auto_matchday_api_respond(auto_matchday_json_error('Azione GET non valida.', 400));
    }

    if ($method === 'POST') {
        csrf_or_same_origin_require('admin_auto_matchday');
        $payload = auto_matchday_api_request_body();

        if ($action === 'preview') {
            auto_matchday_api_respond(auto_matchday_generate_preview($conn, $payload));
        }

        if ($action === 'validate') {
            auto_matchday_api_respond(auto_matchday_validate_payload($conn, $payload));
        }

        if ($action === 'save') {
            auto_matchday_api_respond(auto_matchday_save_matches($conn, $payload));
        }

        auto_matchday_api_respond(auto_matchday_json_error('Azione POST non valida.', 400));
    }

    auto_matchday_api_respond(auto_matchday_json_error('Metodo non consentito.', 405));
} catch (Throwable $e) {
    auto_matchday_api_respond(auto_matchday_json_error('Errore interno: ' . $e->getMessage(), 500));
}
