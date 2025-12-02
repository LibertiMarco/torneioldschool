<?php
require_once __DIR__ . '/security.php';
$hasPlayerProfile = false;
if (isset($_SESSION['user_id'])) {
    // Usa la connessione solo se disponibile, altrimenti evita fatal error
    if (!isset($conn) || !($conn instanceof mysqli)) {
        require_once __DIR__ . '/db.php';
    }
    if (isset($conn) && $conn instanceof mysqli) {
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
                  $nome_display = $nome_completo !== '' ? $nome_completo : 'Utente';
                ?>
                <div class="user-card">
                    <div class="user-card__avatar">
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Profilo utente">
                    </div>
                    <div class="user-card__text">
                        <span class="welcome-label">Ciao,</span>
                        <span class="welcome-name"><?= htmlspecialchars($nome_display) ?></span>
                    </div>
                </div>
                <div class="user-actions">
                    <a class="user-menu-item" href="/account.php">
                        <span>Il mio account</span>
                        <span class="item-arrow">></span>
                    </a>
                    <?php if ($hasPlayerProfile): ?>
                        <a class="user-menu-item" href="/statistiche_giocatore.php">
                            <span>Statistiche giocatore</span>
                            <span class="item-arrow">></span>
                        </a>
                    <?php endif; ?>

                    <?php if ($_SESSION['ruolo'] === 'admin'): ?>
                        <a class="user-menu-item" href="/admin_dashboard.php">
                            <span>Gestione Sito</span>
                            <span class="item-arrow">></span>
                        </a>
                    <?php endif; ?>

                    <a class="user-menu-item" href="/logout.php">
                        <span>Logout</span>
                        <span class="item-arrow">></span>
                    </a>
                </div>
            <?php else: ?>
                <div class="user-card guest-card">
                    <div class="user-card__text">
                        <span class="welcome-label">Ciao!</span>
                        <span class="welcome-name">Accedi o registrati</span>
                    </div>
                </div>
                <div class="user-actions">
                    <a class="user-menu-item" href="/register.php">
                        <span>Iscriviti</span>
                        <span class="item-arrow">></span>
                    </a>
                    <a class="user-menu-item" href="/login.php">
                        <span>Accedi</span>
                        <span class="item-arrow">></span>
                    </a>
                </div>
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
    justify-content: flex-start;
    gap: 16px;
    background: #15293e;
    padding: 14px 22px;
    min-height: 72px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 4000;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
}

.header-spacer {
    height: 72px;
    width: 100%;
}

/* LOGO NITIDO */
.header-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 0;
}

.header-logo a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.header-logo img {
    width: 52px;
    height: 52px;
    display: block;
    object-fit: contain;
    border-radius: 50%;
}

/* NAV DESKTOP */
.header-nav {
    display: flex;
    gap: 20px;
    align-items: center;
    flex: 1;
    justify-content: center;
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
.user-dropdown {
    position: relative;
    margin-left: auto;
    display: flex;
    align-items: center;
}

.user-btn {
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
}

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
    right: 0;
    top: 58px;
    width: 240px;
    background: #0f1f33;
    border-radius: 14px;
    padding: 12px;
    box-shadow: 0 12px 28px rgba(0,0,0,0.28);
    border: 1px solid #233854;
    color: #e7edf7;
}

.user-menu::before {
    content: "";
    position: absolute;
    top: -8px;
    right: 18px;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 8px solid #0f1f33;
}

.user-menu.open {
    display: block;
}

.user-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: linear-gradient(135deg, #15293e, #1f3f63);
    border-radius: 12px;
    margin-bottom: 10px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
}

.guest-card {
    justify-content: flex-start;
}

.user-card__avatar img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.55);
    background: #0f1f33;
}

.user-card__text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
    color: #f7f9fd;
}

.welcome-label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: rgba(247, 249, 253, 0.8);
}

.welcome-name {
    font-weight: 700;
    font-size: 16px;
    color: #fff;
}

.user-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.user-menu-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.03);
    color: #f7fbff !important;
    text-decoration: none;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: background 0.2s ease, transform 0.15s ease, color 0.2s ease, border-color 0.2s ease;
}

.user-menu-item:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #fff !important;
    border-color: rgba(255, 255, 255, 0.16);
    transform: translateX(2px);
}

.item-arrow {
    color: rgba(255, 255, 255, 0.8);
    font-weight: 700;
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
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
    }

    .header-logo {
        position: static;
        transform: none;
        margin: 0 auto;
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
        flex: 0 0 auto;
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
        justify-content: center;
        padding: 12px 16px;
        min-height: 68px;
        position: fixed;
    }

    .header-spacer {
        height: 68px;
    }

    .header-logo img {
        width: 48px;
        height: 48px;
    }

    .user-dropdown {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        margin-left: 0;
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
