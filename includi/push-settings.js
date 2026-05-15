(function () {
  if (window.__TOS_PUSH_SETTINGS_LOADED__) {
    return;
  }

  window.__TOS_PUSH_SETTINGS_LOADED__ = true;

  const panel = document.getElementById('pushSettingsPanel');
  if (!panel) {
    return;
  }

  const statusEl = document.getElementById('pushStatusMessage');
  const deviceEl = document.getElementById('pushDeviceStatus');
  const hintEl = document.getElementById('pushSupportHint');
  const toggleBtn = document.getElementById('pushToggleBtn');
  const testBtn = document.getElementById('pushTestBtn');

  const state = {
    loading: false,
    configured: false,
    publicKey: '',
    csrfToken: '',
    subscriptionCount: 0,
    localSubscription: null,
  };

  function supportsPush() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  }

  function isSecureEnough() {
    return window.isSecureContext || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
  }

  function isIos() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent || '');
  }

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  function setStatus(message, tone) {
    if (!statusEl) {
      return;
    }

    statusEl.textContent = message || '';
    statusEl.dataset.tone = tone || 'neutral';
  }

  function setHint(message) {
    if (hintEl) {
      hintEl.textContent = message || '';
    }
  }

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
  }

  async function readJsonResponse(response) {
    const raw = await response.text();
    let data = null;

    try {
      data = raw ? JSON.parse(raw) : {};
    } catch (error) {
      data = {};
    }

    return data;
  }

  async function fetchConfig() {
    const response = await fetch('/api/push_subscription.php', { credentials: 'include' });
    const data = await readJsonResponse(response);
    if (!response.ok) {
      throw new Error(data && data.error ? data.error : 'Impossibile leggere la configurazione push.');
    }

    state.configured = !!data.configured;
    state.publicKey = data.publicKey || '';
    state.csrfToken = data.csrfToken || '';
    state.subscriptionCount = Number.isFinite(data.subscriptionCount) ? data.subscriptionCount : 0;
    return data;
  }

  async function ensureRegistration() {
    return navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
  }

  async function getLocalSubscription() {
    const registration = await ensureRegistration();
    return registration.pushManager.getSubscription();
  }

  async function persistAction(action, payload) {
    if (!state.csrfToken) {
      await fetchConfig();
    }

    const response = await fetch('/api/push_subscription.php', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': state.csrfToken,
      },
      body: JSON.stringify(Object.assign({ action }, payload || {})),
    });
    const data = await readJsonResponse(response);
    if (!response.ok) {
      throw new Error(data && data.error ? data.error : 'Operazione push non riuscita.');
    }

    if (data.csrfToken) {
      state.csrfToken = data.csrfToken;
    }
    if (Number.isFinite(data.subscriptionCount)) {
      state.subscriptionCount = data.subscriptionCount;
    }

    return data;
  }

  function render() {
    const supported = supportsPush();
    const secure = isSecureEnough();
    const standaloneBlocked = isIos() && !isStandalone();
    const permission = supported ? Notification.permission : 'unsupported';
    const localEnabled = !!state.localSubscription;

    panel.dataset.ready = '1';

    if (deviceEl) {
      if (!supported) {
        deviceEl.textContent = 'Questo browser non supporta le notifiche push web.';
      } else if (!secure) {
        deviceEl.textContent = 'Le notifiche push richiedono HTTPS sul dominio pubblico.';
      } else if (standaloneBlocked) {
        deviceEl.textContent = 'Su iPhone/iPad devi aprire il sito dalla schermata Home per attivare le notifiche.';
      } else if (!state.configured) {
        deviceEl.textContent = 'Configurazione server push non ancora disponibile.';
      } else if (localEnabled) {
        deviceEl.textContent = 'Notifiche attive su questo dispositivo.';
      } else if (permission === 'denied') {
        deviceEl.textContent = 'Permesso notifiche bloccato nel browser.';
      } else {
        deviceEl.textContent = 'Notifiche non ancora attive su questo dispositivo.';
      }
    }

    if (toggleBtn) {
      toggleBtn.disabled = state.loading || !supported || !secure || standaloneBlocked || !state.configured;
      toggleBtn.textContent = localEnabled ? 'Disattiva su questo dispositivo' : 'Attiva su questo dispositivo';
    }

    if (testBtn) {
      testBtn.disabled = state.loading || !localEnabled;
    }

    if (!supported) {
      setHint('Apri il sito da un browser moderno con supporto Service Worker e Push API.');
      return;
    }

    if (!secure) {
      setHint('Su telefono funziona solo in HTTPS. In locale puoi provare da localhost, ma sul dominio pubblico serve certificato attivo.');
      return;
    }

    if (standaloneBlocked) {
      setHint('Su iPhone/iPad: usa Safari, scegli "Aggiungi a Home" e riapri il sito dall’icona prima di attivare le notifiche.');
      return;
    }

    setHint(state.subscriptionCount > 0
      ? `Dispositivi attivi collegati al tuo account: ${state.subscriptionCount}.`
      : 'Nessun dispositivo push attivo collegato al tuo account.'
    );
  }

  async function refreshState() {
    if (!supportsPush()) {
      render();
      return;
    }

    if (isSecureEnough()) {
      try {
        state.localSubscription = await getLocalSubscription();
      } catch (error) {
        state.localSubscription = null;
      }
    }

    try {
      await fetchConfig();
    } catch (error) {
      state.configured = false;
      setStatus(error.message, 'error');
    }

    render();
  }

  async function enablePush() {
    if (state.loading) {
      return;
    }

    state.loading = true;
    render();
    setStatus('Attivazione notifiche in corso...', 'neutral');

    try {
      await fetchConfig();
      if (!state.configured || !state.publicKey) {
        throw new Error('Chiavi VAPID mancanti sul server.');
      }

      const registration = await ensureRegistration();
      let permission = Notification.permission;
      if (permission === 'default') {
        permission = await Notification.requestPermission();
      }
      if (permission !== 'granted') {
        throw new Error('Permesso notifiche non concesso.');
      }

      const existingSubscription = await registration.pushManager.getSubscription();
      const subscription = existingSubscription || await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(state.publicKey),
      });

      await persistAction('subscribe', {
        subscription: subscription.toJSON(),
        contentEncoding: 'aes128gcm',
      });

      state.localSubscription = subscription;
      setStatus('Notifiche push attivate su questo dispositivo.', 'success');
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Attivazione notifiche non riuscita.', 'error');
    } finally {
      state.loading = false;
      await refreshState();
    }
  }

  async function disablePush() {
    if (state.loading) {
      return;
    }

    state.loading = true;
    render();
    setStatus('Disattivazione notifiche in corso...', 'neutral');

    try {
      const registration = await ensureRegistration();
      const subscription = await registration.pushManager.getSubscription();
      if (subscription) {
        await persistAction('unsubscribe', {
          subscription: subscription.toJSON(),
        });
        await subscription.unsubscribe();
      }

      state.localSubscription = null;
      setStatus('Notifiche push disattivate su questo dispositivo.', 'success');
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Disattivazione notifiche non riuscita.', 'error');
    } finally {
      state.loading = false;
      await refreshState();
    }
  }

  async function sendTest() {
    if (state.loading) {
      return;
    }

    state.loading = true;
    render();
    setStatus('Invio notifica di test...', 'neutral');

    try {
      await persistAction('test', {});
      setStatus('Notifica di test inviata. Se sei su iPhone apri il sito dalla Home.', 'success');
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Invio test non riuscito.', 'error');
    } finally {
      state.loading = false;
      render();
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        if (state.localSubscription) {
          disablePush();
        } else {
          enablePush();
        }
      });
    }

    if (testBtn) {
      testBtn.addEventListener('click', sendTest);
    }

    refreshState();
  });
})();
