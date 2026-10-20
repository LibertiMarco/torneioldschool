/* Controller bridge. The controller DOM stays private; Flutter renders the UI.
 * CSRF fields, record identifiers and validation continue to belong to PHP.
 */
(() => {
  'use strict';
  const frame = document.getElementById('staff-source');
  const channel = window.tosStaffChannel;
  const routes = new Set([
    'gestione_tornei', 'gestione_partite', 'gestione_squadre', 'gestione_giocatori',
    'crea_giornata_automatica', 'sincronizza_video_partite', 'gestione_account_giocatore',
    'gestione_blog', 'gestione_staff', 'gestione_albo', 'gestione_totocalcio', 'gestione_fantacalcio',
    'grafiche_settimana', 'grafiche_post_partita', 'copertina_partite', 'grafiche_classifiche',
    'grafiche_presentazione', 'statistiche_partita', 'crea_calendario_allinone',
  ].map(name => `/api/${name}.php`).concat('/admin_mobile.php'));
  let doc, generation = 0, sequence = 0, ids, elements, timer, observer;
  let lastAction = null, approved = null, lastSnapshot = '', busy = false;
  let uploads = new Map();
  let previewTimer = null;
  const clean = value => String(value || '').replace(/\s+/g, ' ').trim();
  const editable = element => element.isContentEditable || element.getAttribute('contenteditable') === 'true';
  const emit = (type, data = {}) => {
    const message = JSON.stringify({type, channel, document: String(generation), ...data});
    if (window.TosStaff) window.TosStaff.postMessage(message);
    else window.parent.postMessage(message, window.location.origin);
  };
  const visible = element => {
    if (element?.closest('#tos-consent-banner')) return false;
    if (!element || element.hidden || element.closest('[hidden], [aria-hidden="true"]')) return false;
    for (let node = element; node && node.nodeType === 1; node = node.parentElement) {
      const style = doc.defaultView.getComputedStyle(node);
      if (style.display === 'none' || style.visibility === 'hidden') return false;
    }
    return true;
  };
  const id = element => {
    if (!ids.has(element)) {
      const key = `${generation}-${++sequence}`;
      ids.set(element, key); elements.set(key, element);
    }
    return ids.get(element);
  };
  const label = element => {
    const labels = Array.from(element.labels || []);
    let text = labels.map(node => {
      const clone = node.cloneNode(true);
      clone.querySelectorAll('input,select,textarea,button').forEach(child => child.remove());
      return clean(clone.textContent);
    }).join(' ');
    if (!text) text = clean(element.getAttribute('aria-label'));
    if (!text) {
      const before = element.previousElementSibling;
      if (before?.tagName === 'LABEL') text = clean(before.textContent);
    }
    if (/^[\s🟨🟥]+$/u.test(text)) text = labels[0]?.title || (text.includes('🟨') ? 'Cartellino giallo' : 'Cartellino rosso');
    return text || element.getAttribute('aria-label') || element.placeholder || element.title || element.name || element.dataset.field || 'Valore';
  };
  function control(element) {
    const type = editable(element) ? 'rich' : element.tagName === 'SELECT' ? 'select'
      : element.tagName === 'TEXTAREA' ? 'textarea' : element.type;
    if (type === 'hidden') return null;
    // Passwords are editable, but their existing value must never cross the bridge.
    return {kind: 'field', id: id(element), type, label: label(element),
      value: type === 'password' ? '' : type === 'rich' ? element.innerHTML : element.value || '',
      checked: !!element.checked, required: !!element.required, disabled: element.matches(':disabled') || !!element.readOnly,
      min: element.min || '', max: element.max || '', step: element.step || '',
      multiple: !!element.multiple, accept: element.accept || '',
      maxBytes: element.type === 'file' && element.id === 'image' ? 20 * 1024 * 1024 : 32 * 1024 * 1024,
      values: type === 'select' ? Array.from(element.selectedOptions).map(option => option.value) : [],
      options: type === 'select' ? Array.from(element.options).map(option => ({value: option.value, label: clean(option.text), disabled: option.disabled})) : [],
      files: type === 'file' ? Array.from(element.files || []).map(file => file.name) : []};
  }
  function action(element) {
    if (element.closest('.editor-toolbar') || element.hasAttribute('data-editor-bold') || element.classList.contains('admin-back-link')) return null;
    const text = clean(element.getAttribute('aria-label') || element.textContent || element.value || element.title);
    if (!text || /^(Torna (alla dashboard|al blog)|Dashboard)$/i.test(text)) return null;
    if (element.tagName === 'A') {
      const url = new URL(element.href, doc.location.href);
      if (url.origin === location.origin && !routes.has(url.pathname)) return null;
      if (!['https:','http:'].includes(url.protocol)) return null;
    }
    return {kind: 'action', id: id(element), label: text, disabled: element.matches(':disabled'),
      selected: element.classList.contains('active') || element.getAttribute('aria-selected') === 'true',
      tab: !!element.closest('.tab-buttons,.tabs,.admin-btn-group,.action-selector,.action-switch,.action-toggles') || element.classList.contains('action-toggle'),
      danger: /elimina|rimuovi|cancella|disattiva|rifiuta/i.test(text)};
  }
  const textNode = (kind, text) => text ? {kind, text} : null;
  function matchForm(form) {
    const used = new Set();
    const sections = [
      ['Seleziona la partita', ['selTorneoMod','selFaseMod','selGiornataMod','selPartitaMod','btnStatsMod']],
      ['Risultato', ['squadra_casa_mod','squadra_ospite_mod','gol_casa_mod','gol_ospite_mod','decisa_rigori_mod','rigori_casa_mod','rigori_ospite_mod','giocata_mod']],
      ['Dettagli partita', ['torneo_mod','fase_mod','giornata_mod','round_eliminazione_mod','faseLegMod','data_partita_mod','ora_partita_mod','campo_mod']],
      ['Arbitro e video', ['arbitro_mod','link_youtube_mod','link_instagram_mod']],
    ].map(([title, keys]) => ({title, blocks: keys.map(key => {
      const element = form.querySelector(`#${key}`);
      if (!element) return null;
      used.add(element);
      const block = walk(element);
      if (block && /^(gol|rigori)_(casa|ospite)_mod$/.test(key)) block.score = true;
      return block;
    }).filter(Boolean)})).filter(section => section.blocks.length);
    const rest = Array.from(form.querySelectorAll('input,select,textarea,button,.form-message'))
      .filter(element => !used.has(element)).map(element => walk(element)).filter(Boolean);
    return {kind:'match-form', id:id(form), sections, blocks:rest};
  }
  function matchLineup(form) {
    const field = element => element && visible(element) ? control(element) : null;
    const info = doc.querySelector('#partitaInfo')?.cloneNode(true);
    info?.querySelectorAll('br').forEach(br => br.replaceWith(doc.createTextNode(' · ')));
    const teams = Array.from(form.querySelectorAll('.team-lineup')).filter(visible).map(team => ({
      id:id(team), name:clean(team.querySelector('h2')?.textContent),
      actions:Array.from(team.querySelectorAll('[data-select-team]')).map(action).filter(Boolean),
      players:Array.from(team.querySelectorAll('.player-row')).filter(visible).map(row => ({
        id:id(row), name:clean(row.querySelector('.player-name > span')?.textContent),
        badges:Array.from(row.querySelectorAll('.player-meta-badge,.disciplinary-tag')).map(tag => clean(tag.textContent)),
        fields:Array.from(row.querySelectorAll('input,select')).map(element => {
          const block = field(element);
          if (!block) return null;
          block.stat = element.dataset.field;
          if (block.stat === 'presenza') block.label = 'Presente';
          return block;
        }).filter(Boolean),
      })),
    }));
    const finalize = field(form.querySelector('#finalizzaPartita'));
    if (finalize) finalize.label = 'Finalizza partita';
    const save = form.querySelector('#saveLineup') ? action(form.querySelector('#saveLineup')) : null;
    if (!teams.length) {
      if (finalize) finalize.disabled = true;
      if (save) save.disabled = true;
    }
    return {kind:'match-lineup', id:id(form), teams,
      info:clean(info?.textContent),
      home:clean(form.querySelector('#homeScoreName')?.textContent),
      away:clean(form.querySelector('#awayScoreName')?.textContent),
      score:clean(form.querySelector('#liveScore')?.textContent),
      back:doc.querySelector('#btnBackStats') ? action(doc.querySelector('#btnBackStats')) : null,
      finalize, save,
      notice:doc.querySelector('#msgBox')?.style.opacity === '1' ? clean(doc.querySelector('#msgBox').textContent) : '',
      error:doc.querySelector('#msgBox')?.classList.contains('msg-error') || false,
      loading:teams.length ? '' : clean(form.querySelector('#lineupRoot')?.textContent),
    };
  }
  function walk(element, inTable = false) {
    if (element.nodeType === 3) return textNode('text', clean(element.textContent));
    if (element.nodeType !== 1) return null;
    const tag = element.tagName;
    if (['SCRIPT','STYLE','LINK','META','NOSCRIPT','HEADER','FOOTER'].includes(tag) || element.matches('#header-container,#footer-container,.editor-toolbar')) return null;
    // Website file inputs are frequently visually hidden behind a custom label.
    if (tag === 'INPUT' && element.type === 'file') {
      if (visible(element.parentElement)) return control(element);
    }
    if (!visible(element)) return null;
    if (doc.location.pathname === '/api/gestione_partite.php' && element.matches('form#formModifica')) return matchForm(element);
    if (tag === 'INPUT' && ['submit','button','reset'].includes(element.type)) return action(element);
    if (['INPUT','SELECT','TEXTAREA'].includes(tag) || editable(element)) return control(element);
    if (tag === 'BUTTON' || tag === 'A' || element.getAttribute('role') === 'button') return action(element);
    if (tag === 'CANVAS') {
      try {
        const preview = doc.createElement('canvas');
        preview.width = Math.min(element.width, 540);
        preview.height = Math.round(element.height * preview.width / element.width);
        if (!preview.width || !preview.height) return null;
        preview.getContext('2d').drawImage(element, 0, 0, preview.width, preview.height);
        return {kind: 'image', id: id(element), data: preview.toDataURL('image/png'),
          photo: doc.defaultView.PhotoTouchEditor?.describe?.(element.id) || null,
          pointerEditor: element.classList.contains('editing') || element.dataset.tosPointerEditor === 'true',
          canvasWidth: element.width, canvasHeight: element.height,
          label: clean(element.closest('.card,article,section')?.querySelector('h2,h3')?.textContent) || 'Anteprima grafica', export: true};
      } catch (_) { return textNode('notice', 'Anteprima non disponibile. Puoi usare il pulsante di esportazione.'); }
    }
    if (tag === 'IMG') {
      if (!element.src || !element.complete) return null;
      const url = new URL(element.src, doc.location.href);
      if (!['https:','data:','blob:'].includes(url.protocol)) return null;
      return {kind: 'image', id: id(element), url: url.href, label: element.alt || '', export: false};
    }
    if (tag === 'TABLE' && !inTable) {
      const headerCells = Array.from(element.querySelectorAll('thead th'));
      const headers = headerCells.map(cell => clean(cell.textContent));
      const rows = Array.from(element.querySelectorAll('tbody > tr')).filter(visible);
      return {kind: 'table', id: id(element), headers, total: rows.length,
        sorting: headerCells.filter(cell => cell.matches('[data-sort],[data-col],.sortable')).map(cell => ({...action(cell), label: `Ordina per ${clean(cell.textContent)}`})),
        rows: rows.map(row => ({id: id(row), search: clean(row.textContent), cells: Array.from(row.children).map((cell, index) => ({label: headers[index] || '', blocks: children(cell, true)}))}))};
    }
    if (/^H[1-6]$/.test(tag) && !element.querySelector('a,button')) return {...textNode('heading', clean(element.textContent)), id: id(element)};
    if (element.matches('.admin-alert,.alert,.status,[role="alert"],.success-message,.error-message')) return clean(element.textContent) ? {...textNode('notice', clean(element.textContent)), id: id(element)} : null;
    if (tag === 'DETAILS') {
      return {kind: 'expansion', id: id(element), title: clean(element.querySelector('summary')?.textContent),
        blocks: Array.from(element.children).filter(child => child.tagName !== 'SUMMARY').map(child => walk(child, inTable)).filter(Boolean)};
    }
    if (tag === 'LABEL') {
      // Flutter owns the label of the contained control.
      return {kind: 'group', blocks: children(element, inTable).filter(block => block.kind !== 'text')};
    }
    if (['P','SMALL','LI','DT','DD'].includes(tag) && !element.querySelector('input,select,textarea,button,a,canvas,[contenteditable="true"]')) return textNode('text', clean(element.textContent));
    const blocks = children(element, inTable);
    if (!blocks.length) return null;
    return {kind: tag === 'FORM' ? 'form' : element.matches('.tab-buttons,.tabs,.admin-btn-group,.action-selector,.action-switch,.action-toggles') ? 'tabs' : element.matches('.player-row,.work-card,.result,.auto-team-availability') ? 'panel' : 'group', id: id(element), blocks};
  }
  const children = (element, inTable) => Array.from(element.childNodes).map(node => walk(node, inTable)).filter(Boolean);
  function snapshot() {
    clearTimeout(timer);
    if (!doc || busy) return;
    try {
      const modal = Array.from(doc.querySelectorAll('[role="dialog"],dialog[open],.modal,.modal-overlay,.confirm-overlay,.confirm-modal,.delete-modal')).find(visible);
      const root = modal || doc.querySelector('main') || doc.querySelector('.admin-container') || doc.body;
      const lineup = !modal && doc.location.pathname === '/api/statistiche_partita.php' && doc.querySelector('form#lineupForm');
      const data = {blocks: lineup ? [matchLineup(lineup)] : children(root, false), modal: !!modal};
      const serialized = JSON.stringify(data);
      if (serialized !== lastSnapshot) { lastSnapshot = serialized; emit('snapshot', data); }
    } catch (_) { emit('error', {message: 'Impossibile aggiornare lo strumento. Riprova.'}); }
  }
  const schedule = () => { clearTimeout(timer); timer = setTimeout(snapshot, 120); };
  // Pointer/scale streams must not continually postpone the preview snapshot.
  const previewChanged = () => {
    if (previewTimer !== null) return;
    previewTimer = setTimeout(() => {previewTimer=null;snapshot();}, 60);
  };
  const readFile = (blob, name) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve({name: name || 'grafica.png', mime: blob.type || 'application/octet-stream', data: reader.result});
    reader.onerror = () => reject(new Error('Impossibile esportare il file.'));
    reader.readAsDataURL(blob);
  });
  async function fileMessage(blob, name) {
    try { emit('download', await readFile(blob, name)); }
    catch (_) { emit('error', {message: 'Impossibile esportare il file.'}); }
  }
  function attach() {
    observer?.disconnect(); clearTimeout(timer);
    clearTimeout(previewTimer);previewTimer=null;
    try {
      doc = frame.contentDocument;
      const path = doc.location.pathname;
      if (!routes.has(path)) {
        emit('error', {message: 'Sessione scaduta o pagina non disponibile. Accedi nuovamente.'}); return;
      }
      if (!doc.querySelector('main,form,canvas')) {
        emit('error', {message: clean(doc.body?.textContent) || 'Strumento non disponibile.'}); return;
      }
      generation++; sequence = 0; ids = new WeakMap(); elements = new Map(); uploads = new Map(); lastSnapshot = ''; busy = false;
      const win = doc.defaultView;
      win.alert = message => emit('notice', {message: String(message)});
      win.confirm = message => {
        if (approved === String(message)) { approved = null; return true; }
        emit('confirm', {message: String(message), action: lastAction}); return false;
      };
      // Programmatic file pickers (also used by dynamically added upload rows).
      const inputClick = win.HTMLInputElement.prototype.click;
      win.HTMLInputElement.prototype.click = function () {
        if (this.type === 'file') emit('pick', {field: control(this)});
        else inputClick.call(this);
      };
      doc.addEventListener('invalid', event => emit('notice', {message: `${label(event.target)}: ${event.target.validationMessage}`}), true);
      doc.addEventListener('click', event => {
        const anchor = event.target.closest('a');
        if (anchor && !anchor.download && new URL(anchor.href, doc.location.href).origin !== location.origin) {
          event.preventDefault(); emit('link', {url: anchor.href}); return;
        }
        if (anchor?.download || anchor?.href?.startsWith('blob:') || anchor?.href?.startsWith('data:')) {
          event.preventDefault();
          win.fetch(anchor.href).then(response => response.blob()).then(blob => fileMessage(blob, anchor.download)).catch(() => emit('error', {message: 'Esportazione non riuscita.'}));
        }
      }, true);
      // Native sharing captures the original generator's file output too.
      try {
        Object.defineProperty(win.navigator, 'share', {value: async data => {
          const files = await Promise.all((data.files || []).map(file => readFile(file, file.name)));
          if (files.length) emit('download', files.length === 1 ? files[0] : {files});
        }});
        Object.defineProperty(win.navigator, 'canShare', {value: data => !!data.files?.length});
      } catch (_) { /* Anchor export and native canvas export remain available. */ }
      if (win.GraphicsDownloads) {
        win.GraphicsDownloads.saveAllImages = async generated => {
          const files = await Promise.all(generated.map(async item => readFile(await win.GraphicsDownloads.canvasToBlob(item.canvas), item.name)));
          if (files.length) emit('download', {files});
        };
      }
      win.addEventListener('beforeunload', () => { busy = true; emit('loading'); });
      observer = new MutationObserver(schedule);
      observer.observe(doc.body, {subtree: true, childList: true, attributes: true, characterData: true});
      doc.addEventListener('input', schedule, true); doc.addEventListener('change', schedule, true);
      snapshot();
    } catch (_) { emit('error', {message: 'Accesso allo strumento non disponibile.'}); }
  }
  function command(message) {
    if (message.channel !== channel || message.document !== String(generation) || !doc) return;
    const element = elements.get(message.id);
    if (!element || !element.isConnected) return;
    if (element.matches(':disabled') && message.type !== 'export') return;
    try {
      if (message.type === 'change') {
        if (element.matches(':disabled') || element.readOnly) return;
        if (editable(element)) element.innerHTML = message.value;
        else if (element.type === 'checkbox' || element.type === 'radio') element.checked = !!message.checked;
        else if (element.tagName === 'SELECT' && element.multiple) Array.from(element.options).forEach(option => { option.selected = (message.values || []).includes(option.value); });
        else element.value = String(message.value ?? '');
        element.dispatchEvent(new doc.defaultView.Event('input', {bubbles: true}));
        element.dispatchEvent(new doc.defaultView.Event('change', {bubbles: true}));
      } else if (message.type === 'fileBegin' && element.type === 'file') {
        uploads.set(message.id, (message.files || []).map(file => ({...file, data: ''})));
      } else if (message.type === 'fileChunk' && element.type === 'file') {
        const file = uploads.get(message.id)?.[message.index];
        if (file && typeof message.data === 'string') file.data += message.data;
      } else if (message.type === 'fileEnd' && element.type === 'file') {
        const files = uploads.get(message.id);
        uploads.delete(message.id);
        if (files) command({...message, type: 'files', files});
      } else if (message.type === 'files' && element.type === 'file') {
        const transfer = new doc.defaultView.DataTransfer();
        for (const file of message.files || []) {
          const bytes = Uint8Array.from(atob(file.data), char => char.charCodeAt(0));
          transfer.items.add(new doc.defaultView.File([bytes], file.name, {type: file.mime || 'application/octet-stream'}));
        }
        element.files = transfer.files;
        element.dispatchEvent(new doc.defaultView.Event('change', {bubbles: true}));
      } else if (message.type === 'click') {
        if (element.matches(':disabled') || !visible(element)) return;
        lastAction = {type: 'click', id: message.id, document: message.document};
        approved = typeof message.approved === 'string' ? message.approved : null;
        element.click(); approved = null;
      } else if (message.type === 'export' && element.tagName === 'CANVAS') {
        element.toBlob(blob => blob && fileMessage(blob, message.name || 'grafica.png'), 'image/png');
      } else if (message.type === 'photoTransform' && element.tagName === 'CANVAS') {
        if (!doc.defaultView.PhotoTouchEditor?.apply?.(element.id, message.values || {})) {
          emit('error', {message: 'Foto non disponibile. Ricarica l’anteprima.'});
        }
        previewChanged();
      } else if (message.type === 'canvasPointer' && element.tagName === 'CANVAS' &&
          (element.classList.contains('editing') || element.dataset.tosPointerEditor === 'true')) {
        const {event,x,y,pointer}=message;
        if (!['pointerdown','pointermove','pointerup','pointercancel'].includes(event) ||
            ![x,y,pointer].every(Number.isFinite) || Math.abs(x)>10 || Math.abs(y)>10) return;
        const rect=element.getBoundingClientRect();
        // Pointer capture is owned by Flutter. Synthetic DOM events have no
        // browser pointer to capture, while the original editor still receives
        // its unmodified coordinate/gesture logic.
        const capture=element.setPointerCapture;
        element.setPointerCapture=()=>{};
        try {
          element.dispatchEvent(new doc.defaultView.PointerEvent(event,{bubbles:true,cancelable:true,
            pointerId:pointer,pointerType:'touch',button:0,
            clientX:rect.left+x*rect.width,clientY:rect.top+y*rect.height}));
        } finally {element.setPointerCapture=capture;}
        previewChanged();
      } else if (message.type === 'reload' && doc.location.pathname === '/api/statistiche_partita.php' && element.matches('form#lineupForm')) {
        doc.defaultView.location.reload();
      }
      schedule();
    } catch (_) { emit('error', {message: 'Operazione non riuscita. Controlla i dati e riprova.'}); }
  }
  window.tosStaffCommand = command;
  window.addEventListener('message', event => {
    if (event.source !== window.parent || event.origin !== location.origin) return;
    try { command(typeof event.data === 'string' ? JSON.parse(event.data) : event.data); } catch (_) { /* Ignore unrelated messages. */ }
  });
  frame.addEventListener('load', attach);
  if (frame.contentDocument?.readyState === 'complete' && frame.contentDocument.location.pathname !== 'blank') attach();
  setInterval(snapshot, 1800);
})();
