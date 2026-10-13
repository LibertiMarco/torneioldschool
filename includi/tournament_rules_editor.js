/* Shared by the website and the native staff bridge. */
(() => {
  'use strict';
  function parse(text) {
    if (!text.trim()) return [];
    if (/<[a-z][\s\S]*>/i.test(text)) return [{title: 'Regole esistenti', body: text}];
    return text.trim().split(/\n\s*\n/).map((part, index) => {
      const match = part.match(/^([^:\n]{1,120}):\s*([\s\S]*)$/);
      return match ? {title: match[1], body: match[2]} : {title: `Sezione ${index + 1}`, body: part};
    });
  }
  window.TosRulesEditor = {parse, create(input) {
    let sections = [], defaults = [], sequence = 0;
    const form = input.form;
    const data = document.createElement('input');
    data.type = 'hidden'; data.name = 'regole_sezioni';
    const root = document.createElement('div'); root.className = 'rules-sections-editor';
    input.hidden = true;
    input.after(data, root);
    function write() {
      input.value = sections.map(s => `${s.title}: ${s.body}`).join('\n\n');
      data.value = JSON.stringify(sections.map(({key, title, body, auto}) => ({key, title, body, auto})));
    }
    function render() {
      root.replaceChildren();
      sections.forEach((section, index) => {
        const box = document.createElement('details'); box.open = true;
        box.className = 'rules-section';
        const summary = document.createElement('summary'); summary.textContent = section.title || `Sezione ${index + 1}`;
        const titleLabel = document.createElement('label'); titleLabel.textContent = 'Titolo sezione';
        const title = document.createElement('input'); title.type = 'text'; title.value = section.title;
        title.id = `${input.id}-rule-title-${++sequence}`; titleLabel.htmlFor = title.id;
        const bodyLabel = document.createElement('label'); bodyLabel.textContent = 'Testo della sezione';
        const body = document.createElement('textarea'); body.rows = 4; body.value = section.body;
        body.id = `${input.id}-rule-body-${sequence}`; bodyLabel.htmlFor = body.id;
        const update = () => {
          section.title = title.value; section.body = body.value; section.auto = false;
          summary.textContent = title.value || `Sezione ${index + 1}`;
          write(); input.dispatchEvent(new Event('input', {bubbles: true}));
        };
        title.addEventListener('input', update); body.addEventListener('input', update);
        box.append(summary, titleLabel, title, bodyLabel, body);
        const fallback = defaults.find(s => s.key === section.key);
        const reset = document.createElement('button'); reset.type = 'button'; reset.className = 'notes-reset-btn';
        reset.textContent = fallback ? 'Ripristina questa sezione' : 'Rimuovi sezione';
        reset.addEventListener('click', () => {
          if (fallback) Object.assign(section, fallback, {auto: true});
          else sections.splice(sections.indexOf(section), 1);
          write(); render();
        });
        box.append(reset); root.append(box);
      });
      const add = document.createElement('button'); add.type = 'button'; add.className = 'notes-reset-btn';
      add.textContent = 'Aggiungi sezione';
      add.addEventListener('click', () => {
        sections.push({key: `custom-${++sequence}`, title: 'Note aggiuntive', body: '', auto: false});
        write(); render();
      });
      root.append(add);
    }
    const defaultSections = generated => parse(generated).map(s => ({...s,
      key: /^Struttura/i.test(s.title) ? 'structure' : /^Fase 1/i.test(s.title) ? 'first-phase'
        : /^Fase 2|^Tabellone/i.test(s.title) ? 'final-phase'
        : /^Premi/i.test(s.title) ? 'awards' : /^Regole/i.test(s.title) ? 'game-rules' : 'calendar', auto: true}));
    function sync(generated, force = false) {
      defaults = defaultSections(generated);
      if (force || !sections.length) sections = defaults.map(s => ({...s}));
      else sections = sections.map(s => s.auto && defaults.some(d => d.key === s.key)
        ? {...defaults.find(d => d.key === s.key)} : s).filter(s => !s.auto || defaults.some(d => d.key === s.key));
      for (const fallback of defaults) if (!sections.some(s => s.key === fallback.key)) sections.push({...fallback});
      write(); render();
    }
    function load(text, saved, generated) {
      defaults = defaultSections(generated);
      sections = Array.isArray(saved) && saved.length ? saved.map(s => ({...s}))
        : parse(text).map((s, index) => ({...s, key: defaults[index]?.key || `custom-${index}`, auto: false}));
      if (!sections.length) sections = defaults.map(s => ({...s}));
      write();
      // Preserve legacy HTML and prose verbatim until the user edits a section.
      if (text.trim()) input.value = text;
      render();
    }
    form.addEventListener('reset', () => setTimeout(() => sync('', true), 0));
    return {sync, load};
  }};
})();
