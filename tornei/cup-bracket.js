/* Shared projection for existing tournament pages and pages generated from templates. */
(() => {
  const rounds = ['TRENTADUESIMI','SEDICESIMI','OTTAVI','QUARTI','SEMIFINALE','FINALE'];
  const labels = {TRENTADUESIMI:'Trentaduesimi di finale',SEDICESIMI:'Sedicesimi di finale',OTTAVI:'Ottavi di finale',QUARTI:'Quarti di finale',SEMIFINALE:'Semifinali',FINALE:'Finale'};
  const prefixes = {TRENTADUESIMI:'TD',SEDICESIMI:'SD',OTTAVI:'O',QUARTI:'Q',SEMIFINALE:'S',FINALE:'F'};
  const key = value => String(value || '').trim().toLowerCase();
  const phaseKey = value => String(value || '').toUpperCase().replace('BRONZE','BRONZO');
  const roundKey = game => {
    const raw = String(game.fase_round || game.giornata || '').toUpperCase();
    return ({1:'FINALE',2:'SEMIFINALE',3:'QUARTI',4:'OTTAVI',5:'SEDICESIMI',6:'TRENTADUESIMI'})[raw] || raw;
  };
  const leg = game => ({1:'ANDATA',2:'RITORNO'})[game.fase_leg] || String(game.fase_leg || '').toUpperCase();
  function seedOrder(size) {
    let order = [1,2];
    while (order.length < size) order = order.flatMap(seed => [seed, order.length * 2 + 1 - seed]);
    return order;
  }
  const side = (name, label, candidates, group='', logo='') => ({name, label, group, logo, candidates: candidates || new Set(name ? [key(name)] : [])});
  const display = value => value ? (value.name ? `${value.label} · ${value.name}` : value.label) : 'Accesso diretto';
  function winner(games, label, regularPositions) {
    if (!games.length || games.some(g => Number(g.giocata) !== 1 || g.gol_casa == null || g.gol_ospite == null)) return null;
    const legs = new Set(games.map(leg));
    if (legs.has('ANDATA') !== legs.has('RITORNO')) return null;
    const totals = new Map(), names = new Map();
    for (const game of games) for (const end of ['casa','ospite']) {
      const name = game[`squadra_${end}`], k = key(name);
      names.set(k,name); totals.set(k,(totals.get(k) || 0) + Number(game[`gol_${end}`]));
    }
    if (totals.size !== 2) return null;
    const sorted = [...totals.keys()].sort((a,b) => totals.get(b)-totals.get(a));
    let win = totals.get(sorted[0]) !== totals.get(sorted[1]) ? sorted[0] : null;
    if (!win) {
      const penalties = games.filter(g => Number(g.decisa_rigori) === 1 && g.rigori_casa != null && g.rigori_ospite != null);
      const last = penalties.at(-1);
      if (last && Number(last.rigori_casa) !== Number(last.rigori_ospite)) win = key(last[Number(last.rigori_casa) > Number(last.rigori_ospite) ? 'squadra_casa' : 'squadra_ospite']);
    }
    if (!win && games.length === 1 && !legs.has('ANDATA') && !legs.has('RITORNO')
      && Number(games[0].decisa_rigori) !== 1) {
      const positions = sorted.map(name => regularPositions?.get(name)
        ?? games[0][key(games[0].squadra_casa) === name ? 'regular_position_casa' : 'regular_position_ospite']);
      if (positions.every(position => Number.isFinite(position) && position > 0) && positions[0] !== positions[1]) {
        win = sorted[positions[0] < positions[1] ? 0 : 1];
      }
    }
    const logoGame=win && games.find(g=>key(g.squadra_casa)===win || key(g.squadra_ospite)===win);
    const logo=logoGame ? logoGame[key(logoGame.squadra_casa)===win ? 'logo_casa' : 'logo_ospite'] || '' : '';
    return win ? side(names.get(win), label,undefined,'',logo) : null;
  }
  function build(seeds, matches, phase) {
    const regularPositions = new Map(seeds.filter(seed => seed.name && seed.regularPosition > 0)
      .map(seed => [key(seed.name), seed.regularPosition]));
    const actual = matches.filter(m => phaseKey(m.fase) === phase).map(game => ({...game,
      regular_position_casa: regularPositions.get(key(game.squadra_casa)),
      regular_position_ospite: regularPositions.get(key(game.squadra_ospite))}));
    let size = 2;
    while (size < seeds.length) size *= 2;
    const configuredSize=size;
    for (const game of actual) {
      const index = rounds.indexOf(roundKey(game));
      if (index >= 0) size = Math.max(size, 2 ** (rounds.length-index));
    }
    if (seeds.length < 2 && !actual.length) return [];
    const participants = Array.from({length:size}, (_,i) => i < seeds.length
      ? side(seeds[i].name || '',seeds[i].label,undefined,seeds[i].group,seeds[i].logo)
      : !seeds.length || size>configuredSize ? side('',`${i+1}ª qualificata`) : null);
    let sides = seedOrder(size).map(seed => participants[seed-1]);
    for(let i=0;i<sides.length;i+=2) {
      const home=sides[i],away=sides[i+1];
      if(!home || !away || !home.group || home.group!==away.group) continue;
      for(let j=0;j<sides.length;j+=2) {
        const otherHome=sides[j],otherAway=sides[j+1];
        if(j===i || !otherHome || !otherAway || otherAway.group===home.group || otherHome.group===away.group) continue;
        [sides[i+1],sides[j+1]]=[otherAway,away];break;
      }
    }
    let roundIndex = rounds.length-Math.log2(size);
    const firstRound=rounds[roundIndex] || `ROUND_${size}`, locked=new Set(), alignedPairs=new Set();
    for (const game of actual.filter(m=>roundKey(m)===firstRound)) {
      const names=[key(game.squadra_casa),key(game.squadra_ospite)], pair=[...names].sort().join('|');
      if(alignedPairs.has(pair)) continue; alignedPairs.add(pair);
      const positions=names.map(name=>sides.findIndex(s=>s && key(s.name)===name));
      if(positions.some(p=>p<0 || locked.has(Math.floor(p/2)))) continue;
      const target=Math.floor(positions[0]/2);
      names.forEach((name,end)=>{
        const from=sides.findIndex(s=>s && key(s.name)===name),to=target*2+end;
        [sides[to],sides[from]]=[sides[from],sides[to]];
      });
      locked.add(target);
    }
    const output = [], handledRounds=new Set();
    while (sides.length >= 2) {
      const round = rounds[roundIndex] || `ROUND_${sides.length}`, groups = new Map(), next = [];
      handledRounds.add(round);
      for (const game of actual.filter(m => roundKey(m) === round)) {
        const pair = [key(game.squadra_casa),key(game.squadra_ospite)].sort().join('|');
        if (!groups.has(pair)) groups.set(pair,[]);
        groups.get(pair).push(game);
      }
      const remaining = [...groups.values()];
      for (let i=0;i<sides.length;i+=2) {
        const home=sides[i], away=sides[i+1], slot=`${prefixes[round] || `R${sides.length}-`}${i/2+1}`;
        const candidates=new Set([...(home?.candidates || []),...(away?.candidates || [])]);
        let selected=-1,best=0;
        remaining.forEach((games,j) => {
          const a=key(games[0].squadra_casa),b=key(games[0].squadra_ospite);
          const exact=home && away && ((home.candidates.has(a) && away.candidates.has(b)) || (home.candidates.has(b) && away.candidates.has(a)));
          const score=exact ? 3 : Number(candidates.has(a))+Number(candidates.has(b));
          if (score>best) { best=score; selected=j; }
        });
        if (selected<0 && remaining.length >= (sides.length-i)/2) selected=0;
        const games=selected>=0 ? remaining.splice(selected,1)[0] : [], label=`Vincente ${slot}`;
        if (games.length) {
          games.sort((a,b)=>Number(leg(a)==='RITORNO')-Number(leg(b)==='RITORNO'));
          output.push(...games.map(g=>({...g,bracket_slot:slot})));
          const possible=new Set([...candidates,...games.flatMap(g=>[key(g.squadra_casa),key(g.squadra_ospite)])]);
          next.push(winner(games,label,regularPositions) || side('',label,possible));
        } else {
          const bye=!home || !away;
          output.push({fase:phase,fase_round:round,bracket_slot:slot,template:true,bye,
            squadra_casa:display(home),squadra_ospite:display(away),logo_casa:home?.logo || '',logo_ospite:away?.logo || ''});
          next.push(bye ? home || away : side('',label,candidates));
        }
      }
      for (const games of remaining) output.push(...games);
      sides=next; roundIndex++;
    }
    output.push(...actual.filter(m=>!handledRounds.has(roundKey(m))));
    return output;
  }
  function selectSeeds(tables, ranges, phase, directCount=0) {
    const result=[];
    const range=ranges.find(r=>phaseKey(r.key)===phase);
    const start=directCount ? 1 : range?.start || 1, count=directCount || range?.spots || (range ? range.end-range.start+1 : 0);
    for (let rank=0;rank<count;rank++) for (const table of tables) {
      const position=start+rank, team=table.teams[position-1];
      result.push({regularPosition:directCount ? null : position,name:team?.nome || '',logo:team?.logo || '',group:tables.length>1 ? table.label : '',label:tables.length>1 ? `${position}ª girone ${table.label}` : `${position}ª in classifica`});
    }
    return result;
  }
  function legacySetup(teams, regular, info, order) {
    const layout=info.mobile_layout || {}, config={...info.config,...layout.config_overrides};
    const format=String(config.formato || config.formula_torneo || '').toLowerCase();
    const total=Number(config.totale_squadre || config.campionato_squadre || teams.length || layout.default_team_count || 18);
    const normalize=value=>String(value || '').toUpperCase().replace(/^(GIRONE|GRUPPO)\s+/,'').trim();
    let labels=[];
    if (format==='girone') {
      labels=Number(config.numero_gironi)>1
        ? Array.from({length:Number(config.numero_gironi)},(_,i)=> {
            let label=''; do { label=String.fromCharCode(65+i%26)+label; i=Math.floor(i/26)-1; } while(i>=0); return label;
          })
        : [...new Set(teams.map(t=>normalize(t.girone)).filter(Boolean))].sort();
    }
    const grouped=labels.length>1;
    let tables=[{label:'',teams:order(teams,regular)}];
    const perGroup=Number(config.squadre_per_girone) || Math.ceil(total/(labels.length || 1));
    if (grouped) {
      const groups=new Map(labels.map(label=>[label,[]])), leftovers=[];
      for (const team of [...teams].sort((a,b)=>Number(a.id)-Number(b.id))) {
        const group=groups.get(normalize(team.girone)); if(group) group.push(team); else leftovers.push(team);
      }
      for(const group of groups.values()) while(group.length<perGroup && leftovers.length) group.push(leftovers.shift());
      while(leftovers.length) [...groups.values()].sort((a,b)=>a.length-b.length)[0].push(leftovers.shift());
      tables=[...groups].map(([label,rows])=>({label,teams:order(rows,regular)}));
    }
    const gold=Number(grouped ? config.qualificati_gold ?? layout.legacy_group_gold ?? 16 : layout.legacy_gold ?? 16);
    const silver=Number(grouped ? config.qualificati_silver ?? layout.legacy_silver ?? Math.max(0,teams.length-gold) : layout.legacy_silver ?? Math.max(0,teams.length-gold));
    const counts=grouped ? [layout.gold_per_group_override ?? Math.floor(gold/labels.length),Math.floor(silver/labels.length),Math.floor(Number(config.qualificati_bronzo || 0)/labels.length)] : [gold,silver,0];
    let start=1;
    const ranges=['GOLD','SILVER','BRONZO'].map((key,i)=>{
      const range={key,start:!grouped && key==='SILVER' && layout.legacy_silver_at_end ? teams.length-silver+1 : start,spots:counts[i]}; start+=counts[i]; return range;
    });
    return {tables,ranges};
  }
  function standingsOrder(rows,matches) {
    const ordered=[];
    for(const points of [...new Set(rows.map(r=>Number(r.punti)||0))].sort((a,b)=>b-a)) {
      const tied=rows.filter(r=>(Number(r.punti)||0)===points);
      if(tied.length===2) {
        let a=0,b=0;
        for(const game of matches.filter(m=>Number(m.giocata)===1 && (!m.fase || phaseKey(m.fase)==='REGULAR'))) {
          const home=game.squadra_casa,away=game.squadra_ospite;
          if(!((home===tied[0].nome && away===tied[1].nome)||(home===tied[1].nome && away===tied[0].nome)) || game.gol_casa==null || game.gol_ospite==null)continue;
          if(Number(game.gol_casa)===Number(game.gol_ospite)){a++;b++;}
          else if((Number(game.gol_casa)>Number(game.gol_ospite))===(home===tied[0].nome))a+=3;else b+=3;
        }
        if(a!==b){ordered.push(...(a>b?tied:tied.reverse()));continue;}
      }
      ordered.push(...tied.sort((a,b)=>(Number(b.differenza_reti)||0)-(Number(a.differenza_reti)||0) || (Number(b.gol_fatti)||0)-(Number(a.gol_fatti)||0) || String(a.nome).localeCompare(String(b.nome),'it',{sensitivity:'base'})));
    }
    return ordered;
  }
  async function loadProjection(slug,phase) {
    const [info,rows,all,mapping]=await Promise.all([json(`/api/get_torneo_by_slug.php?slug=${encodeURIComponent(slug)}`),json(`/api/leggiClassifica.php?torneo=${encodeURIComponent(slug)}`),json(`/api/get_partite.php?torneo=${encodeURIComponent(slug)}`),json(`/api/get_squadre_torneo.php?torneo=${encodeURIComponent(slug)}`)]);
    const matches=Array.isArray(all)?all.flat():Object.values(all).flat();
    const teams=rows.map(team=>({...team,girone:mapping.find(r=>String(r.id)===String(team.id))?.girone || team.girone}));
    const layout=info.mobile_layout || {},config={...info.config,...layout.config_overrides};
    const modern=layout.group_mode==='template',format=String(config.formato || config.formula_torneo || '').toLowerCase();
    const setup=legacySetup(teams,matches,info,standingsOrder);
    const total=Number(config.totale_squadre || config.campionato_squadre || layout.default_team_count || teams.length || 18);
    if(modern || Object.keys(layout.cup_ranges || {}).length) {
      const fixed=layout.cup_ranges || {};
      const gold=Math.min(total,Math.max(0,Number(fixed.GOLD ?? config.qualificati_gold ?? layout.default_gold ?? 16)));
      const silver=Math.min(total-gold,Math.max(0,Number(fixed.SILVER ?? config.qualificati_silver ?? layout.default_silver ?? 2)));
      const bronze=Math.min(total-gold-silver,Math.max(0,Number(fixed.BRONZO ?? config.qualificati_bronzo ?? layout.default_bronze ?? 0)));
      let start=1;setup.ranges=['GOLD','SILVER','BRONZO'].map((key,i)=>{const spots=Math.floor([gold,silver,bronze][i]/setup.tables.length);const range={key,start,spots};start+=spots;return range;});
    }
    return {info,games:build(selectSeeds(setup.tables,setup.ranges,phase,format==='eliminazione'?total:0),matches,phase)};
  }
  const api={build,seedOrder,selectSeeds,roundKey,labels,loadProjection,winner};
  if (typeof module !== 'undefined') module.exports=api;
  if (typeof window === 'undefined') return;
  window.TosCupBracket=api;

  let visit=0, stopLayout=()=>{};
  const flatten=data=>Array.isArray(data) ? data.flat() : Object.values(data || {}).flat();
  async function json(url) {
    const response=await fetch(url); if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const value=await response.json(); if (value.error) throw new Error(value.error); return value;
  }
  async function render(cup) {
    const container=document.getElementById('playoffContainer');
    if (!container) return;
    const current=++visit, phase=phaseKey(cup || 'GOLD');
    const slug=typeof TORNEO !== 'undefined' ? TORNEO : decodeURIComponent(location.pathname.split('/').pop()).replace(/\.(php|html?)$/i,'');
    stopLayout(); container.replaceChildren();
    const message=document.createElement('p'); message.textContent='Caricamento tabellone…'; container.append(message);
    try {
      const [raw,all,teamRows,info]=await Promise.all([
        json(`/api/leggiClassifica.php?torneo=${encodeURIComponent(slug)}`),
        json(`/api/get_partite.php?torneo=${encodeURIComponent(slug)}`),
        json(`/api/get_squadre_torneo.php?torneo=${encodeURIComponent(slug)}`),
        json(`/api/get_torneo_by_slug.php?slug=${encodeURIComponent(slug)}`)
      ]);
      if (current!==visit) return;
      const matches=flatten(all), regular=matches.filter(m=>Number(m.giocata)===1 && (!m.fase || phaseKey(m.fase)==='REGULAR'));
      const teams=typeof mergeGironiIntoClassifica==='function' ? mergeGironiIntoClassifica(raw,teamRows) : raw.map(t=>({...t,girone:teamRows.find(r=>String(r.id)===String(t.id))?.girone || t.girone}));
      const order=typeof orderClassificaRows==='function' ? orderClassificaRows : rows=>rows;
      const fallback=legacySetup(teams,regular,info,order);
      let tables=fallback.tables, ranges=fallback.ranges;
      const grouped=typeof USE_GROUP_TABLES!=='undefined' && USE_GROUP_TABLES;
      if (grouped && typeof getGroupedClassifica==='function') tables=getGroupedClassifica(teams,regular);
      else if (typeof CONFIG!=='undefined') tables=[{label:'',teams:order(teams,regular)}];
      if (typeof CUP_RANGES!=='undefined') {
        let start=1;
        ranges=CUP_RANGES.map(r=>{
          const spots=Math.floor((r.end-r.start+1)/tables.length);
          const range={...r,start,spots}; start+=spots; return range;
        });
      }
      else if (grouped && typeof GROUP_CUP_RANGES!=='undefined') ranges=GROUP_CUP_RANGES;
      else if (typeof SINGLE_CUP_RANGES!=='undefined') ranges=SINGLE_CUP_RANGES;
      else if (typeof GOLD_SPOTS!=='undefined' && typeof SILVER_SPOTS!=='undefined') {
        const gold=grouped && typeof GOLD_PER_GROUP!=='undefined' ? GOLD_PER_GROUP : GOLD_SPOTS;
        const silver=grouped && typeof SILVER_PER_GROUP!=='undefined' ? SILVER_PER_GROUP : SILVER_SPOTS;
        ranges=[{key:'GOLD',start:1,spots:gold},{key:'SILVER',start:gold+1,spots:silver}];
      }
      const direct=typeof FORMATO_TORNEO!=='undefined' && FORMATO_TORNEO==='eliminazione';
      const seeds=selectSeeds(tables,ranges,phase,direct ? (typeof TEAM_COUNT!=='undefined' ? TEAM_COUNT : teams.length) : 0);
      paint(container,build(seeds,matches,phase),phase,slug);
    } catch(error) {
      if (current!==visit) return;
      container.replaceChildren(); message.textContent='Impossibile caricare il tabellone. Riprova selezionando la coppa.'; container.append(message);
      console.error('Tabellone coppa:',error);
    }
  }
  function element(tag,className,text) {
    const node=document.createElement(tag); node.className=className; if (text!=null) node.textContent=text; return node;
  }
  function paint(container,games,phase,slug) {
    container.replaceChildren(); container.classList.add('cup-projection');
    container.dataset.cup=phase;
    container.append(element('h3','',`Coppa ${phase==='BRONZO' ? 'Bronzo' : phase==='SILVER' ? 'Silver' : 'Gold'}`));
    container.append(element('p','cup-note','Gli incontri da creare sono indicativi e seguono la classifica attuale. Gli incroci restano fissi fino alla finale.'));
    if (!games.length) { container.append(element('p','','Nessun posto previsto per questa coppa.')); return; }
    const nav=element('div','cup-rounds'); nav.setAttribute('aria-label','Turni della coppa');
    const hint=element('p','cup-scroll-hint','Scorri in orizzontale per seguire il tabellone.');
    const scroll=element('div','cup-scroll'); scroll.tabIndex=0;scroll.setAttribute('role','region');scroll.setAttribute('aria-label','Tabellone a eliminazione diretta, scorribile in orizzontale');
    const columns=element('div','cup-columns');
    scroll.append(columns);container.append(nav,hint,scroll);
    [...new Set(games.map(roundKey))].forEach((round,index)=>{
      const roundLabel=labels[round] || (round.startsWith('ROUND_') ? `Turno a ${round.slice(6)} squadre` : round);
      const button=element('button','pill-btn pill-btn--toggle',roundLabel); button.type='button';
      const column=element('section','cup-round');
      column.dataset.round=round;
      column.append(element('h4','',roundLabel));
      button.onclick=()=>{
        scroll.scrollTo({left:column.offsetLeft,behavior:'smooth'});
        for (const child of nav.children) { child.classList.toggle('active',child===button);child.setAttribute('aria-pressed',String(child===button)); }
      };
      nav.append(button); columns.append(column);
      button.classList.toggle('active',index===0);button.setAttribute('aria-pressed',String(index===0));
      const nodes=new Map();
      for (const game of games.filter(g=>roundKey(g)===round)) {
        const played=Number(game.giocata)===1, template=game.template;
        const card=element('article',`cup-game${template ? ' is-template' : ''}`);
        const head=element('div','cup-game-head');
        head.append(element('strong','',round==='FINALE' ? '' : game.bracket_slot || ''),element('span','cup-status',template ? game.bye ? 'Accesso diretto' : 'Da creare' : played ? 'Terminata' : 'In programma'));
        card.append(head);
        for (const end of ['casa','ospite']) {
          const row=element('div','cup-team');
          const team=element('span','cup-team-name');
          if (game[`logo_${end}`]) {
            const url=new URL(game[`logo_${end}`],location.origin+'/');
            if (['http:','https:'].includes(url.protocol)) {
              const logo=element('img','cup-logo'); logo.src=url.href; logo.alt=''; logo.loading='lazy'; logo.width=32;logo.height=32;logo.onerror=()=>logo.remove();team.append(logo);
            }
          }
          team.append(element('span','cup-name-text',game[`squadra_${end}`] || 'Da definire'));
          row.append(team,element('strong','',played ? String(game[`gol_${end}`] ?? '–') : '–'));
          card.append(row);
        }
        if (played && Number(game.decisa_rigori)===1) card.append(element('p','cup-meta',`Rigori ${game.rigori_casa}–${game.rigori_ospite}`));
        if (!template) {
          const date=game.data_partita && !String(game.data_partita).startsWith('0000') ? `${game.data_partita}${game.ora_partita ? ' · '+game.ora_partita.slice(0,5) : ''}` : 'Data da definire';
          card.append(element('p','cup-meta',`${date} · ${game.campo || 'Campo da definire'}${leg(game) ? ' · '+leg(game) : ''}`));
          if (game.id && played && Number(game.ha_statistiche_giocatori)===1) {
            const link=element('a','cup-details','Statistiche partita'); link.href=`/tornei/partita_eventi.php?id=${encodeURIComponent(game.id)}&torneo=${encodeURIComponent(slug)}`; card.append(link);
          }
        }
        const slot=game.bracket_slot || [key(game.squadra_casa),key(game.squadra_ospite)].sort().join('|');
        if(!nodes.has(slot)) {
          const node=element('div','cup-node');node.dataset.slot=game.bracket_slot || '';nodes.set(slot,node);column.append(node);
        }
        nodes.get(slot).append(card);
      }
    });
    const champion=element('aside','cup-winner');champion.setAttribute('aria-label','Vincitore della coppa');
    const finalGames=games.filter(g=>roundKey(g)==='FINALE' && !g.template);
    const championSide=winner(finalGames,'Vincitore');
    const championLogo=element('img','cup-champion-logo');championLogo.src=championSide?.logo || '/img/tornei/pallone.png';championLogo.alt='Scudetto squadra vincitrice';championLogo.width=64;championLogo.height=64;
    champion.append(championLogo,element('strong','',championSide?.name || 'Vincente finale'));
    columns.append(champion);
    const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.classList.add('cup-lines');svg.setAttribute('aria-hidden','true');columns.prepend(svg);
    let frame=0;
    const layout=()=>{
      frame=0;
      if(!columns.isConnected) return;
      const cols=[...columns.querySelectorAll('.cup-round')], width=260,gap=56,heading=40;
      let unit=140;
      cols.forEach((col,depth)=>{
        col.style.left=`${depth*(width+gap)}px`;
        for(const node of col.querySelectorAll('.cup-node')) unit=Math.max(unit,(node.offsetHeight+24)/(2**depth));
      });
      const firstCount=cols[0].querySelectorAll('.cup-node').length;
      const height=heading+firstCount*unit+16,fullWidth=cols.length*(width+gap)+220;
      columns.style.width=`${fullWidth}px`;columns.style.height=`${height}px`;
      cols.forEach((col,depth)=>{
        col.style.height=`${height}px`;
        [...col.querySelectorAll('.cup-node')].forEach((node,index)=>{
          const center=heading+(index+.5)*(2**depth)*unit;
          node.dataset.center=String(center);node.style.top=`${center-node.offsetHeight/2}px`;
        });
      });
      svg.setAttribute('width',fullWidth);svg.setAttribute('height',height);svg.replaceChildren();
      const line=(x1,y1,x2,y2)=>{
        const path=document.createElementNS(svg.namespaceURI,'path');const middle=(x1+x2)/2;
        path.setAttribute('d',`M ${x1} ${y1} H ${middle} V ${y2} H ${x2}`);svg.append(path);
      };
      cols.slice(0,-1).forEach((col,depth)=>{
        const parents=[...cols[depth+1].querySelectorAll('.cup-node')];
        [...col.querySelectorAll('.cup-node')].forEach((node,index)=>{
          const parent=parents[Math.floor(index/2)];
          if(parent && node.dataset.slot && parent.dataset.slot) line(depth*(width+gap)+width,Number(node.dataset.center),(depth+1)*(width+gap),Number(parent.dataset.center));
        });
      });
      const finalColumn=cols.find(col=>col.dataset.round==='FINALE'),finalNode=finalColumn?.querySelector('.cup-node');
      champion.style.left=`${cols.length*(width+gap)}px`;
      const finalCenter=Number(finalNode?.dataset.center || height/2);
      champion.style.top=`${finalCenter-champion.offsetHeight/2}px`;
      if(finalNode) line(finalColumn.offsetLeft+width,finalCenter,champion.offsetLeft,finalCenter);
    };
    const queue=()=>{ if(!frame) frame=requestAnimationFrame(layout); };
    const observer=typeof ResizeObserver==='function' ? new ResizeObserver(queue) : null;
    columns.querySelectorAll('.cup-node,.cup-winner').forEach(node=>observer?.observe(node));
    stopLayout=()=>{ observer?.disconnect();if(frame)cancelAnimationFrame(frame); };
    scroll.addEventListener('scroll',()=>{
      const selected=Math.min(nav.children.length-1,Math.round(scroll.scrollLeft/316));
      [...nav.children].forEach((button,index)=>{button.classList.toggle('active',index===selected);button.setAttribute('aria-pressed',String(index===selected));});
    },{passive:true});
    queue();
  }
  const style=element('style','');
  style.textContent='.cup-projection{color:var(--text-color,#15293e)}.cup-note{font-size:.9rem;line-height:1.5;opacity:.8}.cup-rounds{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.cup-round{max-width:760px}.cup-round[hidden]{display:none}.cup-game{border:1px solid #a8b7c7;border-radius:16px;padding:16px;margin:12px 0;background:var(--card-bg,#fff)}.cup-game.is-template{border-style:dashed;background:var(--card-bg,#f5f7fa)}.cup-game-head,.cup-team{display:flex;justify-content:space-between;gap:16px;align-items:center}.cup-game-head{font-size:.8rem;margin-bottom:12px}.cup-status{border:1px solid #a8b7c7;border-radius:20px;padding:3px 9px}.cup-team{font-weight:650;margin:10px 0;overflow-wrap:anywhere}.cup-team>span{min-width:0}.cup-meta,.cup-details{font-size:.8rem;margin:8px 0 0}.cup-rounds button{cursor:pointer}.cup-rounds button.active{background:#15293e;color:white}';
  document.head.append(style);
  style.textContent+='.cup-team-name{display:flex;align-items:center;gap:8px}.cup-logo{object-fit:contain;flex-shrink:0}.cup-details{color:#1f3d5a;font-weight:650}';
  style.textContent+=`
    #playoffContainer.cup-projection{overflow:visible;min-width:0}
    .cup-scroll-hint{font-size:12px;color:#52677d;margin:0 0 12px}
    .cup-scroll{width:100%;overflow:auto;padding:4px 0 14px;overscroll-behavior-x:contain;scrollbar-width:thin;scrollbar-color:#8ba1b6 #edf1f6}
    .cup-columns{position:relative;isolation:isolate;min-height:160px}
    .cup-round{position:absolute;top:0;width:260px;max-width:none}
    .cup-round h4{font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:#52677d}
    .cup-node{position:absolute;left:0;width:260px;z-index:1;border:1px solid #c1ccda;border-radius:12px;background:#fff;box-shadow:0 4px 12px #15293e09;overflow:hidden}
    .cup-game{margin:0;border:0;border-radius:0;padding:10px 12px;box-shadow:none;background:#fff}
    .cup-game+.cup-game{border-top:1px solid #c1ccda}
    .cup-game.is-template{background:#f5f7fa}
    .cup-game-head{font-size:10px;margin-bottom:6px;color:#52677d}
    .cup-status{padding:2px 6px;font-size:10px}
    .cup-team{font-size:12px;line-height:1.35;margin:7px 0;gap:8px}
    .cup-team-name{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
    .cup-team-name .cup-logo{display:none}
    .cup-meta,.cup-details{font-size:10px;line-height:1.4;margin:5px 0 0}
    .cup-lines{position:absolute;top:0;left:0;pointer-events:none;z-index:0;overflow:visible}
    .cup-lines path{fill:none;stroke:#8ba1b6;stroke-width:1.5;stroke-linejoin:round}
    .cup-winner{position:absolute;width:220px;display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center;padding:20px;border:1px solid #dac589;border-radius:16px;background:#fffbef;font-size:14px;z-index:1}
    .cup-trophy{font-size:46px;line-height:1}
  `;
  style.textContent+=`
    .cup-projection{--cup-fill:#fff6d5;--cup-edge:#c69b3a;--cup-ink:#684900}
    .cup-projection[data-cup=SILVER]{--cup-fill:#edf0f4;--cup-edge:#9ca6b2;--cup-ink:#3c4653}
    .cup-projection[data-cup=BRONZO]{--cup-fill:#f5e1d2;--cup-edge:#b37a50;--cup-ink:#72401e}
    .cup-node,.cup-winner{border-color:var(--cup-edge);background:var(--cup-fill)}
    .cup-game,.cup-game.is-template{background:var(--cup-fill)}
    .cup-team-name{display:flex;align-items:center;gap:7px;flex:1}
    .cup-name-text{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;min-width:0}
    .cup-team-name .cup-logo{display:block;width:24px;height:24px;object-fit:contain}
    .cup-champion-logo{object-fit:contain}
    .cup-rounds{padding:5px;background:#eef2f7;border:1px solid #d8e0ea;border-radius:14px;width:max-content;max-width:100%}
    .cup-rounds button{background:transparent;border:0;border-radius:10px;color:#526176;padding:10px 16px;box-shadow:none;font-size:12px;font-weight:700}
    .cup-rounds button.active{background:var(--cup-fill);color:var(--cup-ink);box-shadow:0 1px 5px #15293e22;outline:1px solid var(--cup-edge)}
    .cup-rounds button:focus-visible{outline:2px solid #15293e;outline-offset:2px}
  `;
  // Deferred script runs after the page script, before its DOMContentLoaded listeners.
  window.caricaPlayoff=render;
})();
