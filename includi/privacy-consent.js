(function () {
  if (window.__TOS_CONSENT_LOADED__) {
    return;
  }
  window.__TOS_CONSENT_LOADED__ = true;

  const STORAGE_KEY = 'tosConsent';
  const BANNER_ID = 'tos-consent-banner';

  // ---------- Tracking module ----------
  const Tracking = (function () {
    let allowed = false;
    const endpoint = '/api/track_event.php';

    function sanitizeDetails(details) {
      if (!details || typeof details !== 'object') return {};
      const clean = {};
      Object.keys(details).forEach((key) => {
        const safeKey = String(key).replace(/[^a-zA-Z0-9_.-]/g, '').slice(0, 50);
        if (!safeKey) return;
        const value = details[key];
        if (typeof value === 'string') {
          clean[safeKey] = value.slice(0, 200);
        } else if (typeof value === 'number' || typeof value === 'boolean') {
          clean[safeKey] = value;
        }
      });
      return clean;
    }

    function send(payload) {
      if (!allowed) return;
      const body = JSON.stringify(payload);
      if (navigator.sendBeacon) {
        const blob = new Blob([body], { type: 'application/json' });
        navigator.sendBeacon(endpoint, blob);
        return;
      }
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body,
        keepalive: true,
      }).catch(() => {});
    }

    function track(eventType, details) {
      if (!allowed) return;
      const payload = {
        event_type: String(eventType || '').slice(0, 64) || 'custom',
        path: window.location.pathname,
        referrer: document.referrer || '',
        title: document.title || '',
        details: sanitizeDetails(details || {}),
        ts: Date.now(),
      };
      send(payload);
    }

    function trackPageView() {
      track('page_view', { referrer: document.referrer || '', title: document.title || '' });
    }

    function handleClick(event) {
      const target = event.target instanceof Element ? event.target.closest('[data-track]') : null;
      if (target) {
        track('ui_interaction', {
          action: target.dataset.track || '',
          label: (target.dataset.trackLabel || target.textContent || '').trim().slice(0, 80),
        });
        return;
      }

      const tabBtn = event.target instanceof Element ? event.target.closest('.tab-button') : null;
      if (tabBtn) {
        track('ui_interaction', {
          action: 'tab_click',
          tab: (tabBtn.dataset.tab || tabBtn.textContent || '').trim().slice(0, 50),
        });
      }
    }

    function handleChange(event) {
      const select = event.target instanceof Element ? event.target.closest('select') : null;
      if (select) {
        track('select_change', {
          id: select.id || '',
          name: select.name || '',
          value: String(select.value || '').slice(0, 80),
        });
      }
    }

    function handleSubmit(event) {
      const form = event.target instanceof HTMLFormElement ? event.target : null;
      if (!form) return;
      track('form_submit', {
        id: form.id || '',
        action: form.getAttribute('action') || window.location.pathname,
      });
    }

    document.addEventListener('click', handleClick, { passive: true });
    document.addEventListener('change', handleChange, { passive: true });
    document.addEventListener('submit', handleSubmit, true);

    return {
      enable() {
        if (allowed) return;
        allowed = true;
        trackPageView();
      },
      disable() {
        allowed = false;
      },
      track,
    };
  })();

  window.tosTrackEvent = function (eventType, details) {
    Tracking.track(eventType, details);
  };

  // ---------- Consent banner ----------
  const style = document.createElement('style');
  style.textContent = `
    #${BANNER_ID} {
      position: fixed;
      bottom: 16px;
      left: 16px;
      right: 16px;
      max-width: 720px;
      margin: 0 auto;
      background: #111827;
      color: #f9fafb;
      padding: 14px 16px;
      border-radius: 14px;
      box-shadow: 0 16px 40px rgba(0,0,0,0.3);
      z-index: 9999;
      display: none;
      gap: 10px;
    }
    #${BANNER_ID}.is-visible { display: flex; flex-direction: column; }
    #${BANNER_ID} .consent-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
    #${BANNER_ID} button { border: none; cursor: pointer; font-weight: 700; padding: 10px 14px; border-radius: 10px; }
    #${BANNER_ID} .btn-primary { background: #3b82f6; color: #fff; }
    #${BANNER_ID} .btn-ghost { background: transparent; color: #f9fafb; border: 1px solid rgba(255,255,255,0.25); }
    #${BANNER_ID} a { color: #93c5fd; text-decoration: underline; }
    @media (min-width: 600px) {
      #${BANNER_ID} { flex-direction: row; align-items: center; }
      #${BANNER_ID} .consent-actions { margin-top: 0; margin-left: auto; }
    }
  `;
  document.head.appendChild(style);

  function loadConsent() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      return saved ? JSON.parse(saved) : null;
    } catch (err) {
      return null;
    }
  }

  function saveConsent(consent) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));
    } catch (err) {}
  }

  function hideBanner() {
    const banner = document.getElementById(BANNER_ID);
    if (banner) {
      banner.classList.remove('is-visible');
    }
  }

  function showBanner() {
    const banner = document.getElementById(BANNER_ID);
    if (banner) {
      banner.classList.add('is-visible');
    }
  }

  function applyConsent(consent) {
    if (consent && consent.tracking) {
      Tracking.enable();
    } else {
      Tracking.disable();
    }
  }

  function renderBanner() {
    if (document.getElementById(BANNER_ID)) return;

    const banner = document.createElement('div');
    banner.id = BANNER_ID;
    banner.innerHTML = `
      <div class="consent-text">
        Usiamo cookie tecnici e, se acconsenti, registriamo alcune azioni per migliorare il sito (senza salvare i contenuti dei form).
        <a href="/privacy.php">Privacy</a> &middot; <a href="/cookie.php">Cookie</a>
      </div>
      <div class="consent-actions">
        <button type="button" class="btn-ghost" data-consent="reject">Rifiuta</button>
        <button type="button" class="btn-primary" data-consent="accept">Accetta</button>
      </div>
    `;
    document.body.appendChild(banner);

    banner.addEventListener('click', (event) => {
      const btn = event.target instanceof Element ? event.target.closest('[data-consent]') : null;
      if (!btn) return;
      const choice = btn.getAttribute('data-consent');
      const consent = { tracking: choice === 'accept', ts: Date.now() };
      saveConsent(consent);
      applyConsent(consent);
      hideBanner();
    });
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-open-consent]') : null;
    if (trigger) {
      event.preventDefault();
      showBanner();
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    renderBanner();
    const consent = loadConsent();
    if (!consent) {
      showBanner();
    }
    applyConsent(consent);
  });
})();
