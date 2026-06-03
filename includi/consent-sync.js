(function () {
  const STORAGE_KEY = 'tosConsent';
  const ENDPOINT = '/api/consensi_anonimi.php';
  const SYNC_CACHE_KEY = 'tosConsentSyncState';
  const MIN_SYNC_INTERVAL_MS = 6 * 60 * 60 * 1000;

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

  function readSyncState() {
    try {
      const raw = sessionStorage.getItem(SYNC_CACHE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;
      return {
        payload: typeof parsed.payload === 'string' ? parsed.payload : '',
        ts: Number(parsed.ts) || 0,
      };
    } catch (err) {
      return null;
    }
  }

  function writeSyncState(payload) {
    try {
      sessionStorage.setItem(SYNC_CACHE_KEY, JSON.stringify({
        payload,
        ts: Date.now(),
      }));
    } catch (err) {
      // ignore storage issues
    }
  }

  let lastSent = null;

  async function sync(consent, options = {}) {
    if (!consent) return;
    const force = options.force === true;
    const payload = JSON.stringify(consent);
    const syncState = readSyncState();
    if (!force && lastSent === payload) return;
    if (
      !force &&
      syncState &&
      syncState.payload === payload &&
      (Date.now() - syncState.ts) < MIN_SYNC_INTERVAL_MS
    ) {
      return;
    }
    lastSent = payload;
    try {
      await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: payload,
      });
      writeSyncState(payload);
    } catch (err) {
      // ignore network issues; will retry on next change
    }
  }

  function handleChange(options = {}) {
    sync(readConsent(), options);
  }

  document.addEventListener('DOMContentLoaded', handleChange);
  window.addEventListener('storage', (event) => {
    if (event.key === STORAGE_KEY) {
      handleChange({ force: true });
    }
  });

  // Expose a manual hook for other scripts (e.g., reCAPTCHA consent button)
  window.__tosSyncConsent__ = function () {
    handleChange({ force: true });
  };
})();
