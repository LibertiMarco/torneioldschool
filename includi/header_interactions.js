(function () {
  let listenersReady = false;

  function collectHeaders() {
    return Array.from(document.querySelectorAll(".site-header"))
      .filter((header) => header.dataset.headerReady === "true");
  }

  function closeMenus(header, { mobile = true, user = true } = {}) {
    if (!header) {
      return;
    }

    if (mobile) {
      const nav = header.querySelector("#mainNav");
      nav?.classList.remove("open");
    }

    if (user) {
      const userMenu = header.querySelector("#userMenu");
      userMenu?.classList.remove("open");
    }
  }

  function closeAll(exceptHeader, options) {
    collectHeaders().forEach((header) => {
      if (header !== exceptHeader) {
        closeMenus(header, options);
      }
    });
  }

  function toggleMobile(header) {
    const nav = header.querySelector("#mainNav");
    if (!nav) {
      return;
    }

    const isOpen = nav.classList.toggle("open");

    if (isOpen) {
      closeMenus(header, { mobile: false, user: true });
      closeAll(header);
    }
  }

  function toggleUser(header) {
    const userMenu = header.querySelector("#userMenu");
    if (!userMenu) {
      return;
    }

    const isOpen = userMenu.classList.toggle("open");

    if (isOpen) {
      closeMenus(header, { mobile: true, user: false });
      closeAll(header);
    }
  }

  function handleDocumentClick(event) {
    const target = event.target instanceof Element ? event.target : null;

    if (!target) {
      closeAll();
      return;
    }

    const mobileBtn = target.closest("#mobileMenuBtn");
    if (mobileBtn) {
      const header = mobileBtn.closest(".site-header");
      if (header?.dataset.headerReady === "true") {
        event.preventDefault();
        toggleMobile(header);
        return;
      }
    }

    const userBtn = target.closest("#userBtn");
    if (userBtn) {
      const header = userBtn.closest(".site-header");
      if (header?.dataset.headerReady === "true") {
        event.preventDefault();
        toggleUser(header);
        return;
      }
    }

    const header = target.closest(".site-header");
    if (header?.dataset.headerReady === "true") {
      const userMenu = header.querySelector("#userMenu");
      if (userMenu?.classList.contains("open") && !target.closest("#userMenu")) {
        userMenu.classList.remove("open");
      }

      const nav = header.querySelector("#mainNav");
      if (nav?.classList.contains("open") && !target.closest("#mainNav")) {
        nav.classList.remove("open");
      }

      closeAll(header);
      return;
    }

    closeAll();
  }

  function handleResize() {
    if (window.innerWidth > 768) {
      collectHeaders().forEach((header) => closeMenus(header));
    }
  }

  function ensureListeners() {
    if (listenersReady) {
      return;
    }

    document.addEventListener("click", handleDocumentClick);
    window.addEventListener("resize", handleResize);
    listenersReady = true;
  }

  function initHeaderInteractions(root) {
    const scope = root && typeof root.querySelectorAll === "function" ? root : document;
    const headers = scope.querySelectorAll(".site-header");

    if (!headers.length) {
      ensureListeners();
      return;
    }

    headers.forEach((header) => {
      if (header.dataset.headerReady === "true") {
        return;
      }

      header.dataset.headerReady = "true";

      const nav = header.querySelector("#mainNav");
      if (nav) {
        nav.addEventListener("click", (event) => {
          const link = event.target instanceof Element ? event.target.closest("a") : null;
          if (link && window.innerWidth <= 768) {
            closeMenus(header);
          }
        });
      }

      const userMenu = header.querySelector("#userMenu");
      if (userMenu) {
        userMenu.addEventListener("click", (event) => {
          const link = event.target instanceof Element ? event.target.closest("a") : null;
          if (link) {
            closeMenus(header);
          }
        });
      }
    });

    ensureListeners();
  }

  window.initHeaderInteractions = initHeaderInteractions;

  if (document.readyState !== "loading") {
    initHeaderInteractions(document);
  } else {
    document.addEventListener("DOMContentLoaded", () => initHeaderInteractions(document));
  }
})();