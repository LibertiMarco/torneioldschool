const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert');
const root=path.join(__dirname,'..'),read=name=>fs.readFileSync(path.join(root,'api',name),'utf8');
const tournament={codice:'SerieA',nome:'Serie A'};
const rows=Array.from({length:22},(_,i)=>({nome:i===0?'Squadra con un nome particolarmente lungo':'Squadra '+(i+1),logo:'/img/logo_old_school.png',punti:66-i*3,giocate:22,vinte:22-i,pareggiate:0,perse:i,differenza_reti:44-i*2,girone:''}));
if(process.argv.includes('--serve')){
  let html=read('grafiche_classifiche.php');html=html.slice(html.indexOf('<!doctype html>'));
  html=html.replace(/<select id="tournament">[\s\S]*?<\/select>/,'<select id="tournament"><option value="">Tutti i tornei</option><option value="SerieA">Serie A</option></select>');
  html=html.replace(/window.StandingsPage.init\(<\?=[\s\S]*?\?>\)/,'window.StandingsPage.init('+JSON.stringify([tournament])+')');
  html=html.replace(/<\?[\s\S]*?\?>/g,'');
  html=html.replace('</body>',`<button id="checkPng">Verifica PNG esportati</button><pre id="pngResult"></pre><script>
  document.getElementById('checkPng').onclick=async()=>{
    try{
      const canvases=[...document.querySelectorAll('canvas')];if(!canvases.length)throw Error('Genera prima le grafiche');
      for(const canvas of canvases){
        const blob=await GraphicsDownloads.canvasToBlob(canvas),bitmap=await createImageBitmap(blob);
        if(bitmap.width!==canvas.width||bitmap.height!==canvas.height)throw Error('Dimensioni PNG errate');
        const copy=document.createElement('canvas');copy.width=bitmap.width;copy.height=bitmap.height;
        copy.getContext('2d').drawImage(bitmap,0,0);bitmap.close();
        const a=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data,b=copy.getContext('2d').getImageData(0,0,copy.width,copy.height).data;
        for(let i=0;i<a.length;i++)if(a[i]!==b[i])throw Error('PNG diverso dall’anteprima');
      }
      document.getElementById('pngResult').textContent='PASS: '+canvases.length+' PNG, dimensioni corrette e pixel identici all’anteprima.';
    }catch(error){document.getElementById('pngResult').textContent='FAIL: '+error.message;}
  };</script></body>`);
  require('http').createServer((req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(url.pathname==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);}
    else if(url.pathname==='/api/leggiClassifica.php'){res.setHeader('Content-Type','application/json');res.end(JSON.stringify(rows));}
    else if(/^\/api\/[a-z_-]+\.js$/.test(url.pathname)){res.setHeader('Content-Type','text/javascript');res.end(read(path.basename(url.pathname)));}
    else if(url.pathname==='/img/logo_old_school.png'){res.setHeader('Content-Type','image/png');res.end(fs.readFileSync(path.join(root,'img/logo_old_school.png')));}
    else {res.statusCode=404;res.end();}
  }).listen(8767,'127.0.0.1',()=>console.log('Standings browser fixture: http://127.0.0.1:8767'));
}else{
  const labels=[];
  const ctx=new Proxy({}, {get:(o,key)=>o[key]||(()=>{})});
  const sandbox={window:{},document:{createElement:()=>({getContext:()=>ctx})},MatchdayRenderer:{
    loadImage:async()=>null,contained:()=>false,tournamentTheme:()=>({primary:'#0055b8',secondary:'#00a8e0'}),
    text(context,value,x,y){labels.push({value:String(value),x,y});}
  }};
  vm.createContext(sandbox);vm.runInContext(read('classifiche-renderer.js'),sandbox);
  (async()=>{
    const renderer=sandbox.window.StandingsRenderer;
    const groups=renderer.groups([{nome:'B1',girone:'B'},{nome:'A1',girone:'A'},{nome:'A2',girone:'A'}]);
    assert.equal(groups[0][0],'A');assert.equal(groups[0][1][1].nome,'A2','Ranking order lost');
    for(const [format,height,pages] of [['story',1920,1],['post',1350,2]]){
      labels.length=0;
      const result=await renderer.draw(tournament,rows,format,'','07/10/2026');
      assert.equal(result.length,pages);assert(result.every(c=>c.width===1080&&c.height===height));
      const names=labels.filter(item=>item.value.startsWith('SQUADRA '));
      assert.equal(names.length,22,'Teams missing or repeated');
      assert(names.every(item=>item.y>438&&item.y<height-130),'Rows overlap footer');
      assert(labels.some(item=>item.value==='22'&&item.x===66),'Rank restarts on second page');
    }
    assert.equal((await renderer.draw(tournament,[], 'post')).length,0);
    // Shared downloads must preserve the existing PNG download behavior.
    const downloads=[],downloadSandbox={window:{},navigator:{userAgent:'Desktop'},File:class{},URL:{createObjectURL:()=> 'blob:test',revokeObjectURL(){}},setTimeout:fn=>fn(),document:{body:{appendChild(){}},createElement:()=>({click(){downloads.push(this.download);},remove(){}})}};
    vm.createContext(downloadSandbox);vm.runInContext(read('grafiche_downloads.js'),downloadSandbox);
    const items=[{name:'one.png',canvas:{toBlob:fn=>fn({})}},{name:'two.png',canvas:{toBlob:fn=>fn({})}}];
    await downloadSandbox.window.GraphicsDownloads.saveImage(items[0]);assert.equal(downloads[0],'one.png');
    await downloadSandbox.window.GraphicsDownloads.saveAllImages(items);await new Promise(resolve=>setImmediate(resolve));assert.equal(downloads.length,3);
    new vm.Script(read('grafiche_classifiche.js'));
    console.log('PASS: standings groups, ranking order, story/post dimensions, pagination, complete rows, PNG downloads and JS syntax.');
  })().catch(error=>{console.error(error);process.exitCode=1;});
}
