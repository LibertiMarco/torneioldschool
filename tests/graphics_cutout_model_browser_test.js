// Real model smoke test. Run with node; requires Chrome and network for ORT.
const fs = require('fs');
const path = require('path');
const http = require('http');
const os = require('os');
const {spawn} = require('child_process');
const root = path.resolve(__dirname, '..');
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cutout-model-'));
const testImage = process.env.CUTOUT_TEST_IMAGE;
const subjects = process.env.CUTOUT_TEST_SUBJECTS === 'all' ? 'all' : 'main';
const sources = testImage ? ['/test-photo.jpg'] : ['/img/giocatori/MarcoLiberti.jpg', '/img/blog/vincitriceseriea.jpg'];
// Optional normalized [x,y,expectedAlpha] samples for a private photo regression.
const samples = JSON.parse(process.env.CUTOUT_TEST_SAMPLES || '[]');
const profile = process.env.CUTOUT_TEST_PROFILE ? JSON.parse(fs.readFileSync(process.env.CUTOUT_TEST_PROFILE,'utf8')) : null;
const policy = fs.readFileSync(path.join(root, '.htaccess'), 'utf8').match(/Header set Content-Security-Policy "([^"]+)"/)[1];
let browser, timer;
const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  if (url.pathname === '/result') {
    let body = '';
    req.on('data', data => body += data);
    req.on('end', () => {
      res.end('OK');
      const result = JSON.parse(body);
      console.log(JSON.stringify(result));
      finish(result.error ? 1 : 0);
    });
    return;
  }
  res.setHeader('Content-Security-Policy', policy);
  if (url.pathname === '/') {
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end(`<!doctype html><script src="/api/grafiche_scontorno.js"></script><script>
      (async () => {
        const results = [];
        for (const src of ${JSON.stringify(sources)}) {
          const image = new Image();image.src = src;await image.decode();
          const start = performance.now();
          const subjectMode = !${Boolean(testImage)} && src.includes('/blog/') ? 'all' : ${JSON.stringify(subjects)};
          const mask = await GraphicsCutout.createMask(image, {subjects:subjectMode});
          const data = mask.getContext('2d').getImageData(0,0,mask.width,mask.height).data;
          const samples = ${JSON.stringify(samples)};
          for (const [x,y,expected] of samples) {
            const actual = data[(Math.min(mask.height-1,Math.floor(y*mask.height))*mask.width+Math.min(mask.width-1,Math.floor(x*mask.width)))*4+3];
            if (Math.abs(actual-expected)>40) throw new Error('Unexpected alpha '+actual+' at '+x+','+y+' (expected '+expected+')');
          }
          const profile = ${JSON.stringify(profile)};
          if (profile) {
            if(image.naturalWidth!==profile.width||image.naturalHeight!==profile.height)throw new Error('Regression photo dimensions do not match');
            for(const region of profile.regions) {
              let correct=0,total=0;
              const [left,top,right,bottom]=region.bounds;
              for(let y=top;y<bottom;y++)for(let x=left;x<right;x++) {
                const alpha=data[(y*mask.width+x)*4+3];total++;
                if(region.foreground ? alpha>=230 : alpha<=10)correct++;
              }
              if(correct/total<region.minimum)throw new Error(region.name+': '+Math.round(correct/total*100)+'% correct alpha');
            }
          }
          let foreground = 0, background = 0, soft = 0;
          for (let i=3;i<data.length;i+=4) {
            if(data[i]>240)foreground++;
            if(data[i]<10)background++;
            if(data[i]>=10&&data[i]<=240)soft++;
          }
          if(foreground<100||background<100||soft<10)throw new Error('Invalid mask: '+src);
          if(mask.width!==image.naturalWidth||mask.height!==image.naturalHeight)throw new Error('Wrong output size');
          results.push({src,subjects:subjectMode,checkedRegions:profile?.regions.length||0,ms:Math.round(performance.now()-start),foreground,background,soft});
          const preview=document.createElement('canvas');preview.width=mask.width;preview.height=mask.height;
          const ctx=preview.getContext('2d');ctx.drawImage(image,0,0);ctx.globalCompositeOperation='destination-in';ctx.drawImage(mask,0,0);
          await fetch('/preview?name='+src.split('/').pop(), {method:'POST',body:preview.toDataURL('image/png')});
          const qa=document.createElement('canvas');qa.width=mask.width*2;qa.height=mask.height;
          const qc=qa.getContext('2d');qc.fillStyle='#fff';qc.fillRect(0,0,mask.width,mask.height);
          qc.fillStyle='#172033';qc.fillRect(mask.width,0,mask.width,mask.height);
          qc.drawImage(preview,0,0);qc.drawImage(preview,mask.width,0);
          await fetch('/preview?name='+src.split('/').pop()+'-qa', {method:'POST',body:qa.toDataURL('image/png')});
          mask.width=mask.height=1;
        }
        await fetch('/result',{method:'POST',body:JSON.stringify({results})});
      })().catch(error=>fetch('/result',{method:'POST',body:JSON.stringify({error:error.message})}));
    </script>`);
    return;
  }
  if (url.pathname === '/preview') {
    let body='';req.on('data',data=>body+=data);req.on('end',()=>{
      const file=path.join(dir,path.basename(url.searchParams.get('name'))+'.png');
      fs.writeFileSync(file,Buffer.from(body.split(',')[1],'base64'));console.log('Preview: '+file);res.end('OK');
    });return;
  }
  if (url.pathname === '/test-photo.jpg' && testImage) {
    res.setHeader('Content-Type','image/jpeg');fs.createReadStream(testImage).pipe(res);return;
  }
  const file = path.resolve(root, '.' + url.pathname);
  if (!file.startsWith(root + path.sep) || !fs.existsSync(file)) {res.statusCode=404;res.end();return;}
  res.setHeader('Content-Type', file.endsWith('.js')?'text/javascript':file.endsWith('.jpg')?'image/jpeg':'application/octet-stream');
  fs.createReadStream(file).pipe(res);
});
function finish(code) {
  clearTimeout(timer);
  if(browser)browser.kill();
  server.close();
  process.exitCode=code;
}
server.listen(0, '127.0.0.1', () => {
  browser=spawn(process.env.CHROMIUM_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new','--disable-gpu','--no-first-run','--disable-extensions',
    ...(process.env.CUTOUT_TEST_IOS?['--user-agent=Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 Version/26.0 Mobile/15E148 Safari/604.1']:[]),
    '--user-data-dir='+path.join(dir,'profile'),'http://127.0.0.1:'+server.address().port
  ], {stdio:'ignore'});
  browser.on('error', error=>{console.error(error);finish(1);});
  timer=setTimeout(()=>{console.error('Model browser test timed out');finish(1);}, 360000);
});
