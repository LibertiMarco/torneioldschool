/* Shared website controller; the staff bridge renders the same model natively. */
(() => {
  'use strict';
  const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const dayDate = value => new Date(`${value}T12:00:00`);
  const addDays = (date, amount) => { const next = new Date(date); next.setDate(next.getDate() + amount); return next; };
  function mount({document: doc, matches, availableTeams, initial}) {
    const form = doc.getElementById('formCrea'), root = doc.getElementById('batchRows');
    const calendar = doc.getElementById('matchCalendar'), date = doc.getElementById('batchDate');
    const field = doc.getElementById('campoCrea'), count = doc.getElementById('batchCount');
    let sequence = 0, mode = 'week', cursor = iso(new Date());
    date.value = cursor;
    const element = (tag, text, className) => {
      const node = doc.createElement(tag);
      if (text) node.textContent = text;
      if (className) node.className = className;
      return node;
    };
    const button = (text, action) => {
      const node = element('button', text); node.type = 'button'; node.addEventListener('click', action); return node;
    };
    const toolbar = element('div', '', 'calendar-toolbar');
    const title = element('h3');
    const previous = button('Precedente', () => move(-1));
    const today = button('Oggi', () => { cursor = iso(new Date()); date.value = cursor; refresh(); });
    const next = button('Successivo', () => move(1));
    const day = button('Giorno', () => { mode = 'day'; renderCalendar(); });
    const week = button('Settimana', () => { mode = 'week'; renderCalendar(); });
    day.classList.add('action-toggle'); week.classList.add('action-toggle');
    toolbar.append(previous, today, next, day, week);
    const note = element('p', 'Orari di inizio sul campo selezionato, anche degli altri tornei. Blu: presenti. Arancione: da creare. La durata delle partite non è indicata.');
    const body = element('div', '', 'calendar-scroll');
    calendar.append(title, toolbar, note, body);
    const rows = () => [...root.querySelectorAll('.batch-match-row')];
    function move(direction) { cursor = iso(addDays(dayDate(cursor), direction * (mode === 'week' ? 7 : 1))); renderCalendar(); }
    function addRow(value = {}) {
      if (rows().length >= 50) return;
      const key = sequence++, row = element('div', '', 'batch-match-row');
      row.append(element('h4', '', 'batch-row-title'));
      for (const [name, label, type] of [['squadra_casa', 'Squadra casa', 'select'], ['squadra_ospite', 'Squadra ospite', 'select'], ['ora_partita', 'Ora', 'input']]) {
        const wrap = element('div'); const id = `batch-${key}-${name}`;
        const lab = element('label', label); lab.htmlFor = id;
        const input = element(type); input.id = id; input.name = `partite[${key}][${name}]`; input.required = true;
        input.dataset.field = name;
        if (type === 'input') { input.type = 'time'; input.value = value[name] || ''; }
        else input.dataset.initial = value[name] || '';
        wrap.append(lab, input); row.append(wrap);
      }
      row.append(button('Rimuovi partita', () => { row.remove(); refresh(); }));
      root.append(row); refresh();
    }
    function refresh() {
      const all = rows(), teams = availableTeams();
      all.forEach((row, index) => {
        row.querySelector('h4').textContent = `Partita ${index + 1}`;
        row.querySelector('button').disabled = all.length === 1;
        row.querySelectorAll('select').forEach(select => {
          const selected = select.value || select.dataset.initial || ''; delete select.dataset.initial;
          const used = new Set(all.flatMap(other => [...other.querySelectorAll('select')].filter(input => input !== select).map(input => input.value)));
          select.replaceChildren(new Option('-- Seleziona squadra --', ''));
          teams.forEach(team => { const option = new Option(team, team); option.disabled = used.has(team); select.add(option); });
          select.value = teams.includes(selected) ? selected : '';
          select.disabled = teams.length === 0;
          select.setCustomValidity(selected && used.has(selected) ? 'Squadra gia selezionata in questa richiesta.' : '');
        });
      });
      count.value = String(all.length);
      doc.getElementById('batchAdd').disabled = all.length >= 50;
      doc.getElementById('batchSave').textContent = `Crea ${all.length} ${all.length === 1 ? 'partita' : 'partite'}`;
      renderCalendar();
    }
    function renderCalendar() {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(cursor) || Number.isNaN(dayDate(cursor).getTime())) cursor = iso(new Date());
      let start = dayDate(cursor);
      if (mode === 'week') start = addDays(start, -((start.getDay() + 6) % 7));
      const days = Array.from({length: mode === 'week' ? 7 : 1}, (_, i) => {
        const value = addDays(start, i);
        return {date: iso(value), label: value.toLocaleDateString('it-IT', {weekday: 'short', day: 'numeric', month: 'short'}), today: iso(value) === iso(new Date()), events: []};
      });
      const events = matches().filter(match => field.value && match.campo === field.value).map(match => ({
        date: match.data_partita, time: String(match.ora_partita || '').slice(0, 5),
        title: `${match.squadra_casa} – ${match.squadra_ospite}`,
        subtitle: `${match.torneo} · ${match.fase || 'REGULAR'} · G${match.giornata}`, draft: false,
      }));
      if (field.value) rows().forEach(row => {
        const values = Object.fromEntries([...row.querySelectorAll('[data-field]')].map(input => [input.dataset.field, input.value]));
        if (values.squadra_casa && values.squadra_ospite && values.ora_partita) events.push({
          date: date.value, time: values.ora_partita, title: `${values.squadra_casa} – ${values.squadra_ospite}`,
          subtitle: 'Da creare', draft: true,
        });
      });
      days.forEach(day => { day.events = events.filter(event => event.date === day.date).sort((a, b) => a.time.localeCompare(b.time)); });
      const hours = days.flatMap(day => day.events.map(event => Number(event.time.slice(0, 2)))).filter(Number.isFinite);
      const firstHour = Math.min(16, ...hours), lastHour = Math.max(22, ...hours);
      const heading = mode === 'week'
        ? `${days[0].label} – ${days[6].label} ${start.getFullYear()}`
        : `${days[0].label} ${start.getFullYear()}`;
      title.textContent = field.value ? `${heading} · ${field.value}` : 'Calendario del campo';
      day.setAttribute('aria-pressed', String(mode === 'day'));
      week.setAttribute('aria-pressed', String(mode === 'week'));
      const grid = element('div', '', 'calendar-grid');
      grid.style.gridTemplateColumns = `52px repeat(${days.length}, minmax(${days.length === 1 ? 240 : 140}px, 1fr))`;
      grid.append(element('div', '', 'calendar-day'));
      days.forEach(item => grid.append(element('div', item.label, `calendar-day${item.today ? ' is-today' : ''}`)));
      for (let hour = firstHour; hour <= lastHour; hour++) {
        grid.append(element('div', `${String(hour).padStart(2, '0')}:00`, 'calendar-hour'));
        days.forEach(item => {
          const cell = element('div', '', 'calendar-cell');
          item.events.filter(event => Number(event.time.slice(0, 2)) === hour).forEach(event => {
            const card = element('article', '', `calendar-event${event.draft ? ' is-draft' : ''}`);
            card.append(element('strong', event.time), element('span', event.title), element('small', event.subtitle));
            cell.append(card);
          });
          grid.append(cell);
        });
      }
      body.replaceChildren(grid);
      if (!field.value) note.textContent = 'Seleziona un campo per vedere le partite gia presenti.';
      else note.textContent = 'Orari di inizio sul campo selezionato, anche degli altri tornei. Blu: presenti. Arancione: da creare. La durata delle partite non è indicata.';
      calendar.tosCalendarModel = {title: title.textContent, note: note.textContent, days, firstHour, lastHour};
    }
    doc.getElementById('batchAdd').addEventListener('click', () => addRow());
    form.addEventListener('input', event => {
      if (event.target === date) cursor = date.value;
      if (root.contains(event.target) || event.target === date) renderCalendar();
    });
    form.addEventListener('change', event => {
      if (event.target === date) cursor = date.value;
      refresh();
    });
    if (initial && typeof initial === 'object') {
      for (const key of ['torneo','fase','campo','giornata','round_eliminazione','fase_leg','data_partita','arbitro']) {
        const control = form.elements.namedItem(key);
        if (control && typeof initial[key] === 'string') { control.value = initial[key]; control.dispatchEvent(new Event('change', {bubbles:true})); }
      }
      cursor = date.value || cursor;
    }
    const initialRows = initial?.partite && typeof initial.partite === 'object' ? Object.values(initial.partite).slice(0, 50) : [{}];
    initialRows.forEach(value => addRow(value && typeof value === 'object' ? value : {}));
    if (!rows().length) addRow();
    return {refresh, saved() { root.replaceChildren(); addRow(); refresh(); }};
  }
  window.TosMatchBatch = {mount};
})();
