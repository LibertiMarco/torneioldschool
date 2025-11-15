<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Statistiche Partita - Tornei Old School</title>

  <!-- stesso CSS della pagina principale -->
  <link rel="stylesheet" href="../style.css" />
  <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Oswald:wght@500&display=swap" rel="stylesheet">
</head>

<body>

  <!-- HEADER -->
  <div id="header-container"></div>

  <!-- CONTENUTO -->
  <main class="content">
    <button id="btnBack" onclick="history.back()">⟵</button>
    <h1 class="titolo">Statistiche Partita</h1>

    <!-- ✅ Qui iniettiamo la STESSA match-card del calendario -->
    <div id="partitaContainer" style="margin-bottom:20px;"></div>

    <!-- ✅ Riepilogo eventi -->
    <div id="riepilogoEventi" style="margin-bottom:20px;"></div>

    <!-- ✅ Cards giocatori -->
    <div id="eventiGiocatori" class="eventi-giocatori-grid"></div>
  </main>

  <!-- FOOTER -->
  <div id="footer-container"></div>

  <!-- SCRIPT: HEADER -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      fetch("/torneioldschool/includi/header.php")
        .then(r => r.text())
        .then(html => { document.getElementById("header-container").innerHTML = html; })
        .catch(e => console.error("Errore header:", e));
    });
  </script>

  <!-- SCRIPT: FOOTER -->
  <script>
    fetch("/torneioldschool/includi/footer.html")
      .then(r => r.text())
      .then(html => { document.getElementById("footer-container").innerHTML = html; })
      .catch(e => console.error("Errore footer:", e));
  </script>

  <!-- SCRIPT: PAGINA -->
  <script src="partita_eventi.js"></script>
</body>
</html>
