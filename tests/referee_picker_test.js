const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
(async()=>{
  const dom=new JSDOM('<main><label>Arbitro</label><input id="arbitro_mod" name="arbitro_mod" value="Arbitro Storico"></main>',
    {url:'https://example.test/api/gestione_partite.php',runScripts:'outside-only'});
  const {window}=dom,document=window.document;
  const script=document.createElement('script');script.dataset.csrf='fixture-csrf';
  Object.defineProperty(document,'currentScript',{get:()=>script});
  window.HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','')};
  window.HTMLDialogElement.prototype.close=function(){this.removeAttribute('open')};
  const writes=[];
  window.fetch=async(url,options)=>{
    assert.equal(url,'/api/referees.php');
    if(options){writes.push(JSON.parse(options.body));return{ok:true,json:async()=>({name:'Nuovo Arbitro'})}}
    return{ok:true,json:async()=>({referees:['Vincenzo Amoroso','Arbitro Storico']})};
  };
  window.eval(fs.readFileSync(path.join(__dirname,'../api/referee-picker.js'),'utf8'));
  await new Promise(resolve=>setTimeout(resolve,10));
  const select=document.getElementById('arbitro_mod');
  assert.equal(select.tagName,'SELECT');assert.equal(select.name,'arbitro_mod');
  assert.equal(select.value,'Arbitro Storico','Existing match selection must survive initialization');
  select.value='__tos_new_referee__';select.dispatchEvent(new window.Event('change'));
  assert(document.querySelector('dialog[open]'),'Inline add opens a real form');
  document.querySelector('dialog input').value='Nuovo Arbitro';
  document.querySelector('dialog form').dispatchEvent(new window.Event('submit',{cancelable:true}));
  await new Promise(resolve=>setTimeout(resolve,10));
  assert.deepEqual(writes,[{name:'Nuovo Arbitro',_csrf:'fixture-csrf'}]);
  assert.equal(select.value,'Nuovo Arbitro');assert.equal(document.querySelector('dialog'),null);
  select.value='__tos_new_referee__';select.dispatchEvent(new window.Event('change'));
  const buttons=document.querySelectorAll('dialog button');buttons[1].click();
  assert.equal(writes.length,1,'Cancel never writes');
  window.close();
  console.log('Referee picker: existing selection, shared input identity, protected add, immediate selection and cancel passed.');
})().catch(error=>{console.error(error);process.exit(1)});
