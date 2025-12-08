(function () {
  if (window.__HEADER_INTERACTIONS_INITIALIZED__) {
    return;
  }

  window.__HEADER_INTERACTIONS_INITIALIZED__ = true;

  const headerStates = new Map();
  let globalListenersReady = false;

  function updateBadgeDisplay(badgeEl, unread) {
    if (!badgeEl) return;
    const count = Math.max(0, Number.isFinite(unread) ? unread : 0);
    badgeEl.textContent = count;
    badgeEl.style.display = count > 0 ? "inline-flex" : "none";
  }

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

  function showConfirmDialog(message, onConfirm) {
    const existing = document.getElementById("notifConfirmModal");
    if (existing) existing.remove();

    const overlay = document.createElement("div");
    overlay.id = "notifConfirmModal";
    overlay.style.position = "fixed";
    overlay.style.inset = "0";
    overlay.style.background = "rgba(0,0,0,0.4)";
    overlay.style.display = "flex";
    overlay.style.alignItems = "center";
    overlay.style.justifyContent = "center";
    overlay.style.zIndex = "9999";

    const dialog = document.createElement("div");
    dialog.style.background = "#fff";
    dialog.style.borderRadius = "12px";
    dialog.style.boxShadow = "0 12px 30px rgba(0,0,0,0.15)";
    dialog.style.padding = "20px";
    dialog.style.maxWidth = "320px";
    dialog.style.width = "90%";
    dialog.style.textAlign = "center";

    const msg = document.createElement("p");
    msg.textContent = message || "Sei sicuro di voler eliminare?";
    msg.style.margin = "0 0 14px";
    msg.style.color = "#15293e";
    msg.style.fontWeight = "600";

    const actions = document.createElement("div");
    actions.style.display = "flex";
    actions.style.justifyContent = "center";
    actions.style.gap = "10px";

    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.textContent = "Annulla";
    cancelBtn.style.padding = "8px 14px";
    cancelBtn.style.borderRadius = "8px";
    cancelBtn.style.border = "1px solid #c7d1e6";
    cancelBtn.style.background = "#f4f6fb";
    cancelBtn.style.color = "#15293e";
    cancelBtn.style.cursor = "pointer";
    cancelBtn.addEventListener("click", () => overlay.remove());

    const confirmBtn = document.createElement("button");
    confirmBtn.type = "button";
    confirmBtn.textContent = "Elimina";
    confirmBtn.style.padding = "8px 14px";
    confirmBtn.style.borderRadius = "8px";
    confirmBtn.style.border = "1px solid #b00000";
    confirmBtn.style.background = "#d80000";
    confirmBtn.style.color = "#ffffff";
    confirmBtn.style.cursor = "pointer";
    confirmBtn.addEventListener("click", () => {
      overlay.remove();
      if (typeof onConfirm === "function") onConfirm();
    });

    actions.appendChild(cancelBtn);
    actions.appendChild(confirmBtn);
    dialog.appendChild(msg);
    dialog.appendChild(actions);
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
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
    const state = { mainNav, userMenu, notifBtn, notifMenu, notifBadge, notifLoaded: false, notifLoading: false, badgeLoading: false, badgePollId: null };
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

    if (state.notifBadge) {
      startNotifBadgePolling(state);
    }
  }

  function renderNotifications(state, data, markedAsRead = false) {
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
      const handleDelete = (notif, itemEl) => {
        if (!notif || !notif.id) return;
        showConfirmDialog("Vuoi eliminare questa notifica?", () => {
          fetch("/api/notifications.php", {
            method: "POST",
            credentials: "include",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ delete_id: notif.id, type: notif.type || "generic" })
          })
            .then(res => res.ok ? res.json() : Promise.reject(res))
            .then(resp => {
              if (resp && resp.success) {
                if (itemEl && itemEl.parentNode) itemEl.parentNode.removeChild(itemEl);
                const remaining = menu.querySelectorAll(".notif-item").length;
                if (remaining === 0) {
                  menu.appendChild(makeEmpty("Nessuna notifica"));
                }
                if (badge) {
                  const current = parseInt(badge.textContent || "0", 10) || 0;
                  const nextVal = Math.max(0, current - (notif.read ? 0 : 1));
                  updateBadgeDisplay(badge, nextVal);
                }
              }
            })
            .catch(() => {});
        });
      };

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
        const actions = document.createElement("div");
        actions.className = "notif-actions";
        const delBtn = document.createElement("button");
        delBtn.type = "button";
        delBtn.className = "notif-delete";
        delBtn.textContent = "Elimina";
        delBtn.style.marginLeft = "auto";
        delBtn.style.background = "#d80000";
        delBtn.style.border = "1px solid #b00000";
        delBtn.style.color = "#ffffff";
        delBtn.style.cursor = "pointer";
        delBtn.style.fontWeight = "700";
        delBtn.style.borderRadius = "6px";
        delBtn.style.padding = "6px 10px";
        delBtn.style.fontSize = "12px";
        delBtn.style.transition = "all 0.15s ease";
        delBtn.addEventListener("mouseover", () => {
          delBtn.style.background = "#b00000";
          delBtn.style.borderColor = "#900000";
        });
        delBtn.addEventListener("mouseout", () => {
          delBtn.style.background = "#d80000";
          delBtn.style.borderColor = "#b00000";
        });
        delBtn.addEventListener("click", (ev) => {
          ev.stopPropagation();
          handleDelete(n, item);
        });
        actions.appendChild(delBtn);
        item.appendChild(title);
        if (n.text) item.appendChild(text);
        if (n.time) item.appendChild(meta);
        item.appendChild(actions);
        menu.appendChild(item);
      });
    }

    if (badge) {
      const unread = (data && typeof data.unread === "number") ? data.unread : 0;
      updateBadgeDisplay(badge, markedAsRead ? 0 : unread);
    }
  }

  function fetchBadgeCount(state) {
    if (!state || state.badgeLoading || !state.notifBadge) return;
    state.badgeLoading = true;
    fetch("/api/notifications.php", { credentials: "include" })
      .then((res) => res.ok ? res.json() : Promise.reject(res))
      .then((data) => {
        const unread = (data && typeof data.unread === "number") ? data.unread : 0;
        updateBadgeDisplay(state.notifBadge, unread);
      })
      .catch(() => {})
      .finally(() => { state.badgeLoading = false; });
  }

  function startNotifBadgePolling(state) {
    if (!state || state.badgePollId || !state.notifBadge) return;
    fetchBadgeCount(state);
    state.badgePollId = window.setInterval(() => fetchBadgeCount(state), 60000);
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
        renderNotifications(state, data || {}, true);
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
