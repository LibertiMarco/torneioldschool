<?php
require_once __DIR__ . '/security.php';
$hasPlayerProfile = false;
if (isset($_SESSION['user_id'])) {
    // Verifica se l'utente ha un giocatore associato per mostrare il link dedicato
    require_once __DIR__ . '/db.php';
    $stmt = $conn->prepare("SELECT id FROM giocatori WHERE utente_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        if ($stmt->execute()) {
            $stmt->store_result();
            $hasPlayerProfile = $stmt->num_rows > 0;
        }
        $stmt->close();
    }
}

$isLoggedIn = isset($_SESSION['user_id']);
$sessionAvatar = $_SESSION['avatar'] ?? '';
$avatarUrl = '/img/icone/user.png';
if (!empty($sessionAvatar)) {
    if (preg_match('#^https?://#i', $sessionAvatar)) {
        $avatarUrl = $sessionAvatar;
    } else {
        $avatarUrl = '/' . ltrim($sessionAvatar, '/');
    }
}
?>

<header class="site-header" data-auth="<?= $isLoggedIn ? '1' : '0' ?>">

    <!-- HAMBURGER (solo mobile) -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <img src="/img/icone/menu.png" alt="menu" />
    </button>

    <!-- LOGO -->
    <div class="header-logo">
        <a href="/index.php">
            <img src="/img/logo_old_school.png" alt="Logo">
        </a>
    </div>

    <!-- NAVIGAZIONE DESKTOP + MOBILE -->
    <nav class="header-nav" id="mainNav">
        <a href="/tornei.php">Tornei</a>
        <a href="/blog.php">Blog</a>
        <a href="/chisiamo.php">Chi siamo</a>
        <a href="/contatti.php">Contatti</a>
    </nav>

    <!-- MENU UTENTE -->
    <div class="user-dropdown">
        <button id="userBtn" class="user-btn">
            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Profilo utente">
        </button>

        <div id="userMenu" class="user-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php 
                  $nome = $_SESSION['nome'] ?? '';
                  $cognome = $_SESSION['cognome'] ?? '';
                  $nome_completo = trim($nome . ' ' . $cognome);
                ?>
                <span class="welcome-text">Ciao, <?= htmlspecialchars($nome_completo) ?></span>
                <a href="/account.php">Il mio account</a>
                <?php if ($hasPlayerProfile): ?>
                    <a href="/statistiche_giocatore.php">Statistiche giocatore</a>
                <?php endif; ?>

                <?php if ($_SESSION['ruolo'] === 'admin'): ?>
                    <a href="/admin_dashboard.php">Gestione Sito</a>
                <?php endif; ?>

                <a href="/logout.php">Logout</a>
            <?php else: ?>
                <a href="/register.php">Iscriviti</a>
                <a href="/login.php">Accedi</a>
            <?php endif; ?>
        </div>
    </div>

</header>
<div class="header-spacer" aria-hidden="true"></div>

<style>
/* ----- STRUTTURA BASE ----- */
.site-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #15293e;
    padding: 10px 18px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 4000;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
}

.header-spacer {
    height: 74px;
    width: 100%;
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
    letter-spacing: 0.6px;
    font-kerning: none;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
}

.header-nav a:hover {
    color: #cdd9ff;
}

/* USER BTN */
.user-btn img {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.45);
    background: #0f1f33;
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
        position: absolute;
        left: 15px;
        right: auto;
        top: 60px;
        width: auto;
        max-width: 220px;
        background: rgba(21, 41, 62, 0.8);
        padding: 12px 16px;
        display: none;
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
        border-radius: 12px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.18);
        z-index: 2000;
    }

    .header-nav.open {
        display: flex;
    }

    .header-nav a {
        color: #fff;
        font-size: 16px;
        white-space: nowrap;
        padding: 6px 0;
        text-align: left;
    }

    .site-header {
        justify-content: space-between;
    }
}
</style>

<script>
(function () {
    if (window.__HEADER_INTERACTIONS_SCRIPT__) {
        return;
    }

    window.__HEADER_INTERACTIONS_SCRIPT__ = true;

    const script = document.createElement("script");
    script.src = "/includi/app.min.js?v=20251204";
    script.defer = true;
    document.head.appendChild(script);
})();
</script>
<script src="/includi/consent-sync.js?v=20251206" defer></script>

<!-- Inline fallback per menu header -->
<script>
(function() {
  if (window.__HEADER_INLINE_READY__) return;
  window.__HEADER_INLINE_READY__ = true;

  function closeMenus(header, state) {
    if (state.mainNav) state.mainNav.classList.remove("open");
    if (state.userMenu) state.userMenu.classList.remove("open");
  }

  function setupHeader() {
    var header = document.querySelector(".site-header");
    if (!header) return;
    var mobileBtn = header.querySelector("#mobileMenuBtn");
    var mainNav = header.querySelector("#mainNav");
    var userBtn = header.querySelector("#userBtn");
    var userMenu = header.querySelector("#userMenu");
    var state = { mainNav: mainNav, userMenu: userMenu };

    if (mobileBtn && mainNav) {
      mobileBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        var isOpen = mainNav.classList.toggle("open");
        if (isOpen && userMenu) userMenu.classList.remove("open");
      });
    }

    if (userBtn && userMenu) {
      userBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        var isOpen = userMenu.classList.toggle("open");
        if (isOpen && mainNav) mainNav.classList.remove("open");
      });
    }

    document.addEventListener("click", function (e) {
      if (header.contains(e.target)) return;
      closeMenus(header, state);
    });

    window.addEventListener("resize", function () {
      if (window.innerWidth > 768) closeMenus(header, state);
    });
  }

  if (document.readyState !== "loading") {
    setupHeader();
  } else {
    document.addEventListener("DOMContentLoaded", setupHeader);
  }
})();
</script>
