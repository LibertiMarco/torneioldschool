<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Amministratore</title>
    <link rel="stylesheet" href="/torneioldschool/style.css">
    <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
</head>
<body>
    <?php include __DIR__ . '/includi/header.php'; ?>

    <main class="admin-dashboard">
        <h1 class="admin-title">Pannello Amministratore</h1>

        <div class="cards-container">
            <div class="admin-card">
                <h3>Gestione Tornei</h3>
                <p>Crea, modifica o elimina tornei esistenti.</p><br>
                <a href="api/gestione_tornei.php">Gestisci</a>
            </div>

            <div class="admin-card">
                <h3>Gestione Squadre</h3>
                <p>Visualizza e aggiorna le squadre iscritte ai tornei.</p><br>
                <a href="api/gestione_squadre.php">Vai</a>
            </div>

            <div class="admin-card">
                <h3>Gestione Giocatori</h3>
                <p>Visualizza e aggiorna i giocatori delle squadre.</p><br>
                <a href="api/gestione_giocatori.php">Vai</a>
            </div>

            <div class="admin-card">
                <h3>Calendario & Risultati</h3>
                <p>Inserisci o aggiorna date e punteggi dei tornei.</p><br>
                <a href="api/gestione_partite.php">Apri</a>
            </div>

            <div class="admin-card">
                <h3>Utenti & Iscrizioni</h3>
                <p>Controlla gli utenti registrati e le loro iscrizioni.</p><br>
                <a href="api/gestione_utenti.php">Visualizza</a>
            </div>
        </div>

        <form action="logout.php" method="post">
            <button class="logout-btn" type="submit">Esci dal pannello</button>
        </form><br><br>
    </main>
</body>
</html>
