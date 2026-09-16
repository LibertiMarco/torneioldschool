/* Shared canvas renderer: the preview and downloaded PNG are the same image. */
window.MatchdayRenderer = (() => {
  const ink = '#08243b', green = '#008447', red = '#e30724';
  const display = 'Impact, "Arial Narrow", sans-serif';
  const imageCache = new Map();
  function loadImage(src) {
    if (!src) return Promise.resolve(null);
    if (!imageCache.has(src)) imageCache.set(src, new Promise(resolve => {
      const img = new Image();
      const timer = setTimeout(() => resolve(null), 10000);
      img.crossOrigin = 'anonymous';
      img.onload = () => { clearTimeout(timer); resolve(img); };
      img.onerror = () => { clearTimeout(timer); resolve(null); };
      img.src = src;
    }));
    return imageCache.get(src);
  }
  function contained(ctx, img, x, y, w, h) {
    if (!img?.naturalWidth || !img?.naturalHeight) return false;
    const scale = Math.min(w / img.naturalWidth, h / img.naturalHeight);
    const iw = img.naturalWidth * scale, ih = img.naturalHeight * scale;
    ctx.drawImage(img, x + (w - iw) / 2, y + (h - ih) / 2, iw, ih);
    return true;
  }
  function text(ctx, value, x, y, maxWidth, size, color = ink, align = 'center', family = display) {
    ctx.fillStyle = color; ctx.textAlign = align; ctx.textBaseline = 'middle';
    ctx.font = `${family === display ? '400' : '700'} ${size}px ${family}`;
    ctx.fillText(String(value), x, y, maxWidth);
  }
  function lines(ctx, value, maxWidth, size, family = display) {
    ctx.font = `400 ${size}px ${family}`;
    const label = String(value).trim(), words = label.split(/\s+/);
    if (ctx.measureText(label).width <= maxWidth || words.length < 2) return [label];
    let result = [label], bestWidth = Infinity;
    for (let i = 1; i < words.length; i++) {
      const pair = [words.slice(0, i).join(' '), words.slice(i).join(' ')];
      const width = Math.max(...pair.map(line => ctx.measureText(line).width));
      if (width < bestWidth) { bestWidth = width; result = pair; }
    }
    return result;
  }
  function crest(ctx, squad, logo, x, cy, size) {
    if (!contained(ctx, logo, x, cy - size / 2, size, size)) {
      ctx.fillStyle = '#e6ecef'; ctx.beginPath(); ctx.arc(x + size / 2, cy, size * .43, 0, Math.PI * 2); ctx.fill();
      const initials = String(squad.nome || '?').split(/\s+/).slice(0, 2).map(s => s[0]).join('').toUpperCase();
      text(ctx, initials, x + size / 2, cy, size * .7, size * .34);
    }
  }
  function team(ctx, squad, logo, x, cy, size, align, nameX, nameWidth, fontSize) {
    crest(ctx, squad, logo, x, cy, size);
    const nameLines = lines(ctx, squad.nome || 'Da definire', nameWidth, fontSize);
    nameLines.forEach((line, i) => text(ctx, line, nameX, cy + (i - (nameLines.length - 1) / 2) * fontSize * 1.12, nameWidth, fontSize, ink, align));
  }
  function dateLabel(value, short = false) {
    if (!value) return 'Data da definire';
    const date = new Date(`${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) return 'Data da definire';
    return new Intl.DateTimeFormat('it-IT', {weekday: short ? 'short' : 'long', day: '2-digit', month: '2-digit'}).format(date).toUpperCase();
  }
  function groupDays(tournament) {
    const days = new Map();
    for (const section of tournament.grafiche?.[0]?.sezioni || []) {
      for (const match of section.partite || []) {
        const key = match.data || '';
        if (!days.has(key)) days.set(key, []);
        days.get(key).push({...match, section: section.nome || ''});
      }
    }
    return [...days].sort(([a], [b]) => (a || '9999').localeCompare(b || '9999')).map(([date, matches]) => ({
      date, matches: matches.sort((a, b) => (a.ora || '99:99').localeCompare(b.ora || '99:99'))
    }));
  }
  function extraInfo(match) {
    const info = [], result = match.risultato, leg = match.risultato_andata;
    if (result?.decisa_rigori && result.rigori_casa != null && result.rigori_ospite != null) info.push(`D.C.R. ${result.rigori_casa}–${result.rigori_ospite}`);
    if (leg) info.push(`ANDATA: ${leg.squadra_casa} ${leg.gol_casa}–${leg.gol_ospite} ${leg.squadra_ospite}`);
    return info.join('  •  ');
  }
  async function drawTournament(tournament, week) {
    const days = groupDays(tournament), matches = days.flatMap(day => day.matches);
    const count = matches.length;
    const width = 1080, headerH = 450, footerH = 130, dayH = 66, dayGap = 20;
    // Grow unusually busy schedules rather than shrinking names into unreadable rows.
    const hasDetails = matches.some(match => match.risultato || match.risultato_andata)
      || days.some(day => new Set(day.matches.map(match => match.section)).size > 1);
    const rowH = count === 1 ? 1100 : Math.max(hasDetails ? 128 : 104, Math.min(380, Math.floor((1920 - headerH - footerH - days.length * (dayH + dayGap)) / Math.max(count, 1))));
    const height = Math.max(1920, headerH + footerH + count * rowH + days.length * (dayH + dayGap));
    const canvas = document.createElement('canvas'); canvas.width = width; canvas.height = height;
    const ctx = canvas.getContext('2d');
    const logoSource = squad => squad.logo_url_assoluto || squad.logo;
    const [brand, logos] = await Promise.all([
      loadImage('/img/logo_old_school.png'),
      Promise.all(matches.map(async match => Promise.all([loadImage(logoSource(match.squadra_casa)), loadImage(logoSource(match.squadra_ospite))])))
    ]);
    ctx.fillStyle = '#fbfcfa'; ctx.fillRect(0, 0, width, height);
    // Very light diagonal paper pattern, kept clear of the match rows.
    ctx.strokeStyle = '#08243b06'; ctx.lineWidth = 1;
    for (let x = -height; x < width; x += 38) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x + height, height); ctx.stroke(); }
    for (const y of [48, 111]) {
      ctx.fillStyle = green; ctx.fillRect(0, y, width / 2, 36);
      ctx.fillStyle = red; ctx.fillRect(width / 2, y, width / 2, 36);
    }
    ctx.fillStyle = '#fbfcfa'; ctx.beginPath(); ctx.arc(540, 116, 112, 0, Math.PI * 2); ctx.fill();
    if (!contained(ctx, brand, 451, 27, 178, 178)) text(ctx, 'TOS', 540, 116, 155, 76);
    text(ctx, 'T O R N E I   O L D   S C H O O L', 540, 244, 920, 24);
    const title = String(tournament.nome || 'MATCHDAY').toUpperCase();
    const titleLines = lines(ctx, title, 980, 112);
    if (titleLines.length === 1) {
      const words = title.split(/\s+/);
      ctx.font = `400 112px ${display}`;
      const natural = ctx.measureText(title).width, scale = Math.min(1, 980 / natural);
      ctx.save(); ctx.translate(540 - natural * scale / 2, 331); ctx.scale(scale, 1);
      let x = 0;
      words.forEach((word, i) => {
        text(ctx, word, x, 0, ctx.measureText(word).width + 1, 112, i === 0 ? green : (i === words.length - 1 && words.length > 2 ? red : ink), 'left');
        x += ctx.measureText(`${word} `).width;
      });
      ctx.restore();
    } else {
      titleLines.forEach((line, i) => text(ctx, line, 540, 289 + i * 72, 980, 76, i === 0 ? green : ink));
    }
    const dates = days.map(day => day.date).filter(Boolean);
    const first = dates[0] || week.dal, last = dates[dates.length - 1] || week.al;
    text(ctx, first === last ? dateLabel(first, true) : `${dateLabel(first, true)}  —  ${dateLabel(last, true)}`, 540, titleLines.length > 1 ? 421 : 413, 940, 29);
    let y = headerH, index = 0;
    for (const [dayIndex, day] of days.entries()) {
      const color = dayIndex % 2 === 0 ? green : red;
      ctx.fillStyle = color; ctx.fillRect(38, y, 1004, dayH);
      text(ctx, dateLabel(day.date), 62, y + dayH / 2, 575, 32, '#fff', 'left');
      const sections = [...new Set(day.matches.map(match => match.section).filter(Boolean))];
      if (sections.length === 1) text(ctx, sections[0].toUpperCase(), 1020, y + dayH / 2, 350, 21, '#fff', 'right');
      y += dayH;
      for (const match of day.matches) {
        const extra = extraInfo(match), mixed = sections.length > 1;
        const details = [mixed ? match.section : '', match.risultato ? (match.ora ? `ORE ${match.ora}` : 'Ora da definire') : '', extra].filter(Boolean).join('  •  ');
        const [homeLogo, awayLogo] = logos[index++];
        ctx.fillStyle = index % 2 ? '#ffffff' : '#f2f5f4'; ctx.fillRect(38, y, 1004, rowH);
        ctx.strokeStyle = '#dce3e3'; ctx.lineWidth = 1.5;
        ctx.strokeRect(38, y, 1004, rowH);
        if (count === 1) {
          const cy = y + 350, result = match.risultato;
          crest(ctx, match.squadra_casa, homeLogo, 120, cy, 260);
          crest(ctx, match.squadra_ospite, awayLogo, 700, cy, 260);
          text(ctx, result ? `${result.gol_casa} – ${result.gol_ospite}` : 'VS', 540, cy, 230, 84, red);
          for (const [squad, x] of [[match.squadra_casa, 250], [match.squadra_ospite, 830]]) {
            lines(ctx, squad.nome, 390, 46).forEach((line, i) => text(ctx, line, x, cy + 205 + i * 54, 390, 46));
          }
          ctx.fillStyle = red; ctx.fillRect(425, y + 710, 230, 66);
          text(ctx, match.ora || 'DA DEFINIRE', 540, y + 743, 210, match.ora ? 48 : 30, '#fff');
          text(ctx, match.campo || 'Luogo da definire', 540, y + 830, 910, 38);
          if (extra) text(ctx, extra, 540, y + 930, 930, 24, '#40586a', 'center', 'Arial, sans-serif');
          y += rowH;
          continue;
        }
        const cy = y + (rowH - (details ? 27 : 0)) / 2;
        const logoSize = Math.min(100, rowH - (details ? 44 : 22));
        const fontSize = rowH < 125 ? 29 : 34;
        for (const x of [401, 679]) { ctx.beginPath(); ctx.moveTo(x, y + 14); ctx.lineTo(x, y + rowH - (details ? 34 : 14)); ctx.stroke(); }
        team(ctx, match.squadra_casa, homeLogo, 55, cy, logoSize, 'left', 174, 213, fontSize);
        team(ctx, match.squadra_ospite, awayLogo, 1025 - logoSize, cy, logoSize, 'right', 906, 213, fontSize);
        const result = match.risultato;
        const label = result ? `${result.gol_casa} – ${result.gol_ospite}` : (match.ora || 'DA DEFINIRE');
        ctx.fillStyle = result ? ink : red; ctx.fillRect(454, cy - 34, 172, 44);
        text(ctx, label, 540, cy - 12, 158, match.ora || result ? 35 : 22, '#fff');
        text(ctx, match.campo || 'Luogo da definire', 540, cy + 30, 253, 23);
        if (details) text(ctx, details, 540, y + rowH - 17, 934, 18, '#40586a', 'center', 'Arial, sans-serif');
        y += rowH;
      }
      y += dayGap;
    }
    text(ctx, 'IL CALCIO, QUELLO VERO.', 540, height - 108, 900, 23);
    ctx.fillStyle = green; ctx.fillRect(0, height - 72, 516, 26);
    ctx.fillStyle = red; ctx.fillRect(564, height - 72, 516, 26);
    text(ctx, 'TORNEIOLDSCHOOL.IT', 540, height - 23, 900, 16, '#40586a', 'center', 'Arial, sans-serif');
    return canvas;
  }
  return {drawTournament};
})();
