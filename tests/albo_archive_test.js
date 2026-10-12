const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM}=require('jsdom');
const page=fs.readFileSync(path.join(__dirname,'../albo.php'),'utf8');
const script=page.slice(page.indexOf('    function formatPeriodo'),page.indexOf('</script>',page.indexOf('    function formatPeriodo'))).replace(/<\?=[\s\S]*?\?>/g,'');
(async()=>{
  const rows=[
    {edition_key:'calcio:1',sezione:'calcio',competizione:'Coppa unica',anno:'2025',latest_sort_time:1,premi:[{premio:'Vincente',vincitrice:'Vincente 2025'}]},
    {edition_key:'calcio:2',sezione:'calcio',competizione:'Coppa unica',anno:'2026',latest_sort_time:2,premi:[{premio:'Capocannoniere',vincitrice:'<img src=x onerror="throw 1">',logo_vincitrice:'javascript:alert(1)'}],link_torneo:'javascript:alert(1)'},
  ];
  const dom=new JSDOM('<select id="filterAnno"></select><select id="filterCompetizione"></select><input id="filterSearch"><div id="alboGrid"></div><div id="footer-container"></div>',{url:'https://example.test/albo.php',runScripts:'outside-only'});
  dom.window.fetch=async url=>({json:async()=>({data:rows}),text:async()=>''});
  dom.window.eval(script);
  await new Promise(resolve=>setTimeout(resolve,40));
  const doc=dom.window.document;
  assert.equal(doc.querySelectorAll('#filterCompetizione option').length,2);
  assert(doc.getElementById('alboGrid').textContent.includes('<img src=x onerror="throw 1">'));
  assert(!doc.querySelector('[onerror*="throw"]'));
  assert(!doc.querySelector('a[href^="javascript:"]'));
  assert(!doc.querySelector('img[src^="javascript:"]'));
  const year=doc.getElementById('filterAnno');year.value='2025';year.dispatchEvent(new dom.window.Event('change'));
  assert(doc.getElementById('alboGrid').textContent.includes('Vincente 2025'));
  assert.equal(doc.querySelectorAll('#filterCompetizione option').length,1);
  year.value='';year.dispatchEvent(new dom.window.Event('change'));
  const select=doc.getElementById('filterCompetizione');select.value='calcio:1';select.dispatchEvent(new dom.window.Event('change'));
  assert(doc.getElementById('alboGrid').textContent.includes('Vincente 2025'));
  dom.window.close();console.log('Website archive: edition/year filtering and safe winner, image and link rendering passed.');
})().catch(error=>{console.error(error);process.exit(1)});
