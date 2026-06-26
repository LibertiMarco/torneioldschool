<?php

require_once __DIR__ . '/includi/security.php';

if (isset($_GET['destroy']) && $_GET['destroy'] === '1') {
    session_unset();
    session_destroy();
    echo 'destroyed';
    exit;
}

$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'smoke-admin@example.test';
$_SESSION['nome'] = 'Smoke';
$_SESSION['cognome'] = 'Admin';
$_SESSION['ruolo'] = 'admin';

echo 'ok';
