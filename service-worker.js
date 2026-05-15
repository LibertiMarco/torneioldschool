self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }

  return outputArray;
}

self.addEventListener('push', (event) => {
  let data = {};

  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = {
      title: 'Tornei Old School',
      body: event.data ? event.data.text() : 'Nuova notifica disponibile.',
    };
  }

  const title = data.title || 'Tornei Old School';
  const options = {
    body: data.body || '',
    icon: data.icon || '/img/logo_old_school.png',
    badge: data.badge || '/img/logo_old_school.png',
    tag: data.tag || 'tos-notification',
    renotify: !!data.renotify,
    data: {
      url: data.url || '/account.php',
    },
    timestamp: Number.isFinite(data.timestamp) ? data.timestamp : Date.now(),
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url === targetUrl && 'focus' in client) {
          return client.focus();
        }
      }

      for (const client of clientList) {
        if ('navigate' in client && 'focus' in client) {
          return client.navigate(targetUrl).then(() => client.focus());
        }
      }

      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }

      return undefined;
    })
  );
});

self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil((async () => {
    try {
      const configResponse = await fetch('/api/push_subscription.php', { credentials: 'include' });
      if (!configResponse.ok) {
        return;
      }

      const config = await configResponse.json();
      if (!config || !config.configured || !config.publicKey || !config.csrfToken) {
        return;
      }

      const subscription = await self.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(config.publicKey),
      });

      await fetch('/api/push_subscription.php', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': config.csrfToken,
        },
        body: JSON.stringify({
          action: 'subscribe',
          subscription: subscription.toJSON(),
          contentEncoding: 'aes128gcm',
        }),
      });
    } catch (error) {
      // Ignora: il browser ritenterà la subscription quando l'utente tornerà nell'account.
    }
  })());
});
