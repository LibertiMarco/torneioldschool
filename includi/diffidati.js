function initDiffidati() {
  const marcatori = document.querySelector('.tab-button[data-tab="marcatori"]');
  const marcatoriSection = document.getElementById('marcatori');
  if (!marcatori || !marcatoriSection || document.getElementById('diffidati')) return;
  const section = document.createElement('section');
  section.id = 'diffidati';
  section.className = 'tab-section';
  const title = document.createElement('h2');
  title.textContent = 'Elenco Diffidati';
  const list = document.createElement('div');
  list.id = 'diffidatiList';
  list.className = 'table-wrapper';
  list.setAttribute('aria-live', 'polite');
  section.append(title, list);
  marcatoriSection.after(section);
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'tab-button';
  button.dataset.tab = 'diffidati';
  button.textContent = 'Elenco Diffidati';
  marcatori.after(button);
  button.addEventListener('click', () => {
    document.querySelectorAll('.tab-button').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-section').forEach(el => el.classList.remove('active'));
    button.classList.add('active');
    section.classList.add('active');
    load();
  });
  async function load() {
    list.textContent = 'Caricamento...';
    const slug = window.__TEMPLATE_TORNEO_SLUG__ || decodeURIComponent(location.pathname.split('/').pop()).replace(/\.(php|html?)$/i, '');
    try {
      const response = await fetch(`/api/diffidati.php?torneo=${encodeURIComponent(slug)}`, {cache: 'no-store'});
      if (!response.ok) throw new Error('Caricamento fallito');
      const players = await response.json();
      if (!Array.isArray(players)) throw new Error('Risposta non valida');
      if (!players.length) { list.textContent = 'Nessun giocatore diffidato.'; return; }
      const table = document.createElement('table');
      table.className = 'marcatori-table';
      const header = table.createTHead().insertRow();
      ['Giocatore', 'Squadra', 'Giornate ammonizioni'].forEach(label => {
        const th = document.createElement('th'); th.scope = 'col'; th.textContent = label; header.append(th);
      });
      const body = table.createTBody();
      players.forEach(player => {
        const row = body.insertRow();
        [`${player.cognome || ''} ${player.nome || ''}`.trim(), player.squadra,
          player.giornate.map(day => day == null ? 'Giornata non indicata' : `${day} gio`).join(', ')]
          .forEach(value => { row.insertCell().textContent = value; });
      });
      list.replaceChildren(table);
    } catch (error) { list.textContent = 'Errore caricamento diffidati. Riprova aprendo questa scheda.'; }
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDiffidati);
} else {
  initDiffidati();
}
