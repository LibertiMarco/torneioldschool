<?php
require_once __DIR__ . '/../includi/admin_guard.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Gestione Totocalcio</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link rel="apple-touch-icon" href="/img/logo_old_school.png">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; background: #f6f8fb; }
    main.admin-wrapper { max-width: 1100px; margin: 0 auto; padding: 36px 16px 70px; flex: 1 0 auto; }
    .work-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; }
    .work-card { background: #fff; border: 1px solid #e5eaf0; border-radius: 14px; padding: 20px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); }
    .work-card h3 { margin: 0 0 10px; color: #15293e; }
    .work-card p { margin: 0 0 10px; color: #4c5b71; line-height: 1.55; }
    .tag { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; background: #e8edf5; color: #15293e; font-weight: 800; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
    .todo-list { margin: 0; padding-left: 18px; color: #475569; line-height: 1.65; }
  </style>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/../includi/header.php'; ?>

  <main class="admin-wrapper">
    <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
    <h1 class="admin-title">Gestione Totocalcio</h1>
    <p style="margin: 0 0 20px; color: #475569;">Area admin pronta per sviluppare il Totocalcio insieme nei prossimi passaggi.</p>

    <section class="work-grid">
      <article class="work-card">
        <span class="tag">Stato</span>
        <h3>Modulo inizializzato</h3>
        <p>La sezione admin esiste gia ed e collegata alla dashboard. Qui potremo aggiungere configurazione, schedine, giornate e risultati.</p>
      </article>

      <article class="work-card">
        <span class="tag">Prossimi blocchi</span>
        <h3>Cosa possiamo costruire</h3>
        <ul class="todo-list">
          <li>Creazione giornate o eventi del Totocalcio</li>
          <li>Apertura e chiusura delle schedine</li>
          <li>Gestione pronostici e punteggi</li>
          <li>Classifica utenti e riepilogo vincite</li>
        </ul>
      </article>

      <article class="work-card">
        <span class="tag">Nota</span>
        <h3>Base pronta</h3>
        <p>Quando vuoi possiamo partire dal modello dati oppure dalla prima schermata operativa del Totocalcio.</p>
      </article>
    </section>
  </main>

  <div id="footer-container"></div>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const footer = document.getElementById('footer-container');
      if (!footer) return;
      fetch('/includi/footer.html')
        .then(response => response.text())
        .then(html => { footer.innerHTML = html; })
        .catch(err => console.error('Errore nel caricamento del footer:', err));
    });
  </script>
</body>
</html>
