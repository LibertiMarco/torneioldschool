(() => {
  const root=document.getElementById('tos-records-table');if(!root)return;
  let tournament='',format='';
  const el=(tag,text,parent)=>{const n=document.createElement(tag);if(text!=null)n.textContent=text;if(parent)parent.append(n);return n;};
  const link=(text,url,parent)=>{const a=el('a',text,parent);a.href=url;return a;};
  async function load(){
    root.replaceChildren(el('p','Calcolo dei record in corso…'));root.firstChild.className='records-state';
    try{
      const response=await fetch('/api/records.php?'+new URLSearchParams({scope:'records',torneo:tournament,formato:format}),{cache:'no-store'});
      const data=await response.json();if(!response.ok)throw Error(data.error?.message||'Impossibile calcolare i record.');
      root.replaceChildren();const filters=el('div',null,root);filters.className='records-filters';
      for(const [title,values,current,change] of [['Torneo',data.tournaments,tournament,v=>tournament=v],['Formato',data.formats,format,v=>format=v]]){
        const label=el('label',title+' ',filters),select=el('select',null,label);const all=el('option',title==='Torneo'?'Storico TOS':'Tutti (separati)',select);all.value='';
        values.forEach(v=>{const o=el('option',v,select);o.value=v;});select.value=current;select.onchange=()=>{change(select.value);load();};
      }
      if(!Object.keys(data.groups).length)el('p','Nessuna partita conclusa con risultato e formato disponibili per questi filtri.',root).className='records-state';
      for(const [name,group] of Object.entries(data.groups)){
        const scroll=el('div',null,root);scroll.className='records-scroll';const table=el('table',null,scroll);el('caption',name,table);
        const head=el('tr',null,el('thead',null,table));for(const title of ['Record','Valore','Detentori e partite']){const th=el('th',title,head);th.scope='col';}
        const body=el('tbody',null,table);
        for(const record of Object.values(group.records)){
          const row=el('tr',null,body);el('td',record.label,row);const value=el('span',record.value==null?'—':String(record.value),el('td',null,row));value.className='records-value'+(record.value==null?' is-empty':'');const cell=el('td',null,row);
          if(!record.holders.length){el('span','Dati insufficienti',cell).className='records-empty';continue;}
          const add=(holder,list)=>{const item=el('li',null,list),p=holder.match;
            const subject=holder.player_id?link(holder.subject,'/giocatore_partite.php?'+new URLSearchParams({id:holder.player_id}),item):el('strong',holder.subject,item);subject.className='records-subject';
            const match=link('','/tornei/partita_eventi.php?'+new URLSearchParams({id:p.id,torneo:p.torneo}),item);match.className='records-match';match.append(document.createTextNode(p.squadra_casa+' '));el('span',`${p.gol_casa}–${p.gol_ospite}`,match).className='records-score';match.append(document.createTextNode(' '+p.squadra_ospite));
            const date=/^\d{4}-\d{2}-\d{2}$/.test(p.data_partita)?p.data_partita.split('-').reverse().join('/'):p.data_partita;
            el('small',`${p.torneo_nome||p.torneo} · ${date}`,item);
          };
          const list=el('ul',null,cell);record.holders.slice(0,3).forEach(h=>add(h,list));
          if(record.holders.length>3){const more=el('details',null,cell);el('summary',`Mostra altri ${record.holders.length-3} pari merito`,more);const rest=el('ul',null,more);record.holders.slice(3).forEach(h=>add(h,rest));}
        }
      }
      const method=el('details',null,root);method.className='records-method';el('summary','Come vengono calcolati',method);el('p',data.coverage,method);
    }catch(error){root.replaceChildren(el('p',error.message));const retry=el('button','Riprova',root);retry.onclick=load;}
  }
  load();
})();
