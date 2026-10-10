<?php
require_once __DIR__ . '/account_profile.php';

function mobile_profile_input(array $input): array {
    $allowed = ['nome', 'cognome', 'password', 'confirm_password', 'current_password',
        'consenso_newsletter', 'consenso_marketing', 'consenso_tracking',
        'upload_foto_giocatore', 'revoca_consensi', '_csrf'];
    foreach ($input as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            throw new MobileAuthError('invalid_request', 'Campo account non consentito.', 400);
        }
        if (str_starts_with($key, 'consenso_')) {
            if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                throw new MobileAuthError('invalid_request', 'Preferenza non valida.', 400);
            }
            $input[$key] = !empty($value) ? 1 : 0;
        } elseif (!is_string($value) || strlen($value) > 1024) {
            throw new MobileAuthError('invalid_request', 'Campo account non valido.', 400);
        }
    }
    return $input;
}

// Identity is supplied exclusively by the verified bearer/session endpoint.
function mobile_profile_handle(int $userId, ?array $input, array $files = [], bool $browserSession = false): array {
    if ($userId <= 0) { throw new MobileAuthError('invalid_session', 'Accesso richiesto.', 401); }
    $input = $input === null ? null : mobile_profile_input($input);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: '',
        getenv('DB_PASSWORD') ?: '', getenv('DB_NAME') ?: '', (int)(getenv('DB_PORT') ?: 3306));
    $conn->set_charset('utf8mb4');
    try {
        $result = account_profile_run($conn, $userId, $input, $files, true, $browserSession);
        if ($result['errorMessage'] !== '') {
            throw new MobileAuthError('invalid_profile', $result['errorMessage'], 422);
        }
        return account_profile_public($result);
    } finally {
        $conn->close();
    }
}
