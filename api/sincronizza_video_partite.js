(() => {
    'use strict';
    const form = document.getElementById('videoSyncSearch');
    const progress = document.getElementById('videoSyncProgress');
    if (!form || !progress) return;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        if (button.disabled) return;
        const body = new FormData(form);
        button.disabled = true;
        progress.hidden = false;
        progress.className = 'status';
        progress.textContent = 'Avvio della ricerca…';
        form.setAttribute('aria-busy', 'true');
        async function request(data) {
            const response = await fetch(form.action || location.href, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(25000)
            });
            const type = response.headers.get('content-type') || '';
            if (!type.includes('application/json')) {
                throw new Error(response.status === 504
                    ? 'Il server ha interrotto un passaggio della ricerca. Riprova; se succede ancora, verifica i log del server.'
                    : 'Il server non ha restituito la ricerca. Ricarica la pagina e verifica di essere ancora connesso.');
            }
            const result = await response.json();
            if (!response.ok || result.error) throw new Error(result.error || 'Ricerca non disponibile.');
            return result;
        }
        try {
            let result = await request(body);
            while (!result.done) {
                progress.textContent = result.progress;
                const step = new FormData();
                step.set('action', 'step');
                step.set('_csrf', body.get('_csrf'));
                step.set('job', result.job);
                result = await request(step);
            }
            location.assign(location.pathname);
        } catch (error) {
            progress.className = 'error';
            progress.textContent = error.name === 'TimeoutError'
                ? 'Un passaggio non ha risposto in tempo. Riprova la ricerca.'
                : error.message || 'Connessione interrotta. Riprova la ricerca.';
        } finally {
            button.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
})();
