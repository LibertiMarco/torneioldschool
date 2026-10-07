// node tests/graphics_layout_browser_test.js; open http://127.0.0.1:8766.
// Exercises production canvas renderers without database access or saved-data changes.
const fs=require('fs'),path=require('path'),http=require('http');
const root=path.join(__dirname,'..');
const page=fs.readFileSync(path.join(root,'api/copertina_partite.php'),'utf8');
const youtube=page.slice(page.indexOf('function drawYoutube()'),page.indexOf('function draw(){'));
const html=`<!doctype html><meta charset="utf-8"><title>Verifica layout grafiche</title>
<style>body{font-family:Arial;background:#ddd}canvas,img{width:480px;vertical-align:top;margin:10px}#youtubeCanvas{width:640px}</style>
<button id="run">Verifica YouTube e MATCHDAY</button><pre id="result"></pre>
<canvas id="youtubeCanvas" width="1280" height="720"></canvas><div id="exports"></div>
<script src="/renderer.js"></script><script>
const $=id=>document.getElementById(id),selected={torneo_categoria:'CALCIO'},homeLogo=null,awayLogo=null,brand=null;
let photo=null;
const base=(ctx,w,h)=>{ctx.fillStyle='#08243b';ctx.fillRect(0,0,w,h)},coverAccent=()=> '#d5b65a',coverCompetitionTheme=()=>({}),score=()=>2;
const upper=(v,f)=>v||f,fit=()=>{},contain=()=>{},tournamentImageBox=()=>{};
const placeholder=(ctx,x,y,w,h)=>{ctx.fillStyle='#ff00ff';ctx.fillRect(x,y,w,h)},cover=placeholder;
${youtube}
$('run').onclick=async()=>{
try{
const expect=(v,m)=>{if(!v)throw Error(m)};
drawYoutube();const c=$('youtubeCanvas'),ctx=c.getContext('2d');
for(const y of [5,150,600,715]){
const left=ctx.getImageData(689,y,1,1).data,right=ctx.getImageData(690,y,1,1).data;
expect(left[0]===8&&right[0]===255&&right[2]===255,'Separazione non verticale a y='+y);
}
async function exportCheck(canvas){
const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
const decoded=await createImageBitmap(blob);expect(decoded.width===canvas.width&&decoded.height===canvas.height,'Dimensioni PNG');decoded.close();
const img=new Image();img.src=URL.createObjectURL(blob);await img.decode();$('exports').append(img);
}
await exportCheck(c);
const squad={nome:'Squadra',logo:'/logo.png'};
for(const dates of [['2026-10-07'],['2026-10-07','2026-10-09']]){
const calls=[],original=CanvasRenderingContext2D.prototype.fillText;
CanvasRenderingContext2D.prototype.fillText=function(value,...args){calls.push({value,x:args[0],y:args[1]});return original.call(this,value,...args)};
let graphic;
try{graphic=await MatchdayRenderer.drawTournament({nome:'SERIE A',grafiche:[{sezioni:[{nome:'GIORNATA 1',partite:dates.map(data=>({data,ora:'20:00',squadra_casa:squad,squadra_ospite:squad}))}]}]},{dal:dates[0],al:dates.at(-1)});}finally{CanvasRenderingContext2D.prototype.fillText=original;}
const header=calls.find(item=>item.x===540&&item.y===413);
expect(header&&!header.value.includes('?'),'Data con punto interrogativo');
expect(header.value.includes(' - ')===(dates.length>1),'Intervallo date errato');
expect(graphic.width===1080&&graphic.height===1920,'Formato MATCHDAY');
$('exports').append(graphic);await exportCheck(graphic);
}
$('result').textContent='PASS: separazione verticale 1280x720; data singola e intervallo centrati senza ?; PNG decodificati con dimensioni corrette.';
}catch(e){$('result').textContent='FAIL: '+e.message;}
};</script>`;
http.createServer((req,res)=>{
if(req.url==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);}
else if(req.url==='/renderer.js'){res.setHeader('Content-Type','text/javascript');res.end(fs.readFileSync(path.join(root,'api/matchday-renderer.js')));}
else if(req.url==='/logo.png'||req.url==='/img/logo_old_school.png'){res.setHeader('Content-Type','image/png');res.end(fs.readFileSync(path.join(root,'img/logo_old_school.png')));}
else{res.statusCode=404;res.end();}
}).listen(8766,'127.0.0.1',()=>console.log('Layout test: http://127.0.0.1:8766'));
