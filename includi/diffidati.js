function initDiffidati() {
  const marcatori = document.querySelector('.tab-button[data-tab="marcatori"]');
  const marcatoriSection = document.getElementById('marcatori');
  if (!marcatori || !marcatoriSection || document.getElementById('diffidati')) return;
  const section = document.createElement('section');
  section.id = 'diffidati';
  section.className = 'tab-section';
  const title = document.createElement('h2');
  title.textContent = 'Elenco Diffidati';
  const heading = document.createElement('div');
  heading.className = 'diffidati-heading';
  const description = document.createElement('p');
  description.textContent = 'Giocatori in diffida e giornate delle ammonizioni.';
  const summary = document.createElement('span');
  summary.className = 'diffidati-count';
  summary.hidden = true;
  const headingText = document.createElement('div');
  headingText.append(title, description);
  heading.append(headingText, summary);
  const list = document.createElement('div');
  list.id = 'diffidatiList';
  list.className = 'diffidati-panel';
  list.setAttribute('aria-live', 'polite');
  section.append(heading, list);
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
    summary.hidden = true;
    const message = document.createElement('p');
    message.className = 'diffidati-message';
    message.textContent = 'Caricamento diffidati...';
    list.replaceChildren(message);
    const slug = window.__TEMPLATE_TORNEO_SLUG__ || decodeURIComponent(location.pathname.split('/').pop()).replace(/\.(php|html?)$/i, '');
    try {
      const response = await fetch(`/api/diffidati.php?torneo=${encodeURIComponent(slug)}`, {cache: 'no-store'});
      if (!response.ok) throw new Error('Caricamento fallito');
      const players = await response.json();
      if (!Array.isArray(players)) throw new Error('Risposta non valida');
      summary.textContent = `${players.length} ${players.length === 1 ? 'giocatore' : 'giocatori'}`;
      summary.hidden = false;
      if (!players.length) { message.textContent = 'Nessun giocatore diffidato.'; return; }
      const table = document.createElement('table');
      table.className = 'diffidati-table';
      table.setAttribute('aria-label', 'Giocatori diffidati e giornate delle ammonizioni');
      const header = table.createTHead().insertRow();
      ['Giocatore', 'Squadra', 'Giornate ammonizioni'].forEach(label => {
        const th = document.createElement('th'); th.scope = 'col'; th.textContent = label; header.append(th);
      });
      const body = table.createTBody();
      players.forEach(player => {
        const row = body.insertRow();
        const name = row.insertCell();
        name.className = 'diffidati-player';
        name.textContent = `${player.cognome || ''} ${player.nome || ''}`.trim();
        const team = row.insertCell();
        team.className = 'diffidati-team';
        team.dataset.label = 'Squadra';
        team.textContent = player.squadra;
        const days = row.insertCell();
        days.className = 'diffidati-days';
        days.dataset.label = 'Ammonizioni';
        const badges = document.createElement('div');
        badges.className = 'diffidati-badges';
        player.giornate.forEach(day => {
          const badge = document.createElement('span');
          badge.className = 'diffidati-day';
          badge.textContent = day == null ? 'Giornata non indicata' : `${day} gio`;
          badge.title = day == null ? 'Giornata non indicata' : `Ammonito nella giornata ${day}`;
          badges.append(badge);
        });
        days.append(badges);
      });
      list.replaceChildren(table);
    } catch (error) { message.textContent = 'Errore caricamento diffidati. Riprova aprendo questa scheda.'; }
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDiffidati);
} else {
  initDiffidati();
}
