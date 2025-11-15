<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Home - Tornei Old School</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/png" href="/torneioldschool/img/logo_old_school.png">
</head>

<body>
  
  <!-- HEADER -->
  <?php include __DIR__ . "/includi/header.php"; ?>

  <!-- CONTENUTO PRINCIPALE -->
  <div class="content">
    <div class="homepage">
      
      <!-- HERO PRINCIPALE -->
      <section class="home-hero">
        <div class="hero-overlay">
          <h1>TORNEI</h1>
          <p>Accedi a tutte le informazioni e alle regole dei nostri tornei!</p>
          <a href="/torneioldschool/tornei.php" class="hero-btn">Tornei</a>
        </div>
      </section>

      <!-- CHI SIAMO -->
      <section class="chisiamo-hero">
        <div class="hero-overlay">
          <h1>Chi Siamo</h1>
          <p>Lo facciamo per passione, per condividere divertimento e amicizia con chiunque voglia partecipare.</p>
          <a href="chisiamo.php" class="hero-btn">Scopri di più</a>
        </div>
      </section>

      <!-- CONTATTI -->
      <section class="contatti-hero">
        <div class="hero-overlay">
          <h1>Contattaci</h1>
          <p>Siamo sempre disponibili per domande, iscrizioni o collaborazioni.</p>
          <a href="contatti.php" class="hero-btn">Contatti</a>
        </div>
      </section>

      <!-- ISCRIZIONE HERO -->
      <section class="iscrizione-hero">
        <div class="hero-overlay">
          <h1>Iscriviti ai Tornei Old School</h1>
          <p>Accedi alle classifiche, scopri le squadre e ricevi via email gli aggiornamenti sui nuovi tornei!</p>
          <a href="register.php" class="hero-btn">Iscriviti Ora</a>
        </div>
      </section>

      <!-- NEWS -->
      <section class="home-news">
        <h2>Ultime Notizie</h2>

        <div id="newsGrid" class="news-grid">
            <!-- Caricamento automatico via JS -->
        </div>
      </section>

    </div> <!-- fine homepage -->
  </div> <!-- fine content -->

  <!-- FOOTER -->
  <div id="footer-container"></div>

  <!-- SCRIPT FOOTER -->
  <script>
    fetch("/torneioldschool/includi/footer.html")
      .then(response => response.text())
      .then(data => document.getElementById("footer-container").innerHTML = data)
      .catch(error => console.error("Errore nel caricamento del footer:", error));
  </script>

<script>
async function loadNews() {
    const r = await fetch('/torneioldschool/api/blog.php?azione=ultimi');
    const posts = await r.json();
    const box = document.getElementById("newsGrid");

    box.innerHTML = ""; // pulizia

    if (posts.length === 0) {
        box.innerHTML = "<p>Nessuna notizia disponibile.</p>";
        return;
    }

    posts.forEach(p => {
        const imageSrc = p.immagine ? p.immagine : '/torneioldschool/img/blog/placeholder.jpg';
        box.innerHTML += `
        <article onclick="location.href='/torneioldschool/articolo.php?id=${p.id}'" style="cursor:pointer">
            <img src="${imageSrc}" alt="">
            <h3>${p.titolo}</h3>
            <p>${p.data}</p>
        </article>`;
    });
}

loadNews();
</script>

  <!-- SCRIPT HEADER -->
  <script src="/torneioldschool/includi/header-interactions.js"></script>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    fetch("/torneioldschool/includi/header.php")
      .then(response => response.text())
      .then(data => {
        document.getElementById("header-container").innerHTML = data;
        initHeaderInteractions();

        // Effetto scroll header
        const header = document.querySelector(".site-header");
        window.addEventListener("scroll", () => {
          if (window.scrollY > 50) header.classList.add("scrolled");
          else header.classList.remove("scrolled");
        });

        // Dropdown Tornei
        const dropdown = document.querySelector(".dropdown");
        const btn = dropdown?.querySelector(".dropbtn");
        const menu = dropdown?.querySelector(".dropdown-content");

        if (btn && menu) {
          btn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.toggle("open");
            menu.style.display = dropdown.classList.contains("open") ? "block" : "none";
          });
          document.addEventListener("click", (e) => {
            if (!dropdown.contains(e.target)) {
              dropdown.classList.remove("open");
              if (menu) menu.style.display = "none";
            }
          });
        }
      })
      .catch(error => console.error("Errore nel caricamento dell'header:", error));
  });
  </script>

</body>
</html>
