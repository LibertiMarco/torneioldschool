(function () {
  let documentListenersBound = false;

  function handleDocumentClick(event) {
    const mainNav = document.getElementById("mainNav");
    const mobileMenuBtn = document.getElementById("mobileMenuBtn");
    const userMenu = document.getElementById("userMenu");
    const userBtn = document.getElementById("userBtn");
    const target = event.target;

    if (mainNav && mainNav.classList.contains("open")) {
      const clickedToggle = mobileMenuBtn && (mobileMenuBtn === target || mobileMenuBtn.contains(target));
      const clickedInsideMenu = mainNav.contains(target);
      if (!clickedToggle && !clickedInsideMenu) {
        mainNav.classList.remove("open");
      }
    }

    if (userMenu && userMenu.classList.contains("open")) {
      const clickedToggle = userBtn && (userBtn === target || userBtn.contains(target));
      const clickedInsideMenu = userMenu.contains(target);
      if (!clickedToggle && !clickedInsideMenu) {
        userMenu.classList.remove("open");
      }
    }
  }

  function handleResize() {
    const mainNav = document.getElementById("mainNav");
    if (mainNav && window.innerWidth > 768) {
      mainNav.classList.remove("open");
    }
  }

  window.initHeaderInteractions = function initHeaderInteractions() {
    const header = document.querySelector(".site-header");
    if (!header) {
      return;
    }

    const mobileMenuBtn = document.getElementById("mobileMenuBtn");
    const mainNav = document.getElementById("mainNav");
    if (mobileMenuBtn && mainNav && !mobileMenuBtn.dataset.bound) {
      mobileMenuBtn.addEventListener("click", function (event) {
        event.stopPropagation();
        mainNav.classList.toggle("open");
      });
      mobileMenuBtn.dataset.bound = "true";
    }

    const userBtn = document.getElementById("userBtn");
    if (userBtn && !userBtn.dataset.bound) {
      userBtn.addEventListener("click", function (event) {
        event.stopPropagation();
        const userMenu = document.getElementById("userMenu");
        if (userMenu) {
          userMenu.classList.toggle("open");
        }
      });
      userBtn.dataset.bound = "true";
    }

    if (!documentListenersBound) {
      document.addEventListener("click", handleDocumentClick);
      window.addEventListener("resize", handleResize);
      documentListenersBound = true;
    }
  };
})();