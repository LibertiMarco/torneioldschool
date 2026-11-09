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
      kind: data.data?.kind || '',
      request_id: data.data?.request_id || '',
    },
    timestamp: Number.isFinite(data.timestamp) ? data.timestamp : Date.now(),
  };
  if(options.data.kind==='player_link_request' && /^[1-9][0-9]{0,9}$/.test(String(options.data.request_id))) {
    options.actions=[{action:'accept',title:'Accetta'},{action:'reject',title:'Rifiuta'}];
  }

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  if(['accept','reject'].includes(event.action) && event.notification.data?.kind==='player_link_request') {
    event.waitUntil((async()=>{
      const id=Number(event.notification.data.request_id);
      try {
        if(!Number.isSafeInteger(id) || id<=0)throw new Error('Richiesta non valida.');
        const sessionResponse=await fetch('/app-preview/api.php?action=session',{credentials:'include',cache:'no-store'});
        const session=await sessionResponse.json();
        if(!sessionResponse.ok || !session.user?.permissions?.admin || !session.csrf)throw new Error('Accedi come amministratore per gestire questa richiesta.');
        const response=await fetch('/app-preview/api.php?action=player_link_requests',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json'},body:JSON.stringify({_csrf:session.csrf,action:'review',request_id:id,accept:event.action==='accept'})});
        const result=await response.json();if(!response.ok || !result.ok)throw new Error(result.error?.message || 'Operazione non riuscita. Riprova nella gestione.');
        await self.registration.showNotification(event.action==='accept'?'Richiesta accettata':'Richiesta rifiutata',{body:result.message,icon:'/img/logo_old_school.png',tag:'player-link-result-'+id,data:{url:'/api/gestione_account_giocatore.php'}});
      } catch(error) {
        await self.registration.showNotification('Richiesta non confermata',{body:error.message || 'Connessione non disponibile. Riprova nella gestione.',icon:'/img/logo_old_school.png',tag:'player-link-result-'+id,data:{url:'/api/gestione_account_giocatore.php'}});
      }
    })());return;
  }
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
