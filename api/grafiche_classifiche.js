window.StandingsPage = (() => {
  const safe=value=>String(value).normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();
  function init(tournaments) {
    const $=id=>document.getElementById(id),grid=$('grid'),status=$('status'),button=$('generate'),all=$('downloadAll');
    let generated=[];
    button.disabled=!tournaments.length;
    if(!tournaments.length&&!status.textContent)status.textContent='Nessun torneo con squadre disponibile.';
    async function generate(){
      const selected=tournaments.filter(item=>!$('tournament').value||item.codice===$('tournament').value),format=$('format').value;
      button.disabled=true;$('tournament').disabled=true;$('format').disabled=true;all.hidden=true;grid.replaceChildren();generated=[];
      status.textContent='Recupero classifiche e generazione immagini…';
      const errors=[];
      const now=new Date(),date=new Intl.DateTimeFormat('it-IT',{timeZone:'Europe/Rome',day:'2-digit',month:'2-digit',year:'numeric'}).format(now);
      const stamp=date.split('/').reverse().join('-');
      try{
        for(const tournament of selected){
          try{
            const res=await fetch(`/api/leggiClassifica.php?torneo=${encodeURIComponent(tournament.codice)}`,{cache:'no-cache'}),rows=await res.json();
            if(!res.ok||!Array.isArray(rows))throw new Error('Impossibile recuperare la classifica');
            if(!rows.length){errors.push(`${tournament.nome}: nessuna squadra disponibile.`);continue;}
            for(const [group,teams] of StandingsRenderer.groups(rows)){
              const pages=await StandingsRenderer.draw(tournament,teams,format,group,date);
              pages.forEach((canvas,index)=>{
                const item={canvas,name:`classifica-${safe(tournament.codice)}${group?'-girone-'+safe(group):''}-${format}-${stamp}${pages.length>1?'-'+(index+1):''}.png`};
                const card=document.createElement('section');card.className='card';
                const head=document.createElement('div');head.className='card-head';
                const title=document.createElement('h2');title.textContent=tournament.nome+(group?` · Girone ${group}`:'')+(pages.length>1?` · ${index+1}/${pages.length}`:'');
                const save=document.createElement('button');save.type='button';save.textContent=GraphicsDownloads.isIPhoneSafari?'Salva immagine':'Scarica PNG';
                save.onclick=async()=>{try{await GraphicsDownloads.saveImage(item);}catch(error){status.textContent=error.message;}};
                head.append(title,save);card.append(head,canvas);grid.append(card);generated.push(item);
              });
            }
          }catch(error){errors.push(`${tournament.nome}: ${error.message}.`);}
        }
        status.textContent=`Create ${generated.length} immagini.${errors.length?' '+errors.join(' '):''}`;
        all.textContent=GraphicsDownloads.isIPhoneSafari?'Salva tutte':'Scarica tutte';all.hidden=generated.length<2;
      }finally{button.disabled=false;$('tournament').disabled=false;$('format').disabled=false;}
    }
    button.onclick=generate;
    all.onclick=async()=>{try{await GraphicsDownloads.saveAllImages(generated,error=>{status.textContent=error.message;});}catch(error){if(error?.name!=='AbortError')status.textContent=error.message;}};
    return {generate};
  }
  return {init};
})();
