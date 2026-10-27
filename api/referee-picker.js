/* Shared with Flutter through the existing staff engine. */
(() => {
  const script = document.currentScript;
  const csrf = script?.dataset.csrf || '';
  const NEW = '__tos_new_referee__';
  const controls = [];
  let names = [];
  function populate(select, value = select.value) {
    select.replaceChildren(new Option('Seleziona arbitro', ''));
    const available = new Set([...names, ...(value && value !== NEW ? [value] : [])]);
    for (const name of available) select.add(new Option(name, name));
    select.add(new Option('+ Aggiungi nuovo arbitro', NEW));
    select.value = value === NEW ? '' : value;
  }
  function manage() {
    const dialog = document.createElement('dialog');
    dialog.setAttribute('aria-label', 'Gestisci arbitri');
    const title = document.createElement('h3'); title.textContent = 'Gestisci arbitri';
    const help = document.createElement('p'); help.textContent = 'Le modifiche valgono per la picklist. Le partite già salvate mantengono il nome originale.';
    const form = document.createElement('form');
    const label = document.createElement('label'); label.textContent = 'Arbitro da modificare';
    const picker = document.createElement('select'); picker.required = true; label.append(picker);
    const nameLabel = document.createElement('label'); nameLabel.textContent = 'Nome e cognome';
    const field = document.createElement('input'); field.maxLength = 150; field.required = true; nameLabel.append(field);
    const error = document.createElement('p'); error.setAttribute('role', 'alert');
    const save = document.createElement('button'); save.type = 'submit'; save.textContent = 'Salva modifica';
    const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Rimuovi dalla picklist';
    const close = document.createElement('button'); close.type = 'button'; close.textContent = 'Chiudi';
    const refresh = () => {
      picker.replaceChildren(new Option('Seleziona arbitro', ''));
      names.forEach(name => picker.add(new Option(name, name)));
      field.value = ''; save.disabled = remove.disabled = names.length === 0;
    };
    picker.onchange = () => { field.value = picker.value; error.textContent = ''; };
    close.onclick = () => { dialog.close(); dialog.remove(); };
    const submit = async action => {
      if (!picker.value) { error.textContent = 'Seleziona un arbitro.'; return; }
      if (action === 'remove' && !window.confirm('Rimuovere ' + picker.value + ' dalla picklist? Le partite già salvate resteranno invariate.')) return;
      save.disabled = remove.disabled = close.disabled = true; error.textContent = '';
      try {
        const body = action === 'rename'
          ? {action, old_name: picker.value, name: field.value, _csrf: csrf}
          : {action, name: picker.value, _csrf: csrf};
        const response = await fetch('/api/referees.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
        const data = await response.json();
        if (!response.ok) throw new Error(data.error?.message || 'Salvataggio non riuscito.');
        names = data.referees;
        controls.forEach(control => populate(control));
        refresh(); error.textContent = action === 'rename' ? 'Arbitro aggiornato.' : 'Arbitro rimosso dalla picklist.';
      } catch (failure) { error.textContent = failure.message; }
      finally { save.disabled = remove.disabled = names.length === 0; close.disabled = false; }
    };
    form.onsubmit = event => { event.preventDefault(); submit('rename'); };
    remove.onclick = () => submit('remove');
    form.append(label, nameLabel, error, save, remove, close);
    dialog.append(title, help, form); document.body.append(dialog); refresh(); dialog.showModal();
  }
  for (const input of document.querySelectorAll('input[name^="arbitro"]')) {
    const select = document.createElement('select');
    for (const attribute of input.attributes) if (!['type','value','placeholder'].includes(attribute.name)) select.setAttribute(attribute.name,attribute.value);
    populate(select,input.value);input.replaceWith(select);controls.push(select);
    const manageButton = document.createElement('button');
    manageButton.type = 'button'; manageButton.textContent = 'Gestisci arbitri';
    manageButton.onclick = manage; select.after(manageButton);
    select.addEventListener('change', () => {
      if (select.value !== NEW) return;
      select.value = '';
      const dialog = document.createElement('dialog');
      dialog.setAttribute('aria-label','Aggiungi arbitro');
      const title = document.createElement('h3');title.textContent='Aggiungi nuovo arbitro';
      const form = document.createElement('form');
      const label = document.createElement('label');label.textContent='Nome e cognome';
      const field = document.createElement('input');field.required=true;field.maxLength=150;label.append(field);
      const error = document.createElement('p');error.setAttribute('role','alert');
      const save = document.createElement('button');save.type='submit';save.textContent='Salva e seleziona';
      const cancel = document.createElement('button');cancel.type='button';cancel.textContent='Annulla';
      cancel.onclick=()=>{dialog.close();dialog.remove()};
      form.append(label,error,save,cancel);dialog.append(title,form);document.body.append(dialog);dialog.showModal();
      form.onsubmit=async event=>{
        event.preventDefault();save.disabled=true;error.textContent='';
        try {
          const response=await fetch('/api/referees.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:field.value,_csrf:csrf})});
          const data=await response.json();if(!response.ok)throw new Error(data.error?.message||'Salvataggio non riuscito.');
          names=data.referees || [...new Set([...names,data.name])];
          for(const control of controls)populate(control,control===select?data.name:control.value);
          select.dispatchEvent(new Event('change',{bubbles:true}));dialog.close();dialog.remove();
        }catch(failure){error.textContent=failure.message}finally{save.disabled=false}
      };
    });
  }
  fetch('/api/referees.php').then(response=>{
    if(!response.ok)throw new Error('Anagrafica arbitri non disponibile.');return response.json();
  }).then(data=>{names=data.referees;controls.forEach(control=>populate(control));}).catch(()=>{
    // Existing values remain editable via the selector, but no success is simulated.
    for(const control of controls){const notice=document.createElement('p');notice.setAttribute('role','alert');notice.textContent='Anagrafica arbitri non disponibile. Ricarica la pagina.';control.after(notice);}
  });
})();
