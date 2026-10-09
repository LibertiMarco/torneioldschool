/* One canvas is both the interactive preview and the full-resolution export. */
window.PresentationRenderer = (() => {
  const {contained, text} = MatchdayRenderer;
  const display = 'Impact, "Arial Narrow", sans-serif';
  const serif = 'Georgia, serif';
  function profile(bg, accent, secondary, motif, layout = 'left', frame = [48, 310, 984, 740], font = display) {
    return {bg, accent, secondary, motif, layout, frame, font};
  }
  const profiles = {
    'brasileirao': profile('#073b2b','#f3db51','#168854','wave','center',[40,310,1000,745]),
    'conference': profile('#081f19','#61df7f','#185c40','facets','left',[58,300,964,760]),
    'champions': profile('#07132f','#b6ccff','#29478f','orbit','center',[42,326,996,734],serif),
    'champions-a': profile('#08162f','#d4bb74','#294571','arch','center',[52,326,976,730],serif),
    'champions-b': profile('#171d36','#c4d2ea','#515e96','constellation','right',[58,300,964,768],serif),
    'europa': profile('#1b1918','#ff9a3e','#7b361b','chevrons','left',[40,314,1000,742]),
    'serie-a': profile('#082e53','#62cef7','#1468aa','columns','left',[48,302,984,762]),
    'serie-b': profile('#092f29','#75d7ab','#12664b','stripes','center',[58,318,964,742]),
    'serie-c': profile('#102332','#e5c86b','#365264','steps','right',[40,302,1000,760]),
    'bundesliga': profile('#25151a','#fa5b61','#911c2c','slant','left',[32,292,1016,780]),
    'premier': profile('#251039','#acffdc','#603179','diamonds','right',[48,316,984,746]),
    'primera': profile('#261d29','#ff8773','#665164','steps','left',[40,304,1000,758]),
    'portugal': profile('#062f31','#d7d77b','#147575','tiles','center',[60,316,960,746]),
    'ligue': profile('#0b1b35','#badd6b','#315da1','rings','right',[50,298,980,764]),
    'eredivisie': profile('#101e50','#ff839c','#334b9a','grid','left',[48,320,984,744]),
    'saudi': profile('#102e28','#d7c891','#4c6950','arch','right',[58,304,964,750],serif),
    'africa': profile('#263320','#edbc66','#73723c','weave','center',[42,316,996,750]),
    'christmas': profile('#301b24','#e6c299','#713743','branches','center',[58,326,964,736],serif),
    'formula': profile('#1c222c','#ff785b','#454f5f','speed','left',[32,304,1016,764]),
    'esport': profile('#1b1636','#c2a0ff','#54458e','circuit','right',[40,300,1000,770]),
    'mcleague': profile('#171d21','#f3d841','#4c5154','blocks','left',[48,294,984,780]),
    'supercup': profile('#17232d','#e6c173','#536273','rays','center',[60,318,960,744],serif),
    'weekend': profile('#282237','#ffa97a','#645376','court','right',[38,302,1004,762]),
    'world': profile('#182a42','#ddbd80','#446187','globe','center',[44,326,992,738],serif),
    'world-a': profile('#102c3b','#e1cf93','#357382','meridian','center',[58,322,964,740],serif),
    'world-b': profile('#252743','#bcc8ec','#655d89','rings','left',[40,306,1000,758]),
    'intercontinental': profile('#192c31','#d8bc82','#50736d','horizon','right',[48,306,984,752],serif),
    'short-world': profile('#162b40','#87d8eb','#365c87','steps','left',[36,298,1008,776]),
    'night-world': profile('#19263c','#e9c7a3','#4d5c88','constellation','center',[40,318,1000,750]),
    'night-world-2': profile('#26203d','#cfb7f3','#695181','orbit','right',[60,300,960,758]),
    'night-premier': profile('#251936','#b2dfaf','#5d4375','court','center',[40,316,1000,754]),
    'night-italy': profile('#182f46','#97c9ee','#406080','slant','right',[56,294,968,770]),
    'editorial': profile('#162638','#d7bb79','#445972','grid')
  };
  function hash(value) {
    let n = 2166136261;
    for (const c of String(value)) n = Math.imul(n ^ c.charCodeAt(0), 16777619);
    return n >>> 0;
  }
  const fallbackMotifs = ['tiles','meridian','slant','rings','weave','steps','orbit','columns'];
  function identity(tournament, logo = null) {
    const key = profiles[tournament.template] ? tournament.template : 'editorial';
    const style = {...profiles[key], frame: [...profiles[key].frame], key, tournamentId: String(tournament.id)};
    if (key === 'editorial') {
      const seed = hash(tournament.id);
      style.motif = fallbackMotifs[seed % fallbackMotifs.length];
      style.layout = ['left','center','right'][Math.floor(seed / 8) % 3];
      style.frame = [[48,302,984,766],[40,322,1000,740],[60,310,960,754]][Math.floor(seed / 24) % 3];
      // Derive the fallback accent from the actual logo, never a generated asset.
      if (logo) {
        try {
          const sample = document.createElement('canvas'); sample.width = sample.height = 32;
          const ctx = sample.getContext('2d'); ctx.drawImage(logo,0,0,32,32);
          const pixels = ctx.getImageData(0,0,32,32).data;
          let best = 0;
          for (let i=0;i<pixels.length;i+=4) {
            const rgb = [...pixels.slice(i,i+3)], max = Math.max(...rgb), min = Math.min(...rgb);
            const score = (max-min) * pixels[i+3] / 255;
            if (score > best && max > 90) {best = score; style.accent = '#'+rgb.map(v=>v.toString(16).padStart(2,'0')).join('');}
          }
        } catch (_) { /* Cross-origin logos keep the editorial palette. */ }
      }
    }
    return style;
  }
  function polygon(ctx, points, color) {
    ctx.fillStyle=color;ctx.beginPath();points.forEach(([x,y],i)=>i?ctx.lineTo(x,y):ctx.moveTo(x,y));ctx.closePath();ctx.fill();
  }
  function line(ctx, points, color, width=2) {
    ctx.strokeStyle=color;ctx.lineWidth=width;ctx.beginPath();points.forEach(([x,y],i)=>i?ctx.lineTo(x,y):ctx.moveTo(x,y));ctx.stroke();
  }
  function background(ctx,s) {
    ctx.fillStyle=s.bg;ctx.fillRect(0,0,1080,1350);
    const gradient=ctx.createLinearGradient(0,0,1080,1350);
    gradient.addColorStop(0,s.secondary+'55');gradient.addColorStop(.65,s.bg);gradient.addColorStop(1,s.secondary+'22');
    ctx.fillStyle=gradient;ctx.fillRect(0,0,1080,1350);
    ctx.save();ctx.globalAlpha=.2;
    const a=s.accent,b=s.secondary;
    switch(s.motif) {
      case 'wave':
        for(let i=0;i<5;i++){ctx.strokeStyle=i===0?a:b;ctx.lineWidth=22-i*3;ctx.beginPath();ctx.moveTo(-80,135+i*44);ctx.bezierCurveTo(250,-110,680,400,1150,85+i*52);ctx.stroke();}
        polygon(ctx,[[0,1170],[270,1350],[0,1350]],a);break;
      case 'facets':
        polygon(ctx,[[0,0],[360,0],[0,390]],a);polygon(ctx,[[1080,0],[640,260],[1080,570]],b);polygon(ctx,[[700,1350],[1080,980],[1080,1350]],a);break;
      case 'orbit':
        ctx.strokeStyle=a;ctx.lineWidth=2;for(let i=0;i<5;i++){ctx.beginPath();ctx.ellipse(850,110,480+i*55,170+i*25,-.45,0,Math.PI*2);ctx.stroke();}break;
      case 'arch':
        for(let i=0;i<5;i++){ctx.strokeStyle=a;ctx.lineWidth=3;ctx.beginPath();ctx.arc(540,340,340+i*52,Math.PI,Math.PI*2);ctx.stroke();}line(ctx,[[22,420],[22,1320],[1058,1320],[1058,420]],a);break;
      case 'constellation':
        for(let i=0;i<14;i++){const x=80+(i*197)%930,y=30+(i*73)%230;ctx.fillStyle=a;ctx.beginPath();ctx.arc(x,y,3,0,Math.PI*2);ctx.fill();if(i%2)line(ctx,[[x,y],[80+((i-1)*197)%930,30+((i-1)*73)%230]],b);}break;
      case 'chevrons':
        for(let i=0;i<4;i++)line(ctx,[[740+i*70,-20],[930+i*70,170],[740+i*70,360]],a,24);break;
      case 'columns':
        ctx.fillStyle=b;for(let i=0;i<6;i++)ctx.fillRect(i*200-70,0,36,1350);ctx.fillStyle=a;ctx.fillRect(0,0,18,1350);break;
      case 'stripes':
        for(let i=0;i<5;i++)line(ctx,[[i*180-200,0],[i*180+650,1350]],a,24);break;
      case 'steps':
        for(let i=0;i<6;i++)line(ctx,[[650+i*60,0],[650+i*60,60+i*30],[1080,60+i*30]],a,4);line(ctx,[[0,1200],[220,1200],[220,1350]],a,14);break;
      case 'slant':
        polygon(ctx,[[650,0],[900,0],[1080,240],[1080,430]],a);polygon(ctx,[[0,1060],[0,1350],[300,1350]],b);break;
      case 'diamonds':
        for(let i=0;i<5;i++)line(ctx,[[800,10+i*24],[1030+i*24,190],[800,370+i*24],[570-i*24,190],[800,10+i*24]],a);break;
      case 'tiles':
        for(let x=0;x<1080;x+=90)for(let y=0;y<1350;y+=90){ctx.strokeStyle=a;ctx.lineWidth=1;ctx.strokeRect(x+8,y+8,74,74);}break;
      case 'rings':
        for(let i=0;i<7;i++){ctx.strokeStyle=a;ctx.lineWidth=12;ctx.beginPath();ctx.arc(1010,0,90+i*48,0,Math.PI*2);ctx.stroke();}break;
      case 'grid':
        for(let x=0;x<1080;x+=60)line(ctx,[[x,0],[x,1350]],b);for(let y=0;y<1350;y+=60)line(ctx,[[0,y],[1080,y]],b);break;
      case 'weave':
        for(let i=0;i<18;i++){const x=i*75;line(ctx,[[x-100,0],[x+80,150],[x-100,300]],a,8);line(ctx,[[x,1110],[x+150,1230],[x,1350]],a,8);}break;
      case 'branches':
        for(const x of [10,1070])for(let y=40;y<1350;y+=80){line(ctx,[[x,y],[x+(x<100?60:-60),y+65]],a,3);line(ctx,[[x,y],[x+(x<100?60:-60),y-30]],a,2);}break;
      case 'speed':
        for(let i=0;i<8;i++)line(ctx,[[650+i*25,20],[1080,180+i*25]],a,8);for(let i=0;i<6;i++){ctx.fillStyle=(i%2?a:b);ctx.fillRect(30+i*34,1280,34,34);}break;
      case 'circuit':
        for(let i=0;i<5;i++)line(ctx,[[1080,60+i*35],[820-i*40,60+i*35],[740-i*40,140+i*35],[500,140+i*35]],a,3);break;
      case 'blocks':
        ctx.fillStyle=a;ctx.fillRect(0,0,1080,22);ctx.fillRect(0,1250,220,100);ctx.fillRect(930,120,150,130);break;
      case 'rays':
        for(let i=0;i<14;i++){const angle=(i/13)*Math.PI;line(ctx,[[540,300],[540+Math.cos(angle)*1000,300-Math.sin(angle)*1000]],a,3);}break;
      case 'court':
        line(ctx,[[28,26],[1052,26],[1052,1324],[28,1324],[28,26]],a,3);ctx.strokeStyle=a;ctx.beginPath();ctx.arc(1060,170,210,0,Math.PI*2);ctx.stroke();break;
      case 'globe': case 'meridian':
        ctx.strokeStyle=a;for(let i=1;i<=5;i++){ctx.beginPath();ctx.ellipse(s.motif==='globe'?850:200,80,250,i*52,.35,0,Math.PI*2);ctx.stroke();}for(let i=1;i<5;i++){ctx.beginPath();ctx.ellipse(s.motif==='globe'?850:200,80,i*48,260,.35,0,Math.PI*2);ctx.stroke();}break;
      case 'horizon':
        for(let i=0;i<7;i++){ctx.strokeStyle=a;ctx.beginPath();ctx.ellipse(540,280+i*18,750,90,0,Math.PI,Math.PI*2);ctx.stroke();}break;
    }
    ctx.restore();
  }
  function photoRect(image,frame,transform={}) {
    const [x,y,w,h]=frame;
    const scale=Math.min(w/image.naturalWidth,h/image.naturalHeight)*(transform.zoom??1);
    const iw=image.naturalWidth*scale,ih=image.naturalHeight*scale;
    // A contained photo can move to either edge; a larger photo can be cropped to either edge.
    const travelX=Math.abs(w-iw)/2,travelY=Math.abs(h-ih)/2;
    return {x:x+(w-iw)/2+(transform.x??0)*travelX,y:y+(h-ih)/2+(transform.y??0)*travelY,w:iw,h:ih,travelX,travelY};
  }
  function teamName(ctx,name,s) {
    const available=s.layout==='center'?950:770;
    ctx.font=`400 86px ${s.font}`;
    let lines=[String(name).toUpperCase()];
    const words=lines[0].split(/\s+/);
    if(ctx.measureText(lines[0]).width>available&&words.length>1){
      let best=Infinity;
      for(let i=1;i<words.length;i++){const pair=[words.slice(0,i).join(' '),words.slice(i).join(' ')];const max=Math.max(...pair.map(v=>ctx.measureText(v).width));if(max<best){best=max;lines=pair;}}
    }
    const longest=Math.max(...lines.map(v=>ctx.measureText(v).width));
    const size=Math.min(s.layout==='center'&&lines.length>1?50:86,86*available/Math.max(1,longest));
    const x=s.layout==='center'?540:s.layout==='right'?840:240;
    const y=s.layout==='center'?1238:1195;
    lines.forEach((v,i)=>text(ctx,v,x,y+(i-(lines.length-1)/2)*size*1.14,available,size,'#ffffff',s.layout==='center'?'center':s.layout==='right'?'right':'left',s.font));
  }
  function draw(canvas,tournament,team,assets,transform={}) {
    const s=assets.style||identity(tournament,assets.competition);
    const ctx=canvas.getContext('2d');canvas.width=1080;canvas.height=1350;
    ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';background(ctx,s);
    // White plates improve readability while leaving official logo pixels unchanged.
    for(const x of [48,900]){ctx.fillStyle='#ffffff';ctx.fillRect(x,42,132,132);}
    contained(ctx,assets.brand,56,50,116,116);contained(ctx,assets.competition,908,50,116,116);
    text(ctx,'PRESENTAZIONE SQUADRA',540,81,650,23,s.accent,'center','Arial, sans-serif');
    text(ctx,String(tournament.nome||'').toUpperCase(),540,143,660,52,'#ffffff','center',s.font);
    const aligned=s.layout==='right'?1030:s.layout==='left'?50:540;
    text(ctx,'LE SQUADRE DEL CAMPIONATO',aligned,246,940,22,s.accent,s.layout==='right'?'right':s.layout==='left'?'left':'center','Arial, sans-serif');
    const [x,y,w,h]=s.frame;
    ctx.fillStyle=s.accent;ctx.fillRect(x-3,y-3,w+6,h+6);
    ctx.fillStyle=s.bg;ctx.fillRect(x,y,w,h);
    // All image movement is clipped to the frame; template elements never move.
    ctx.save();ctx.beginPath();ctx.rect(x,y,w,h);ctx.clip();
    let rect=null;
    if(assets.photo){rect=photoRect(assets.photo,s.frame,transform);ctx.drawImage(assets.photo,rect.x,rect.y,rect.w,rect.h);}
    else {text(ctx,'CARICA LA FOTO DELLA SQUADRA',540,y+h/2,w-70,32,'#becada','center','Arial, sans-serif');}
    ctx.restore();
    const crestX=s.layout==='center'?501:s.layout==='right'?890:48;
    const crestY=s.layout==='center'?1090:1118;
    const crestSize=s.layout==='center'?78:140;
    ctx.fillStyle='#ffffff';ctx.fillRect(crestX-8,crestY-8,crestSize+16,crestSize+16);
    contained(ctx,assets.crest,crestX,crestY,crestSize,crestSize);
    if(team)teamName(ctx,team.nome,s);
    line(ctx,[[48,1300],[1032,1300]],s.accent,2);
    text(ctx,'TORNEIOLDSCHOOL.IT',540,1326,960,17,'#cbd5df','center','Arial, sans-serif');
    return {style:s,rect};
  }
  return {profiles,identity,photoRect,draw};
})();
