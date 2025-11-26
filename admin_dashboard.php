<?php
session_start();
if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
    header("Location: index.php");
    exit;
}
header('X-Robots-Tag: noindex, nofollow', true);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard Amministratore</title>
    <link rel="stylesheet" href="/style.min.css?v=20251126">
    <link rel="icon" type="image/png" href="/img/logo_old_school.png">
    <link rel="apple-touch-icon" href="/img/logo_old_school.png">
</head>
<body class="admin-page">
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

      <div class="admin-card">
        <h3>Gestione Blog</h3>
        <p>Pubblica nuovi articoli e tieni aggiornato il blog.</p><br>
        <a href="api/gestione_blog.php">Crea articoli</a>
      </div>
    </div>

    <a class="logout-btn" href="index.php">Esci dal pannello</a>
    </main>
    <div id="footer-container"></div>
    <script src="/includi/app.min.js?v=20251202"></script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        fetch("/includi/footer.html")
          .then(r => r.text())
          .then(html => {
            const footer = document.getElementById("footer-container");
            if (footer) footer.innerHTML = html;
          })
          .catch(err => console.error("Errore nel caricamento del footer:", err));

        if (typeof initHeaderInteractions === "function") {
          initHeaderInteractions();
        }
      });
    </script>
</body>
</html>
