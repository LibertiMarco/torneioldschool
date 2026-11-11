window.CupBracketRenderer = {async draw(slug, phase) {
  const api = window.TosCupBracket, {info, games} = await api.loadProjection(slug, phase);
  if (!games.length) throw new Error('Nessun tabellone disponibile per ' + phase);
  const rounds = [...new Set(games.map(api.roundKey))];
  const columns = rounds.map(round => {
    const groups = new Map();
    for (const game of games.filter(game => api.roundKey(game) === round)) {
      const key = game.bracket_slot || [game.squadra_casa, game.squadra_ospite].sort().join('|');
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(game);
    }
    return [...groups.values()];
  });
  const canvas = document.createElement('canvas'); canvas.width = 1080; canvas.height = 1920;
  const ctx = canvas.getContext('2d');
  const theme = MatchdayRenderer.tournamentTheme(info);
  const metal = {GOLD: ['#ffe5a0', '#dba632'], SILVER: ['#e9f3ff', '#92a9c5'], BRONZO: ['#ffdbc0', '#bc794b']}[String(phase).toUpperCase()] || ['#e9f3ff', '#92a9c5'];
  const accentInk = {GOLD: '#805b10', SILVER: '#465b73', BRONZO: '#854c28'}[String(phase).toUpperCase()] || '#465b73';
  const urls = [...new Set(games.flatMap(game => [game.logo_casa, game.logo_ospite]).filter(Boolean))];
  const logos = new Map();
  const [brand] = await Promise.all([
    MatchdayRenderer.loadImage('/img/logo_old_school.png'),
    ...urls.map(async url => logos.set(url, await MatchdayRenderer.loadImage(new URL(url, location.origin + '/').href)))
  ]);
  const label = (value, x, y, width, size, color = '#172b40', align = 'left') =>
    MatchdayRenderer.text(ctx, value, x, y, width, size, color, align);
  const box = (x, y, w, h, radius = 18) => {
    ctx.beginPath(); ctx.roundRect(x, y, w, h, radius);
    ctx.fillStyle = '#ffffff'; ctx.fill(); ctx.strokeStyle = metal[1]; ctx.lineWidth = 2; ctx.stroke();
  };
  const background = ctx.createLinearGradient(0, 0, 1080, 1920);
  background.addColorStop(0, '#ffffff'); background.addColorStop(.55, '#f7f8fa'); background.addColorStop(1, '#ffffff');
  ctx.fillStyle = background; ctx.fillRect(0, 0, 1080, 1920);
  ctx.save(); ctx.globalAlpha = .035; ctx.strokeStyle = theme.primary; ctx.lineWidth = 3;
  for (let x = -1920; x < 1080; x += 110) {ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x + 1920, 1920); ctx.stroke();}
  ctx.restore();
  for (const [y, color] of [[96, theme.primary], [119, '#ffffff'], [142, theme.secondary]]) {
    ctx.fillStyle = color; ctx.fillRect(0, y, 1080, 16);
  }
  ctx.fillStyle = '#ffffff'; ctx.beginPath(); ctx.arc(540, 130, 102, 0, Math.PI * 2); ctx.fill();
  if (!MatchdayRenderer.contained(ctx, brand, 455, 45, 170, 170)) label('TOS', 540, 130, 160, 68, '#10263a', 'center');
  label('T O R N E I   O L D   S C H O O L', 540, 267, 960, 24, '#536273', 'center');
  label(String(info.nome || slug).toUpperCase(), 540, 345, 960, 75, '#172b40', 'center');
  const ribbon = ctx.createLinearGradient(260, 0, 820, 0);
  ribbon.addColorStop(0, metal[1]); ribbon.addColorStop(.5, metal[0]); ribbon.addColorStop(1, metal[1]);
  ctx.fillStyle = ribbon; ctx.beginPath(); ctx.roundRect(240, 405, 600, 98, 18); ctx.fill();
  label('COPPA ' + phase, 540, 454, 550, 64, '#102039', 'center');
  label('T A B E L L O N E', 540, 550, 960, 30, '#536273', 'center');

  // Fit the complete bracket inside the same 9:16 frame for every cup and draw size.
  const left = 42, top = 680, areaH = 960, gap = 30;
  const width = (996 - gap * (columns.length - 1)) / columns.length;
  const unit = areaH / columns[0].length;
  const centers = columns.map((nodes, depth) => nodes.map((_, i) => top + (i + .5) * 2 ** depth * unit));
  const connector = (x1, y1, x2, y2) => {
    ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo((x1 + x2) / 2, y1); ctx.lineTo((x1 + x2) / 2, y2); ctx.lineTo(x2, y2);
    ctx.strokeStyle = metal[1]; ctx.lineWidth = 3; ctx.stroke();
  };
  columns.slice(0, -1).forEach((nodes, depth) => nodes.forEach((group, i) => {
    const next = centers[depth + 1][Math.floor(i / 2)];
    if (next != null) connector(left + depth * (width + gap) + width, centers[depth][i], left + (depth + 1) * (width + gap), next);
  }));
  columns.forEach((nodes, depth) => {
    const x = left + depth * (width + gap);
    box(x, 610, width, 48, 10);
    label(String(api.labels[rounds[depth]] || rounds[depth]).toUpperCase(), x + width / 2, 634, width - 16, 27, accentInk, 'center');
    nodes.forEach((group, index) => {
      const height = Math.min(unit - 20, 36 + group.length * 102), y = centers[depth][index] - height / 2;
      // Scale unusually dense draws locally without changing export dimensions.
      ctx.save(); ctx.translate(x, y); ctx.scale(1, height / (36 + group.length * 102));
      box(0, 0, width, 36 + group.length * 102, 14);
      label(group[0].bracket_slot || rounds[depth], 12, 18, width - 24, 19, accentInk);
      group.forEach((game, leg) => {
        const base = 56 + leg * 102, played = Number(game.giocata) === 1;
        ['casa', 'ospite'].forEach((side, teamIndex) => {
          const cy = base + teamIndex * 38;
          if (!MatchdayRenderer.contained(ctx, logos.get(game['logo_' + side]), 10, cy - 16, 32, 32)) {
            ctx.strokeStyle = '#8ba0ba'; ctx.strokeRect(17, cy - 10, 18, 20);
          }
          label(game['squadra_' + side] || 'Da definire', 49, cy, width - 92, 25);
          label(played ? game['gol_' + side] : '–', width - 14, cy, 32, 29, accentInk, 'right');
        });
        if (group.length > 1) label(game.fase_leg || '', 12, base + 65, width - 24, 17, '#536273');
      });
      ctx.restore();
    });
  });
  const finalIndex = rounds.indexOf('FINALE');
  if (finalIndex >= 0) {
    const winner = api.winner(columns[finalIndex][0], 'Vincente finale');
    box(240, 1700, 600, 108);
    const trophy = await MatchdayRenderer.loadImage('/img/tornei/coppa-' + ({GOLD:'gold', SILVER:'silver', BRONZO:'bronzo'}[String(phase).toUpperCase()] || 'gold') + '.svg');
    MatchdayRenderer.contained(ctx, logos.get(winner?.logo) || trophy, 262, 1722, 64, 64);
    label('VINCITRICE', 350, 1728, 460, 20, accentInk);
    label(winner?.name || 'Vincente finale', 350, 1770, 460, 37);
  }
  ctx.fillStyle = theme.primary; ctx.fillRect(0, 1870, 540, 12);
  ctx.fillStyle = theme.secondary; ctx.fillRect(540, 1870, 540, 12);
  label('TORNEIOLDSCHOOL.IT', 540, 1846, 960, 22, '#536273', 'center');
  return canvas;
}};

