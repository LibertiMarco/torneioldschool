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
    const state = { mainNav, userMenu, notifBtn, notifMenu, notifBadge, notifLoaded: false, notifLoading: false };
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
        if (state.notifMenu) {
          state.notifMenu.classList.remove("open");
        }
      });
    }

    if (notifBtn && notifMenu) {
      notifBtn.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        const isOpen = notifMenu.classList.toggle("open");
        notifBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
        if (isOpen) {
          if (mainNav) mainNav.classList.remove("open");
          if (userMenu) userMenu.classList.remove("open");
          loadNotifications(state);
        }
      });
    }
  }

  function renderNotifications(state, data) {
    const menu = state.notifMenu;
    const badge = state.notifBadge;
    if (!menu) return;

    menu.innerHTML = "";

    const makeEmpty = (text) => {
      const div = document.createElement("div");
      div.className = "notif-empty";
      div.textContent = text;
      return div;
    };

    const list = (data && Array.isArray(data.notifications)) ? data.notifications : [];
    if (!list.length) {
      menu.appendChild(makeEmpty("Nessuna notifica"));
    } else {
      list.forEach((n) => {
        const item = document.createElement("div");
        item.className = "notif-item";
        if (n.link) {
          item.style.cursor = "pointer";
          item.addEventListener("click", () => { window.location.href = n.link; });
        }
        const title = document.createElement("div");
        title.className = "notif-title";
        title.textContent = n.title || "Notifica";
        const text = document.createElement("div");
        text.className = "notif-text";
        text.textContent = n.text || "";
        const meta = document.createElement("div");
        meta.className = "notif-meta";
        meta.textContent = n.time || "";
        item.appendChild(title);
        if (n.text) item.appendChild(text);
        if (n.time) item.appendChild(meta);
        menu.appendChild(item);
      });
    }

    if (badge) {
      const unread = (data && typeof data.unread === "number") ? data.unread : 0;
      badge.textContent = unread;
      badge.style.display = unread > 0 ? "inline-flex" : "none";
    }
  }

  function loadNotifications(state) {
    if (!state || state.notifLoading) return;
    state.notifLoading = true;
    if (state.notifMenu) {
      state.notifMenu.innerHTML = '<div class="notif-empty">Caricamento...</div>';
    }
    fetch("/api/notifications.php?mark_read=1", { credentials: "include" })
      .then((res) => res.ok ? res.json() : Promise.reject(res))
      .then((data) => {
        renderNotifications(state, data || {});
      })
      .catch(() => {
        if (state.notifMenu) {
          state.notifMenu.innerHTML = '<div class="notif-empty">Errore nel caricamento</div>';
        }
      })
      .finally(() => {
        state.notifLoading = false;
      });
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
    script.src = "/includi/privacy-consent.js?v=20251206";
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
