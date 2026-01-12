<?php
// Richiede sessione attiva e utente autenticato prima di mostrare le pagine torneo.
require_once __DIR__ . '/security.php';

if (!isset($_SESSION['user_id'])) {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '/index.php';
    login_remember_redirect($currentPath);
    header('Location: /login.php');
    exit;
}

// Precarica i consensi dal DB e li scrive in un cookie leggibile dal banner JS,
// cosi l'utente autenticato non deve riconfermare ad ogni accesso.
try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/consent_helpers.php';

    $userId = (int)$_SESSION['user_id'];
    $email = consent_get_user_email($conn, $userId) ?? '';
    $consents = consent_current_snapshot($conn, $userId, $email);

    $payload = [
        'tracking' => !empty($consents['tracking']),
        'marketing' => !empty($consents['marketing']),
        'newsletter' => !empty($consents['newsletter']),
        'ts' => round(microtime(true) * 1000), // allineato a Date.now() JS
    ];

    $encoded = rawurlencode(json_encode($payload));
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    setcookie('tosConsent', $encoded, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => false, // deve essere accessibile da JS
        'samesite' => 'Lax',
    ]);
} catch (Throwable $e) {
    // se il DB non e disponibile evitiamo di bloccare la pagina
}
