window.CupBracketRenderer={async draw(slug,phase) {
  const api=window.TosCupBracket,{info,games}=await api.loadProjection(slug,phase);
  if(!games.length)throw new Error('Nessun tabellone disponibile per '+phase);
  const rounds=[...new Set(games.map(api.roundKey))],columns=rounds.map(round=>{
    const groups=new Map();
    for(const game of games.filter(m=>api.roundKey(m)===round)) {
      const key=game.bracket_slot || [game.squadra_casa,game.squadra_ospite].sort().join('|');
      if(!groups.has(key))groups.set(key,[]);groups.get(key).push(game);
    }
    return [...groups.values()];
  });
  const width=320,gap=52,margin=60,top=200;
  const unit=Math.max(148,...columns.flatMap((nodes,depth)=>nodes.map(group=>(40+group.length*90+24)/2**depth)));
  const canvas=document.createElement('canvas');canvas.width=Math.max(1080,margin*2+columns.length*(width+gap)+220);canvas.height=Math.max(900,top+columns[0].length*unit+100);
  const ctx=canvas.getContext('2d'),color={GOLD:'#b89232',SILVER:'#8a98aa',BRONZO:'#b0774f'}[phase] || '#15293e';
  const fill={GOLD:'#fff4ce',SILVER:'#edf1f6',BRONZO:'#f4dfd0'}[phase] || '#f3f6fa';
  const logos=new Map();
  const urls=[...new Set(games.flatMap(g=>[g.logo_casa,g.logo_ospite]).filter(Boolean))];
  await Promise.all(urls.map(async url=>logos.set(url,await MatchdayRenderer.loadImage(new URL(url,location.origin+'/').href))));
  const text=(value,x,y,maxWidth=220,size=16,weight=600)=>{
    ctx.fillStyle='#15293e';ctx.font=`${weight} ${size}px Arial`;let label=String(value || '');
    while(ctx.measureText(label).width>maxWidth && label.length>1)label=label.slice(0,-2)+'…';
    ctx.fillText(label,x,y);
  };
  function box(x,y,w,h){ctx.fillStyle=fill;ctx.strokeStyle=color;ctx.lineWidth=1.5;ctx.beginPath();if(ctx.roundRect)ctx.roundRect(x,y,w,h,12);else{const r=12;ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();}ctx.fill();ctx.stroke();}
  function shield(url,x,y,size){const image=logos.get(url);if(MatchdayRenderer.contained(ctx,image,x,y,size,size))return;ctx.strokeStyle=color;ctx.lineWidth=2;ctx.strokeRect(x+4,y+2,size-8,size-6);}
  const centers=columns.map((nodes,depth)=>nodes.map((_,i)=>top+(i+.5)*2**depth*unit));
  ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.fillStyle=color;ctx.fillRect(0,0,canvas.width,12);
  text(info.nome,margin,68,canvas.width-120,30,800);text(`COPPA ${phase} · TABELLONE`,margin,110,canvas.width-120,22,700);
  text('TORNEI OLD SCHOOL',margin,canvas.height-35,canvas.width-120,14,700);
  const connector=(x1,y1,x2,y2)=>{ctx.beginPath();ctx.moveTo(x1,y1);ctx.lineTo((x1+x2)/2,y1);ctx.lineTo((x1+x2)/2,y2);ctx.lineTo(x2,y2);ctx.strokeStyle=color;ctx.lineWidth=2;ctx.stroke();};
  columns.slice(0,-1).forEach((nodes,depth)=>nodes.forEach((group,index)=>{if(centers[depth+1][Math.floor(index/2)]!=null)connector(margin+depth*(width+gap)+width,centers[depth][index],margin+(depth+1)*(width+gap),centers[depth+1][Math.floor(index/2)]);}));
  columns.forEach((nodes,depth)=>{
    text(api.labels[rounds[depth]] || rounds[depth],margin+depth*(width+gap),top-42,width,17,800);
    nodes.forEach((group,index)=>{
      const x=margin+depth*(width+gap),height=40+group.length*90,y=centers[depth][index]-height/2;
      box(x,y,width,height);text(rounds[depth]==='FINALE'?'':group[0].bracket_slot,x+12,y+22,60,12,700);
      group.forEach((game,legIndex)=>{
        const base=y+36+legIndex*90,played=Number(game.giocata)===1;
        ['casa','ospite'].forEach((side,teamIndex)=>{const rowY=base+teamIndex*32;shield(game['logo_'+side],x+10,rowY-12,24);text(game['squadra_'+side],x+42,rowY+6,width-88,14,700);text(played?game['gol_'+side]:'–',x+width-28,rowY+6,24,16,800);});
        if(group.length>1)text(game.fase_leg || '',x+12,base+73,width-24,11,500);
      });
    });
  });
  const finalIndex=rounds.indexOf('FINALE'),finalGames=finalIndex>=0?columns[finalIndex][0]:[],winner=api.winner(finalGames,'Vincente finale');
  const center=finalIndex>=0?centers[finalIndex][0]:canvas.height/2,x=margin+columns.length*(width+gap);
  if(finalIndex>=0)connector(margin+finalIndex*(width+gap)+width,center,x,center);
  box(x,center-80,220,160);shield(winner?.logo,x+78,center-60,64);text(winner?.name || 'Vincente finale',x+14,center+42,192,17,800);
  return canvas;
}};
