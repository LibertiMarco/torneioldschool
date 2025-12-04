<?php
// Richiede sessione attiva e utente autenticato prima di mostrare le pagine torneo.
require_once __DIR__ . '/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
