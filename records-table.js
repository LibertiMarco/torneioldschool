(() => {
  const root=document.getElementById('tos-records-table');if(!root)return;
  const el=(tag,text,parent)=>{const n=document.createElement(tag);if(text!=null)n.textContent=text;if(parent)parent.append(n);return n;};
  const link=(text,url,parent)=>{const a=el('a',text,parent);a.href=url;return a;};
  async function load(){
    root.replaceChildren(el('p','Calcolo dei record in corso…'));root.firstChild.className='records-state';
    try{
      const response=await fetch('/api/records.php?scope=records',{cache:'no-store'});
      const data=await response.json();if(!response.ok)throw Error(data.error?.message||'Impossibile calcolare i record.');
      root.replaceChildren();
      if(!Object.keys(data.groups).length)el('p','Nessuna partita conclusa con risultato disponibile.',root).className='records-state';
      for(const [name,group] of Object.entries(data.groups)){
        const table=el('table',null,root);el('caption',name,table);const body=el('tbody',null,table);
        for(const [key,record] of Object.entries(group.records)){
          const cell=el('td',null,el('tr',null,body));el('h3',record.label,cell).className='records-label';
          if(!record.holders.length){el('p','Dati insufficienti',cell).className='records-empty';continue;}
          const add=(holder,list)=>{
            const item=el('li',null,list),p=holder.match;
            if(holder.player_id){
              const player=el('div',null,item);player.className='records-player';
              link(holder.subject,'/giocatore_partite.php?'+new URLSearchParams({id:holder.player_id}),player);
              el('strong',`${record.value} gol`,player).className='records-player-goals';
            }
            const match=link('','/tornei/partita_eventi.php?'+new URLSearchParams({id:p.id,torneo:p.torneo}),item);match.className='records-match'+(holder.player_id?' records-match--secondary':'');
            el('span',p.squadra_casa,match);el('strong',`${p.gol_casa}–${p.gol_ospite}`,match);el('span',p.squadra_ospite,match);
            const date=/^\d{4}-\d{2}-\d{2}$/.test(p.data_partita)?p.data_partita.split('-').reverse().join('/'):p.data_partita;
            match.title=`${p.torneo_nome||p.torneo} · ${date}`;
            if(key==='scorers')el('small',`${record.value} marcatori diversi`,item).className='records-count';
          };
          const list=el('ul',null,cell);add(record.holders[0],list);
          if(record.holders.length>1){const more=el('details',null,cell);el('summary',`Altri detentori · ${record.holders.length-1}`,more);const rest=el('ul',null,more);record.holders.slice(1).forEach(h=>add(h,rest));}
        }
      }
      const method=el('details',null,root);method.className='records-method';el('summary','Come vengono calcolati',method);el('p',data.coverage,method);
    }catch(error){root.replaceChildren(el('p',error.message));const retry=el('button','Riprova',root);retry.onclick=load;}
  }
  load();
})();