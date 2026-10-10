/* Reserve competition colors first, then resolve collisions across all active tournaments. */
window.CoverColors = (() => {
  const colors = ['#00bf63', '#1769e0', '#6c39c6', '#e43b35', '#e77722', '#009c9a', '#a51e49', '#82502e', '#bc368c', '#64732b', '#596675', '#b99015'];
  const compact = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\.php$|\.html$/g, '').replace(/[^a-z0-9]/g, '');
  const key = tournament => tournament.id ? `id:${tournament.id}` : `name:${compact(tournament.filetorneo || tournament.nome)}`;
  function hash(value) {
    let result = 2166136261;
    for (const character of String(value)) { result ^= character.charCodeAt(0); result = Math.imul(result, 16777619); }
    return Math.abs(result);
  }
  function preferred(tournament) {
    if ([tournament.nome, tournament.filetorneo].some(name => /^champions(?:league)?2$/.test(compact(name)))) return {color: '#071b57', priority: 0};
    const theme = window.MatchdayRenderer.tournamentTheme({nome: tournament.nome || tournament.filetorneo});
    if (['brasileirao', 'mcleague'].includes(theme.competition)) return {color: theme.primary, priority: 1};
    if (/saudi|arabia/.test(compact(tournament.nome || tournament.filetorneo))) return {color: '#00bf63', priority: 1};
    return {color: colors[hash(tournament.id || tournament.filetorneo || tournament.nome) % colors.length], priority: 2};
  }
  function assign(tournaments) {
    const unique = new Map();
    for (const tournament of tournaments) {
      const id = key(tournament);
      if (id !== 'name:' && !unique.has(id)) unique.set(id, tournament);
    }
    const ordered = [...unique.values()].map(tournament => ({tournament, ...preferred(tournament)}))
      .sort((a, b) => a.priority - b.priority || key(a.tournament).localeCompare(key(b.tournament), 'en', {numeric: true}));
    const used = new Set(), assigned = new Map(), aliases = new Map();
    for (const {tournament, color: preferredColor} of ordered) {
      let color = preferredColor;
      if (used.has(color)) color = colors.find(candidate => !used.has(candidate));
      // Extra tournaments also receive unique colors after the fixed palette is exhausted.
      if (!color) {
        let index = 0;
        do { color = `hsl(${(index++ * 137.508) % 360} 65% 38%)`; } while (used.has(color));
      }
      used.add(color);
      assigned.set(key(tournament), color);
      for (const name of [tournament.nome, tournament.filetorneo]) if (name) aliases.set(compact(name), color);
    }
    return {get: tournament => assigned.get(key(tournament)) || aliases.get(compact(tournament.filetorneo)) || aliases.get(compact(tournament.nome))};
  }
  return {assign};
})();
