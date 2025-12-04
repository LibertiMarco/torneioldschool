(function () {
  if (window.__HEADER_INTERACTIONS_INITIALIZED__) {
    return;
  }

  window.__HEADER_INTERACTIONS_INITIALIZED__ = true;

  const headerStates = new Map();
  let globalListenersReady = false;

  function closeMenus(header, state) {
    if (!state) {
      return;
    }

    if (state.mainNav) {
      state.mainNav.classList.remove("open");
    }

    if (state.userMenu) {
      state.userMenu.classList.remove("open");
    }

    if (state.notifMenu) {
      state.notifMenu.classList.remove("open");
    }

    if (state.notifBtn) {
      state.notifBtn.setAttribute("aria-expanded", "false");
    }
  }

  function handleDocumentClick(event) {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    headerStates.forEach((state, header) => {
      if (!header.contains(target)) {
        closeMenus(header, state);
      }
    });
  }

  function handleResize() {
    if (window.innerWidth > 768) {
      headerStates.forEach((state, header) => {
        closeMenus(header, state);
      });
    }
  }

  function ensureGlobalListeners() {
    if (globalListenersReady) {
      return;
    }

    document.addEventListener("click", handleDocumentClick);
    window.addEventListener("resize", handleResize);
    globalListenersReady = true;
  }

  function setupHeader(header) {
    if (!header || headerStates.has(header)) {
      return;
    }

    const mobileBtn = header.querySelector("#mobileMenuBtn");
    const mainNav = header.querySelector("#mainNav");
    const userBtn = header.querySelector("#userBtn");
    const userMenu = header.querySelector("#userMenu");
    const notifBtn = header.querySelector("#notifBtn");
    const notifMenu = header.querySelector("#notifMenu");
    const notifBadge = header.querySelector("#notifBadge");

    const state = { mainNav, userMenu, notifBtn, notifMenu, notifBadge };
    headerStates.set(header, state);

    if (mobileBtn && mainNav) {
      mobileBtn.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        const isOpen = mainNav.classList.toggle("open");
        if (isOpen && userMenu) {
          userMenu.classList.remove("open");
        }
      });
    }

    if (userBtn && userMenu) {
      userBtn.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        const isOpen = userMenu.classList.toggle("open");
        if (isOpen && mainNav) {
          mainNav.classList.remove("open");
        }
        if (isOpen && notifMenu) {
          notifMenu.classList.remove("open");
          notifBtn?.setAttribute("aria-expanded", "false");
        }
      });
    }

    function renderNotifs(list) {
      if (!notifMenu || !notifBadge) return;
      const items = Array.isArray(list) ? list : [];
      if (!items.length) {
        notifBadge.style.display = "none";
        notifMenu.innerHTML = '<div class="notif-empty">Nessuna notifica</div>';
        return;
      }
      notifMenu.innerHTML = items
        .map((n) => {
          return `
            <div class="notif-item">
              <div class="notif-title">${n.title || "Notifica"}</div>
              <div class="notif-text">${n.text || ""}</div>
              <div class="notif-meta">${n.time || ""}</div>
            </div>
          `;
        })
        .join("");
    }

    function loadNotifs(markRead) {
      if (!notifBtn || !notifMenu) return;
      const url = "/api/notifications.php" + (markRead ? "?mark_read=1" : "");
      fetch(url, { credentials: "include" })
        .then((res) => (res.ok ? res.json() : { notifications: [], unread: 0 }))
        .then((data) => {
          const list = data.notifications || [];
          const unread = data.unread || 0;
          renderNotifs(list);
          if (notifBadge) {
            if (unread > 0) {
              notifBadge.textContent = unread;
              notifBadge.style.display = "inline-flex";
            } else {
              notifBadge.style.display = "none";
            }
          }
        })
        .catch(() => {
          renderNotifs([]);
        });
    }

    if (notifBtn && notifMenu) {
      notifBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const isOpen = notifMenu.classList.toggle("open");
        notifBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
        if (isOpen && mainNav) mainNav.classList.remove("open");
        if (isOpen && userMenu) userMenu.classList.remove("open");
        if (isOpen) loadNotifs(true);
      });
      loadNotifs(false);
    }
  }

  function resolveScope(root) {
    if (root && typeof root.querySelectorAll === "function") {
      return root;
    }

    return document;
  }

  function publishAuthState(headers) {
    const isAuth = Array.from(headers || []).some(
      (header) => header && header.getAttribute("data-auth") === "1"
    );
    document.documentElement.setAttribute("data-user-auth", isAuth ? "1" : "0");
    if (document.body) {
      document.body.setAttribute("data-user-auth", isAuth ? "1" : "0");
    }
    window.__TOS_IS_AUTH = isAuth;
  }

  function initHeaderInteractions(root) {
    const scope = resolveScope(root);
    const headers = scope.querySelectorAll(".site-header");

    if (!headers.length) {
      publishAuthState([]);
      return;
    }

    publishAuthState(headers);
    headers.forEach(setupHeader);
    ensureGlobalListeners();
  }

  function loadPrivacyScript() {
    if (window.__TOS_CONSENT_LOADER__) {
      return;
    }
    window.__TOS_CONSENT_LOADER__ = true;
    const script = document.createElement("script");
    script.src = "/includi/privacy-consent.min.js?v=20251204";
    script.defer = true;
    document.head.appendChild(script);
  }

  window.initHeaderInteractions = initHeaderInteractions;

  loadPrivacyScript();

  if (document.readyState !== "loading") {
    initHeaderInteractions(document);
  } else {
    document.addEventListener("DOMContentLoaded", () => initHeaderInteractions(document));
  }
})();
