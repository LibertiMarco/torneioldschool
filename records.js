(() => {
  const root = document.getElementById('records');
  if (!root) return;
  const player = root.dataset.player || '0';
  const el = (tag, text, parent) => { const n = document.createElement(tag); if (text != null) n.textContent = text; if (parent) parent.append(n); return n; };
  const link = (text, url, parent) => { const n = el('a', text, parent); n.href = url; return n; };
  let tournament = '', format = '';
  async function load() {
    root.replaceChildren(el('p', 'Caricamento…'));
    try {
      const response = await fetch('/api/records.php?' + new URLSearchParams({torneo:tournament,formato:format,player_id:player}), {cache:'no-store'});
      const data = await response.json(); if (!response.ok) throw Error(typeof data.error === 'object' ? data.error.message : data.error);
      root.replaceChildren();
      el('p', data.coverage, root);
      const filters = el('div', null, root); filters.className = 'record-filters';
      for (const [title, values, current, change] of [['Torneo',data.tournaments,tournament,v=>tournament=v],['Formato',data.formats,format,v=>format=v]]) {
        const label=el('label',title+' ',filters), select=el('select',null,label);
        const all=el('option',title==='Torneo'?'Storico TOS':'Tutti (separati)',select); all.value='';
        values.forEach(v=>{const o=el('option',v,select);o.value=v;}); select.value=current;
        select.onchange=()=>{change(select.value);load();};
      }
      const groups=Object.entries(data.groups);
      if (!groups.length) {
        if (root.dataset.keepersOnly === '1') {
          const stats=el('dl',null,root);
          for (const label of ['Presenze in porta','Gol subiti','Media gol subiti','Porte inviolate','Percentuale porte inviolate']) { el('dt',label,stats);el('dd','—',stats); }
          el('p','Nessun tabellino con dati portieri completi e verificati. Le statistiche saranno disponibili dopo la verifica dello staff.',root);
        } else el('p','Nessun dato verificato disponibile per questi filtri.',root);
      }
      for (const [name,group] of groups) {
        el('h2',name,root);
        if (Number(player)>0) {
          const k=group.goalkeeper, card=el('section',null,root); card.className='record-card';
          el('h3','Presenze effettive in porta',card);
          el('p',`Presenze: ${k.appearances} · Gol subiti: ${k.goals_conceded} · Media: ${k.average??'—'} · Porte inviolate: ${k.clean_sheets} · Percentuale: ${k.clean_sheet_percentage==null?'—':k.clean_sheet_percentage+'%'}`,card);
          el('p',`Copertura: ${group.verified_keeper_matches} di ${group.eligible_matches} partite ammissibili con dati portieri completi. Zero presenze indica nessuna presenza verificabile; media e percentuale non sono disponibili.`,card);
        }
        if (root.dataset.keepersOnly === '1') continue;
        const grid=el('div',null,root);grid.className='records-grid';
        Object.values(group.records).forEach(record=>{
          const card=el('section',null,grid);card.className='record-card';el('h3',record.label,card);
          el('strong',record.value==null?'Dati insufficienti':String(record.value),card);
          const addHolder=(h,parent)=>{const p=h.match, item=el('li',null,parent);el('span',h.subject+' · ',item);
            link(`${p.squadra_casa} ${p.gol_casa}–${p.gol_ospite} ${p.squadra_ospite}`, '/tornei/partita_eventi.php?'+new URLSearchParams({id:p.id,torneo:p.torneo}),item);
            el('p',`${p.torneo_nome || p.torneo} · ${p.data_partita}`,item);
            if(h.player_id) link('Partite giocatore','/giocatore_partite.php?'+new URLSearchParams({id:h.player_id}),item);
          };
          const list=el('ul',null,card);record.holders.slice(0,3).forEach(h=>addHolder(h,list));
          if(record.holders.length>3){const more=el('details',null,card);el('summary',`Altri ${record.holders.length-3} detentori`,more);const l=el('ul',null,more);record.holders.slice(3).forEach(h=>addHolder(h,l));}
        });
      }
    } catch(e) {
      root.replaceChildren(el('p',typeof e.message === 'string' ? e.message : 'Impossibile caricare le statistiche.'));
      const retry=el('button','Riprova',root);retry.type='button';retry.onclick=load;
    }
  }
  load();
})();
