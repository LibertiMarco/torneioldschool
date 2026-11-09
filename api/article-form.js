/* Keep the original editors and File objects alive until the server confirms saving. */
(() => {
  for (const form of document.querySelectorAll('#formCreaBlog,#formModificaBlog')) {
    let busy=false;
    const notice=document.createElement('div');notice.className='admin-alert';notice.dataset.persistentNotice='true';notice.setAttribute('role','status');notice.hidden=true;form.prepend(notice);
    form.addEventListener('click',event=>{
      if(event.target.closest('button[type=submit]')) {
        const solo=form.querySelector('#solo_media_mod');if(solo)solo.value='0';
      }
    });
    form.addEventListener('submit',async event=>{
      if(event.defaultPrevented) return;
      event.preventDefault();if(busy)return;busy=true;
      const payload=new FormData(form);
      const controls=[...form.querySelectorAll('input,select,textarea,button')].map(control=>[control,control.disabled]);
      const editors=[...form.querySelectorAll('[contenteditable]')].map(editor=>[editor,editor.getAttribute('contenteditable')]);
      controls.forEach(([control])=>control.disabled=true);editors.forEach(([editor])=>editor.setAttribute('contenteditable','false'));
      notice.hidden=false;notice.className='admin-alert';notice.textContent='Salvataggio in corso...';
      try {
        const response=await fetch(form.action || location.href,{method:'POST',body:payload,headers:{'X-TOS-Article-Form':'1','Accept':'application/json'},credentials:'same-origin'});
        if(response.redirected) throw new Error('La sessione è scaduta. Accedi nuovamente: testo e file restano nel modulo.');
        let result;try{result=await response.json();}catch(_){throw new Error(`Il server ha risposto in modo non valido (HTTP ${response.status}).`);}
        if(!response.ok || !result.ok) throw new Error(result.error || `Il server ha rifiutato il salvataggio (HTTP ${response.status}).`);
        if(result.article && typeof postsData!=='undefined') {
          const index=postsData.findIndex(post=>Number(post.id)===Number(result.article.id));
          if(index<0) postsData.push(result.article);else Object.assign(postsData[index],result.article);
          const select=document.getElementById('modSelectArticolo');
          const existingOption=select && [...select.options].find(option=>Number(option.value)===Number(result.article.id));
          if(existingOption)existingOption.textContent=result.article.titolo;
          if(select && ![...select.options].some(option=>Number(option.value)===Number(result.article.id))) {
            const option=document.createElement('option');option.value=String(result.article.id);option.textContent=result.article.titolo;select.append(option);select.disabled=false;
            document.querySelectorAll('#formModificaBlog button[type=submit]').forEach(button=>button.disabled=false);
          }
        }
        notice.className='admin-alert success';notice.textContent=result.message || 'Articolo salvato.';
        if(form.id==='formModificaBlog' && result.article?.media)renderMediaList(result.article.media);
        if(form.id==='formCreaBlog') { form.reset();hydrateSections('create',''); }
        form.querySelectorAll('[type=file]').forEach(input=>{input.value='';});
        form.querySelectorAll('.file-upload-filename').forEach(label=>label.textContent=label.dataset.default || 'Nessun file selezionato');
        const solo=form.querySelector('#solo_media_mod');if(solo)solo.value='0';
      } catch(error) {
        notice.className='admin-alert error';notice.textContent=(error instanceof TypeError ? 'Connessione non disponibile o caricamento interrotto.' : error.message)+' Testo, formattazione e file selezionati sono stati mantenuti. Puoi riprovare.';
      } finally {
        busy=false;controls.forEach(([control,disabled])=>control.disabled=disabled);
        editors.forEach(([editor,value])=>editor.setAttribute('contenteditable',value));
        form.querySelectorAll('button[type=submit]').forEach(button=>button.disabled=false);
      }
    });
    // The media workflow also submits programmatically.
    form.submit=()=>form.requestSubmit();
  }
})();
