const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM}=require('jsdom');
(async()=>{
 const dom=new JSDOM('<h2>Marcatori e presenze totali</h2><div id="tos-records-table"></div>',{url:'https://example.test/',runScripts:'outside-only'}),queries=[];
 const match={id:5,torneo:'Coppa',torneo_nome:'Coppa Old School',data_partita:'2026-10-01',squadra_casa:'Casa',squadra_ospite:'Ospite',gol_casa:7,gol_ospite:5};
 const records=Object.fromEntries(Array.from({length:7},(_,i)=>[String(i),{label:'Record '+i,value:i===0?12:null,holders:i===0?Array.from({length:4},(_,j)=>({subject:'Detentore '+j,player_id:7,match})):[]}]));
 dom.window.fetch=async url=>{queries.push(url);return {ok:true,json:async()=>({groups:{'Generali':{records}},formats:['Calcio a 5'],tournaments:['Coppa'],coverage:'Calcolati automaticamente'})};};
 dom.window.eval(fs.readFileSync(path.join(__dirname,'../records-table.js'),'utf8'));await new Promise(r=>setTimeout(r,0));
 const doc=dom.window.document;
 assert.equal(doc.querySelectorAll('tbody tr').length,7);
 assert.equal(doc.querySelector('caption').textContent,'Generali');
 assert.equal(doc.querySelector('summary').textContent,'Altri detentori · 3');
 assert(doc.querySelector('a[href*="partita_eventi.php"]'));
 assert(!doc.querySelector('a[href*="record.php"]'));
 assert(queries[0].includes('scope=records'));
 assert.equal(doc.querySelectorAll('select').length,0);
 assert(!queries[0].includes('torneo='));assert(!queries[0].includes('formato='));
 dom.window.close();console.log('Tabella record generali: sette righe, pari merito e nessuna suddivisione per torneo o formato.');
})().catch(error=>{console.error(error);process.exit(1);});
