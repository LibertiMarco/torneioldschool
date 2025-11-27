(function () {
  const STORAGE_KEY = 'tosConsent';
  const ENDPOINT = '/api/consensi_anonimi.php';

  function readConsent() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return {
        marketing: !!parsed.marketing,
        newsletter: !!parsed.newsletter,
        tracking: !!parsed.tracking,
        recaptcha: !!parsed.recaptcha,
      };
    } catch (err) {
      return null;
    }
  }

  let lastSent = null;

  async function sync(consent) {
    if (!consent) return;
    const payload = JSON.stringify(consent);
    if (lastSent === payload) return;
    lastSent = payload;
    try {
      await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: payload,
      });
    } catch (err) {
      // ignore network issues; will retry on next change
    }
  }

  function handleChange() {
    sync(readConsent());
  }

  document.addEventListener('DOMContentLoaded', handleChange);
  window.addEventListener('storage', (event) => {
    if (event.key === STORAGE_KEY) {
      handleChange();
    }
  });

  // Expose a manual hook for other scripts (e.g., reCAPTCHA consent button)
  window.__tosSyncConsent__ = handleChange;
})();
