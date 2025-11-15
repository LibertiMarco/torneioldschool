<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<header class="site-header">

    <!-- HAMBURGER (solo mobile) -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <img src="/torneioldschool/img/icone/menu.png" alt="menu" />
    </button>

    <!-- LOGO -->
    <div class="header-logo">
        <a href="/torneioldschool/index.php">
            <img src="/torneioldschool/img/logo_old_school.png" alt="Logo">
        </a>
    </div>

    <!-- NAVIGAZIONE DESKTOP + MOBILE -->
    <nav class="header-nav" id="mainNav">
        <a href="/torneioldschool/tornei.php">Tornei</a>
        <a href="/torneioldschool/chisiamo.html">Chi siamo</a>
        <a href="/torneioldschool/blog.php">Blog</a>
        <a href="/torneioldschool/contatti.php">Contatti</a>
    </nav>

    <!-- MENU UTENTE -->
    <div class="user-dropdown">
        <button id="userBtn" class="user-btn">
            <img src="/torneioldschool/img/icone/user.png" alt="Utente">
        </button>

        <div id="userMenu" class="user-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php 
                  $nome = $_SESSION['nome'] ?? '';
                  $cognome = $_SESSION['cognome'] ?? '';
                  $nome_completo = trim($nome . ' ' . $cognome);
                ?>
                <span class="welcome-text">👋 Ciao, <?= htmlspecialchars($nome_completo) ?></span>

                <?php if ($_SESSION['ruolo'] === 'admin'): ?>
                    <a href="/torneioldschool/admin_dashboard.php">Gestione Sito</a>
                <?php endif; ?>

                <a href="/torneioldschool/logout.php">Logout</a>
            <?php else: ?>
                <a href="/torneioldschool/register.php">Iscriviti</a>
                <a href="/torneioldschool/login.php">Accedi</a>
            <?php endif; ?>
        </div>
    </div>

</header>

<style>
/* ----- STRUTTURA BASE ----- */
.site-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #15293e;
    padding: 10px 18px;
    position: relative;
}

/* LOGO NITIDO */
.header-logo img {
    height: 46px;
    width: auto;
}

/* NAV DESKTOP */
.header-nav {
    display: flex;
    gap: 20px;
}

.header-nav a {
    color: white;
    text-decoration: none;
    font-weight: 600;
}

.header-nav a:hover {
    color: #cdd9ff;
}

/* USER BTN */
.user-btn img {
    width: 32px;
}

/* USER DROPDOWN */
.user-menu {
    display: none;
    position: absolute;
    right: 10px;
    top: 58px;
    background: white;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 3px 20px rgba(0,0,0,0.25);
}

.user-menu.open {
    display: block;
}

.user-menu a {
    display: block;
    padding: 8px 0;
    color: #15293e;
    text-decoration: none;
}

/* ----- MOBILE ----- */
.mobile-menu-btn {
    background: transparent;
    border: none;
    display: none;
}

.mobile-menu-btn img {
    width: 30px;
}

/* MENU MOBILE */
@media (max-width: 768px) {

    .mobile-menu-btn {
        display: block;
    }

    .header-nav {
        position: fixed;
        left: 0;
        top: 0;
        height: 100%;
        width: 70%;
        max-width: 270px;
        background: rgba(21, 41, 62, 0.95);
        backdrop-filter: blur(4px);
        padding-top: 80px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding-left: 25px;
        transform: translateX(-100%);
        transition: 0.35s ease;
        z-index: 2000;
    }

    .header-nav.open {
        transform: translateX(0);
    }

    .header-nav a {
        color: #fff;
        font-size: 18px;
    }

    .site-header {
        justify-content: space-between;
    }
}
</style>

<script>
// MENU MOBILE
document.addEventListener("click", function(e){
    const menu = document.getElementById("mainNav");
    const btn = document.getElementById("mobileMenuBtn");

    if (btn.contains(e.target)) {
        menu.classList.toggle("open");
    } 
});

// MENU UTENTE
document.getElementById("userBtn").addEventListener("click", function(e){
    e.stopPropagation();
    document.getElementById("userMenu").classList.toggle("open");
});

document.addEventListener("click", function(e){
    const menu = document.getElementById("userMenu");
    if (!menu.contains(e.target) && e.target.id !== "userBtn") {
        menu.classList.remove("open");
    }
});
</script>
