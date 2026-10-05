<?php
// Keep existing bookmarks working; all forms and results now live in Gestione Giocatori.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST['action'] = 'import';
    require __DIR__ . '/gestione_giocatori.php';
    exit;
}
header('Location: /api/gestione_giocatori.php?action=import');
exit;