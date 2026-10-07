/* Standings use the same image loading, typography and palettes as MATCHDAY. */
window.StandingsRenderer = (() => {
  const {loadImage, contained, text, tournamentTheme} = MatchdayRenderer;
  function groups(rows) {
    const result=new Map();
    for(const row of rows){
      const key=String(row.girone||'').trim();
      if(!result.has(key))result.set(key,[]);
      result.get(key).push(row);
    }
    return [...result].sort(([a],[b])=>a.localeCompare(b,'it',{numeric:true}));
  }
  async function draw(tournament, rows, format, group='', updated='') {
    const width=1080,height=format==='post'?1350:1920;
    // Paginate dense tables instead of shrinking their text to illegibility.
    const perPage=format==='post'?12:20,pages=[];
    const theme=tournamentTheme(tournament),ink='#08243b';
    const logoUrl=src=>!src?null:/^(https?:|data:|\/)/i.test(src)?src:'/'+src.replace(/^(\.\.\/)+/,'');
    const [brand,logos]=await Promise.all([loadImage('/img/logo_old_school.png'),Promise.all(rows.map(row=>loadImage(logoUrl(row.logo))))]);
    for(let offset=0;offset<rows.length;offset+=perPage){
      const current=rows.slice(offset,offset+perPage),canvas=document.createElement('canvas');
      canvas.width=width;canvas.height=height;const ctx=canvas.getContext('2d');
      ctx.fillStyle='#fbfcfa';ctx.fillRect(0,0,width,height);
      for(const y of [40,85]){
        ctx.fillStyle=theme.primary;ctx.fillRect(0,y,540,28);
        ctx.fillStyle=theme.secondary;ctx.fillRect(540,y,540,28);
      }
      ctx.fillStyle='#fbfcfa';ctx.beginPath();ctx.arc(540,100,82,0,Math.PI*2);ctx.fill();
      if(!contained(ctx,brand,472,32,136,136))text(ctx,'TOS',540,100,136,56);
      text(ctx,'T O R N E I   O L D   S C H O O L',540,195,920,22);
      text(ctx,String(tournament.nome).toUpperCase(),540,267,980,86,theme.title||theme.primary);
      text(ctx,group?`CLASSIFICA · GIRONE ${group.toUpperCase()}`:'CLASSIFICA',540,338,980,42);
      if(updated)text(ctx,`AGGIORNATA AL ${updated}`,540,390,940,22,ink,'center','Arial, sans-serif');
      const top=438,headerH=54,bottom=height-130;
      const rowH=Math.min(format==='post'?66:72,(bottom-top-headerH)/current.length);
      ctx.fillStyle=theme.primary;ctx.fillRect(38,top,1004,headerH);
      const headerColor=theme.primary==='#ffd400'?'#101820':'#ffffff';
      text(ctx,'#',66,top+27,40,24,headerColor);
      text(ctx,'SQUADRA',162,top+27,430,24,headerColor,'left');
      const cols=[['PT',652,'punti'],['G',729,'giocate'],['V',796,'vinte'],['N',863,'pareggiate'],['P',930,'perse'],['DR',1004,'differenza_reti']];
      for(const [label,x] of cols)text(ctx,label,x,top+27,66,24,headerColor);
      current.forEach((row,i)=>{
        const y=top+headerH+i*rowH,cy=y+rowH/2;
        ctx.fillStyle=i%2?'#f2f5f4':'#ffffff';ctx.fillRect(38,y,1004,rowH);
        ctx.strokeStyle='#dce3e3';ctx.lineWidth=1;ctx.strokeRect(38,y,1004,rowH);
        text(ctx,offset+i+1,66,cy,42,27);
        const size=Math.min(48,rowH-10),logo=logos[offset+i];
        if(!contained(ctx,logo,99,cy-size/2,size,size))text(ctx,String(row.nome||'').slice(0,2).toUpperCase(),123,cy,44,22);
        text(ctx,String(row.nome||'').toUpperCase(),162,cy,434,30,ink,'left');
        for(const [,x,key] of cols)text(ctx,Number(row[key])||0,x,cy,66,key==='punti'?32:27);
      });
      text(ctx,'PT PUNTI   G GIOCATE   V VINTE   N PAREGGI   P PERSE   DR DIFFERENZA RETI',540,height-96,970,18,ink,'center','Arial, sans-serif');
      const count=Math.ceil(rows.length/perPage);
      text(ctx,count>1?`TORNEIOLDSCHOOL.IT · ${pages.length+1}/${count}`:'TORNEIOLDSCHOOL.IT',540,height-36,900,21,ink,'center','Arial, sans-serif');
      ctx.fillStyle=theme.primary;ctx.fillRect(0,height-16,540,16);ctx.fillStyle=theme.secondary;ctx.fillRect(540,height-16,540,16);
      pages.push(canvas);
    }
    return pages;
  }
  return {groups,draw};
})();
